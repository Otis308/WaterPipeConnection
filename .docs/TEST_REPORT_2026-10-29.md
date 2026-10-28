# Kết quả kiểm thử — 29/10/2026

**Cập nhật sau người dùng restart/cài Storage/thêm bio:** metadata MySQL đã có bio/phone/address; avatar smoke, 58 backend assertion, MySQL temporary và 7 Edge upload/reload checks qua. DOM audit chooser qua. Đăng ký/đăng nhập + matchmaking HTTP và 14 Edge checks qua, tài khoản thử đã dọn. Node 8080 chạy và handshake/greeting qua; đóng kết nối còn mã1006/wasClean=false, server thiếu phản hồi close frame ở opcode0x8. Các lỗi thiếu bio/Storage/Node ghi bên dưới là trạng thái trước khi người dùng chuẩn bị lại môi trường. Sáu test legacy và giới hạn gameplay realtime vẫn còn. Chi tiết mới nhất ở WORK_STATUS.

Nhánh `integrate-huunghia-on-thong-20261029`, nền commit `c3d158c`. Báo cáo kết quả chạy trong phiên này, không phải bảo đảm toàn bộ ứng dụng hoạt động. Không sửa code chức năng, cài dependency, import SQL, rebuild/restart hoặc sửa tài khoản cũ. File SQL người dùng đang chỉnh/stage được giữ nguyên.

## Tổng quan 12 nhóm

| Nhóm | Cách kiểm tra | Kết quả |
| --- | --- | --- |
| 1. Cú pháp | PHP lint toàn bộ PHP tracked; Node check JS ứng dụng/test; compile Python không tạo pycache | 120 PHP, 25 JS và 7 Python test qua; Node check websocket-server/server.js cũng qua |
| 2. Core OOP/BFS/Elo/seed/PvP cũ | tests/run_tests.php đủ 23 ca trong bản sao tạm, SQLite riêng, broadcast giả | 17 qua, 6 không qua; chi tiết bên dưới |
| 3. Scoring, queue và HTTP/game DOM | run_scoring_tests.php, scoring_integration.php, scoring_browser.test.js, scoring_http_test.py + Edge | 42 +25 +21 +10 HTTP +7 DOM assertion qua; không dùng DB production |
| 4. Khóa/mở và đăng nhập | account_toggle_test.php, SQLite :memory: | 15 assertion qua, gồm từ chối đăng nhập bị khóa và đăng nhập lại sau mở |
| 5. Generator portal | portal_map_generator_test.php, PHP thuần | 1.778 assertion qua |
| 6. Bộ tìm lời giải độc lập | portal_bfs_test.cjs và 42 fixture map sinh từ PHP | Qua: thao tác hợp lệ, nối đủ cặp, xung đột cấu hình/cross, witness giả không ảnh hưởng; không bảo đảm ít thao tác nhất |
| 7. Preview, nước, giao diện quản lý gộp | portal_map_preview_test.cjs, portal_water_test.cjs; render Blade thật bằng dữ liệu tổng hợp | Mô phỏng DOM qua; 7 kiểm tra cấu trúc dashboard qua. Không phải E2E portal bằng tài khoản admin thật |
| 8. Lưu nguyên map thành level | portal_level_save_test.php, real SQL writer/SQLite :memory: | 10 assertion qua; không tạo level trên DB đang dùng |
| 9. Chat PvP | pvp_chat_http_test.py + Edge, pvp_chat_mysql_test.php | 30 HTTP +20 Edge +8 MySQL TEMPORARY assertion qua. Browser fixture chặn socket script vì incompatibility có sẵn; không suy ra WebSocket thật chạy tốt |
| 10. Profile/lịch sử cá nhân | profile_http_test.py, project/SQLite/session riêng, Edge | 67 HTTP +7 Edge assertion qua |
| 11. Avatar | avatar_storage_smoke.php, avatar_upload_http_test.py, profile_avatar_dom_audit.py, profile_runtime_probe.php | Không qua do runtime Storage thiếu; upload input bị disabled đúng guard. Test historical blocked-upload đã retired, không tính là pass |
| 12. Đăng ký/matchmaking realtime | matchmaking_http_test.py --browser, tài khoản thử ngẫu nhiên; kiểm tra TCP 8080 | Dừng ở đăng ký: HTTP 200 nhưng body PHP fatal, Unknown column bio. Phần matchmaking/browser sau đăng ký chưa chạy. Port 8080 không có TCP listener, chưa kiểm gameplay realtime |

Các số assertion có phần giao nhau; không cộng thành một số “tính năng đã qua”. Những script fixture/browser helper không được gọi độc lập ngoài harness của chúng.

## Lỗi đã xác nhận

### Đăng ký mới bị lỗi schema

