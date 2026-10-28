"""Real Apache chat + optional two isolated Edge browser contexts; private SQLite only.
Run: python tests/pvp_chat_http_test.py [path/to/msedge.exe]
No service restart, migrations, production sessions or production DB writes.
"""
import base64
from concurrent.futures import ThreadPoolExecutor
from http.client import HTTPConnection
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
from pathlib import Path
import secrets
import shutil
import socket
import subprocess
import sys
import tempfile
from threading import Thread, Lock
from urllib.parse import urlencode

root = Path(__file__).resolve().parents[1]
tag = secrets.token_hex(10)
scratch = root / 'tests' / ('.pvp-chat-' + tag)
scratch.mkdir()
gateway = root / 'public' / ('.pvp-chat-' + tag + '.php')
key = secrets.token_hex(24)
container_db = '/var/www/html/tests/' + scratch.name + '/chat.sqlite'
sessions = [dict(uid=i, sid=secrets.token_hex(24), csrf=secrets.token_hex(24)) for i in [1, 2, 3]]
checks = 0
server = None
edge = None
edge_profile = None
fault = {'drop_post': False, 'fail_get': False, 'poll_active': 0, 'poll_max': 0}
fault_lock = Lock()


def fixture(action, **extra):
    data = base64.b64encode(json.dumps(dict(db=container_db, action=action, **extra)).encode()).decode()
    result = subprocess.run(['docker', 'exec', '--user', 'www-data', 'huit_pipegame_app', 'php', '/var/www/html/tests/pvp_chat_fixture.php', data], capture_output=True, timeout=40)
    if result.returncode:
        raise RuntimeError(result.stderr.decode(errors='replace')[:800])
    return json.loads(result.stdout)


def check(condition, message):
    global checks
    checks += 1
    if not condition:
        raise AssertionError(message)


class Proxy(BaseHTTPRequestHandler):
    def forward(self):
        path = self.path.split('?', 1)[0]
        is_poll = path == '/api/pvp/chat' and self.command == 'GET'
        payload = self.rfile.read(int(self.headers.get('Content-Length', 0))) if self.command == 'POST' else None
        if path == '/__fault':
            with fault_lock:
                fault.update(json.loads(payload))
            self.send_response(200); self.end_headers(); return
        if path == '/__finish':
            fixture('finish')
            self.send_response(200); self.end_headers(); return
        if path in ['/TrangGame.html'] or path.startswith('/api/pvp/'):
            target = '/' + gateway.name + ('?' + self.path.split('?', 1)[1] if '?' in self.path else '')
        elif path.startswith(('/CSS/', '/JS/', '/Images/', '/Sounds/', '/Bootstrap/')):
            target = self.path
        else:
            self.send_error(404); return
        with fault_lock:
            if is_poll:
                fault['poll_active'] += 1
                fault['poll_max'] = max(fault['poll_max'], fault['poll_active'])
            fail_get = is_poll and fault['fail_get']
            drop = path == '/api/pvp/chat' and self.command == 'POST' and fault['drop_post']
            if drop: fault['drop_post'] = False
        try:
            if fail_get:
                self.send_error(503); return
            headers = {name: self.headers[name] for name in ['Cookie', 'Content-Type', 'X-CSRF-TOKEN'] if name in self.headers}
            headers.update({'X-Chat-Fixture': key, 'X-Chat-Path': path})
            conn = HTTPConnection('127.0.0.1', 8000, timeout=20)
            try:
                conn.request(self.command, target, payload, headers)
                response = conn.getresponse(); data = response.read()
                if drop:
                    self.send_error(503); return  # DB committed, response lost to browser.
                self.send_response(response.status)
                for name, value in response.getheaders():
                    if name.lower() not in ['connection', 'transfer-encoding', 'content-length', 'date', 'server']:
                        self.send_header(name, value)
                self.send_header('Content-Length', str(len(data))); self.end_headers(); self.wfile.write(data)
            finally: conn.close()
        except (BrokenPipeError, ConnectionResetError, ConnectionAbortedError):
            pass
        finally:
            if is_poll:
                with fault_lock: fault['poll_active'] -= 1

    do_GET = do_POST = forward
    def log_message(self, *args): pass


