"""Real Apache/controller/Laravel Storage, isolated SQLite and two fixture owners.

python tests/avatar_upload_http_test.py [<msedge.exe>] [--mysql-check] [--restart-check]
The temporary gateway injects only the fixture PDO; production dispatcher/kernel
are untouched. A loopback proxy maps /profile to that header-protected gateway.
Images and their URLs use the real public disk/Apache, not a fake filesystem.
"""
import base64
import hashlib
from http.client import HTTPConnection, HTTPException
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
from pathlib import Path
import re
import secrets
import shutil
import socket
import sqlite3
import subprocess
import sys
import tempfile
from threading import Thread
import time
from urllib.error import HTTPError
from urllib.parse import urlencode
from urllib.request import build_opener, HTTPRedirectHandler, ProxyHandler, Request

root = Path(__file__).resolve().parents[1]
tag = secrets.token_hex(12)
scratch = root / 'tests' / ('.avatar-upload-' + tag)
scratch.mkdir()
gateway = root / 'public' / ('.avatar-fixture-' + tag + '.php')
gateway_key = secrets.token_hex(24)
uid = 1500000000 + secrets.randbelow(300000000)
other_id = uid + 1
fixture = dict(sid=secrets.token_hex(24), csrf=secrets.token_hex(24), uid=uid)
dbfile = scratch / 'profile.sqlite'
db = sqlite3.connect(dbfile)
checks = 0
cookie_name = 'PHPSESSID'
server = None


def check(condition, message):
    global checks
    checks += 1
    if not condition:
        raise AssertionError(message)


def docker(*args):
    result = subprocess.run(['docker', *args], capture_output=True, timeout=30)
    if result.returncode:
        raise RuntimeError(result.stderr.decode(errors='replace')[:500])
    return result.stdout


def session(action):
    encoded = base64.b64encode(json.dumps(dict(fixture, action=action)).encode()).decode()
    return json.loads(docker('exec', '--user', 'www-data', 'huit_pipegame_app', 'php',
                             '/var/www/html/tests/avatar_upload_fixture.php', encoded))


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


client = build_opener(ProxyHandler({}), NoRedirect())


class FixtureProxy(BaseHTTPRequestHandler):
    def forward(self):
        if self.path.split('?', 1)[0] == '/profile':
            target = '/' + gateway.name + ('?' + self.path.split('?', 1)[1] if '?' in self.path else '')
        elif self.command == 'GET' and self.path.startswith(('/CSS/', '/JS/', '/Bootstrap/', '/Images/', '/storage/')):
            target = self.path
        elif self.path == '/favicon.ico':
            self.send_response(204)
            self.end_headers()
            return
        else:
            self.send_error(404)
            return
        payload = self.rfile.read(int(self.headers.get('Content-Length', 0))) if self.command == 'POST' else None
        headers = {name: self.headers[name] for name in ['Cookie', 'Content-Type', 'User-Agent'] if name in self.headers}
        headers['X-Avatar-Fixture'] = gateway_key
        connection = HTTPConnection('127.0.0.1', 8000, timeout=20)
        try:
            connection.request(self.command, target, body=payload, headers=headers)
            response = connection.getresponse()
            body = response.read()
            self.send_response(response.status)
            for name, value in response.getheaders():
                if name.lower() not in ['connection', 'transfer-encoding', 'content-length', 'date', 'server']:
                    self.send_header(name, value)
            self.send_header('Content-Length', str(len(body)))
            self.end_headers()
            self.wfile.write(body)
        except (OSError, HTTPException):
            # Expected during --restart-check; return a retryable response instead
            # of a noisy server-thread traceback while Apache is restarting.
            try:
                self.send_error(503)
            except OSError:
                pass
        finally:
            connection.close()

    do_GET = do_POST = forward

    def log_message(self, *args):
        pass


def request(path='/profile', payload=None, content_type=None, authenticated=True):
    headers = {}
    if authenticated:
        headers['Cookie'] = cookie_name + '=' + fixture['sid']
    if content_type:
        headers['Content-Type'] = content_type
    try:
        response = client.open(Request(base + path, data=payload, headers=headers), timeout=20)
    except HTTPError as error:
        response = error
    with response:
        return response.status, response.read(), response.headers


