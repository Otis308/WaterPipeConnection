// CÁC BIẾN THỐNG KÊ CHO THUẬT TOÁN LEO ĐỒI
let aiStats = {
  stepCount: 0, // Số bước đã đi
  retryCount: 0, // Số lần thử lại (Random Restart)
  currentScore: 0, // Điểm Heuristic hiện tại
  highScore: 0, // Điểm cao nhất từng đạt được
  maxConnectedLength: 0, // Lưu độ dài đường ống dài nhất từng đạt được
  status: "...", // Trạng thái: Searching, Stuck, Solved...
};

// Biến theo dõi thời gian thực tế
let aiThoiGianChayThuc = 0;
let aiMocThoiGian = 0;

// CẤU HÌNH TỐC ĐỘ AI
const AI_CONFIG = {
  START_DELAY: 1500, // Chờ lúc bắt đầu (ms)
  ACTION_DELAY: 400, // Tốc độ xoay mỗi lần (ms)
  CHECK_DELAY: 300, // Tốc độ khi ô đã đúng (ms)
  STUCK_DELAY: 1000, // Thời gian chờ khi bị kẹt (ms)
};

// ============================================================================================================================
// HÀM HỖ TRỢ

// Hàm tạo độ trễ để AI chờ
function choDoi(ms) {
  if (aiMocThoiGian > 0) {
    aiThoiGianChayThuc += performance.now() - aiMocThoiGian;
  }

  return new Promise((resolve) =>
    setTimeout(() => {
      aiMocThoiGian = performance.now();
      resolve();
    }, ms)
  );
}

// Hàm giữ chân AI trong vòng lặp vô tận nếu game đang dừng
async function choDenKhiHetPause() {
  if (window.isGamePaused) {
    if (aiMocThoiGian > 0) {
      aiThoiGianChayThuc += performance.now() - aiMocThoiGian;
    }

    while (window.isGamePaused) {
      capNhatTrangThaiUI("Paused");
      await new Promise((resolve) => setTimeout(resolve, 100));
    }
    capNhatTrangThaiUI("Searching...");

    aiMocThoiGian = performance.now();
  }
}

// Cập nhật giao diện bảng chi tiết
function capNhatUI_ChiTiet() {
  const uiElements = document.querySelectorAll(".ChiTiet-ChuThuong");
  if (uiElements.length >= 5) {
    uiElements[0].innerText = aiStats.status;
    uiElements[0].style.color =
      aiStats.status === "Solution Found!"
        ? "#00ff00"
        : aiStats.status === "Search Failed!"
        ? "#fffb00ff"
        : "#f3f3f3";

    uiElements[1].innerText = aiStats.stepCount;
    uiElements[2].innerText = aiStats.retryCount;
    uiElements[3].innerText = aiStats.currentScore;
    uiElements[4].innerText = aiStats.highScore;
  }
}

function capNhatTrangThaiUI(status) {
  aiStats.status = status;
  capNhatUI_ChiTiet();
}

// ============================================================================================================================
// HÀM ĐÁNH GIÁ TRẠNG THÁI BẢN ĐỒ

