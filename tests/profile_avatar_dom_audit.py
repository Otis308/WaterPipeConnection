"""Audit the current avatar control with real Edge, without opening the shared DB.

Usage: python tests/profile_avatar_dom_audit.py [<msedge.exe>]
The real container Blade engine renders its current view with fixture data.
A temporary loopback server serves that snapshot at /profile; its styles/scripts
come from the running Apache on port 8000. No session/account from the user's
browser is accessed, and the disabled attribute is never removed.
"""
import hashlib
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
from pathlib import Path
import socket
import subprocess
import sys
import tempfile
from threading import Thread
from urllib.request import build_opener, ProxyHandler

root = Path(__file__).resolve().parents[1]
edge_exe = sys.argv[1] if len(sys.argv) > 1 else r'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe'
files = ['resources/views/game/profile.blade.php', 'app/Http/Controllers/PlayerProfileController.php',
         'public/CSS/Profile.css', 'public/JS/Profile.js', 'vendor/autoload.php',
         'public/Bootstrap/CSS/bootstrap.min.css']
container_hashes = subprocess.run(['docker', 'exec', 'huit_pipegame_app', 'sha256sum',
                                 *('/var/www/html/' + name for name in files)],
                                capture_output=True, check=True, timeout=15).stdout.decode().splitlines()
for name, line in zip(files, container_hashes, strict=True):
    assert hashlib.sha256((root / name).read_bytes()).hexdigest() == line.split()[0], 'Container differs: ' + name
client = build_opener(ProxyHandler({}))
for name in ['CSS/Profile.css', 'JS/Profile.js', 'Bootstrap/CSS/bootstrap.min.css']:
    with client.open('http://127.0.0.1:8000/' + name, timeout=10) as response:
        assert response.status == 200
        assert response.read() == (root / 'public' / name).read_bytes(), 'Apache asset differs: ' + name

render = br'''<?php
require '/var/www/html/vendor/autoload.php';
$html = HuitBladeEngine::render('game.profile', [
    'profile' => ['name'=>'Avatar DOM fixture', 'username'=>'fixture', 'created_at'=>'2026-10-28',
        'email'=>null, 'bio'=>'Fixture only', 'avatar_url'=>'/Images/CupSao.png'],
    'source'=>'solo', 'notice'=>null, 'old'=>[], 'loadError'=>false,
    'avatarReady'=>(new App\Services\AvatarStorage())->ready(),
    'soloStats'=>null, 'pvpStats'=>null,
    'history'=>['available'=>true, 'items'=>[], 'page'=>1, 'pages'=>1],
]);
// Destroy only this CLI invocation's randomly created, anonymous session.
$_SESSION = [];
session_destroy();
echo $html;
'''
html = subprocess.run(['docker', 'exec', '--user', 'www-data', '-i', 'huit_pipegame_app', 'php'],
                      input=render, capture_output=True, check=True, timeout=15).stdout
assert b'Blade Render Error' not in html and b'id="profile-avatar"' in html
html = html.replace(b'<head>', b'<head><base href="http://127.0.0.1:8000/"><link rel="icon" href="data:,">', 1)


class Snapshot(BaseHTTPRequestHandler):
    def do_GET(self):
        if self.path != '/profile':
            self.send_error(404)
            return
        self.send_response(200)
        self.send_header('Content-Type', 'text/html; charset=utf-8')
        self.send_header('Cache-Control', 'no-store')
        self.send_header('Content-Length', str(len(html)))
        self.end_headers()
        self.wfile.write(html)

    def log_message(self, *args):
        pass


