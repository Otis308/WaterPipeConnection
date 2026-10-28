"""Verify the blocked avatar path against the running Docker Apache, without DB access.

Usage: python tests/profile_avatar_blocked_http_test.py
Requires the existing huit_pipegame_app container on localhost:8000. Creates two
random fixture sessions, never follows redirects or GETs a DB-backed page, and
destroys only those sessions afterwards. This is NOT a successful uploader test.
"""
import base64
import json
import secrets
import subprocess
from pathlib import Path
from urllib.error import HTTPError
from urllib.request import build_opener, HTTPRedirectHandler, ProxyHandler, Request

# Historical diagnostic for the pre-upload implementation. Never send its fake
# session to a live upload-enabled controller/shared DB; use the isolated test.
if (Path(__file__).resolve().parents[1] / 'app/Services/AvatarStorage.php').exists():
    print('Historical blocked-upload test retired. Run tests/avatar_upload_http_test.py (isolated DB).')
    raise SystemExit(0)


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


client = build_opener(ProxyHandler({}), NoRedirect())
checks = 0
sessions = []


def check(condition, message):
    global checks
    checks += 1
    if not condition:
        raise AssertionError(message)


def session_action(fixture, action):
    # JSON is data, encoded as a base64 literal inside the fixed PHP program.
    # No cookie, CSRF token or session contents are printed in the report.
    encoded = base64.b64encode(json.dumps(dict(fixture, action=action)).encode()).decode()
    program = '''<?php
    $fixture = json_decode(base64_decode('__FIXTURE__'), true);
    session_id($fixture['sid']);
    require '/var/www/html/vendor/autoload.php';
    if (class_exists('Illuminate\\Support\\Facades\\Storage')
        || class_exists('Illuminate\\Filesystem\\FilesystemManager')) {
        session_destroy();
        fwrite(STDERR, "This test only supports the verified missing-Storage runtime.\n");
        exit(2);
    }
    if ($fixture['action'] === 'seed') {
        $_SESSION = ['_token' => $fixture['csrf']];
        if ($fixture['authenticated']) {
            $_SESSION['huit_auth_user'] = ['id' => 2147483647, 'username' => 'avatar-fixture-only'];
        }
    }
    $result = [
        'cookie_name' => session_name(),
        'notice' => $_SESSION['profile_notice'] ?? null,
        'fixture_identity_unchanged' => !$fixture['authenticated']
            || ($_SESSION['huit_auth_user'] ?? []) === ['id' => 2147483647, 'username' => 'avatar-fixture-only'],
        'default_avatar_hash' => hash_file('sha256', '/var/www/html/public/Images/CupSao.png'),
    ];
    if ($fixture['action'] === 'destroy') {
        $_SESSION = [];
        session_destroy();
    } else {
        session_write_close();
    }
    echo json_encode($result);
    '''.replace('__FIXTURE__', encoded)
    # Match Apache's OS user so PHP's session ownership checks can read the fixture.
    result = subprocess.run(['docker', 'exec', '--user', 'www-data', '-i', 'huit_pipegame_app', 'php'],
                            input=program.encode(), capture_output=True, timeout=15)
    if result.returncode:
        raise RuntimeError('Fixture session action failed: ' + result.stderr.decode(errors='replace')[:300])
    return json.loads(result.stdout)


def upload(fixture, filename, payload, mime, valid_csrf=True):
    boundary = 'avatar-fixture-' + secrets.token_hex(12)
    data = b''
    for name, value in {
        '_token': fixture['csrf'] if valid_csrf else 'invalid-fixture-token',
        'name': 'must-not-save', 'bio': 'must-not-save', 'user_id': '2',
    }.items():
        data += (f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n').encode()
    data += (f'--{boundary}\r\nContent-Disposition: form-data; name="avatar"; filename="{filename}"\r\n'
             f'Content-Type: {mime}\r\n\r\n').encode() + payload + f'\r\n--{boundary}--\r\n'.encode()
    request = Request('http://127.0.0.1:8000/profile', data=data, headers={
        'Content-Type': f'multipart/form-data; boundary={boundary}',
        'Cookie': fixture['cookie_name'] + '=' + fixture['sid'],
    })
    try:
        response = client.open(request, timeout=15)
    except HTTPError as error:
        response = error
    with response:
        body = response.read().decode('utf-8', errors='replace')
        return response.status, response.headers.get('Location'), '<title>HTTP 419</title>' in body


try:
    for authenticated in [False, True]:
        fixture = dict(sid=secrets.token_hex(24), csrf=secrets.token_hex(24), authenticated=authenticated)
        sessions.append(fixture)
        state = session_action(fixture, 'seed')
        fixture['cookie_name'] = state['cookie_name']
        fixture['original_avatar_hash'] = state['default_avatar_hash']
    anonymous, owner = sessions
    png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')
    anonymous_result = upload(anonymous, 'avatar.png', png, 'image/png')
    check(anonymous_result[:2] == (302, '/'),
          f'Anonymous fixture must redirect without reaching avatar update; got {anonymous_result}')
    csrf_result = upload(owner, 'avatar.png', png, 'image/png', False)
    check(csrf_result[0] in (419, 500) and csrf_result[2],
          'Invalid CSRF must render the real 419 rejection page before avatar processing')
    for filename, data, mime in [
        ('valid.png', png, 'image/png'),
        ('fake.png', b'<?php echo "must-not-run";', 'image/png'),
        ('avatar.svg', b'<svg xmlns="http://www.w3.org/2000/svg"/>', 'image/svg+xml'),
        ('large.png', png + b'x' * (2 * 1024 * 1024), 'image/png'),
    ]:
        check(upload(owner, filename, data, mime)[:2] == (303, '/profile'),
              'Multipart request reaches the existing avatar rejection path')
        state = session_action(owner, 'inspect')
        notice = state['notice'] or {}
        check(notice.get('success') is False and 'chưa có Laravel Storage' in notice.get('message', ''),
              'The server explicitly rejects avatar while real Storage is missing')
        check(state['fixture_identity_unchanged'] and state['default_avatar_hash'] == owner['original_avatar_hash'],
              'Rejected upload preserves fixture identity and the default image')
    print(f'Avatar blocked path: {checks} assertions passed against Docker Apache localhost:8000.')
    print(f'Invalid CSRF: HTTP {csrf_result[0]}, application rendered HTTP 419 page; no upload accepted.')
    print('Valid PNG, forged PNG, SVG and >2 MB were all rejected; no successful upload or image validation claimed.')
finally:
    for fixture in sessions:
        session_action(fixture, 'destroy')