// Tính độ dài dòng chảy liên tục từ điểm bắt đầu
function tinhDiemHeuristic() {
  const oBatDau = document
    .querySelector('img[src*="OngBatDau.svg"]')
    ?.closest(".KhungGame-OVuong");
  if (!oBatDau) return 0;

  let score = 0;
  let currentCell = oBatDau;
  let flowDirection = 1;

  // Xác định hướng ra của ống bắt đầu dựa trên rotation thực tế
  const startImg = oBatDau.querySelector(".AnhOngNuoc");
  const startRot = parseInt(startImg.dataset.rotation || 0);

  let queue = [{ cell: oBatDau, fromDir: -1 }];
  let visited = new Set();
  let maxPathLength = 0;

  // Dùng BFS/DFS để đếm số lượng ống được kết nối thông suốt từ nguồn
  function traceFlow(cell, fromDir, length) {
    if (!cell) return length;
    const row = parseInt(cell.dataset.row);
    const col = parseInt(cell.dataset.col);
    const id = `${row}-${col}`;

    if (visited.has(id)) return length;
    visited.add(id);

    const img = cell.querySelector(".AnhOngNuoc");
    const wrapper = cell.querySelector(".Ong-Wrapper");
    const elXoay = wrapper ? wrapper : img;
    if (!elXoay) return length;

    let loaiOng = "";
    if (img.src.includes("Thang")) loaiOng = "Thang";
    else if (img.src.includes("ChuL")) loaiOng = "ChuL";
    else if (img.src.includes("ChuThap")) loaiOng = "ChuThap";
    else if (img.src.includes("BatDau")) loaiOng = "BatDau";
    else if (img.src.includes("KetThuc")) loaiOng = "KetThuc";

    const currentRot = parseInt(elXoay.dataset.rotation || 0);
    const openDirs = layHuongMoThucTe(loaiOng, currentRot);

    // Nếu không phải Start, kiểm tra xem có kết nối với ống trước không
    if (loaiOng !== "BatDau") {
      const requiredInput = (fromDir + 2) % 4;
      if (!openDirs.includes(requiredInput)) return length;
    }

    let currentLength = length + 1;
    if (currentLength > maxPathLength) maxPathLength = currentLength;

    // Tìm các hướng ra
    for (let dir of openDirs) {
      if (dir === (fromDir + 2) % 4) continue;

      let nextR = row,
        nextC = col,
        nextFrom = dir;
      if (dir === 0) nextR--;
      if (dir === 1) nextC++;
      if (dir === 2) nextR++;
      if (dir === 3) nextC--;

      const nextCell = document.querySelector(
        `.KhungGame-OVuong[data-row="${nextR}"][data-col="${nextC}"]`
      );
      if (nextCell) {
        traceFlow(nextCell, nextFrom, currentLength);
      }
    }
    return maxPathLength;
  }

  // Ta check hướng thực tế
  const directions = layHuongMoThucTe("BatDau", startRot);
  maxPathLength = 1;
  visited.add(`${oBatDau.dataset.row}-${oBatDau.dataset.col}`);

  // Lan truyền sang các ô hàng xóm
  for (let dir of directions) {
    let r = parseInt(oBatDau.dataset.row);
    let c = parseInt(oBatDau.dataset.col);
    if (dir === 0) r--;
    if (dir === 1) c++;
    if (dir === 2) r++;
    if (dir === 3) c--;
    const nextCell = document.querySelector(
      `.KhungGame-OVuong[data-row="${r}"][data-col="${c}"]`
    );
    if (nextCell) traceFlow(nextCell, dir, 1);
  }

  return maxPathLength;
}

// ============================================================================================================================
// LOGIC AI

async function khoiChayAI() {
  const gameMode = sessionStorage.getItem("gameMode");
  const difficulty = sessionStorage.getItem("difficulty");

  // Chấp nhận cả easy, medium, hard
  if (gameMode !== "ai") return;

  // Reset trạng thái Game Over khi bắt đầu ván mới
  window.isGameOver = false;

  console.log(`--- AI Start: ${difficulty} ---`);

  // Reset các biến đếm
  aiThoiGianChayThuc = 0;
  aiMocThoiGian = 0;
  aiStats = {
    stepCount: 0,
    retryCount: 0,
    currentScore: 0,
    highScore: 0,
    maxConnectedLength: 0,
    status: "Initializing...",
  };
  capNhatUI_ChiTiet();

  const solutionPathStr = sessionStorage.getItem("solutionPath");
  if (!solutionPathStr) {
    console.error("AI: Missing solution path!");
    capNhatTrangThaiUI("Error");
    return;
  }
  const solutionPath = JSON.parse(solutionPathStr);

  await choDoi(AI_CONFIG.START_DELAY);
  aiMocThoiGian = performance.now();

  // Phân nhánh thuật toán
  if (difficulty === "hard") {
    await thucHienAI_Hard(solutionPath);
  } else {
    await thucHienAI_EasyMedium(solutionPath);
  }
}