node_program = r'''
import { setTimeout as delay } from 'node:timers/promises';
const port = process.argv[2];
let page;
for (let i = 0; i < 80; i++) {
    try {
        const pages = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
        page = pages.find(item => item.type === 'page' && item.url.endsWith('/profile'));
        if (page) break;
    } catch {}
    await delay(100);
}
if (!page) throw Error('Edge page did not start');
const ws = new WebSocket(page.webSocketDebuggerUrl);
await new Promise((resolve, reject) => { ws.addEventListener('open', resolve); ws.addEventListener('error', reject); });
let sequence = 0, chooserEvents = 0;
const pending = new Map();
ws.addEventListener('message', event => {
    const message = JSON.parse(event.data);
    if (message.method === 'Page.fileChooserOpened') chooserEvents++;
    const handler = pending.get(message.id);
    if (handler) {
        pending.delete(message.id);
        message.error ? handler.reject(Error(message.error.message)) : handler.resolve(message.result);
    }
});
const send = (method, params={}) => new Promise((resolve,reject) => {
    const id = ++sequence; pending.set(id,{resolve,reject}); ws.send(JSON.stringify({id,method,params}));
});
const evaluate = async expression => {
    const answer = await send('Runtime.evaluate', {expression, returnByValue:true});
    if (answer.exceptionDetails) throw Error('DOM evaluation failed');
    return answer.result.value;
};
try {
    let ready = false;
    for (let i=0; i<150; i++) {
        try { ready = await evaluate("document.readyState === 'complete' && !!document.getElementById('profile-avatar')"); }
        catch(error) { if (!error.message.includes('context')) throw error; }
        if (ready) break;
        await delay(100);
    }
    if (!ready) throw Error('Profile snapshot did not load');
    const state = await evaluate(`(() => {
        const input = document.getElementById('profile-avatar');
        input.scrollIntoView({block:'center'});
        const rect=input.getBoundingClientRect(), style=getComputedStyle(input);
        const x=rect.left+Math.min(35, rect.width/4), y=rect.top+rect.height/2;
        const hit=document.elementFromPoint(x,y);
        const pointerBlockers=[];
        for(let parent=input.parentElement;parent;parent=parent.parentElement) {
            if(getComputedStyle(parent).pointerEvents==='none') pointerBlockers.push(parent.tagName);
        }
        return {
            disabled_attribute:input.hasAttribute('disabled'), disabled_property:input.disabled,
            effective_disabled:input.matches(':disabled'),
            disabled_fieldset_count:input.closest('fieldset[disabled]') ? 1 : 0,
            pointer_events:style.pointerEvents, ancestor_pointer_blockers:pointerBlockers,
            display:style.display, visibility:style.visibility, width:rect.width, height:rect.height,
            button_hit_target_is_input:hit===input, hit_target:hit?.id || hit?.tagName,
            form_encoding:input.form.enctype, field_name:input.getAttribute('name'),
            helper_text:document.getElementById('avatar-help').textContent.trim(),
            loaded_scripts:Array.from(document.scripts, script=>script.src).filter(Boolean), x,y
        };
    })()`);
    const listeners=[];
    // Inspect the input, every ancestor, document and window BEFORE adding any test handler.
    const expressions=["document.getElementById('profile-avatar')", 'document', 'window'];
    const depth=await evaluate("(() => { let n=0,e=document.getElementById('profile-avatar').parentElement; for(;e;e=e.parentElement)n++; return n; })()");
    for(let i=1;i<=depth;i++) expressions.push("document.getElementById('profile-avatar')"+'.parentElement'.repeat(i));
    for(const expression of expressions) {
        const object=await send('Runtime.evaluate',{expression});
        const result=await send('DOMDebugger.getEventListeners',{objectId:object.result.objectId});
        for(const listener of result.listeners) {
            if(['click','mousedown','mouseup','pointerdown','pointerup'].includes(listener.type)) {
                listeners.push({target:expression,type:listener.type});
            }
        }
    }
    state.click_or_pointer_listeners=listeners;
    if(state.disabled_attribute || state.effective_disabled || state.form_encoding!=='multipart/form-data' || state.field_name!=='avatar') throw Error('Upload is not enabled with multipart');
    if(state.disabled_fieldset_count || state.pointer_events!=='auto' || state.ancestor_pointer_blockers.length
        || !state.button_hit_target_is_input || listeners.length) throw Error('Additional click blocker found: '+JSON.stringify(state));
    await send('Page.enable');
    await send('Page.setInterceptFileChooserDialog',{enabled:true});
    await send('Input.dispatchMouseEvent',{type:'mouseMoved',x:state.x,y:state.y});
    await send('Input.dispatchMouseEvent',{type:'mousePressed',x:state.x,y:state.y,button:'left',buttons:1,clickCount:1});
    await send('Input.dispatchMouseEvent',{type:'mouseReleased',x:state.x,y:state.y,button:'left',buttons:0,clickCount:1});
    await delay(500);
    state.mouse_click_attempted=true;
    state.file_chooser_open_events=chooserEvents;
    state.selected_file_count=await evaluate("document.getElementById('profile-avatar').files.length");
    if(chooserEvents!==1 || state.selected_file_count!==0) throw Error('Expected one native file chooser request');
    delete state.x; delete state.y;
    console.log(JSON.stringify(state));
} finally {
    ws.send(JSON.stringify({id:++sequence,method:'Browser.close'}));
    await delay(300); ws.close();
}
'''

server = ThreadingHTTPServer(('127.0.0.1', 0), Snapshot)
thread = Thread(target=server.serve_forever, daemon=True)
thread.start()
try:
    with tempfile.TemporaryDirectory(prefix='avatar-dom-audit-') as scratch:
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            debug_port = sock.getsockname()[1]
        edge = subprocess.Popen([edge_exe, '--headless', '--disable-gpu', '--no-first-run',
            '--no-default-browser-check', '--disable-extensions', '--disable-background-networking',
            '--host-resolver-rules=MAP fonts.googleapis.com ~NOTFOUND, MAP fonts.gstatic.com ~NOTFOUND',
            '--window-size=1200,1100', '--user-data-dir=' + scratch,
            '--remote-debugging-port=' + str(debug_port), f'http://127.0.0.1:{server.server_port}/profile'],
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        try:
            result = subprocess.run(['node', '--input-type=module', '-', str(debug_port)],
                input=node_program.encode(), capture_output=True, timeout=40)
            if result.returncode:
                raise RuntimeError(result.stderr.decode(errors='replace')[:1000])
            state = json.loads(result.stdout)
            print('Source version: 6 container hashes and 3 Apache asset responses match the workspace.')
            print('Scope: real container-rendered view with fixture data, real Apache CSS/JS, Edge CDP mouse click; no shared DB or existing Chrome session.')
            print(json.dumps(state, ensure_ascii=True, indent=2))
            print('Trusted mouse click requested the file chooser. Full selection/save/reload is tested by avatar_upload_http_test.py + avatar_upload_browser.mjs.')
        finally:
            if edge.poll() is None:
                edge.terminate()
            edge.wait(timeout=8)
finally:
    server.shutdown()
    server.server_close()
    thread.join(timeout=3)