def upload(data=None, filename='avatar.png', mime='image/png', token=None, extra=None):
    boundary = 'avatar-upload-' + secrets.token_hex(12)
    fields = dict(_token=fixture['csrf'] if token is None else token, name='Avatar fixture', bio='Fixture bio', user_id=str(other_id))
    fields.update(extra or {})
    body = b''.join((f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n').encode() for key, value in fields.items())
    if data is not None:
        body += (f'--{boundary}\r\nContent-Disposition: form-data; name="avatar"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n').encode() + data + b'\r\n'
    body += f'--{boundary}--\r\n'.encode()
    return request(payload=body, content_type='multipart/form-data; boundary=' + boundary)


def avatar():
    return db.execute('SELECT avatar FROM user_profiles WHERE user_id=?', (uid,)).fetchone()[0]


def files():
    folder = root / 'storage/app/public/avatars' / str(uid)
    return {item.name for item in folder.iterdir()} if folder.exists() else set()


def failure(data, filename, mime, message):
    old = avatar()
    previous = files()
    check(upload(data, filename, mime)[0] == 303, message + ': POST handled')
    notice = session('inspect')['notice']
    check(notice and not notice['success'], message + ': error shown')
    check(avatar() == old and files() == previous, message + ': old image/DB preserved, no orphan')


try:
    db.executescript('''
        CREATE TABLE users(id INTEGER PRIMARY KEY, username TEXT, name TEXT, email TEXT, created_at TEXT);
        CREATE TABLE user_profiles(user_id INTEGER PRIMARY KEY, bio TEXT, avatar TEXT DEFAULT 'Images/CupSao.png');
        CREATE TABLE game_sessions(id INTEGER PRIMARY KEY, user_id INTEGER, game_mode TEXT, result TEXT,
            score_earned INTEGER, telemetry_nosql TEXT, played_at TEXT, map_size TEXT, difficulty TEXT, duration_ms INTEGER);
    ''')
    for owner, name in [(uid, 'avatar-fixture'), (other_id, 'other-fixture')]:
        db.execute('INSERT INTO users VALUES(?,?,?,?,?)', (owner, name, name, name + '@invalid.test', '2026-10-28'))
        db.execute('INSERT INTO user_profiles(user_id,bio) VALUES(?,?)', (owner, 'Fixture bio'))
    db.commit()
    gateway.write_text('''<?php
if (!hash_equals('__KEY__', $_SERVER['HTTP_X_AVATAR_FIXTURE'] ?? '')) { http_response_code(404); exit; }
require dirname(__DIR__) . '/vendor/autoload.php';
$fixtureDir = dirname(__DIR__) . '/tests/.avatar-upload-__TAG__';
if (($_GET['driver'] ?? '') === 'mysql') {
    // Connection-local temporary tables shadow the real names. Never call the
    // production DB constructor or modify an existing MySQL user/profile row.
    $host = getenv('DB_HOST') ?: 'db';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_DATABASE') ?: 'huit_water_pipe_db';
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        getenv('DB_USERNAME') ?: 'root', (getenv('DB_PASSWORD') === false ? '' : getenv('DB_PASSWORD')), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TEMPORARY TABLE users (id BIGINT PRIMARY KEY, username VARCHAR(100), name VARCHAR(100), email VARCHAR(120), created_at TIMESTAMP NULL)');
    $pdo->exec("CREATE TEMPORARY TABLE user_profiles (user_id BIGINT PRIMARY KEY, bio TEXT, avatar VARCHAR(255) DEFAULT 'Images/CupSao.png')");
    $pdo->prepare('INSERT INTO users VALUES(?,?,?,?,NULL)')->execute([__UID__, 'avatar-fixture', 'Avatar fixture', 'fixture@invalid.test']);
    $pdo->prepare('INSERT INTO user_profiles(user_id,bio) VALUES(?,?)')->execute([__UID__, 'Fixture bio']);
    register_shutdown_function(static function() use($pdo,$fixtureDir) {
        $stmt=$pdo->prepare('SELECT avatar FROM user_profiles WHERE user_id=?');
        $stmt->execute([__UID__]);
        file_put_contents($fixtureDir.'/mysql-result.json', json_encode(['avatar'=>$stmt->fetchColumn(), 'in_transaction'=>$pdo->inTransaction()]));
    });
} else {
    $pdo = new PDO('sqlite:' . $fixtureDir . '/profile.sqlite');
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$reflection = new ReflectionClass(App\\Services\\PdoDatabaseService::class);
$db = $reflection->newInstanceWithoutConstructor();
$reflection->getProperty('conn')->setValue($db, $pdo);
$reflection->getProperty('driver')->setValue($db, ($_GET['driver'] ?? '') === 'mysql' ? 'mysql' : 'sqlite');
$controller = new App\\Http\\Controllers\\PlayerProfileController($db);
if ($_SERVER['REQUEST_METHOD'] === 'POST') $controller->update();
else $controller->show();
'''.replace('__KEY__', gateway_key).replace('__TAG__', tag).replace('__UID__', str(uid)), encoding='utf-8')
    session('seed')
    image_data = session('images')['images']
    images = {key: base64.b64decode(value) for key, value in image_data.items()}
    for extension, data in images.items():
        (scratch / ('fixture.' + extension)).write_bytes(data)
    server = ThreadingHTTPServer(('127.0.0.1', 0), FixtureProxy)
    thread = Thread(target=server.serve_forever, daemon=True)
    thread.start()
    base = f'http://127.0.0.1:{server.server_port}'
    default_hash = hashlib.sha256((root / 'public/Images/CupSao.png').read_bytes()).hexdigest()
    status, page, _ = request()
    check(status == 200 and b'Blade Render Error' not in page, 'Real Blade/controller renders fixture')
    check(b'name="avatar"' in page and b'enctype="multipart/form-data"' in page, 'Named file input and multipart form')
    input_tag = re.search(rb'<input id="profile-avatar"[^>]+>', page)[0]
    check(b'disabled' not in input_tag, 'File input enabled only with ready backend')
    check(request(authenticated=False)[0] == 302, 'Anonymous cannot see private profile')
    check(upload(images['png'], token='invalid')[0] in (419, 500), 'Invalid CSRF cannot upload')
    check(avatar() == 'Images/CupSao.png', 'Invalid CSRF did not change avatar')
    previous = None
    for extension, mime in [('png', 'image/png'), ('jpg', 'image/jpeg'), ('webp', 'image/webp')]:
        check(upload(images[extension], '../../client-name.php', mime)[0] == 303, 'Upload ' + extension)
        notice = session('inspect')['notice']
        check(notice and notice['success'], 'Success notice ' + extension)
        current = avatar()
        check(re.fullmatch(fr'avatars/{uid}/[a-f0-9]{{40}}\.{extension}', current) is not None, 'Random relative path, ignores client filename')
        check(db.execute('SELECT avatar FROM user_profiles WHERE user_id=?', (other_id,)).fetchone()[0] == 'Images/CupSao.png', 'POST user_id cannot alter another owner')
        status, content, headers = request('/storage/' + current)
        check(status == 200 and content == images[extension], 'Stored image served at Storage URL')
        check(headers.get_content_type() == mime and headers.get('X-Content-Type-Options') == 'nosniff', 'Correct safe response MIME')
        check(current.encode() in request()[1], 'Reload profile keeps committed new avatar')
        if previous:
            check(not (root / 'storage/app/public' / previous).exists(), 'Old owned image deleted after successful replacement')
        previous = current
    check(hashlib.sha256((root / 'public/Images/CupSao.png').read_bytes()).hexdigest() == default_hash, 'Default image never deleted or modified')
    failure(b'<?php echo "bad";', 'fake.png', 'image/png', 'Forged MIME')
    failure(b'<svg xmlns="http://www.w3.org/2000/svg"/>', 'avatar.svg', 'image/svg+xml', 'SVG rejected')
    failure(images['png'] + b'x' * (2 * 1024 * 1024), 'large.png', 'image/png', 'More than 2 MB')
    failure(images['png'][:40], 'truncated.png', 'image/png', 'Undecodable image')
    old = avatar()
    check(upload(extra={'avatar': '../../other.png'})[0] == 303 and not session('inspect')['notice']['success'], 'Client path string rejected')
    check(avatar() == old, 'Client path cannot replace image')
    db.execute("CREATE TRIGGER fail_avatar BEFORE UPDATE ON user_profiles BEGIN SELECT RAISE(ABORT,'fixture failure'); END")
    db.commit()
    failure(images['png'], 'valid.png', 'image/png', 'DB failure rolls back new file and text')
    db.execute('DROP TRIGGER fail_avatar')
    db.commit()
    folder = '/var/www/html/storage/app/public/avatars/' + str(uid)
    docker('exec', 'huit_pipegame_app', 'chmod', '0555', folder)
    try:
        failure(images['png'], 'valid.png', 'image/png', 'Storage write failure')
    finally:
        docker('exec', 'huit_pipegame_app', 'chmod', '0755', folder)
    # A forged old DB reference must never cause another owner's file to be removed.
    other_path = f'avatars/{other_id}/' + secrets.token_hex(20) + '.png'
    other_file = root / 'storage/app/public' / other_path
    other_file.parent.mkdir()
    other_file.write_bytes(images['png'])
    db.execute('UPDATE user_profiles SET avatar=? WHERE user_id=?', (other_path, uid))
    db.commit()
    check(upload(images['png'])[0] == 303 and session('inspect')['notice']['success'], 'Replace a foreign old path safely')
    check(other_file.read_bytes() == images['png'], 'Another owner image is never deleted')
    check(upload()[0] == 303 and session('inspect')['notice']['success'], 'Text-only save still works')
    if '--mysql-check' in sys.argv:
        # Reuse multipart bytes through the proxy, selecting only temporary tables.
        boundary = 'mysql-avatar-' + secrets.token_hex(10)
        payload = b''.join((f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n').encode()
                           for key,value in dict(_token=fixture['csrf'],name='Avatar fixture',bio='Fixture bio').items())
        payload += (f'--{boundary}\r\nContent-Disposition: form-data; name="avatar"; filename="fixture.png"\r\nContent-Type: image/png\r\n\r\n').encode() + images['png'] + f'\r\n--{boundary}--\r\n'.encode()
        check(request('/profile?driver=mysql', payload, 'multipart/form-data; boundary='+boundary)[0] == 303,
              'MySQL temporary table upload handled')
        check(session('inspect')['notice']['success'], 'MySQL metadata/FOR UPDATE/avatar transaction succeeded')
        mysql_result = json.loads((scratch / 'mysql-result.json').read_text('utf-8'))
        check(not mysql_result['in_transaction'] and re.fullmatch(fr'avatars/{uid}/[a-f0-9]{{40}}\.png', mysql_result['avatar']) is not None,
              'MySQL commit saved relative avatar in the connection-local temporary profile')
        check(request('/storage/' + mysql_result['avatar'])[1] == images['png'], 'MySQL temporary profile image serves correctly')
        print('MySQL: real connection, temporary users/profiles only; existing rows/schema untouched.')
    if len(sys.argv) > 1 and sys.argv[1] != '--restart-check':
        with socket.socket() as listener:
            listener.bind(('127.0.0.1', 0))
            debug_port = listener.getsockname()[1]
        with tempfile.TemporaryDirectory(prefix='avatar-upload-edge-') as edge_profile:
            edge = subprocess.Popen([sys.argv[1], '--headless', '--disable-gpu', '--no-first-run',
                '--no-default-browser-check', '--disable-extensions', '--disable-background-networking',
                '--window-size=1200,1100',
                '--host-resolver-rules=MAP fonts.googleapis.com ~NOTFOUND, MAP fonts.gstatic.com ~NOTFOUND',
                '--user-data-dir=' + edge_profile, '--remote-debugging-port=' + str(debug_port), 'about:blank'],
                stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            try:
                config = dict(port=debug_port, base=base, sid=fixture['sid'], cookie=cookie_name,
                    image=str(scratch / 'fixture.png'))
                result = subprocess.run(['node', str(root / 'tests/avatar_upload_browser.mjs')],
                    input=json.dumps(config).encode(), capture_output=True, timeout=60)
                if result.returncode:
                    raise RuntimeError(result.stderr.decode(errors='replace')[:1200])
                report = json.loads(result.stdout)
                check(report['passed'], 'Real browser chooser/preview/save/reload')
                print('Browser:', json.dumps(report))
            finally:
                if edge.poll() is None:
                    edge.terminate()
                edge.wait(timeout=8)
    if '--restart-check' in sys.argv:
        persisted = avatar()
        content_before = request('/storage/' + persisted)[1]
        docker('restart', 'huit_pipegame_app')
        for attempt in range(40):
            try:
                if request('/storage/' + persisted)[0] == 200:
                    break
            except Exception:
                pass
            time.sleep(.2)
        check(request('/storage/' + persisted)[1] == content_before, 'Real image URL survives container restart')
        session('seed')
        check(persisted.encode() in request()[1], 'Profile fixture DB path and avatar survive restart')
    print(f'Avatar backend: {checks} assertions passed; real Apache and Storage, isolated SQLite/fixture owners.')
finally:
    if server:
        server.shutdown()
        server.server_close()
        thread.join(timeout=3)
    try:
        session('destroy')
    finally:
        db.close()
        gateway.unlink(missing_ok=True)
        for owner in [uid, other_id]:
            folder = root / 'storage/app/public/avatars' / str(owner)
            if folder.exists():
                for image in folder.iterdir():
                    assert re.fullmatch(r'[a-f0-9]{40}\.(png|jpg|webp)', image.name), 'Unexpected file in fixture folder'
                    image.unlink()
                folder.rmdir()
        resolved = scratch.resolve()
        assert resolved.is_relative_to(root / 'tests') and resolved.name == '.avatar-upload-' + tag
        shutil.rmtree(resolved)