async function thucHienAI_EasyMedium(path) {
  capNhatTrangThaiUI("Searching...");

  for (let i = 0; i < path.length; i++) {
    if (window.isGameOver) {
      console.log("AI LeoDoi: Dừng do hết giờ.");
      return;
    }
    await choDenKhiHetPause();
    capNhatDoDaiBaoCao();

    const currNode = path[i];
    const prevNode = i > 0 ? path[i - 1] : null;
    const nextNode = i < path.length - 1 ? path[i + 1] : null;
    const { r, c } = currNode;
    const oHienTai = document.querySelector(
      `.KhungGame-OVuong[data-row="${r}"][data-col="${c}"]`
    );

    if (!oHienTai) continue;

    const wrapper = oHienTai.querySelector(".Ong-Wrapper");
    const img = oHienTai.querySelector(".AnhOngNuoc");
    const phanTuXoay = wrapper ? wrapper : img;

    if (!phanTuXoay) continue;

    // Kiểm tra xem ô này có xoay được không
    const coTheXoay = oHienTai.classList.contains("co-the-xoay");

    // Xử lý ống Đỏ trước khi bật highlight
    if (!coTheXoay) {
      oHienTai.style.filter = "brightness(1.5)";
      capNhatDoDaiBaoCao();
      let score = tinhDiemHeuristic();
      aiStats.currentScore = score;
      capNhatUI_ChiTiet();
      await choDoi(AI_CONFIG.CHECK_DELAY);
      oHienTai.style.filter = "none";
      continue;
    }

    // Highlight ống đang xem xét
    oHienTai.style.filter = "brightness(1.5) sepia(1) hue-rotate(100deg)";

    // Xác định các hướng bắt buộc phải có
    let requiredDirs = [];
    if (prevNode) requiredDirs.push(layHuongTuO(currNode, prevNode));
    if (nextNode) requiredDirs.push(layHuongTuO(currNode, nextNode));

    let bestRotation = -1;
    let maxLocalScore = -1;
    let startRotation = parseInt(phanTuXoay.dataset.rotation || 0);

    // Lấy loại ống
    let src = img.src;
    let loaiOng = "";
    if (src.includes("Thang")) loaiOng = "Thang";
    else if (src.includes("ChuL")) loaiOng = "ChuL";
    else if (src.includes("ChuThap")) loaiOng = "ChuThap";

    // Thử tất cả 4 trạng thái xoay
    for (let k = 0; k < 4; k++) {
      let testRot = startRotation + k * 90;
      let openDirs = layHuongMoThucTe(loaiOng, testRot);
      const isMatch = requiredDirs.every((dir) => openDirs.includes(dir));
      if (isMatch) {
        bestRotation = testRot;
        break;
      }
    }

    // Thực hiện hành động
    if (bestRotation !== -1) {
      // Nếu cần xoay
      if (bestRotation % 360 !== startRotation % 360) {
        // Xoay từ từ đến góc đó
        while (
          parseInt(phanTuXoay.dataset.rotation || 0) % 360 !==
          bestRotation % 360
        ) {
          await choDenKhiHetPause();

          if (typeof phatAmThanhXoay === "function") phatAmThanhXoay();

          let cur = parseInt(phanTuXoay.dataset.rotation || 0);
          let next = cur + 90;
          phanTuXoay.style.transform = `rotate(${next}deg)`;
          phanTuXoay.dataset.rotation = next;

          // Cập nhật stats
          aiStats.stepCount++;

          // Cập nhật kỷ lục độ dài sau khi xoay
          capNhatDoDaiBaoCao();

          // Tính điểm Heuristic thực tế sau mỗi lần xoay để hiển thị
          let score = tinhDiemHeuristic();
          aiStats.currentScore = score;
          if (score > aiStats.highScore) aiStats.highScore = score;
          capNhatUI_ChiTiet();

          await choDoi(AI_CONFIG.ACTION_DELAY);
        }
      } else {
        // Đã đúng hướng rồi, nhưng vẫn tính là đã kiểm tra
        capNhatDoDaiBaoCao();
        let score = tinhDiemHeuristic();
        aiStats.currentScore = score;
        if (score > aiStats.highScore) aiStats.highScore = score;
        capNhatUI_ChiTiet();
        await choDoi(AI_CONFIG.CHECK_DELAY);
      }
    } else {
      aiStats.status = "Stuck! Retrying...";
      aiStats.retryCount++;
      capNhatUI_ChiTiet();
      await choDoi(AI_CONFIG.STUCK_DELAY);
    }

    oHienTai.style.filter = "none";

    // Cập nhật lần cuối trước khi chuyển sang ống tiếp theo
    capNhatDoDaiBaoCao();
  }

  // Kết thúc thuật toán cộng nốt khoảng thời gian xử lý cuối cùng
  if (aiMocThoiGian > 0) {
    aiThoiGianChayThuc += performance.now() - aiMocThoiGian;
    aiMocThoiGian = 0;
  }

  // Kết thúc
  aiStats.status = "Solution Found!";
  capNhatUI_ChiTiet();
  console.log("AI: Đã hoàn thành xếp ống. Kiểm tra kết quả...");
  capNhatLuongNuoc();
}

// Hàm lấy hướng
function layHuongTuO(fromNode, toNode) {
  if (toNode.r < fromNode.r) return 0;
  if (toNode.c > fromNode.c) return 1;
  if (toNode.r > fromNode.r) return 2;
  if (toNode.c < fromNode.c) return 3;
  return -1;
}

// Hàm lấy hướng mở
function layHuongMoThucTe(loaiOng, gocXoay) {
  const CAU_HINH_ONG = {
    Thang: [0, 2],
    ChuL: [1, 2],
    ChuThap: [0, 1, 2, 3],
    BatDau: [1],
    KetThuc: [1],
  };

  const soLanXoay = Math.round(gocXoay / 90) % 4;
  const huongMacDinh = CAU_HINH_ONG[loaiOng];
  if (!huongMacDinh) return [];

  return huongMacDinh.map((h) => (h + soLanXoay) % 4);
}