def request(uid, method='GET', payload=None, query=None, csrf=True):
    session = next((s for s in sessions if s['uid'] == uid), None)
    headers = {}
    if session: headers['Cookie'] = 'PHPSESSID=' + session['sid']
    if csrf and session: headers['X-CSRF-TOKEN'] = session['csrf']
    if payload is not None: headers['Content-Type'] = 'application/json'
    conn = HTTPConnection('127.0.0.1', server.server_port, timeout=20)
    conn.request(method, '/api/pvp/chat' + ('?' + urlencode(query) if query else ''), json.dumps(payload, ensure_ascii=False).encode() if payload is not None else None, headers)
    response = conn.getresponse(); data = json.loads(response.read()); conn.close()
    return response.status, data


try:
    match = fixture('setup')['map_data']
    match_id = match['chat_match_id']
    for session in sessions: fixture('session', **session)
    # Real dispatcher guards, all rejected before constructing production PDO.
    for method, expected, cookie, csrf in [('GET',401,None,None), ('POST',401,None,None), ('POST',419,sessions[0]['sid'],None)]:
        conn = HTTPConnection('127.0.0.1',8000,timeout=10)
        headers = {'Content-Type':'application/json'}
        if cookie: headers['Cookie']='PHPSESSID='+cookie
        conn.request(method,'/api/pvp/chat?match_id='+match_id+'&user_id=1',json.dumps({'match_id':match_id,'user_id':1}) if method=='POST' else None,headers)
        response=conn.getresponse(); response.read(); conn.close()
        check(response.status==expected,'Real index session/CSRF guard '+method+' '+str(expected))
    gateway.write_text("<?php\nif (!hash_equals(" + repr(key) + ", $_SERVER['HTTP_X_CHAT_FIXTURE'] ?? '')) { http_response_code(404); exit; }\n"
                       + "define('CHAT_FIXTURE', ['db' => " + repr(container_db) + "]);\nrequire dirname(__DIR__) . '/tests/pvp_chat_fixture.php';\n", encoding='utf-8')
    server = ThreadingHTTPServer(('127.0.0.1', 0), Proxy)
    Thread(target=server.serve_forever, daemon=True).start()
    def get(uid=1, after=0, mid=match_id, **extra): return request(uid, query=dict(match_id=mid, after_id=after, **extra))
    def post(uid=1, text='Xin chào', cid=None, mid=match_id, **extra): return request(uid, 'POST', dict(match_id=mid, content=text, client_message_id=cid or secrets.token_hex(16), **extra))

    check(get()[0] == 200, 'Member can read')
    check(get(0, user_id=1)[0] == 401, 'Guest cannot forge query user_id')
    check(post(0, user_id=1)[0] == 401, 'Guest cannot forge payload user_id')
    check(get(3, user_id=1)[0] == 403 and post(3, user_id=1)[0] == 403, 'Outsider cannot impersonate a member')
    check(get(1, mid=secrets.token_hex(16))[0] == 403, 'Guessed match denied')
    check(request(1, 'POST', dict(match_id=match_id, content='bad', client_message_id=secrets.token_hex(16)), csrf=False)[0] == 419, 'Explicit CSRF check')
    for invalid in ['', ' \n\t\u00a0', '😀'*501, '<'*501, ['array']]:
        check(post(text=invalid)[0] == 422, 'Empty/nontext/overlength denied')
    first = post(text='<img src=x onerror=alert(1)>\n<script>alert(2)</script>', user_id=2, username='forged')[1]['data']['message']
    check(first['sender_id'] == 1 and first['sender_name'] != 'forged', 'Sender comes from session and DB')
    second = post(2, 'Xin chào từ đối thủ 😀')[1]['data']['message']
    check([m['id'] for m in get(2)[1]['data']['messages']] == [first['id'], second['id']], 'Two-way history in ascending order')
    check([m['id'] for m in get(after=first['id'])[1]['data']['messages']] == [second['id']], 'Cursor fetch only new messages')
    check(get(after=second['id'])[1]['data']['messages'] == [], 'Caught-up cursor is empty')
    check(post(text='😀'*500)[0] == 200, '500 Unicode codepoints accepted')
    client_id = secrets.token_hex(16)
    with ThreadPoolExecutor(max_workers=6) as pool:
        replies = list(pool.map(lambda _: post(2, 'same retry', client_id), range(6)))
    check(all(code == 200 for code, _ in replies) and len({data['data']['message']['id'] for _, data in replies}) == 1, 'Concurrent double-click/retries save exactly once')
    check(post(2, 'changed payload', client_id)[0] == 409, 'Reused ID cannot change content')
    fixture('age')
    with ThreadPoolExecutor(max_workers=8) as pool:
        codes = list(pool.map(lambda _: post(1)[0], range(8)))
    check(codes.count(200) == 5 and codes.count(429) == 3, 'Concurrent server rate limit exactly 5/10s')
    fixture('history')
    rows = get()[1]['data']['messages']
    check(len(rows) == 50 and rows[-1]['content'] == 'history 59' and rows[0]['content'] == 'history 10', 'Initial load is latest 50 in order')
    page = get(after=first['id'])[1]['data']
    check(len(page['messages']) == 50 and page['has_more'], 'Incremental backlog paginated')
    page2 = get(after=page['after_id'])[1]['data']
    check(page2['messages'][0]['id'] > page['messages'][-1]['id'], 'Next page continues without overlap')
    fixture('age')
    fixture('expire')
    check(not get()[1]['data']['can_send'] and post()[0] == 409, 'Deadline enforces readonly before game poll finalizes')
    fixture('finish')
    check(get(2)[0] == 200 and post(2)[0] == 409, 'Finished members keep readonly history')
    check(post(1,first['content'],first['client_message_id'])[1]['data']['message']['id']==first['id'], 'Lost-response retry after finish returns the existing message')
    next_match = fixture('restart')['map_data']
    next_id = next_match['chat_match_id']
    check(next_id != match_id and get(mid=next_id)[1]['data']['messages'] == [], 'Same room restart gets clean new identity')
    check(get(mid=match_id)[0] == 200 and post(mid=match_id)[0] == 409, 'Old match stays readable and cannot receive new messages')
    print(f'Apache + isolated SQLite: {checks} assertions passed', flush=True)
    if len(sys.argv) > 1:
        fixture('history')
        fixture('age')
        edge_profile = tempfile.mkdtemp(prefix='pvp-chat-edge-')
        sock = socket.socket(); sock.bind(('127.0.0.1', 0)); port = sock.getsockname()[1]; sock.close()
        edge = subprocess.Popen([sys.argv[1], '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-allow-origins=*', f'--remote-debugging-port={port}', '--user-data-dir='+edge_profile, 'about:blank'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        browser_config = dict(port=port, base=f'http://127.0.0.1:{server.server_port}', sessions=sessions[:2], map=next_match)
        result = subprocess.run(['node', str(root/'tests/pvp_chat_browser.mjs')], input=json.dumps(browser_config).encode(), capture_output=True, timeout=120)
        print(result.stdout.decode(errors='replace'), end='')
        if result.returncode: raise RuntimeError(result.stderr.decode(errors='replace'))
    else:
        print('Browser checks skipped: pass an Edge executable path.')
finally:
    if edge:
        edge.terminate()
        try: edge.wait(timeout=5)
        except subprocess.TimeoutExpired: edge.kill()
    if server: server.shutdown(); server.server_close()
    gateway.unlink(missing_ok=True)
    for session in sessions:
        try: fixture('destroy-session', **session)
        except Exception: pass
    shutil.rmtree(scratch)
    if edge_profile: shutil.rmtree(edge_profile, ignore_errors=True)
