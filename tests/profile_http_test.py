"""Real index.php + isolated source copy, SQLite, sessions, uploads and HTTP.

Usage: python tests/profile_http_test.py <php.exe> [<msedge.exe>]
Add --browser-only after the Edge path to run only the browser fixture.
Never boots a DB-backed route in the original workspace.
"""
from contextlib import contextmanager
from http.cookiejar import CookieJar
import json
import os
from pathlib import Path
import re
import shutil
import socket
import sqlite3
import subprocess
import sys
import tempfile
import time
import uuid
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import build_opener, HTTPCookieProcessor, HTTPRedirectHandler, Request

root = Path(__file__).resolve().parents[1]
php = sys.argv[1] if len(sys.argv) > 1 else 'php'
checks = 0


def check(condition, message):
    global checks
    checks += 1
    if not condition:
        raise AssertionError(message)


@contextmanager
def isolated_project():
    target = root / 'tests' / ('.profile-http-' + uuid.uuid4().hex)
    target.mkdir()
    try:
        for folder in ['app', 'bootstrap', 'resources', 'public']:
            shutil.copytree(root / folder, target / folder, ignore=shutil.ignore_patterns('storage') if folder == 'public' else None)
        for filename in ['vendor/autoload.php', 'database/stubs.php']:
            dest = target / filename
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(root / filename, dest)
        (target / 'storage/app').mkdir(parents=True)
        yield target
    finally:
        resolved = target.resolve()
        assert resolved.is_relative_to(root / 'tests') and resolved.name.startswith('.profile-http-')
        for attempt in range(30):
            try:
                shutil.rmtree(resolved)
                break
            except PermissionError:
                if attempt == 29:
                    raise
                time.sleep(.1)


def seed(target):
    password = subprocess.run([php], input=b'<?php echo password_hash("profile-fixture-only", PASSWORD_BCRYPT);',
                              capture_output=True, check=True).stdout.decode()
    version = re.search(r"const VERSION = '([^']+)'", (root / 'app/Services/PipeScoringService.php').read_text('utf-8'))[1]
    conn = sqlite3.connect(target / 'storage/app/huit_fallback.sqlite')
    conn.executescript('''
        CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT UNIQUE, name TEXT, email TEXT,
          password TEXT, role TEXT DEFAULT 'user', is_active INTEGER DEFAULT 1,
          high_score INTEGER DEFAULT 0, total_games INTEGER DEFAULT 0, total_wins INTEGER DEFAULT 0,
          coins INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
        CREATE TABLE user_profiles (user_id INTEGER PRIMARY KEY, avatar TEXT DEFAULT 'Images/CupSao.png');
        CREATE TABLE game_sessions (id INTEGER PRIMARY KEY, user_id INTEGER, game_mode TEXT,
          result TEXT, score_earned INTEGER, map_size TEXT, difficulty TEXT, duration_ms INTEGER,
          telemetry_nosql TEXT, played_at TEXT);
        CREATE TABLE pvp_rooms (id INTEGER PRIMARY KEY, room_code TEXT, map_size TEXT,
          difficulty TEXT, num_pairs INTEGER, map_seed TEXT);
        CREATE TABLE pvp_room_players (id INTEGER PRIMARY KEY, room_id INTEGER, user_id INTEGER,
          board_rotations_json TEXT, UNIQUE(room_id, user_id));
        CREATE TABLE pvp_match_history (id INTEGER PRIMARY KEY, room_id INTEGER, user_id INTEGER,
          final_rank INTEGER, score INTEGER, duration_ms INTEGER, win_status TEXT, created_at TEXT,
          UNIQUE(room_id, user_id));
    ''')
    for uid in [1, 2, 3]:
        conn.execute('INSERT INTO users(id,username,name,email,password) VALUES(?,?,?,?,?)',
                     (uid, f'fixture{uid}', f'Player {uid}', f'private{uid}@invalid.test', password))
    conn.execute('INSERT INTO user_profiles(user_id) VALUES(1)')
    conn.execute("INSERT INTO user_profiles(user_id,avatar) VALUES(2,'Images/NenLon.png')")
    for i in range(1, 27):
        meta = dict(scoring_version=version, quality_score=44 if i == 24 else 30)
        if i == 25:
            meta['quality_score'] = None
        if i == 23:
            meta = {}
        conn.execute('INSERT INTO game_sessions VALUES(?,?,?,?,?,?,?,?,?,?)',
                     (i, 1, 'player', 'win' if i % 2 == 0 else 'lose', 9999 if i == 23 else 0,
                      f'OWN{i:02}', 'easy', 12345, json.dumps(meta),
                      '2020-01-01' if i == 26 else '2026-10-28 12:00:00'))
    for i, uid in [(27, 1), (28, 2)]:
        conn.execute('INSERT INTO game_sessions VALUES(?,?,?,?,?,?,?,?,?,?)',
                     (i, uid, 'ai' if uid == 1 else 'player', 'win', 100, 'AI-ONLY' if uid == 1 else 'OTHER-PRIVATE',
                      'hard', 20000, json.dumps(dict(scoring_version=version, quality_score=100)), '2026-10-28 13:00:00'))
    for i in range(1, 13):
        conn.execute('INSERT INTO pvp_rooms VALUES(?,?,?,?,?,?)', (i, f'ROOM{i}', f'PVP{i:02}', 'medium', 2, 'INTERNAL-SEED'))
        meta = {'quality_score': None if i == 12 else (80 if i == 10 else 30)} if i != 11 else {}
        conn.execute('INSERT INTO pvp_room_players(room_id,user_id,board_rotations_json) VALUES(?,?,?)',
                     (i, 1, json.dumps({'scoring': meta, 'private': 'INTERNAL-BOARD'})))
        conn.execute('INSERT INTO pvp_match_history VALUES(?,?,?,?,?,?,?,?)',
                     (i, i, 1, 1 if i % 2 else 2, 9999 if i == 11 else 0, 20000,
                      'loss' if i == 1 else ('win' if i % 2 else 'loss'), '2026-10-28 14:00:00'))
    conn.execute("INSERT INTO pvp_match_history VALUES(99,1,2,1,100,1,'win','2026-10-28 14:00:00')")
    conn.commit()
    return conn