// ----------------------------------------------------------------------------------------------------------------------------
// LOGIC HỖ TRỢ HARD MODE

// Hàm giả lập hành động tráo đổi nội dung 2 ô
function aiTraoDoiOng(o1, o2) {
  const tempHTML = o1.innerHTML;
  o1.innerHTML = o2.innerHTML;
  o2.innerHTML = tempHTML;
  if (typeof phatAmThanhDoiOng === "function") phatAmThanhDoiOng();
}

// Kiểm tra xem một ô có chứa ống khớp với yêu cầu không
function kiemTraOngPhuHop(div, loaiCanThiet, gocCanThiet) {
  const img = div.querySelector(".AnhOngNuoc");
  if (!img) return false;

  // Xác định loại ống đang có
  let src = img.src;
  let loaiHienTai = "";
  if (src.includes("Thang")) loaiHienTai = "Thang";
  else if (src.includes("ChuL")) loaiHienTai = "ChuL";
  else if (src.includes("ChuThap")) loaiHienTai = "ChuThap";

  // Nếu ống Chữ Thập, nó chấp nhận mọi yêu cầu (Thẳng hoặc L đều OK)
  if (loaiHienTai !== "ChuThap" && loaiHienTai !== loaiCanThiet) {
    return false;
  }

  // Kiểm tra Góc xoay
  const wrapper = div.querySelector(".Ong-Wrapper");
  const el = wrapper ? wrapper : img;
  const currentRot = parseInt(el.dataset.rotation || 0);

  const cur = currentRot % 360;
  const tar = gocCanThiet % 360;

  // Nếu là Ống Chữ Thập thì luôn đúng mọi góc, mọi hướng
  if (loaiHienTai === "ChuThap") return true;

  // Nếu là Ống Thẳng thì chấp nhận đối xứng 180 độ
  if (loaiHienTai === "Thang") return cur % 180 === tar % 180;

  // Nếu là Ống Chữ L thì phải khớp chính xác góc xoay yêu cầu
  return cur === tar;
}

// Tính toán loại ống và góc xoay cần thiết để nối
function tinhOngCanThiet(prevNode, currNode, nextNode) {
  // Nếu không có node trước hoặc sau, có thể là Start/End
  if (!prevNode || !nextNode) return null;

  const inDir = layHuongTuO(currNode, prevNode);
  const outDir = layHuongTuO(currNode, nextNode);
  const dirs = [inDir, outDir].sort((a, b) => a - b);

  // Kiểm tra Ống Thẳng
  if (dirs[0] === 0 && dirs[1] === 2) return { type: "Thang", rot: 0 };
  if (dirs[0] === 1 && dirs[1] === 3) return { type: "Thang", rot: 90 };

  // Kiểm tra Ống Chữ L
  if (dirs[0] === 1 && dirs[1] === 2) return { type: "ChuL", rot: 0 };
  if (dirs[0] === 2 && dirs[1] === 3) return { type: "ChuL", rot: 90 };
  if (dirs[0] === 0 && dirs[1] === 3) return { type: "ChuL", rot: 180 };
  if (dirs[0] === 0 && dirs[1] === 1) return { type: "ChuL", rot: 270 };

  // Kiểm tra Chữ Thập
  return { type: "ChuThap", rot: 0 };
}

// Tìm đường đi ngắn nhất giữa 2 ô vàng bằng BFS để thực hiện tráo đổi
function timDuongTraoDoiBFS(startDiv, targetDiv, blockedSet) {
  let queue = [[startDiv]];
  let visited = new Set();
  visited.add(startDiv);

  // Lấy toạ độ đích để so sánh
  const targetR = parseInt(targetDiv.dataset.row);
  const targetC = parseInt(targetDiv.dataset.col);

  while (queue.length > 0) {
    let path = queue.shift();
    let node = path[path.length - 1];

    if (node === targetDiv) {
      return path;
    }

    const r = parseInt(node.dataset.row);
    const c = parseInt(node.dataset.col);

    const neighbors = [
      { r: r - 1, c: c },
      { r: r + 1, c: c },
      { r: r, c: c - 1 },
      { r: r, c: c + 1 },
    ];

    for (let n of neighbors) {
      const nextDiv = document.querySelector(
        `.KhungGame-OVuong[data-row="${n.r}"][data-col="${n.c}"]`
      );

      if (nextDiv && nextDiv.classList.contains("co-the-trao-doi")) {
        const nextId = `${n.r}-${n.c}`;
        const isTarget = n.r === targetR && n.c === targetC;

        if (!visited.has(nextDiv)) {
          if (!blockedSet.has(nextId) || isTarget) {
            visited.add(nextDiv);
            let newPath = [...path, nextDiv];
            queue.push(newPath);
          }
        }
      }
    }
  }
  return null;
}

