# Avatar Storage runtime

This directory is a separate Composer project. **Do not run Composer at the repository root**: its `vendor/autoload.php` is handwritten. Keep this `composer.json` and `composer.lock`; the generated `vendor/` here is ignored. No `laravel/framework`, HTTP, auth or Eloquent package is installed.

From the existing project root, reproduce using Docker:

```powershell
docker compose build app
docker compose up -d --no-deps app
docker compose exec -T app composer install --working-dir=/var/www/html/bootstrap/avatar-storage --no-dev --no-interaction --prefer-dist --no-scripts
docker compose exec -T app php bootstrap/avatar-storage/setup.php
docker compose exec -T --user www-data app php tests/avatar_storage_smoke.php
```

Build is needed once for the newly added GD extension (JPEG/PNG/WebP). Only `app` is rebuilt/recreated. Do not reset/import/migrate the DB or remove volumes. Subsequent dependency installation uses the lockfile, not `composer update`.

Direct packages: `illuminate/filesystem`, `illuminate/container`, `illuminate/config` 12.x; `league/flysystem` and `league/flysystem-local` 3.x. Locked versions are in `composer.lock`; native image dependencies are in the existing Dockerfile. Native `fileinfo` and GD are required by avatar validation.

`bootstrap/avatar_storage.php` is loaded lazily by `App\Services\AvatarStorage`. It loads **this** Composer autoloader after the project's stubs, creates a small real container/config/filesystem manager, and obtains the real `Storage::disk('public')`. The explicit URL avoids the URL-normalization branch that would use methods missing from the existing `Str` stub. Existing stub classes and global helpers remain defined by the project; no classes are redefined or aliased. Smoke tests compare their reflection origins before/after boot.

The disk root is `storage/app/public`, URL `/storage`. `setup.php` safely creates the directories and `public/storage -> ../storage/app/public` link, refusing to overwrite an unrelated path. Files live in `avatars/<authenticated-user-id>/<40-random-hex>.<jpg|png|webp>`; only relative paths are stored in the existing `user_profiles.avatar`. The Compose bind mount `.:/var/www/html` keeps both images and the link across container restart/recreation. `storage/app/public/.htaccess` forces static serving and disables script execution/directory listing. Generated avatars are ignored by Git.

Validation uses genuine HTTP upload status, actual file size (2 MiB), `finfo`, matching `getimagesize` type, and successful GD decoding. Decode memory is bounded to 4096 px per side / 8 megapixels; the original bytes are preserved, including JPEG orientation metadata. SVG/executables/fake or unreadable images are rejected. Client filenames, MIME declarations, user IDs and paths are not trusted. Writes happen through the real disk; DB changes are transactional. A failed write/DB operation retains the old reference; a failed DB operation deletes the new file. The old path is deleted only after commit and only if it matches the current owner's managed path. Cleanup failure after commit is logged and leaves the new avatar usable; it may leave an orphan old image.

Checks (existing PHP/Node/Edge, no npm packages):

```powershell
docker compose exec -T --user www-data app php tests/avatar_storage_smoke.php
python tests/avatar_upload_http_test.py "C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" --mysql-check --restart-check
python tests/profile_avatar_dom_audit.py
```

The upload test creates a header-protected temporary gateway, injects a separate SQLite PDO without calling the production DB constructor, and uses two random fixture owners/sessions. A temporary loopback proxy routes `/profile` to this gateway. Controllers, session/CSRF, GD, Storage, public URLs and Apache are real; existing user/profile rows are not read or modified. Temporary gateway/DB/sessions/images are cleaned afterwards. `--restart-check` restarts only the app and verifies the committed public image remains accessible.

`--mysql-check` additionally connects using the existing DB environment and creates connection-local **TEMPORARY** users/profiles that shadow the real table names. It verifies the MySQL metadata query, `FOR UPDATE`, avatar update and commit using a real multipart upload. No existing row/schema is changed; temporary tables disappear when that connection ends. Persisted reload/browser checks use the isolated SQLite database.

Browser checks use Edge headless: trusted mouse click, `Page.fileChooserOpened`, file selection using CDP `DOM.setFileInputFiles`, actual preview, multipart form submission, upload status, committed URL and reload. This is browser automation, not a manually operated visible Windows file dialog. The production dispatcher is unchanged; controller tests use the fixture gateway to avoid mutating the shared DB.

The historical `profile_avatar_blocked_http_test.py` is retired for the upload-enabled implementation. The general Profile test keeps its fixture disk unconfigured and checks text/history independently; avatar integration is covered by the dedicated test above.

Reference: [Laravel 12 public disk and file URLs](https://laravel.com/docs/12.x/filesystem#the-public-disk).
