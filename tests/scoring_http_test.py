"""Exercise the real index.php scoring route; no DB-backed route is requested."""
import json
import os
from pathlib import Path
import socket
import subprocess
import sys
import time
import uuid
import shutil
from contextlib import contextmanager
from urllib.request import Request, urlopen
from urllib.error import HTTPError, URLError

root = Path(__file__).resolve().parents[1]
php = sys.argv[1] if len(sys.argv) > 1 else "php"
@contextmanager
def scratch_directory():
    scratch = root / "tests" / (".pipe-score-http-" + uuid.uuid4().hex)
    scratch.mkdir(mode=0o755)
    try:
        yield str(scratch)
    finally:
        resolved = scratch.resolve()
        assert resolved.is_relative_to(root / "tests") and resolved.name.startswith(".pipe-score-http-")
        for attempt in range(20):
            try:
                shutil.rmtree(resolved)
                break
            except FileNotFoundError:
                break
            except PermissionError:
                if attempt == 19:
                    raise
                time.sleep(.1)

with scratch_directory() as scratch:
    with socket.socket() as listener:
        listener.bind(("127.0.0.1", 0))
        port = listener.getsockname()[1]
    env = dict(os.environ, HUIT_SCORING_CACHE_DIR=scratch)
    process = subprocess.Popen(
        [php, "-d", "session.save_path=" + scratch, "-S", f"127.0.0.1:{port}", "-t", "public", "public/index.php"],
        cwd=root, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
        creationflags=subprocess.CREATE_NO_WINDOW if os.name == "nt" else 0,
    )
    base = f"http://127.0.0.1:{port}"
    try:
        for _ in range(60):
            try:
                with urlopen(base + "/JS/TrangGame/PipeScore.js", timeout=1) as response:
                    assert response.status == 200
                break
            except URLError:
                if process.poll() is not None:
                    raise RuntimeError("Temporary PHP server exited")
                time.sleep(.05)
        cells = [dict(r=1, c=1, file="OngBatDau.svg", initialRot=0, role="start", pairId=1),
                 dict(r=1, c=2, file="OngXanh/OngXanh-Thang.svg", initialRot=0),
                 dict(r=1, c=3, file="OngKetThuc.svg", initialRot=180, role="end", pairId=1)]
        fixture = dict(numPairs=1, cells=cells, pipes=[dict(pairId=1, start=dict(r=1,c=1), end=dict(r=1,c=3))])

        def post(value):
            request = Request(base + "/api/game/scoring-baseline", data=json.dumps(value).encode(),
                              headers={"Content-Type": "application/json"})
            with urlopen(request, timeout=5) as response:
                assert response.headers.get_content_type() == "application/json"
                return json.load(response)

        result = post(fixture)
        assert result["success"] and result["data"]["status"] == "proven"
        assert result["data"]["vectors"] == [[2]] and result["data"]["total"] == 2
        fixture["_scoring"] = dict(status="proven", vectors=[[999]])
        assert post(fixture)["data"]["vectors"] == [[2]], "HTTP route must discard caller certificates"
        assert post({})["data"]["status"] == "invalid"
        for malformed in [dict(fixture, cells="invalid"), dict(fixture, pipes=[None]),
                          dict(fixture, cells=[dict(cells[0], file=[])])]:
            assert post(malformed)["data"]["status"] == "invalid"
        try:
            post("x" * 65537)
            raise AssertionError("Oversized body accepted")
        except HTTPError as error:
            assert error.code == 422
        print("HTTP index.php: 10 assertions passed (isolated cache/session files, no DB)")
        if len(sys.argv) > 2:
            edge = subprocess.run([sys.argv[2], "--headless", "--disable-gpu", "--no-first-run",
                "--no-default-browser-check", "--disable-extensions", "--user-data-dir=" + scratch + "/edge",
                "--dump-dom", "--virtual-time-budget=3000", (root / "tests/scoring_dom_test.html").as_uri()],
                capture_output=True, timeout=25,
                creationflags=subprocess.CREATE_NO_WINDOW if os.name == "nt" else 0)
            dom=edge.stdout.decode("utf-8", errors="replace")
            assert 'PASS: 7 real DOM assertions' in dom, (edge.returncode,
                edge.stderr.decode('utf-8', errors='replace')[:1000], dom[dom.find('id="browserResult"'):][:2000])
            print("Edge DOM: 7 assertions passed (real game scripts, mock HTTP)")
    finally:
        process.terminate()
        try:
            process.wait(timeout=3)
        except subprocess.TimeoutExpired:
            process.kill()
            process.wait()