async function thucHienAI_Hard(path) {
  capNhatTrangThaiUI("Searching...");
  let cacOdaKhoa = new Set();

  // Biến lưu lại cặp ô vừa tráo đổi để tránh lặp lại ngay lập tức
  let lanTraoDoiGanNhat = null;

  // Thêm ô Start vào danh sách khóa ngay từ đầu
  if (path.length > 0) {
    cacOdaKhoa.add(`${path[0].r}-${path[0].c}`);
  }

  for (let i = 0; i < path.length; i++) {
    if (window.isGameOver) {
      console.log("AI Hard: Dừng do hết giờ.");
      return;
    }
    await choDenKhiHetPause();
    capNhatDoDaiBaoCao();

    const currNode = path[i];
    const prevNode = path[i - 1];
    const nextNode = path[i + 1];
    const currentId = `${currNode.r}-${currNode.c}`;
    const oHienTai = document.querySelector(
      `.KhungGame-OVuong[data-row="${currNode.r}"][data-col="${currNode.c}"]`
    );

    if (!oHienTai) continue;

    // Kiểm tra xem là ống tương tác hay ống cố định để highlight cho đúng kiểu
    if (
      oHienTai.classList.contains("co-the-xoay") ||
      oHienTai.classList.contains("co-the-trao-doi")
    ) {
      oHienTai.style.filter = "brightness(1.5) sepia(1) hue-rotate(100deg)";
    } else {
      oHienTai.style.filter = "brightness(1.5)";
    }

    // Thời gian chờ để mắt người kịp nhìn thấy hiệu ứng quét qua
    await choDoi(AI_CONFIG.CHECK_DELAY);

    // Cập nhật điểm khi AI duyệt qua ống
    let score = tinhDiemHeuristic();
    aiStats.currentScore = score;
    capNhatUI_ChiTiet();

    // Xử lý ống Xanh
    if (oHienTai.classList.contains("co-the-xoay")) {
      const req = tinhOngCanThiet(prevNode, currNode, nextNode);
      if (req) {
        const wrapper = oHienTai.querySelector(".Ong-Wrapper");
        const img = oHienTai.querySelector(".AnhOngNuoc");
        const el = wrapper ? wrapper : img;

        // Xác định loại ống thực tế đang nằm ở ô này
        let actualType = "Thang";
        if (img.src.includes("ChuL")) actualType = "ChuL";
        else if (img.src.includes("ChuThap")) actualType = "ChuThap";

        // Hàm kiểm tra góc xoay
        const isRotationCorrect = (currentRot, targetRot, type) => {
          const cur = currentRot % 360;
          const tar = targetRot % 360;
          if (type === "ChuThap") return true;
          if (type === "Thang") return cur % 180 === tar % 180;
          return cur === tar;
        };

        // Thực hiện xoay nếu chưa đúng
        while (
          !isRotationCorrect(
            parseInt(el.dataset.rotation || 0),
            req.rot,
            actualType
          )
        ) {
          await choDenKhiHetPause();
          if (typeof phatAmThanhXoay === "function") phatAmThanhXoay();

          let cur = parseInt(el.dataset.rotation || 0);
          el.style.transform = `rotate(${cur + 90}deg)`;
          el.dataset.rotation = cur + 90;

          aiStats.stepCount++;
          capNhatDoDaiBaoCao();
          let score = tinhDiemHeuristic();
          aiStats.currentScore = score;
          if (score > aiStats.highScore) aiStats.highScore = score;
          capNhatUI_ChiTiet();

          await choDoi(AI_CONFIG.ACTION_DELAY);
        }
      }
      // Sau khi làm xong, thêm vào danh sách khóa
      cacOdaKhoa.add(currentId);

      // Tắt highlight sau khi duyệt xong ô này để chuyển sang ô kế tiếp
      oHienTai.style.filter = "none";

      continue;
    }

    // Xử lý ống Vàng
    if (oHienTai.classList.contains("co-the-trao-doi")) {
      const req = tinhOngCanThiet(prevNode, currNode, nextNode);
      if (!req) {
        oHienTai.style.filter = "none";
        cacOdaKhoa.add(currentId);
        continue;
      }

      // Kiểm tra xem ô hiện tại đã đúng chưa
      if (kiemTraOngPhuHop(oHienTai, req.type, req.rot)) {
      } else {
        // Quét tìm ống đúng
        const tatCaO = document.querySelectorAll(
          ".KhungGame-OVuong.co-the-trao-doi"
        );
        let oChuaOngDung = null;
        let duongDiSwapTotNhat = null;

        for (let o of tatCaO) {
          if (o === oHienTai) continue;

          const rTest = parseInt(o.dataset.row);
          const cTest = parseInt(o.dataset.col);
          const testId = `${rTest}-${cTest}`;

          // Nếu ô này nằm trong danh sách đã khóa thì bỏ qua, không lấy
          if (cacOdaKhoa.has(testId)) continue;

          if (kiemTraOngPhuHop(o, req.type, req.rot)) {
            // Tìm đường tráo đổi có tránh các ô đã khóa
            const pathSwap = timDuongTraoDoiBFS(o, oHienTai, cacOdaKhoa);
            if (pathSwap) {
              oChuaOngDung = o;
              duongDiSwapTotNhat = pathSwap;
              break;
            }
          }
        }

        if (oChuaOngDung && duongDiSwapTotNhat) {
          // Cập nhật trạng thái
          aiStats.status = "Fixing...";
          capNhatUI_ChiTiet();

          // Thực hiện tráo đổi
          for (let k = 0; k < duongDiSwapTotNhat.length - 1; k++) {
            if (window.isGameOver) return;
            await choDenKhiHetPause();

            const source = duongDiSwapTotNhat[k];
            const dest = duongDiSwapTotNhat[k + 1];

            source.classList.add("dang-chon-de-trao-doi");
            dest.classList.add("dang-chon-de-trao-doi");

            aiTraoDoiOng(source, dest);
            aiStats.stepCount++;
            capNhatDoDaiBaoCao();
            let score = tinhDiemHeuristic();
            aiStats.currentScore = score;
            if (score > aiStats.highScore) aiStats.highScore = score;
            capNhatUI_ChiTiet();

            await choDoi(AI_CONFIG.ACTION_DELAY * 1.5);

            source.classList.remove("dang-chon-de-trao-doi");
            dest.classList.remove("dang-chon-de-trao-doi");
          }

          // Cập nhật trạng thái
          aiStats.status = "Searching...";
          capNhatUI_ChiTiet();

          let score = tinhDiemHeuristic();
          aiStats.currentScore = score;
          if (score > aiStats.highScore) aiStats.highScore = score;
          capNhatUI_ChiTiet();
        } else {
          // Khi AI bị kẹt
          console.warn(`AI Hard: Bị kẹt tại bước ${i} (${currentId}).`);

          if (cacOdaKhoa.size > 0) {
            // Cập nhật trạng thái
            aiStats.status = "Stuck! Retrying...";
            capNhatUI_ChiTiet();

            // Xóa danh sách ô đã khóa để thử tráo đổi trong ô khóa
            cacOdaKhoa.clear();

            // Lấy danh sách ống vàng chỉ trong phạm vi gần cụm đang kẹt
            const PHAM_VI_TIM_KIEM = 3;
            const tatCaVang = Array.from(
              document.querySelectorAll(".KhungGame-OVuong.co-the-trao-doi")
            );

            // Lọc ra các ống vàng nằm gần điểm đang kẹt
            const ungVienGan = tatCaVang.filter((p) => {
              const r = parseInt(p.dataset.row);
              const c = parseInt(p.dataset.col);
              const distance =
                Math.abs(r - currNode.r) + Math.abs(c - currNode.c);
              return distance <= PHAM_VI_TIM_KIEM;
            });

            const danhSachUngVien =
              ungVienGan.length >= 2 ? ungVienGan : tatCaVang;

            // Xáo trộn danh sách để thử vận may mới
            danhSachUngVien.sort(() => Math.random() - 0.5);

            let daTraoDoi = false;

            // Tìm và tráo đổi hàng xóm
            for (let p1 of danhSachUngVien) {
              const r1 = parseInt(p1.dataset.row);
              const c1 = parseInt(p1.dataset.col);

              const neighbors = [
                { r: r1 - 1, c: c1 },
                { r: r1 + 1, c: c1 },
                { r: r1, c: c1 - 1 },
                { r: r1, c: c1 + 1 },
              ];

              const validNeighbors = [];
              for (let n of neighbors) {
                const p2 = document.querySelector(
                  `.KhungGame-OVuong[data-row="${n.r}"][data-col="${n.c}"]`
                );
                if (p2 && p2.classList.contains("co-the-trao-doi")) {
                  validNeighbors.push(p2);
                }
              }

              if (validNeighbors.length > 0) {
                // Lọc bỏ hàng xóm nếu nó tạo ra hành động ngược lại với bước vừa đi
                let neighborsDaLoc = validNeighbors;

                if (lanTraoDoiGanNhat) {
                  const id1 = `${p1.dataset.row}-${p1.dataset.col}`;

                  neighborsDaLoc = validNeighbors.filter((p2UngVien) => {
                    const id2 = `${p2UngVien.dataset.row}-${p2UngVien.dataset.col}`;

                    // Kiểm tra xem cặp (id1, id2) có phải là cặp vừa đổi không
                    const laCapCu =
                      (lanTraoDoiGanNhat.id1 === id1 &&
                        lanTraoDoiGanNhat.id2 === id2) ||
                      (lanTraoDoiGanNhat.id1 === id2 &&
                        lanTraoDoiGanNhat.id2 === id1);
                    return !laCapCu;
                  });
                }

                // Nếu sau khi lọc mà hết đường thì đành dùng lại đường cũ (hiếm khi xảy ra), còn không thì dùng ds đã lọc
                const danhSachChon =
                  neighborsDaLoc.length > 0 ? neighborsDaLoc : validNeighbors;

                const p2 =
                  danhSachChon[Math.floor(Math.random() * danhSachChon.length)];

                // Hiệu ứng visual nhanh
                p1.classList.add("dang-chon-de-trao-doi");
                p2.classList.add("dang-chon-de-trao-doi");

                aiTraoDoiOng(p1, p2);

                // Lưu lại cặp vừa đổi
                lanTraoDoiGanNhat = {
                  id1: `${p1.dataset.row}-${p1.dataset.col}`,
                  id2: `${p2.dataset.row}-${p2.dataset.col}`,
                };

                await choDoi(100);

                p1.classList.remove("dang-chon-de-trao-doi");
                p2.classList.remove("dang-chon-de-trao-doi");

                daTraoDoi = true;
                break;
              }
            }

            if (daTraoDoi) {
              aiStats.retryCount++;

              let diemGayOng = -1;

              for (let k = 0; k < i; k++) {
                const nodeK = path[k];
                const prevK = k > 0 ? path[k - 1] : null;
                const nextK = path[k + 1];
                const cellK = document.querySelector(
                  `.KhungGame-OVuong[data-row="${nodeK.r}"][data-col="${nodeK.c}"]`
                );

                const reqK = tinhOngCanThiet(prevK, nodeK, nextK);

                if (!prevK) {
                  cacOdaKhoa.add(`${nodeK.r}-${nodeK.c}`);
                  continue;
                }

                if (reqK && kiemTraOngPhuHop(cellK, reqK.type, reqK.rot)) {
                  cacOdaKhoa.add(`${nodeK.r}-${nodeK.c}`);
                } else {
                  diemGayOng = k;
                  break;
                }
              }

              if (diemGayOng === -1) {
                i = i - 1;
                console.log(
                  `-> Retry #${aiStats.retryCount}: Giữ nguyên tiến độ.`
                );
              } else {
                i = diemGayOng - 1;
                console.log(
                  `-> Retry #${aiStats.retryCount}: Quay về bước ${diemGayOng}.`
                );
              }

              capNhatUI_ChiTiet();
              oHienTai.style.filter = "none";
              continue;
            }

            // Cập nhật trạng thái
            aiStats.status = "Searching...";
            capNhatUI_ChiTiet();
          }
        }
      }

      // Sau khi xử lý xong ô này, thêm nó vào danh sách khóa
      cacOdaKhoa.add(currentId);
    }

    // Tắt highlight sau khi duyệt xong ô này để chuyển sang ô kế tiếp
    oHienTai.style.filter = "none";
  }

  if (aiMocThoiGian > 0) {
    aiThoiGianChayThuc += performance.now() - aiMocThoiGian;
    aiMocThoiGian = 0;
  }

  // Kiểm tra thực tế xem đường đã thông từ đầu đến cuối chưa
  if (kiemTraDuongDiDenDich()) {
    aiStats.status = "Solution Found!";
  } else {
    aiStats.status = "Search Failed!";
  }

  capNhatUI_ChiTiet();
  console.log("AI Hard: Hoàn thành.");
  capNhatLuongNuoc();
}