- Metadata MySQL đọc qua kết nối PDO trực tiếp, transaction READ ONLY: `user_profiles` hiện **có phone và address**, **không có bio**.
- `PdoDatabaseService::registerPlayer()` INSERT user_profiles có cột bio (file service dòng 118–119); `registerPlayer()`/luồng lưu user khác cũng có INSERT tương tự. HTTP đăng ký thử trả 200 với fatal `SQLSTATE[42S22] / 1054 Unknown column 'bio' in 'field list'`.
- Hai lần thử đăng ký của harness được dọn theo đúng tên thử ngẫu nhiên; mỗi lần tạo được một user trước khi lỗi profile. Không sửa user cũ. Không tự thêm cột trong đợt kiểm tra này.
- Vì harness dừng ở đăng ký, không kết luận matchmaking hỏng; chưa đi tới bước ghép trận.

### Runtime avatar chưa đủ điều kiện

- `AvatarStorage::ready()` yêu cầu Composer autoload riêng và link public/storage cùng GD/fileinfo.
- Probe Docker: thiếu `bootstrap/avatar-storage/vendor/autoload.php`, thiếu link `public/storage`; adapter Storage không được khởi tạo. GD decode/fileinfo có.
- Smoke thất bại `Avatar runtime is not ready`; upload HTTP dừng ở assertion input enabled; audit Edge báo upload không enabled. Đây không phải lỗi bộ chọn file tự phát, mà input bị khóa vì backend chưa ready.
- Không cài Composer, tạo link hoặc restart Docker trong nhiệm vụ kiểm thử.

## Sáu ca của bộ 23 test cũ không qua

| Test | Quan sát |
| --- | --- |
| test_bfs_verifies_simple_connected_pipe | Fixture pipes thiếu start/end theo contract mới; không được hoàn thành như kỳ vọng cũ |
| test_bfs_rejects_path_through_wrong_pair_endpoint | Kỳ vọng trường/giá trị legacy không còn phù hợp output hiện tại |
| test_bfs_calculates_detour_penalty_and_rejects_completion_below_100 | Kỳ vọng luật cũ: 90 điểm, dưới 100 chưa hoàn thành; trái luật quality mới tách kết nối khỏi điểm |
| test_match_service_rejects_fake_win_and_score_over_99_in_progress | Fixture room thiếu started_at_ts nên nhận “Đã hết giờ thi đấu”; test còn kỳ vọng cấm 100 trong progress theo luật cũ |
| test_match_service_submit_solution_rejects_fake_win_and_incomplete_solution | Kết quả final_rank khác kỳ vọng; warning thiếu is_completed; cần cập nhật fixture/thời gian/contract trước khi kết luận lỗi runtime |
| test_match_service_submit_solution_idempotency | Truy cập score không tồn tại trong response hiện tại; warning; cần đối chiếu contract/fixture mới |

Không chỉnh kỳ vọng test cho qua. Bộ hồi quy scoring mới chạy riêng **25/25 assertion qua** trên SQLite :memory:. Vì vậy không quy sáu ca trên thành sáu lỗi game đã được xác nhận.

## Môi trường, cô lập và giới hạn

- PHP Docker 8.3.36; PHP host 8.3.30 dùng cho fixture HTTP; Node 24.11.0; Edge headless với profile/session riêng.
- PHP host ban đầu trỏ extension_dir sang đường dẫn C: không tồn tại, gây thiếu PDO driver. Đã chạy lại bằng override extension_dir chỉ trong tiến trình test / file ini tạm; không sửa php.ini. Kết quả chính ở trên dùng lần chạy đã có driver.
- MySQL chat dùng bảng TEMPORARY che tên bảng trong kết nối riêng. Legacy runner sao chép source vào tests/.full-suite-*, ép MySQL host local port 1 để fallback SQLite riêng, không copy .env; BroadcastingService chỉ được thay bằng fake trong bản sao tạm, không trong source dự án.
- Các thư mục/gateway/session fixture được harness dọn; kiểm tra cuối không còn thư mục test ẩn. Chỉ báo cáo được thêm vào source.
- 42 map solver gồm các ca 5x8 hard/3 cặp SEED_HUIT_2026, 5x12 hard/4 cặp và 36 ca hồi quy theo kích thước/độ khó/số cặp. Ca 5x8 hard/3 cặp xét 46 bước đường, 34 thao tác; đo bằng Node, không hứa thời gian Edge giống hệt.
- Chưa xác nhận gameplay WebSocket thật, OTP/email thật, các luồng shop/payment hoặc mọi CRUD qua trình duyệt; không có suite tự động an toàn bao phủ hết chúng trong bộ test hiện tại. Không gửi email/broadcast thật trong legacy fixture. Test không có nghĩa mọi tính năng đều đã E2E.

Ưu tiên tiếp theo nếu sửa: đồng bộ bio của user_profiles và lỗi JSON HTTP đăng ký; phục hồi runtime avatar; cập nhật bộ 23 test theo contract mới; sau đó chạy lại matchmaking/browser khi có môi trường cần thiết. Đợt này chỉ kiểm thử, chưa thực hiện các sửa đó.
