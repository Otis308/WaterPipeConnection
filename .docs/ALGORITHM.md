# TỔNG HỢP CÁC THUẬT TOÁN AI & GIẢI THUẬT TÍNH TOÁN TRONG GAME NỐI ỐNG NƯỚC
**Dành cho:** Báo cáo đồ án, giải trình kỹ thuật và vấn đáp cùng Giảng viên  
**Vị trí file:** `.docs/ALGORITHM.md`

---

## MỤC LỤC TRA CỨU

| STT | Tên thuật toán | Vị trí áp dụng | File chính | Hàm chính |
| :--- | :--- | :--- | :--- | :--- |
| **1** | **Leo đồi dốc nhất (Steepest-Ascent Hill Climbing)** | AI Bot tự động giải đố (Dễ & Trung bình) | `public/JS/TrangGame/CheDoAI.js` | `tinhDiemHeuristic()`, `thucHienAI_EasyMedium()` |
| **2** | **Leo đồi khởi động ngẫu nhiên (Random-Restart Hill Climbing)** | AI Bot vượt bẫy kẹt cụm ống vàng (Khó) | `public/JS/TrangGame/CheDoAI.js` | `thucHienAI_Hard()` (Khối xử lý kẹt) |
| **3** | **Tìm kiếm theo chiều rộng (BFS Pathfinding)** | Tìm chuỗi ô trung gian để hoán đổi ống Vàng | `public/JS/TrangGame/CheDoAI.js` | `timDuongTraoDoiBFS()` |
| **4** | **Duyệt theo chiều sâu zíc-zắc (Winding DFS)** | Sinh bản đồ ngẫu nhiên đảm bảo luôn có nghiệm | `public/JS/TrangGame/BanDoGame.js`<br>`app/Services/PvpMapService.php` | `timDuongUonLuonZicZacDFS()`, `generateMap()` |
| **5** | **Mô phỏng dòng nước & Thẩm định nghiệm (Water BFS)** | Kiểm tra kết nối 2 chiều và tính điểm tối ưu | `public/JS/TrangGame/BanDoGame.js`<br>`app/Services/PvpBoardStateService.php` | `kiemTraKetNoiCapDuongOngBFS()`, `verifyBoardSolution()` |
| **6** | **Xếp hạng Elo nhiều người chơi (Pairwise Elo Engine)** | Tính biến thiên điểm Rank công bằng (Zero-Sum) | `app/Services/PvpRatingService.php` | `calculateMatchEloDeltas()`, `calculatePairDelta()` |
| **7** | **Bộ sinh ngẫu nhiên theo Seed (MurmurHash3 / LCG)** | Đồng bộ bản đồ tuyệt đối giữa các đấu thủ PvP | `public/JS/TrangGame/BanDoGame.js`<br>`app/Services/PvpMapService.php` | `taoBoSinhSoTheoSeed()`, `makeRng()` |

---

## 1. THUẬT TOÁN LEO ĐỒI (HILL CLIMBING SEARCH)

### 1.1. Mục đích và Ý nghĩa học thuật
Trong môn Trí tuệ nhân tạo (AI), **Leo đồi (Hill Climbing)** là một giải thuật tìm kiếm cục bộ (Local Search) dựa trên hàm Heuristic $h(s)$ để liên tục di chuyển từ trạng thái hiện tại $s$ sang trạng thái lân cận $s'$ có giá trị đánh giá cao hơn, hướng tới đỉnh cực đại (Goal State).

### 1.2. Ứng dụng trong Game Nối Ống Nước
- **Bài toán:** Bot AI cần tự động tìm góc xoay cho các ống Xanh trên bàn cờ để dòng nước chảy thông suốt từ điểm Bắt đầu (`OngBatDau.svg`) tới điểm Kết thúc (`OngKetThuc.svg`).
- **Không gian trạng thái (State Space):** Mỗi ô ống nước có 4 trạng thái góc quay $\{0^\circ, 90^\circ, 180^\circ, 270^\circ\}$.
- **Hàm Heuristic $h(s)$:** Số lượng ống nước được kết nối liên tục, không đứt đoạn bắt đầu từ nguồn nước (được tính toán bằng hàm `tinhDiemHeuristic()`). Càng nhiều ống kết nối đúng thì $h(s)$ càng lớn.