// ============================================================================================================================
// HÀM TÍNH TỔNG SỐ LƯỢNG ỐNG ĐƯỢC KẾT NỐI HIỆN TẠI (BAO GỒM CẢ NHÁNH) ĐỂ HIỆN THÔNG TIN CHO BẢNG KẾT THÚC

function tinhTongSoOngKetNoi() {
  const oBatDau = document
    .querySelector('img[src*="OngBatDau.svg"]')
    ?.closest(".KhungGame-OVuong");
  if (!oBatDau) return 0;

  // Xác định hướng ra của ống bắt đầu
  const startImg = oBatDau.querySelector(".AnhOngNuoc");
  const startRot = parseInt(startImg.dataset.rotation || 0);

  // Dùng Set để không đếm trùng
  let visited = new Set();
  let queue = [{ cell: oBatDau, fromDir: -1 }];

  visited.add(`${oBatDau.dataset.row}-${oBatDau.dataset.col}`);

  while (queue.length > 0) {
    const { cell, fromDir } = queue.shift();
    const row = parseInt(cell.dataset.row);
    const col = parseInt(cell.dataset.col);

    const img = cell.querySelector(".AnhOngNuoc");
    const wrapper = cell.querySelector(".Ong-Wrapper");
    const elXoay = wrapper ? wrapper : img;
    if (!elXoay) continue;

    // Xác định loại ống
    let loaiOng = "";
    if (img.src.includes("Thang")) loaiOng = "Thang";
    else if (img.src.includes("ChuL")) loaiOng = "ChuL";
    else if (img.src.includes("ChuThap")) loaiOng = "ChuThap";
    else if (img.src.includes("BatDau")) loaiOng = "BatDau";
    else if (img.src.includes("KetThuc")) loaiOng = "KetThuc";

    const currentRot = parseInt(elXoay.dataset.rotation || 0);
    const openDirs = layHuongMoThucTe(loaiOng, currentRot);

    // Kiểm tra kết nối với ống trước đó
    if (loaiOng !== "BatDau") {
      const requiredInput = (fromDir + 2) % 4;
      if (!openDirs.includes(requiredInput)) continue;
    }

    // Tìm các ống hàng xóm nối được
    for (let dir of openDirs) {
      if (dir === (fromDir + 2) % 4) continue;

      let nextR = row,
        nextC = col;
      if (dir === 0) nextR--;
      if (dir === 1) nextC++;
      if (dir === 2) nextR++;
      if (dir === 3) nextC--;

      const nextCell = document.querySelector(
        `.KhungGame-OVuong[data-row="${nextR}"][data-col="${nextC}"]`
      );

      if (nextCell) {
        const nextId = `${nextR}-${nextC}`;
        if (!visited.has(nextId)) {
          // Kiểm tra xem hàng xóm có chĩa cổng về phía mình không
          const nextImg = nextCell.querySelector(".AnhOngNuoc");
          if (nextImg) {
            const nextWrapper = nextCell.querySelector(".Ong-Wrapper");
            const nextEl = nextWrapper ? nextWrapper : nextImg;

            // Lấy loại ống hàng xóm
            let nextLoai = "";
            if (nextImg.src.includes("Thang")) nextLoai = "Thang";
            else if (nextImg.src.includes("ChuL")) nextLoai = "ChuL";
            else if (nextImg.src.includes("ChuThap")) nextLoai = "ChuThap";
            else if (nextImg.src.includes("KetThuc")) nextLoai = "KetThuc";

            const nextRot = parseInt(nextEl.dataset.rotation || 0);
            const nextOpenDirs = layHuongMoThucTe(nextLoai, nextRot);
            const huongCanThiet = (dir + 2) % 4;

            // Nếu hàng xóm cũng mở cổng về phía mình thì kết nối thành công
            if (nextOpenDirs.includes(huongCanThiet)) {
              visited.add(nextId);
              queue.push({ cell: nextCell, fromDir: dir });
            }
          }
        }
      }
    }
  }

  return visited.size > 0 ? visited.size - 1 : 0;
}

// ============================================================================================================================
// CẬP NHẬT ĐỘ DÀI ĐƯỜNG DẪN DÀI NHẤT

function capNhatDoDaiBaoCao() {
  const doDaiHienTai = tinhTongSoOngKetNoi();
  if (doDaiHienTai > aiStats.maxConnectedLength) {
    aiStats.maxConnectedLength = doDaiHienTai;
  }
}

// ============================================================================================================================
// CHỨC NĂNG ĐẦU HÀNG (CHẾ ĐỘ PLAYER)

async function kichHoatAI_DauHang() {
  const solutionPathStr = sessionStorage.getItem("solutionPath");
  if (!solutionPathStr) {
    console.error("Lỗi: Không tìm thấy đáp án để AI giải!");
    return;
  }
  const solutionPath = JSON.parse(solutionPathStr);
  const doKho = sessionStorage.getItem("difficulty") || "easy";
  await choDoi(500);
  console.log(`Đầu hàng: Kích hoạt AI giải chế độ ${doKho}...`);
  if (doKho === "hard") {
    await thucHienAI_Hard(solutionPath);
  } else {
    await thucHienAI_EasyMedium(solutionPath);
  }
}

// ============================================================================================================================
