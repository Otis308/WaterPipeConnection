# ĐỒ ÁN MÔN HỌC: WATER PIPE CONNECTION

## 1. HƯỚNG DẪN KHỞI CHẠY DỰ ÁN 

Sau khi clone hoặc tải file zip về máy tính:
```bash
git clone <url-du-an>
cd GameNoiOngNuoc
```

### Khởi chạy bằng Docker
1. Mở **Docker Desktop** trên máy tính của bạn.
2. Nhấp đúp chuột chạy file **`setup.bat`** ngay tại thư mục gốc của dự án.
   - Script sẽ tự động kiểm tra Docker, build 3 container (`app`, `db`, `phpmyadmin`), nạp dữ liệu mẫu và mở trình duyệt.
3. Khởi động **WebSocket Server** để hỗ trợ thi đấu PvP Real-time:
   ```bash
   node websocket-server/server.js
   ```
   *(Server sẽ chạy tại `ws://localhost:8080` và API Broadcast nội bộ tại `http://localhost:8080/broadcast`)*
4. Mở trình duyệt truy cập:
   - 🎮 **Trang Game chính:** [http://localhost:8000](http://localhost:8000)
   - ⚙️ **Cổng Quản trị HUIT Admin:** [http://localhost:8000/admin](http://localhost:8000/admin)
   - 🗄️ **phpMyAdmin:** [http://localhost:8081](http://localhost:8081) *(User: `root`, Mật khẩu: `rootpassword`)*

## 2. CHẠY BỘ KIỂM THỬ TỰ ĐỘNG 

Dự án tích hợp sẵn **23 ca kiểm thử tự động (Unit & Feature Tests)** bao phủ toàn diện:
- Server Authority (AC1 — Xác thực bàn cờ bằng BFS, chống gian lận điểm).
- Seed Uniformity (AC3 — Sinh bản đồ đồng nhất theo seed).
- Elo Pairwise Zero-Sum (AC6 — Xếp hạng Elo đối ứng cho 2 đến 4 người chơi).

**Cách chạy kiểm thử:**
- **Trên Windows:** Nhấp đúp chuột chạy file **`run-tests.bat`**.
- **Hoặc qua dòng lệnh Docker:**
  ```bash
  docker exec -it huit_pipegame_app php tests/run_tests.php
  ```
*(Kết quả kỳ vọng: 23 PASSED, 0 FAILED).*

---

## 3. DANH SÁCH TÀI KHOẢN THỰC THI DEMO (RBAC PHÂN QUYỀN)

| Vai trò | Tên đăng nhập | Email | Mật khẩu chung | Mô tả quyền hạn |
| :--- | :--- | :--- | :--- | :--- |
| **Admin** | `admin` | `admin@huit.edu.vn` | `password123` | Toàn quyền hệ thống, đổi Role người dùng, quản lý toàn bộ màn chơi. |
---

## 4. CẤU TRÚC TÀI LIỆU KỸ THUẬT
1. 📂 [`.docs/ARCHITECTURE.md`](.docs/ARCHITECTURE.md): Sơ đồ kiến trúc 4 tầng, cây thư mục toàn diện, mô hình ERD 11 bảng CSDL, và luồng dữ liệu thời gian thực.
2. 📂 [`.docs/ALGORITHM.md`](.docs/ALGORITHM.md): Toàn bộ thuật toán AI (Hill Climbing, Random-Restart, Winding DFS, BFS Water Flow, Elo Pairwise Zero-Sum) kèm mã nguồn, dòng code, hàm số.