def run_browser_checks(target, base):
    # This bootstrap exists only inside the throwaway public directory.
    # A data favicon avoids an automatic, initially anonymous PHP request racing
    # this immediate login and replacing its session cookie in the test browser.
    (target / 'public/profile-browser-check.html').write_text('''<!doctype html><meta charset="utf-8"><link rel="icon" href="data:,">
        <iframe id="frame" style="width:375px;height:800px;border:0"></iframe><pre id="result">WAIT</pre>
        <script>
        (async () => {
            const result = document.getElementById('result');
            let count = 0;
            const check = (value, message) => { if (!value) throw Error(message); count++; };
            const frame = document.getElementById('frame');
            const load = path => new Promise(resolve => { frame.onload = resolve; frame.src = path; });
            try {
                const response = await fetch('/api/auth/login', {method:'POST', headers:{'Content-Type':'application/json'},
                    body:JSON.stringify({identifier:'fixture1',password:'profile-fixture-only'})});
                check((await response.json()).success, 'browser login');
                await load('/');
                check(frame.contentWindow.getComputedStyle(frame.contentDocument.getElementById('myProfileLink')).display !== 'none', 'home profile link');
                await load('/profile');
                const doc = frame.contentDocument, win = frame.contentWindow;
                check(doc.documentElement.scrollWidth <= doc.documentElement.clientWidth, '375px viewport without page overflow');
                check(win.getComputedStyle(doc.querySelector('.profile-stats')).gridTemplateColumns.split(' ').length === 2, 'mobile stats columns');
                check(doc.getElementById('profile-avatar').disabled, 'avatar explicitly unavailable');
                const form = doc.getElementById('profile-form');
                form.addEventListener('submit', event => event.preventDefault());
                form.dispatchEvent(new win.Event('submit', {bubbles:true,cancelable:true}));
                check(doc.getElementById('profile-save').disabled && doc.getElementById('profile-save-status').textContent.includes('Đang lưu'), 'saving state');
                await load('/profile');
                check(!frame.contentDocument.getElementById('profile-save').disabled, 'save button restored after navigation');
                result.textContent = 'PASS: ' + count + ' browser assertions';
            } catch(error) { result.textContent = 'FAIL: ' + error.message; }
        })();
        </script>''', encoding='utf-8')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        debug_port = sock.getsockname()[1]
    edge = subprocess.Popen([sys.argv[2], '--headless', '--disable-gpu', '--no-first-run',
                             '--no-default-browser-check', '--disable-extensions', '--window-size=430,900',
                             '--host-resolver-rules=MAP fonts.googleapis.com ~NOTFOUND, MAP fonts.gstatic.com ~NOTFOUND',
                             '--user-data-dir=' + str(target / 'edge'), '--remote-debugging-port=' + str(debug_port),
                             base + '/profile-browser-check.html'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        result = subprocess.run(['node', str(root / 'tests/profile_browser_check.mjs'), str(debug_port)],
                                capture_output=True, timeout=50)
        label = result.stdout.decode('utf-8', errors='replace').strip()
        assert result.returncode == 0 and label == 'PASS: 7 browser assertions', \
            'Browser checks: ' + (label or result.stderr.decode('utf-8', errors='replace')[:500])
    finally:
        if edge.poll() is None:
            edge.terminate()
        edge.wait(timeout=8)
    print('Profile browser: 7 assertions passed (375px viewport, real login, form and assets).')


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


with isolated_project() as target, tempfile.TemporaryDirectory(prefix='profile-session-') as session_dir:
    conn = seed(target)
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    base = f'http://127.0.0.1:{port}'
    env = dict(os.environ, DB_HOST='127.0.0.1', DB_PORT='1', DB_DATABASE='profile_test_only',
               DB_USERNAME='profile_test_only', DB_PASSWORD='fixture-only', HUIT_SCORING_CACHE_DIR=str(target / 'cache'))
    process = subprocess.Popen([php, '-d', f'session.save_path={session_dir}',
                                '-d', 'upload_max_filesize=4M', '-d', 'post_max_size=5M',
                                '-S', f'127.0.0.1:{port}', '-t', 'public', 'public/index.php'],
                               cwd=target, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
                               creationflags=subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0)
    jar = CookieJar()
    browser = build_opener(HTTPCookieProcessor(jar), NoRedirect())

    def request(path, data=None, content_type=None, client=browser):
        if isinstance(data, dict):
            data = urlencode(data).encode()
            content_type = 'application/x-www-form-urlencoded'
        req = Request(base + path, data=data, headers={'Content-Type': content_type} if content_type else {})
        try:
            response = client.open(req, timeout=8)
        except HTTPError as error:
            response = error
        with response:
            return response.status, response.read().decode('utf-8', errors='replace'), response.headers

    def login(uid=1):
        status, body, _ = request('/api/auth/login', json.dumps({'identifier': f'fixture{uid}', 'password': 'profile-fixture-only'}).encode(), 'application/json')
        assert status == 200 and json.loads(body)['success'], 'Fixture login failed'
        assert list(jar), 'Fixture login did not set a session cookie'
        assert list(Path(session_dir).iterdir()), 'Fixture session directory is empty'

    def get_profile(path='/profile'):
        status, html, headers = request(path)
        error = re.search(r'(?:Blade Render Error:|Fatal error:)[^<]+', html)
        check(status == 200 and error is None, f'Profile renders with real Blade engine (HTTP {status}, {error[0] if error else "no render error"})')
        return html, headers

    def save(fields, token):
        status, _, headers = request('/profile', dict(_token=token, **fields))
        check(status == 303 and headers['Location'] == '/profile', 'Save redirects using POST/redirect/GET')
        return get_profile()[0]

    try:
        for _ in range(60):
            try:
                request('/JS/Profile.js')
                break
            except URLError:
                if process.poll() is not None:
                    raise RuntimeError('PHP test server exited')
                time.sleep(.05)
        if '--browser-only' in sys.argv:
            run_browser_checks(target, base)
            raise SystemExit(0)
        status, _, headers = request('/profile?user_id=2')
        check(status == 302 and headers['Location'] == '/', 'Anonymous profile redirects without reading owner data')
        status, _, _ = request('/profile', {'name': 'anonymous'})
        check(status == 419 and conn.execute('SELECT name FROM users WHERE id=1').fetchone()[0] == 'Player 1', 'Anonymous POST cannot write')
        login()
        html, headers = get_profile('/profile?user_id=2')
        check('private1@invalid.test' in html and 'private2@invalid.test' not in html, 'GET ignores supplied user ID')
        check('no-store' in headers['Cache-Control'], 'Private profile cannot be cached')
        check('name="avatar"' in html and 'enctype="multipart/form-data"' in html and ' disabled ' in html, 'Multipart avatar field stays disabled in an unconfigured isolated fixture')
        check('bio' not in [row[1] for row in conn.execute('PRAGMA table_info(user_profiles)')], 'GET profile never adds bio column')
        check(html.count('<tbody>') == 1 and html.count('<tr>') == 11 and 'Trang 1 / 3' in html, 'History displays exactly 10 rows')
        check('OTHER-PRIVATE' not in html and 'OWN25' in html and 'OWN26' not in html, 'History filters owner and sorts timestamp before ID')
        check('9999 (điểm cũ)' in html and 'Chưa xác định' in html and '44/100' in html, 'Legacy and unknown solo scores stay distinct')
        check('<strong>26</strong>' in html and '<strong>13</strong>' in html and '<strong>50%</strong>' in html, 'Solo stats exclude AI and other owner')
        check('<strong>44/100</strong>' in html and '<strong>100/100</strong>' not in html, 'Solo best ignores legacy score and AI quality')
        page2 = get_profile('/profile?source=solo&page=2&user_id=2')[0]
        page3 = get_profile('/profile?source=solo&page=999')[0]
        check(page2.count('<tr>') == 11 and 'OWN25' not in page2 and page3.count('<tr>') == 8 and 'OWN26' in page3, 'Pagination clamps page and returns final seven rows')
        pvp = get_profile('/profile?source=pvp')[0]
        check(pvp.count('<tr>') == 11 and 'Trang 1 / 2' in pvp and 'PVP12' in pvp, 'PvP uses separate paginated source')
        check('INTERNAL-SEED' not in pvp and 'INTERNAL-BOARD' not in pvp and 'rating_before' not in pvp, 'No internal PvP fields are rendered')
        check('<strong>80/100</strong>' not in pvp and '9999 (điểm lưu, chưa rõ thang điểm)' in pvp
              and '0 (điểm lưu, chưa rõ thang điểm)' in pvp, 'PvP never infers historical quality from mutable board metadata or zero sentinel')
        check('<strong>12</strong>' in pvp and '<strong>6</strong>' in pvp, 'PvP wins use final rank even for legacy default win_status')
        pvp2 = get_profile('/profile?source=pvp&page=2')[0]
        check(pvp2.count('<tr>') == 3 and pvp2.count('>Thắng</td>') == 1, 'PvP second page contains remaining two rows and correct legacy result')
        token = re.search(r'name="_token" value="([^"]+)"', html)[1]
        for fields in [{}, {'_token': 'wrong'}, {'_token[]': 'array'}]:
            status, _, _ = request('/profile', dict(name='bad-csrf', **fields))
            check(status == 419, 'Missing, invalid and malformed CSRF rejected')
        malicious = '<script>alert("profile")</script>'
        saved = save({'name': malicious, 'bio': 'Hello <img src=x onerror=alert(1)>', 'user_id': 2,
                      'username': 'overwrite', 'email': 'overwrite@invalid.test', 'avatar_path': '../other'}, token)
        check('Đã lưu hồ sơ' in saved and '&lt;script&gt;' in saved and malicious not in saved and '&lt;img' in saved, 'Valid text saved and escaped')
        check(conn.execute('SELECT name,username,email FROM users WHERE id=2').fetchone() == ('Player 2', 'fixture2', 'private2@invalid.test'), 'POST user_id cannot change another owner')
        check(conn.execute('SELECT username,email FROM users WHERE id=1').fetchone() == ('fixture1', 'private1@invalid.test'), 'Username and email are not editable')
        check(conn.execute('SELECT bio,avatar FROM user_profiles WHERE user_id=1').fetchone() == ('Hello <img src=x onerror=alert(1)>', 'Images/CupSao.png'), 'Only allowed text fields update; bio added on save')
        status, body, _ = request('/api/auth/me')
        check(status == 200 and json.loads(body)['data']['name'] == malicious, 'Session display name refreshed')
        for invalid in [{'name': '   ', 'bio': ''}, {'name': 'x' * 101}, {'name': 'Okay', 'bio': 'x' * 501}, {'name[]': 'array'}, {'name': 'bad\x00name'}]:
            result = save(invalid, token)
            check('alert-danger' in result and conn.execute('SELECT name FROM users WHERE id=1').fetchone()[0] == malicious, 'Invalid input cannot change profile')
        check('Đã lưu hồ sơ' in save({'name': 'Tên hợp lệ', 'bio': ''}, token), 'Vietnamese name and empty bio accepted; repeat schema operation is safe')

        # Avatar upload integration is tested separately with real GD/Storage/Apache.
        conn.execute("CREATE TRIGGER fail_bio BEFORE UPDATE ON user_profiles BEGIN SELECT RAISE(ABORT, 'fixture failure'); END")
        conn.commit()
        failed = save({'name': 'rollback-me', 'bio': 'fail'}, token)
        check('Chưa lưu được hồ sơ' in failed and conn.execute('SELECT name FROM users WHERE id=1').fetchone()[0] == 'Tên hợp lệ', 'DB failure rolls back name and bio together')
        conn.execute('DROP TRIGGER fail_bio')
        conn.commit()
        login(3)
        empty = get_profile()[0]
        check('Bạn chưa có lịch sử' in empty and '/Images/CupSao.png' in empty and '<strong>0</strong>' in empty, 'Empty history, zero games and default avatar')
        token3 = re.search(r'name="_token" value="([^"]+)"', empty)[1]
        save({'name': 'No existing profile', 'bio': 'Created'}, token3)
        check(conn.execute('SELECT bio FROM user_profiles WHERE user_id=3').fetchone()[0] == 'Created', 'Save creates missing profile row')
        login(1)
        for path in ['/', '/TrangGame.html']:
            status, page, _ = request(path)
            check(status == 200 and 'Blade Render Error' not in page and 'Fatal error' not in page, 'Home and game still render through real index')
            if path == '/':
                check(re.search(r'id="myProfileLink"[^>]*display:\s*flex', page) is not None, 'Home shows the profile link for an authenticated owner')
        for path in ['/JS/Profile.js', '/CSS/Profile.css', '/Images/CupSao.png', '/JS/TrangGame/BanDoGame.js']:
            check(request(path)[0] == 200, 'Required static asset still served')
        if len(sys.argv) > 2:
            run_browser_checks(target, base)
        # Missing PvP deployment state: source is unavailable, never fabricated zero stats.
        conn.execute('DROP TABLE pvp_match_history')
        conn.execute("CREATE VIEW pvp_match_history AS SELECT missing_column FROM users")
        conn.commit()
        unavailable = get_profile('/profile?source=pvp')[0]
        check('Chưa tải được nguồn lịch sử' in unavailable and 'Chưa có nguồn dữ liệu thống kê' in unavailable, 'Unavailable PvP data has an explicit state')
        print(f'Profile HTTP: {checks} assertions passed (isolated source/SQLite/sessions; avatar integration tested separately).')
    finally:
        process.terminate()
        process.wait(timeout=8)
        conn.close()
