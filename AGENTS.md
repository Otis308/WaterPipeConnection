# Hướng dẫn làm việc với WaterPipeConnection

- Bắt đầu mỗi nhiệm vụ bằng tài liệu này và [.docs/WORK_STATUS.md](.docs/WORK_STATUS.md), sau đó tra đúng mục trong [.docs/CODE_MAP.md](.docs/CODE_MAP.md).
- Bản đồ là chỉ dẫn tra cứu. Trước khi sửa, đọc mã nguồn hiện tại của phần liên quan và các phụ thuộc trực tiếp; chỉ mở rộng khi có căn cứ. Với file dài, tìm tên hàm/lớp, nơi gọi và nơi sử dụng trước khi đọc đoạn cần thiết.
- [.docs/ARCHITECTURE.md](.docs/ARCHITECTURE.md) và [.docs/ALGORITHM.md](.docs/ALGORITHM.md) là tài liệu tham khảo; các khác biệt đã đối chiếu nằm trong CODE_MAP. Không coi mô tả trong tài liệu là bằng chứng thực thi.
- Điểm vào HTTP hiện tại là `public/index.php`, tự phân tuyến sau khi nạp `vendor/autoload.php`. **Phải đọc autoloader tự viết này**, dù thông thường loại trừ `vendor`; nó nạp `bootstrap/huit_hybrid_kernel.php` và `database/stubs.php`.
- Không mặc định `routes/web.php`, `routes/api.php`, `bootstrap/app.php` được chạy, hay `app/Domain/PipeGame` là engine game trình duyệt. Xác minh lời gọi trước.
- Giữ đúng chữ hoa/thường, đặc biệt `public/JS`, `public/CSS`, `public/Images` và `resources/views`. Kiểm tra thứ tự script và khai báo hàm trùng khi sửa JavaScript dùng chung global.
- Ưu tiên Docker đang có. Compose quản lý `app`, `db`, `phpmyadmin`; WebSocket Node chạy riêng. Không tự cài lại môi trường, chạy `setup.bat`, Composer, nâng dependency, rebuild hoặc khởi động lại dịch vụ nếu nhiệm vụ không cần/không cho phép.
- Khảo sát không chạy migration, seed, import SQL hoặc thay đổi dữ liệu. `PdoDatabaseService::__construct()` có thể thay đổi schema/khởi tạo SQLite; cả GET tới controller và bộ test hiện có cũng có thể ghi. Muốn đọc schema, dùng kết nối chỉ đọc trực tiếp, không khởi tạo service này.
- Không đưa giá trị bí mật từ `.env`, Compose, SQL seed, cookie hay token vào tài liệu/báo cáo.
- Mỗi kết luận quan trọng cần đường dẫn và hàm/lớp làm căn cứ. Phân biệt đối chiếu tĩnh, kiểm tra cú pháp, chạy hàm thuần và thử nghiệm luồng thật; không suy rộng kết quả giữa chúng.
- Giữ hướng dẫn hiện có. Cập nhật CODE_MAP và WORK_STATUS khi thay đổi khiến thông tin cũ không còn đúng; ghi kiểm tra đã chạy, giới hạn và việc tiếp theo.
