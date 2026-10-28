# Sửa lỗi hàng đợi PvP — 28/10/2026

## Nguyên nhân và bằng chứng trước sửa

Đã đọc `vendor/autoload.php` → `bootstrap/huit_hybrid_kernel.php` (session/env) + `database/stubs.php`. Script home nạp `TrangChu.js` rồi `PvpSocket.js`, `PvpLobby.js`; không có hàm matchmaking trùng trong các script được nạp. Luồng thực:

`PvpLobby.handleStartPvP()` (option “Đấu 2 người” value=1 → target=2) → `startMatchmaking()` → POST `/api/pvp/matchmaking/join` → `public/index.php` → `PvpApiController::joinMatchmaking()` → `PvpRoomService::joinMatchmaking()` → `cleanupStalePlayerRooms()`.

Hai tài khoản thử mới đăng nhập qua `/api/auth/login`, mỗi tài khoản có cookie jar riêng; `/api/auth/me` đều 200 đúng ID. POST thử không gửi user_id hay Bearer token để chứng minh session thật được dùng.

Phản hồi trước sửa (tài khoản/ID thử và stack rút gọn):

```text
POST /api/pvp/matchmaking/join
HTTP 200
Content-Type: text/html; charset=UTF-8
Fatal error: Uncaught PDOException: SQLSTATE[42S02]: Base table or view not found:
1146 Table 'huit_water_pipe_db.pvp_room_players' doesn't exist
PvpRoomService.php:587, cleanupStalePlayerRooms()
```

Đây là response HTTP nhận được thành công nhưng `res.json()` thất bại vì HTML. Catch cũ gộp lỗi parse với fetch và hiện câu “Lỗi kết nối khi vào hàng đợi tìm trận”. Không có lỗi nghiệp vụ JSON trong phản hồi này. Không suy ra lỗi WebSocket từ thông báo đó.

Kết nối PDO MySQL **trực tiếp, transaction READ ONLY**, không khởi tạo `PdoDatabaseService`, xác nhận database `huit_water_pipe_db` có 15 bảng nền/auth, năm cột rating users và **thiếu cả bốn bảng** rooms/players/queue/history. Database trong fatal HTTP trùng database metadata. `ensurePvpSchema()` cũ chỉ CREATE bảng cho SQLite; migration PHP có khai báo bảng nhưng facade Schema là stub, không chạy DDL. SQL init cũng thiếu các bảng đó.

Apache access log trước sửa (UTC):

```text
[28/Oct/2026:11:16:15 +0000] "POST /api/pvp/matchmaking/join HTTP/1.1" 200
```

PHP runtime có `display_errors=STDOUT`, `log_errors=Off`, `error_log` rỗng. Vì vậy trước sửa không có fatal tương ứng trong stderr; fatal nằm ngay trong body HTML đã thu. Apache error log trỏ `/dev/stderr`, access log trỏ `/dev/stdout`; đọc bằng `docker logs`.

## Thay đổi

- `database/migrations/2026_10_28_pvp_mysql_schema.sql`: chỉ CREATE TABLE IF NOT EXISTS bốn bảng PvP nền, giữ contract migration hiện có + cột board/map/history mà service đang dùng. InnoDB/utf8mb4, FK theo users BIGINT UNSIGNED, unique room/user và queue user. Không seed, reset, DROP, import SQL gốc hay thay bảng nền.
- `PdoDatabaseService::ensurePvpSchema()`: chạy file DDL đó bằng PDO MySQL thật; giữ nhánh SQLite và các bổ sung cột cũ. `connect()` chỉ fallback khi mở MySQL thất bại; lỗi DDL trên DB đã chọn được truyền ra.
- `public/index.php`: dispatch riêng hai POST matchmaking, bọc cả constructor/service bằng Throwable; tắt display_errors, bật log_errors cho hai endpoint, trả JSON 500/no-store và ghi route + exception message/code/file/line vào server log. Response vẫn `{success,message,data}`; không gửi stack/SQL cho client. `PvpApiController::joinMatchmaking()` trả 422 cho cấu hình sai, auth hiện có trả 401. Không đổi auth của các API khác.
- `PvpRoomService::joinMatchmaking()/checkAndMatchQueue()`: sau khi bổ sung bảng, đã tái hiện người thứ hai matched nhưng poll của người đầu lại waiting. Nguyên nhân assignment bị DELETE, poll lại INSERT. Sửa giữ matched_room_code/status trên cột sẵn có, trả room theo đúng caller; poll giữ timestamp/preferences. Hủy xóa queue của user như trước. Không đổi luật/rating.
- `PvpLobby.js`: phân biệt fetch không kết nối, Content-Type/JSON sai, HTTP và nghiệp vụ; áp dụng cho join/poll/cancel. Poll không chồng, phản hồi sau hủy không mở lại phòng. Lỗi giữ nút hủy để xử lý trạng thái chưa được xác nhận; hủy lỗi không giả báo thành công.

