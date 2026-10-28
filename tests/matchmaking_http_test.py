"""Real localhost:8000 dispatcher + MySQL, two real logins with independent cookie jars.
Creates two disposable accounts; removes only its own rows. No reset/seed/import.
Run --reproduce before repair, or no argument to verify queue/match/cancel.
"""
import base64
import http.cookiejar
import json
from pathlib import Path
import secrets
import shutil
import socket
import subprocess
import sys
import tempfile
import urllib.error
import urllib.request

sys.stdout.reconfigure(encoding='utf-8')
tag = secrets.token_hex(8)
names = [f'mm_probe_{tag}_{suffix}' for suffix in 'ab']
password = secrets.token_urlsafe(24)
clients = [urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar())) for _ in range(3)]
root = Path(__file__).resolve().parents[1]


def request(client, path, payload=None):
    req = urllib.request.Request('http://127.0.0.1:8000' + path,
        data=json.dumps(payload).encode() if payload is not None else None,
        headers={'Content-Type': 'application/json', 'Accept': 'application/json'})
    try:
        response = client.open(req, timeout=30)
    except urllib.error.HTTPError as exc:
        response = exc
    body = response.read().decode('utf-8', errors='replace')
    try:
        data = json.loads(body)
    except ValueError:
        data = None
    return response.status, response.headers.get('Content-Type'), body, data


def report(label, response):
    status, content_type, body, data = response
    # Only report matchmaking bodies; login responses contain authentication tokens.
    print(json.dumps({'step': label, 'status': status, 'content_type': content_type,
        'body': data if data is not None else body[:1800]}, ensure_ascii=False))


try:
    ids = []
    for client, name in zip(clients, names):
        r = request(client, '/api/auth/register', {'username': name, 'password': password})
        assert r[0] == 201 and r[3]['success'], 'Registration failed (body withheld)'
        r = request(client, '/api/auth/login', {'identifier': name, 'password': password})
        assert r[0] == 200 and r[3]['success'], 'Login failed (body withheld)'
        ids.append(r[3]['data']['id'])
        me = request(client, '/api/auth/me')
        assert me[0] == 200 and me[3]['data']['id'] == ids[-1]
    assert ids[0] != ids[1]
    print('Two independent cookie sessions authenticated through real login API; tokens withheld.')
    payload = {'target_players': 2, 'preferred_size': '5x6', 'preferred_diff': 'easy'}
    first = request(clients[0], '/api/pvp/matchmaking/join', payload)
    report('first join', first)
    if '--reproduce' not in sys.argv:
        assert first[0] == 200 and first[3]['data']['status'] == 'waiting'
        cancel = request(clients[0], '/api/pvp/matchmaking/cancel', {})
        report('cancel', cancel)
        assert cancel[0] == 200 and cancel[3]['success']
        second = request(clients[1], '/api/pvp/matchmaking/join', payload)
        report('second alone after cancellation', second)
        assert second[3]['data']['status'] == 'waiting' and second[3]['data']['matched_count'] == 1
        matched = request(clients[0], '/api/pvp/matchmaking/join', payload)
        report('pair matched', matched)
        assert matched[0] == 200 and matched[3]['data']['status'] == 'matched'
        room = matched[3]['data']['room']
        assert sorted(p['user_id'] for p in room['players']) == sorted(ids)
        poll = request(clients[1], '/api/pvp/matchmaking/join', payload)
        report('other session polls', poll)
        assert poll[3]['data']['status'] == 'matched' and poll[3]['data']['room_code'] == room['room_code']
        assert poll[3]['data']['room']['is_me_in_room']
        # Leave via the actual API before a second, browser-driven matchmaking run.
        for client in clients[:2]:
            assert request(client, '/api/pvp/leave-room', {'room_code': room['room_code']})[3]['success']
            assert request(client, '/api/pvp/matchmaking/cancel', {})[3]['success']
        invalid = request(clients[0], '/api/pvp/matchmaking/join', {'target_players': 9})
        report('invalid config', invalid)
        assert invalid[0] == 422 and invalid[3]['success'] is False
        anonymous = request(clients[2], '/api/pvp/matchmaking/join', payload)
        report('anonymous', anonymous)
        assert anonymous[0] == 401 and anonymous[3]['success'] is False
        # An isolated constructor failure exercises the real index/Apache JSON boundary.
        # This gateway never opens the shared database; it is removed in finally.
        gateway = root / 'public' / ('.mm-error-' + tag + '.php')
        gateway.write_text('''<?php
namespace App\\Services { class PdoDatabaseService {
    public function __construct() { throw new \\PDOException('MM_TEST_ONLY SQLSTATE[42S02]: simulated missing table'); }
} }
namespace {
    $_SERVER['REQUEST_URI'] = '/api/pvp/matchmaking/join';
    require __DIR__ . '/index.php';
}
''', encoding='utf-8')
        try:
            failure = request(clients[2], '/' + gateway.name, {})
            report('isolated constructor exception', failure)
            assert failure[0] == 500 and failure[1].startswith('application/json')
            assert failure[3]['success'] is False and failure[3]['data'] is None
            assert 'SQLSTATE' not in failure[2] and 'MM_TEST_ONLY' not in failure[2]
        finally:
            gateway.unlink(missing_ok=True)
        print('PASS: waiting, cancel, match, same room on poll, JSON 401/422/500; technical error withheld.')
        if '--browser' in sys.argv:
            edge_path = sys.argv[sys.argv.index('--browser') + 1]
            profile = tempfile.mkdtemp(prefix='mm-edge-')
            sock = socket.socket(); sock.bind(('127.0.0.1', 0)); port = sock.getsockname()[1]; sock.close()
            edge = subprocess.Popen([edge_path, '--headless=new', '--no-first-run', '--no-default-browser-check',
                '--disable-gpu', f'--remote-debugging-port={port}', f'--user-data-dir={profile}', 'about:blank'],
                stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, creationflags=subprocess.CREATE_NO_WINDOW)
            try:
                result = subprocess.run(['node', str(root / 'tests/matchmaking_browser.mjs')],
                    input=json.dumps({'port': port, 'names': names, 'password': password}).encode(),
                    capture_output=True, timeout=100)
                print(result.stdout.decode(errors='replace'))
                if result.returncode:
                    raise RuntimeError(result.stderr.decode(errors='replace')[:1800])
            finally:
                edge.terminate(); edge.wait(timeout=15)
                shutil.rmtree(profile, ignore_errors=True)
finally:
    encoded = base64.b64encode(json.dumps(names).encode()).decode()
    result = subprocess.run(['docker', 'exec', 'huit_pipegame_app', 'php',
        'tests/matchmaking_runtime.php', 'cleanup', encoded], capture_output=True, timeout=30)
    print(result.stdout.decode(errors='replace'))
    if result.returncode:
        print(result.stderr.decode(errors='replace')[:1200])
        raise RuntimeError('Test account cleanup failed')