### 1.3. Vị trí Code trong dự án
- **File:** [`public/JS/TrangGame/CheDoAI.js`](file:///c:/Users/Asus/GameNoiOngNuoc/public/JS/TrangGame/CheDoAI.js)
- **Hàm tính Heuristic:**
  ```javascript
  // Dòng 85 - 179: Tính độ dài dòng chảy liên tục từ nguồn nước (Điểm Heuristic h(s))
  function tinhDiemHeuristic() {
    // Duyệt BFS từ ô OngBatDau, kiểm tra cổng mở thực tế giữa 2 ô lân cận
    // Trả về: maxPathLength (Độ dài đường ống kết nối được)
  }
  ```
- **Hàm thực thi Leo đồi:**
  ```javascript
  // Dòng 228 - 365: Triển khai Steepest-Ascent Hill Climbing trên đường đi nghiệm
  async function thucHienAI_EasyMedium(path) {
    // Duyệt qua từng ô trên đường đi
    // Thử cả 4 góc xoay: testRot = startRotation + k * 90 (k = 0..3)
    // Chọn góc xoay thỏa mãn cả 2 hướng vào (inDir) và ra (outDir)
    // Cập nhật bước đi, phát âm thanh và hiển thị trực tiếp lên bàn cờ
  }
  ```

---

## 2. THUẬT TOÁN LEO ĐỒI KHỞI ĐỘNG NGẪU NHIÊN (RANDOM-RESTART HILL CLIMBING)

### 2.1. Vấn đề cực tiểu địa phương (Local Maxima / Plateau)
Thuật toán Leo đồi đơn giản rất dễ bị kẹt tại **Cực đại cục bộ (Local Maxima)** hoặc **Bình nguyên (Plateau)**: trạng thái mà không có hành động xoay đơn lẻ nào làm tăng điểm heuristic, nhưng bài toán vẫn chưa đạt lời giải toàn cục.
Trong game, trường hợp này xuất hiện ở **Mức độ Khó (Hard Mode)** khi có sự xuất hiện của các cụm **Ống Vàng chỉ cho phép tráo đổi vị trí liền kề**. Nếu ống vàng nằm sai vị trí, AI không thể chỉ xoay mà giải được.

### 2.2. Giải pháp Random-Restart Hill Climbing
Khi phát hiện bị kẹt (`aiStats.status = 'Stuck! Retrying...'`):
1. Giải phóng danh sách các ô đã khóa tạm thời (`cacOdaKhoa.clear()`).
2. Chọn ngẫu nhiên một ống vàng trong bán kính lân cận ($\text{Khoảng cách Manhattan} \le 3$).
3. Thực hiện hoán đổi ngẫu nhiên với một hàng xóm liền kề (Perturbation / Random Swap) để phá vỡ thế kẹt.
4. Quay lui chỉ số bước đi về điểm gãy ống (`diemGayOng`) và tiếp tục chu trình leo đồi mới.

### 2.3. Vị trí Code trong dự án
- **File:** [`public/JS/TrangGame/CheDoAI.js`](file:///c:/Users/Asus/GameNoiOngNuoc/public/JS/TrangGame/CheDoAI.js)
- **Hàm thực thi:** `thucHienAI_Hard(path)` (Dòng 512 - 875)
- **Khối xử lý kẹt và Random Restart:**
  ```javascript
  // Dòng 695 - 840: Xử lý kẹt trạng thái và Random Restart
  if (cacOdaKhoa.size > 0) {
    aiStats.status = "Stuck! Retrying...";
    cacOdaKhoa.clear(); // Giải phóng khóa
    
    // Lọc danh sách ống vàng trong phạm vi gần điểm kẹt (Khoảng cách Manhattan <= 3)
    const PHAM_VI_TIM_KIEM = 3;
    const ungVienGan = tatCaVang.filter(p => Math.abs(r - currNode.r) + Math.abs(c - currNode.c) <= PHAM_VI_TIM_KIEM);
    
    // Xáo trộn ngẫu nhiên và tráo đổi với hàng xóm hợp lệ
    danhSachUngVien.sort(() => Math.random() - 0.5);
    aiTraoDoiOng(p1, p2);
    
    // Quay lui lại điểm gãy ống (diemGayOng)
    i = diemGayOng - 1;
    aiStats.retryCount++; // Tăng biến đếm số lần Random Restart
  }
  ```

---

## 3. THUẬT TOÁN TÌM KIẾM THEO CHIỀU RỘNG (BFS - BREADTH-FIRST SEARCH)

### 3.1. Ứng dụng tìm đường tráo đổi ống Vàng
- **Bài toán:** Ở chế độ Khó, ống vàng cần thiết đang nằm ở ô $A$, nhưng vị trí cần lắp đặt là ô $B$. Luật game quy định ống vàng chỉ được hoán đổi với **ô liền kề trực tiếp (Manhattan = 1)**.
- **Giải pháp:** Sử dụng thuật toán BFS để tìm chuỗi các ô vàng trung gian ngắn nhất từ $A$ đến $B$:
  $$A \to C_1 \to C_2 \to \dots \to B$$
  sao cho không đi qua các ô đã được định vị chính xác trước đó (`blockedSet`).

### 3.2. Vị trí Code trong dự án
- **File:** [`public/JS/TrangGame/CheDoAI.js`](file:///c:/Users/Asus/GameNoiOngNuoc/public/JS/TrangGame/CheDoAI.js)
- **Hàm:** `timDuongTraoDoiBFS(startDiv, targetDiv, blockedSet)` (Dòng 463 - 510)
  ```javascript
  function timDuongTraoDoiBFS(startDiv, targetDiv, blockedSet) {
    let queue = [[startDiv]];
    let visited = new Set([startDiv]);

    while (queue.length > 0) {
      let path = queue.shift();
      let node = path[path.length - 1];

      if (node === targetDiv) return path; // Đã tìm thấy chuỗi tráo đổi ngắn nhất

      // Khảo sát 4 hướng lân cận: Trên, Dưới, Trái, Phải
      for (let n of neighbors) {
        if (nextDiv && nextDiv.classList.contains("co-the-trao-doi")) {
          if (!visited.has(nextDiv) && (!blockedSet.has(nextId) || isTarget)) {
            visited.add(nextDiv);
            queue.push([...path, nextDiv]);
          }
        }
      }
    }
    return null;
  }
  ```

---

## 4. THUẬT TOÁN DUYỆT THEO CHIỀU SÂU ZÍC-ZẮC (WINDING DFS - MAP GENERATION)

### 4.1. Mục đích
Thay vì dùng bản đồ cố định nhàm chán hoặc sinh hoàn toàn ngẫu nhiên dễ tạo ra mê cung vô nghiệm, hệ thống sử dụng thuật toán **Winding DFS (Duyệt chiều sâu uốn lượn có ràng buộc)** để sinh bản đồ ngẫu nhiên:
- Đảm bảo **$100\%$ luôn có ít nhất một đường đi hợp lệ** nối từ Start đến End.
- Đường đi phải uốn lượn lắt léo với số khúc cua tối thiểu $T \ge T_{\min}$ theo độ khó.
- Phân chia không gian ma trận thành $K$ dải độc lập để các cặp ống ($S_1 \to E_1, \dots, S_K \to E_K$) không đè lên nhau.

### 4.2. Vị trí Code trong dự án
- **Frontend:** [`public/JS/TrangGame/BanDoGame.js`](file:///c:/Users/Asus/GameNoiOngNuoc/public/JS/TrangGame/BanDoGame.js) — hàm `timDuongUonLuonZicZacDFS()` (Dòng 728 - 848)
- **Backend (PvP):** [`app/Services/PvpMapService.php`](file:///c:/Users/Asus/GameNoiOngNuoc/app/Services/PvpMapService.php) — hàm `generateWindingPathDFS()` (Dòng 130 - 240)

### 4.3. Nguyên lý thuật toán
```
1. Khởi tạo stack bắt đầu tại startPos.
2. Tại mỗi bước (r, c), lấy danh sách hướng đi hợp lệ nằm trong phân vùng cho phép.
3. Ưu tiên các hướng rẽ vuông góc (turns + 1) để tăng độ ngoằn ngoèo của đường ống.
4. Nếu chạm đích endPos và số khúc cua >= targetTurns:
   -> Lưu lại làm ứng viên đường đi tốt nhất (bestPath).
5. Nếu bị kẹt: Quay lui (Backtrack) thử nhánh khác.
```

---

## 5. THUẬT TOÁN XÁC MINH DÒNG NƯỚC & TÍNH ĐIỂM BẰNG BFS (SERVER-AUTHORITATIVE WATER BFS)

### 5.1. Mục đích
Đảm bảo tính trung thực tuyệt đối trong chế độ chơi mạng PvP (AC1 - Server Authority):
- Client **không thể tự gửi điểm số hay tuyên bố chiến thắng**.
- Backend tự chạy thuật toán BFS trên trạng thái bàn cờ lưu trong CSDL (`board_rotations_json`).
- Kiểm tra tính tương hỗ hai chiều giữa các cổng mở:
  $$\text{Ô } A \text{ mở hướng } d \iff \text{Ô } B \text{ mở hướng đối diện } (d + 2) \pmod 4$$
- Phát hiện và trừ điểm nếu người chơi đi đường vòng xa hơn lời giải tối ưu:
  $$\text{Điểm mỗi cặp} = \max\left(10, \frac{100}{K} - (\text{Độ dài thực tế} - \text{Độ dài tối ưu}) \times 10\right)$$
- **Điều kiện thắng tuyệt đối:** Phải nối đủ $K$ cặp VÀ tổng điểm đạt đúng $100$ điểm.

### 5.2. Vị trí Code trong dự án
- **Backend Service:** [`app/Services/PvpBoardStateService.php`](file:///c:/Users/Asus/GameNoiOngNuoc/app/Services/PvpBoardStateService.php)
  - Hàm `verifyBoardSolution($mapData, $boardRotations)`: Dòng 340 - 510
  - Hàm `getOpenPorts($shape, $rotation)`: Dòng 515 - 530
- **Frontend Client:** [`public/JS/TrangGame/BanDoGame.js`](file:///c:/Users/Asus/GameNoiOngNuoc/public/JS/TrangGame/BanDoGame.js)
  - Hàm `kiemTraKetNoiCapDuongOngBFS(pipePair)`: Dòng 1211 - 1281
  - Hàm `capNhatLuongNuoc()`: Dòng 1313 - 1400

---

## 6. THUẬT TOÁN XẾP HẠNG ELO PAIRWISE ZERO-SUM (MULTIPLAYER ELO RATING)

### 6.1. Mục đích
Áp dụng công thức xếp hạng quốc tế FIDE Elo mở rộng cho trận đấu nhiều người chơi (2 đến 4 người) trong chế độ PvP Ranked:
- Đảm bảo **tính đối ứng Zero-Sum**: Tổng số điểm Elo thưởng cho người thắng bằng đúng tổng số điểm trừ của người thua:
  $$\sum_{i=1}^N \Delta \text{Elo}_i = 0$$
- Ngăn chặn triệt để hiện tượng lạm phát hoặc bốc hơi điểm Elo trong hệ sinh thái game.

### 6.2. Công thức toán học
Với mỗi cặp người chơi $(A, B)$ trong trận đấu $N$ người:
1. **Điểm kỳ vọng (Expected Score) của $A$ trước $B$:**
   $$E_A = \frac{1}{1 + 10^{(R_B - R_A) / 400}}$$
2. **Điểm thực tế (Actual Score) theo thứ hạng kết quả:**
   $$S_A = \begin{cases} 1.0 & \text{nếu Rank}_A < \text{Rank}_B \text{ (Thắng)} \\ 0.5 & \text{nếu Rank}_A = \text{Rank}_B \text{ (Hòa)} \\ 0.0 & \text{nếu Rank}_A > \text{Rank}_B \text{ (Thua)} \end{cases}$$
3. **Biến thiên điểm Pairwise:**
   $$\Delta_{A,B} = \frac{K}{N - 1} \times (S_A - E_A)$$
   Do $S_B = 1 - S_A$ và $E_B = 1 - E_A$, ta luôn có:
   $$\Delta_{B,A} = -\Delta_{A,B}$$
4. **Tổng biến thiên điểm của người chơi $i$:**
   $$\Delta \text{Elo}_i = \sum_{j \neq i} \Delta_{i,j}$$

### 6.3. Vị trí Code trong dự án
- **File:** [`app/Services/PvpRatingService.php`](file:///c:/Users/Asus/GameNoiOngNuoc/app/Services/PvpRatingService.php)
- **Hàm:** `calculateMatchEloDeltas(array $players)` (Dòng 28 - 90) và `calculatePairDelta()` (Dòng 92 - 120)

---

## 7. BỘ SINH SỐ NGẪU NHIÊN THEO SEED (DETERMINISTIC SEED PRNG)

### 7.1. Mục đích
Giải quyết tiêu chí nghiệm thu **AC3 (Seed Uniformity)** trong thi đấu PvP:
- Tất cả người chơi trong phòng đấu phải nhận được cấu hình ma trận ban đầu giống hệt nhau $100\%$ (vị trí đầu mút, loại ống, góc xoay ban đầu, vị trí tường).
- Server tạo mã `seedKey` duy nhất cho trận đấu. Cả Server và tất cả Client đều dùng thuật toán **MurmurHash3 + Linear Congruential Generator (LCG)** để sinh chuỗi số ngẫu nhiên đồng nhất.

### 7.2. Vị trí Code trong dự án
- **Frontend:** [`public/JS/TrangGame/BanDoGame.js`](file:///c:/Users/Asus/GameNoiOngNuoc/public/JS/TrangGame/BanDoGame.js) — hàm `taoBoSinhSoTheoSeed(seedStr)` (Dòng 60 - 71)
- **Backend:** [`app/Services/PvpMapService.php`](file:///c:/Users/Asus/GameNoiOngNuoc/app/Services/PvpMapService.php) — hàm `makeRng(string $seed)` (Dòng 122 - 138)

---

## 8. HỎI ĐÁP VẤN ĐÁP CÙNG GIẢNG VIÊN (ORAL DEFENSE FAQ)
  
### Câu hỏi 1: "Tại sao nhóm lại dùng Hill Climbing cho AI giải đố mà không dùng A* hay Dijkstra?"
> **Trả lời:**  
> "Dijkstra và A* là các thuật toán tìm đường đi ngắn nhất trên đồ thị cố định (Pathfinding on Static Graph). Nhưng ở game của nhóm, bản cờ ống nước là **bài toán thỏa mãn ràng buộc và tối ưu hóa tổ hợp (Constraint Satisfaction & Combinatorial Optimization)**: các đỉnh đồ thị liên tục thay đổi cấu hình cổng khi người chơi xoay ống.  
> Hill Climbing là thuật toán tối ưu hóa cục bộ hoàn hảo cho dạng bài này vì nó mô phỏng tư duy của người chơi: thử xoay từng ô và đánh giá xem dòng nước dài thêm được bao nhiêu (Hàm Heuristic $h(s)$). Ngoài ra, ở chế độ Khó, nhóm kết hợp thêm **Random Restart** để giải quyết nhược điểm kẹt cực đại địa phương của Hill Climbing khi gặp cụm ống vàng."

### Câu hỏi 2: "Hàm Heuristic của nhóm được định nghĩa như thế nào? Có bảo đảm tính chấp nhận được (Admissible) không?"
> **Trả lời:**  
> "Hàm Heuristic $h(s)$ được định nghĩa trong hàm `tinhDiemHeuristic()` tại file `CheDoAI.js`. Nó sử dụng thuật toán duyệt đồ thị để đo **độ dài tối đa của dòng chảy liên tục xuất phát từ ô nguồn (Start Pipe)**.  
> Giá trị $h(s)$ tăng dần khi có thêm các mắt xích ống được kết nối đúng hướng. Khi $h(s)$ đạt bằng độ dài đường đi nghiệm và chạm đến ô Kết thúc (End Pipe), trạng thái đó chính là Goal State (Đích)."

### Câu hỏi 3: "Làm thế nào để hệ thống ngăn chặn việc người chơi gian lận gửi điểm giả 100 điểm lên máy chủ trong PvP?"
> **Trả lời:**  
> "Hệ thống áp dụng kiến trúc **Server Authority (Thẩm quyền tuyệt đối thuộc về Server)** tại `app/Services/PvpBoardStateService.php` và `PvpMatchService.php`:
> 1. Máy chủ hoàn toàn bỏ qua điểm số hay cờ thắng do client gửi lên.
> 2. Mỗi lượt xoay ống của người chơi được kiểm tra và lưu vào CSDL (`board_rotations_json`).
> 3. Khi nộp bài thi (`submitSolution`), Backend tự chạy thuật toán BFS độc lập trên CSDL để kiểm tra tính thông suốt hai chiều giữa các cổng ống.
> 4. Chỉ khi BFS xác nhận tất cả các cặp ống đều đã nối đúng và đạt đủ 100 điểm (dùng đường đi tối ưu không bị trừ điểm đường vòng), máy chủ mới ghi nhận hoàn thành và xếp hạng."

### Câu hỏi 4: "Thuật toán tính điểm Elo có đảm bảo tính công bằng khi phòng đấu có 3 hoặc 4 người không?"
> **Trả lời:**  
> "Có ạ. Nhóm triển khai thuật toán **Elo Pairwise Zero-Sum** trong `app/Services/PvpRatingService.php`. Trong trận đấu $N$ người ($N \in \{2, 3, 4\}$), mỗi người chơi được xem như thi đấu đối đầu từng đôi một với $N - 1$ người còn lại. Hệ số $K$ được chuẩn hóa thành $K / (N - 1)$. Do tính chất đối xứng $\Delta_{A,B} = -\Delta_{B,A}$, tổng biến thiên điểm của toàn bộ phòng đấu luôn luôn triệt tiêu bằng 0 ($\sum \Delta \text{Elo} = 0$), giúp bảo toàn điểm số của toàn hệ thống."