## Kiểm tra đã chạy

`python tests/matchmaking_http_test.py --browser "C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe"`:

| Kiểm tra thật trên Apache/MySQL | Status, Content-Type, body rút gọn |
| --- | --- |
| Người đầu join | 200 application/json; success=true, data.status=waiting, matched_count=1 |
| Hủy hàng đợi | 200 application/json; success=true, data=null |
| Người kia join sau hủy | 200 application/json; waiting, matched_count=1 |
| Người thứ hai join để ghép | 200 application/json; matched, room có đúng hai user |
| Người đầu poll | 200 application/json; matched, cùng room_code, is_me_in_room=true |
| Không session | 401 application/json; success=false, data=null |
| Cấu hình sai | 422 application/json; success=false, data=null |
| Constructor exception cô lập qua index/Apache | 500 application/json; success=false, data=null; thông báo chung, không có SQLSTATE |

Test exception dùng gateway tạm thay duy nhất lớp DB bằng constructor ném lỗi mô phỏng, **không kết nối DB**; không phá bảng production để gây lỗi. Gateway tự dọn trong finally. Log sau sửa (UTC, tên gateway rút gọn):

```text
[28/Oct/2026:11:22:20 +0000] "POST /api/pvp/matchmaking/join HTTP/1.1" 422
[28/Oct/2026:11:22:20 +0000] "POST /api/pvp/matchmaking/join HTTP/1.1" 401
[php:notice] [PvP matchmaking] /api/pvp/matchmaking/join:
MM_TEST_ONLY SQLSTATE[42S02]: simulated missing table (code 0) at .../.mm-error-<test>.php:3
[28/Oct/2026:11:22:20 +0000] "POST /.mm-error-<test>.php HTTP/1.1" 500
```

**14 kiểm tra Edge headless qua**, hai context độc lập cookies/localStorage/sessionStorage. Đăng nhập bằng nút Sign In thật, chọn PvP/Ngẫu nhiên/Đấu 2 người/Bắt đầu bằng DOM click, xác nhận người đầu chờ, hủy và đối thủ vẫn chờ riêng, sau đó cả hai thấy cùng phòng chờ `HUIT53` ở lần chốt, đủ 2/2; không alert kết nối trong luồng thường. Không chặn/sửa script socket trong luồng này. Sáu nhánh lỗi client được tiêm bằng fetch/Response **chỉ trong browser thử**: mất mạng, HTML 200, JSON hỏng, JSON 500, nghiệp vụ 422, session 401; đều hiện thông báo riêng.

`docker exec huit_pipegame_app php tests/scoring_integration.php`: **25 assertion qua** với SQLite :memory: và broadcast giả. PHP lint các file runtime sửa + helper test qua, Node check lobby/browser qua. Không chạy suite cũ có thể ghi dữ liệu nền.

Metadata sau sửa có 19 bảng (thêm bốn bảng PvP nền, chưa tạo bảng chat). SHA-256 nội dung và số hàng của **15 bảng nền giống nhau trước/sau đợt kiểm tra cuối**, gồm users=7, profiles=7, game_sessions=18. Qua nhiều request, CREATE chạy lại không xóa dữ liệu. Tài khoản/phòng/queue thử được dọn theo đúng username ngẫu nhiên của test, giữ user cũ. Auto-increment của bảng tài khoản/phòng có thể tăng do tạo rồi dọn dữ liệu thử.

## Giới hạn và việc tiếp theo

Dừng tại phòng chờ hai người; chưa kiểm tra ready/start-match/gameplay/WebSocket toàn trận trong nhiệm vụ này. Không sửa source tính điểm/profile/avatar/chat/socket. Không restart/rebuild/nâng dependency.

Edge ghi lỗi riêng có sẵn `CustomModal.init()` gọi appendChild khi document.body chưa tồn tại (`public/JS/custom-modal.js:71`, file được nạp trong head); không chặn luồng ghép trận đã kiểm. Headless ghi sáu lỗi autoplay do DOM click không cấp user gesture; đây là giới hạn kiểm thử. Browser test ghi riêng hai nhóm này và fail nếu có exception khác; không sửa hoặc chặn script để che lỗi. Lỗi `PvpSocket.private()` ở trang trận được ghi trong nhiệm vụ chat trước đây **chưa được tái kiểm tra lần này**.
