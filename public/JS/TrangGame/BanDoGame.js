// ============================================================================================================================
// BẢN ĐỒ GAME NỐI ỐNG NƯỚC (CHẾ ĐỘ 1 PLAYER NÂNG CẤP TOÀN DIỆN)
// - Hệ thống 24 bản đồ chuẩn trong customMapDatabase (8 kích thước ma trận x 3 mức độ: Dễ, Trung bình, Khó)
//   + Chế độ Ngẫu nhiên (Random): Chọn ngẫu nhiên 1 trong 8 bản đồ thuộc đúng mức độ khó người chơi đã chọn
//   + Chế độ Tự chọn (Custom): Nạp chính xác bản đồ theo kích thước ma trận và mức độ khó người chơi đã chọn
// - Đa luồng ống nước (Tối thiểu 2 đường ống, tối đa 4 đường ống cùng màu S1->E1 .. S4->E4)
// - Độ khó tăng lũy tiến theo kích thước ma trận và cấp độ (Dễ: Ống Xanh | Trung bình: Xanh + Vàng | Khó: Xanh + Vàng + Đỏ + Tường)
// - Thời gian suy nghĩ tự động tăng theo kích thước ma trận, số lượng đường ống và mức độ khó
// - Giới hạn Gợi ý (Hint) tối đa 3 lần / trận; loại bỏ hoàn toàn chức năng Giải đáp (Give Up / AI Solve)
// ============================================================================================================================

let daKetNoiThanhCong = false;
window.dangChayCheDoDauHang = false;

// Cơ sở dữ liệu 24 bản đồ (8 ma trận x 3 cấp độ khó = 24 map)
// - Easy:   8 map (3x8, 3x10, 3x12, 5x4, 5x6, 5x8, 5x10, 5x12)
// - Medium: 8 map (3x8, 3x10, 3x12, 5x4, 5x6, 5x8, 5x10, 5x12)
// - Hard:   8 map (3x8, 3x10, 3x12, 5x4, 5x6, 5x8, 5x10, 5x12)
const customMapDatabase = {
  "3x8": { easy: null, medium: null, hard: null },
  "3x10": { easy: null, medium: null, hard: null },
  "3x12": { easy: null, medium: null, hard: null },
  "5x4": { easy: null, medium: null, hard: null },
  "5x6": { easy: null, medium: null, hard: null },
  "5x8": { easy: null, medium: null, hard: null },
  "5x10": { easy: null, medium: null, hard: null },
  "5x12": { easy: null, medium: null, hard: null },
};

// Bảng màu nhận diện cho từng cặp Điểm Bắt Đầu (S) - Điểm Kết Thúc (E)
const PIPE_PAIR_COLORS = [
  { pairId: 1, name: "Xanh Dương", hex: "#00e5ff", border: "3px solid #00e5ff", glow: "0 0 14px #00e5ff" },
  { pairId: 2, name: "Đỏ Hồng",    hex: "#ff2a85", border: "3px solid #ff2a85", glow: "0 0 14px #ff2a85" },
  { pairId: 3, name: "Xanh Lá",    hex: "#00e676", border: "3px solid #00e676", glow: "0 0 14px #00e676" },
  { pairId: 4, name: "Tím Vàng",   hex: "#d500f9", border: "3px solid #d500f9", glow: "0 0 14px #d500f9" },
];

// Cấu hình cổng mở mặc định (0: Trên, 1: Phải, 2: Dưới, 3: Trái)
const CAU_HINH_ONG = {
  Thang: [0, 2],
  ChuL: [1, 2],
  ChuThap: [0, 1, 2, 3],
  BatDau: [1],
  KetThuc: [1],
};

const THOI_GIAN_ANIMATION = {
  Thang: 520,
  ChuL: 520,
  ChuThap: 420,
  BatDau: 0,
  KetThuc: 0,
};

let cacTimeoutChoNuoc = [];
window.danhSachDuongOng = [];
window.soLuotGoiYConLai = 3;
window.diemHienTai100 = 0;

function taoBoSinhSoTheoSeed(seedStr) {
  let h = 2166136261 >>> 0;
  for (let i = 0; i < seedStr.length; i++) {
    h = Math.imul(h ^ seedStr.charCodeAt(i), 16777619);
  }
  return function () {
    h += 0x6d2b79f5;
    let t = Math.imul(h ^ (h >>> 15), 1 | h);
    t ^= t + Math.imul(t ^ (t >>> 7), 61 | t);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

function layHuongMoThucTe(loaiOng, gocXoay) {
  const normalized = (((gocXoay % 360) + 360) % 360);
  const soLanXoay = Math.round(normalized / 90) % 4;
  const huongMacDinh = CAU_HINH_ONG[loaiOng];
  if (!huongMacDinh) return [];
  return huongMacDinh.map((h) => (h + soLanXoay) % 4);
}

function tinhThongSoManChoi(mapSizeKey, difficulty) {
  const [rows, cols] = mapSizeKey.split("x").map(Number);
  const totalCells = rows * cols;
  let userSelectedPairs = parseInt(sessionStorage.getItem("numPairs") || "0", 10);
  let numPairs = 2;

  if (cols <= 4) {
    numPairs = 2;
  } else if (cols <= 6) {
    numPairs = userSelectedPairs >= 2 ? Math.min(3, userSelectedPairs) : (difficulty === "hard" ? 3 : 2);
  } else if (cols <= 8) {
    numPairs = userSelectedPairs >= 2 ? Math.min(rows === 3 ? 3 : 4, userSelectedPairs) : (difficulty === "hard" ? 3 : 2);
  } else {
    if (userSelectedPairs >= 2 && userSelectedPairs <= 4) {
      numPairs = userSelectedPairs;
    } else {
      numPairs = difficulty === "hard" ? 4 : difficulty === "medium" ? 3 : 2;
    }
  }

  const baseTimeMs = 45000;
  const sizeBonusMs = totalCells * 900;
  const pairBonusMs = numPairs * 15000;
  const diffMultiplier = difficulty === "hard" ? 1.45 : difficulty === "medium" ? 1.2 : 1.0;
  const totalThinkingTimeMs = Math.round((baseTimeMs + sizeBonusMs + pairBonusMs) * diffMultiplier);

  return { rows, cols, totalCells, numPairs, totalThinkingTimeMs };
}

function khoiTao24BanDoTrongDatabase() {
  const allSizes = Object.keys(customMapDatabase);
  const allDiffs = ["easy", "medium", "hard"];

  allSizes.forEach((sizeKey) => {
    allDiffs.forEach((diff) => {
      if (!customMapDatabase[sizeKey][diff]) {
        const info = tinhThongSoManChoi(sizeKey, diff);
        const seedKey = `HUIT_24MAP_${sizeKey}_${diff}_P${info.numPairs}_V2`;
        customMapDatabase[sizeKey][diff] = taoDuLieuMotBanDo(5, 12, info.rows, info.cols, diff, info.numPairs, seedKey);
      }
    });
  });
}

// ============================================================================================================================
// KHỞI CHẠY MÀN HÌNH GAME
// ============================================================================================================================
document.addEventListener("DOMContentLoaded", function () {
  const khungGame = document.querySelector(".KhungGame");
  if (!khungGame) return;

  const mapOption = sessionStorage.getItem("mapOption") || "random";
  const rawDifficulty = (sessionStorage.getItem("difficulty") || "easy").toLowerCase();
  const difficulty = ["easy", "medium", "hard"].includes(rawDifficulty) ? rawDifficulty : "easy";

  const SO_HANG = 5;
  const SO_COT = 12;
  const TONG_SO_O = SO_HANG * SO_COT;
  let oVuongVangDuocChon = null;

  // 1. LOẠI BỎ NÚT GIẢI ĐÁP / ĐẦU HÀNG
  const nutDauHang = document.getElementById("nutDauHang");
  const khungDauHang = document.getElementById("khungDauHang");
  const lopPhuDauHang = document.getElementById("lopPhu_BangDauHang");
  if (nutDauHang) nutDauHang.style.display = "none";
  if (khungDauHang) khungDauHang.style.display = "none";
  if (lopPhuDauHang) lopPhuDauHang.style.display = "none";

  // 2. Tạo lưới 60 ô (5x12)
  khungGame.innerHTML = "";
  for (let i = 0; i < TONG_SO_O; i++) {
    const oVuong = document.createElement("div");
    oVuong.classList.add("KhungGame-OVuong");
    oVuong.dataset.row = Math.floor(i / SO_COT) + 1;
    oVuong.dataset.col = (i % SO_COT) + 1;
    oVuong.style.position = "relative";
    khungGame.appendChild(oVuong);
  }

  // 3. Hàm thêm ống nước vào ô
  function themAnhVaoO(row, col, tenFileAnh, gocXoayMacDinh = 0, meta = null) {
    const oCanChen = document.querySelector(`.KhungGame-OVuong[data-row="${row}"][data-col="${col}"]`);
    if (!oCanChen) return;

    oCanChen.innerHTML = "";
    oCanChen.style.border = "none";
    oCanChen.style.boxShadow = "none";
    oCanChen.classList.remove("co-the-xoay", "co-the-trao-doi", "dang-chon-de-trao-doi", "da-khoa-goi-y");
    delete oCanChen.dataset.pairId;
    delete oCanChen.dataset.nodeRole;
    delete oCanChen.dataset.correctPipe;
    delete oCanChen.dataset.correctRotation;

    if (tenFileAnh.includes("OngXanh/")) {
      oCanChen.classList.add("co-the-xoay");
      oCanChen.style.cursor = "pointer";
    } else if (tenFileAnh.includes("OngVang/")) {
      oCanChen.classList.add("co-the-trao-doi");
      oCanChen.style.cursor = "grab";
    } else if (tenFileAnh.includes("OngDo/")) {
      oCanChen.classList.add("ong-do-co-dinh");
      oCanChen.style.cursor = "not-allowed";
    }

    if (
      tenFileAnh.includes("-Thang.svg") ||
      tenFileAnh.includes("-ChuThap.svg") ||
      tenFileAnh.includes("-ChuL.svg")
    ) {
      const ongWrapper = document.createElement("div");
      ongWrapper.classList.add("Ong-Wrapper");
      if (tenFileAnh.includes("-Thang.svg")) ongWrapper.innerHTML = svgNuocThang;
      else if (tenFileAnh.includes("-ChuThap.svg")) ongWrapper.innerHTML = svgNuocChuThapToanPhan;
      else ongWrapper.innerHTML = svgNuocChuL;

      const imgOng = document.createElement("img");
      imgOng.src = `Images/svg/Ong/${tenFileAnh}`;
      imgOng.classList.add("AnhOngNuoc");
      ongWrapper.appendChild(imgOng);
      ongWrapper.style.transform = `rotate(${gocXoayMacDinh}deg)`;
      ongWrapper.dataset.rotation = gocXoayMacDinh;
      oCanChen.appendChild(ongWrapper);
    } else {
      const imgOng = document.createElement("img");
      imgOng.src = `Images/svg/Ong/${tenFileAnh}`;
      imgOng.classList.add("AnhOngNuoc");
      imgOng.style.transform = `rotate(${gocXoayMacDinh}deg)`;
      imgOng.dataset.rotation = gocXoayMacDinh;
      oCanChen.appendChild(imgOng);
    }

    if (meta && meta.correctPipe !== undefined) {
      oCanChen.dataset.correctPipe = meta.correctPipe;
      oCanChen.dataset.correctRotation = meta.correctRotation;
    }

    if (meta && meta.endpointColor) {
      oCanChen.dataset.pairId = meta.pairId;
      oCanChen.dataset.nodeRole = meta.role;
      oCanChen.style.border = meta.endpointColor.border;
      oCanChen.style.boxShadow = meta.endpointColor.glow;
      oCanChen.style.borderRadius = "8px";

      const tag = document.createElement("span");
      tag.innerText = `${meta.role === "start" ? "S" : "E"}${meta.pairId}`;
      tag.style.cssText = `position:absolute; top:2px; left:2px; z-index:25; background:${meta.endpointColor.hex}; color:#000; font-weight:900; font-size:11px; padding:1px 5px; border-radius:4px; pointer-events:none; box-shadow:0 1px 4px rgba(0,0,0,0.6);`;
      oCanChen.appendChild(tag);
    }
  }

  // 4. Khởi tạo kho 24 bản đồ và chọn bản đồ
  if (sessionStorage.getItem("gameMode") !== "pvp") khoiTao24BanDoTrongDatabase();

  let selectedSizeKey = sessionStorage.getItem("mapSize") || "5x8";
  if (!customMapDatabase[selectedSizeKey]) selectedSizeKey = "5x8";

  let chosenMapData = null;
  const isPvP = sessionStorage.getItem("gameMode") === "pvp";

  if (isPvP) {
    try {
      const pvpRaw = sessionStorage.getItem("pvpMatchData");
      if (pvpRaw) {
        const pvpMatch = JSON.parse(pvpRaw);
        if (pvpMatch && pvpMatch.map_data) {
          chosenMapData = pvpMatch.map_data;
          selectedSizeKey = pvpMatch.map_data.sizeKey || pvpMatch.map_data.mapSizeKey || selectedSizeKey;
          console.log(`[CHẾ ĐỘ PVP REAL-TIME] Đã nạp bản đồ đồng bộ từ Server (Seed: ${pvpMatch.map_data.seed || 'N/A'})`);
        }
      }
    } catch (e) {
      console.error("[PvP] Lỗi nạp pvpMatchData:", e);
    }
  }

  if (!chosenMapData) {
    if (mapOption === "custom") {
      chosenMapData = customMapDatabase[selectedSizeKey][difficulty];
      console.log(`[CHẾ ĐỘ TỰ CHỌN] Đã nạp đúng Map ${selectedSizeKey} - Độ khó: ${difficulty.toUpperCase()}`);
    } else {
      const danhSach8KichThuoc = Object.keys(customMapDatabase);
      const randomIndex = Math.floor(Math.random() * danhSach8KichThuoc.length);
      selectedSizeKey = danhSach8KichThuoc[randomIndex];
      chosenMapData = customMapDatabase[selectedSizeKey][difficulty];
      console.log(`[CHẾ ĐỘ NGẪU NHIÊN] Đã chọn ngẫu nhiên Map ${selectedSizeKey} (1 trong 8 map cấp độ ${difficulty.toUpperCase()})`);
    }
  }

  window.banDoBanDau = chosenMapData;
  window.chuanDiem = { status: isPvP ? "server" : "pending" };
  sessionStorage.setItem("mapSize", selectedSizeKey);
  window.khoiPhucBanPvP = function (state) {
    chosenMapData.cells.forEach((cell) => {
      const key = `${cell.r}-${cell.c}`;
      const layout = state.layout?.[key] || cell;
      const color = chosenMapData.pipes.find(p => p.pairId === cell.pairId)?.color;
      themAnhVaoO(cell.r, cell.c, layout.file || cell.file, state.rotations?.[key] ?? cell.initialRot,
        { pairId: cell.pairId, role: cell.role, endpointColor: cell.role ? color : null });
    });
    capNhatLuongNuoc();
  };
  napBanDoTuDatabaseLenUI(chosenMapData, themAnhVaoO);
  if (!isPvP) napChuanDiemBanDau();

  // 5. Khởi tạo HUD
  khoiTaoThanhHUDVaNutGoiY3Lan(selectedSizeKey, difficulty, chosenMapData);

  // 6. Khởi tạo đồng hồ thời gian
  khoiTaoDongHoThoiGianTheoDoKho(chosenMapData.thinkingTimeMs);

  // 7. Hàm tráo đổi 2 ô ống Vàng liền kề
  function swapPipeContents(o1, o2) {
    // Hoán đổi toàn bộ nội dung HTML bên trong
    const tempHTML = o1.innerHTML;
    o1.innerHTML = o2.innerHTML;
    o2.innerHTML = tempHTML;

    // Giữ metadata gợi ý theo nội dung ống; tính điểm dùng chuẩn map ban đầu.
    const tempCorrectPipe = o1.dataset.correctPipe;
    const tempCorrectRot = o1.dataset.correctRotation;
    o1.dataset.correctPipe = o2.dataset.correctPipe;
    o1.dataset.correctRotation = o2.dataset.correctRotation;
    o2.dataset.correctPipe = tempCorrectPipe;
    o2.dataset.correctRotation = tempCorrectRot;

    // HIỆU ỨNG: CẢ 2 Ô VỪA ĐỔI ĐỀU PHÁT SÁNG VÀNG ĐẬM TRONG 0.8S
    [o1, o2].forEach((cell) => {
      cell.style.boxShadow = "0 0 24px 6px #ffe600";
      cell.style.border = "3px solid #ffffff";
      cell.style.borderRadius = "8px";
      cell.style.zIndex = "20";
      cell.style.transition = "box-shadow 0.2s ease, border 0.2s ease";
    });

    setTimeout(() => {
      [o1, o2].forEach((cell) => {
        cell.style.boxShadow = "none";
        cell.style.border = "none";
        cell.style.zIndex = "";
      });
    }, 800);
  }

  // Hàm hủy chọn ô vàng đang được chọn
  function huyChonOVang() {
    if (!oVuongVangDuocChon) return;
    oVuongVangDuocChon.classList.remove("dang-chon-de-trao-doi");
    oVuongVangDuocChon.style.boxShadow = "none";
    oVuongVangDuocChon.style.border = "none";
    oVuongVangDuocChon.style.zIndex = "";
    oVuongVangDuocChon = null;
  }

  // 8. Lắng nghe sự kiện click xoay ống Xanh & tráo đổi ống Vàng (chỉ 1 listener duy nhất)
  khungGame.addEventListener("click", function (event) {
    if (daKetNoiThanhCong || window.isGameOver || window.pvpSubmitting || window.pvpSyncFailed || window.pvpRestoring) return;

    const oDuocClick = event.target.closest(".KhungGame-OVuong");
    if (!oDuocClick) {
      // Click ra ngoài lưới: hủy chọn ô vàng đang chọn (nếu có)
      huyChonOVang();
      return;
    }

    // Bỏ qua ô đã được khóa cố định bởi Gợi ý (Hint)
    if (oDuocClick.classList.contains("da-khoa-goi-y")) return;

    // 1. XOAY ỐNG XANH (90 độ mỗi lần nhấn)
    if (oDuocClick.classList.contains("co-the-xoay")) {
      // Click ống xanh khi đang chọn ô vàng → hủy chọn ô vàng
      huyChonOVang();

      const ongWrapper = oDuocClick.querySelector(".Ong-Wrapper");
      const imgBenTrong = oDuocClick.querySelector(".AnhOngNuoc");
      const phanTuDeXoay = ongWrapper ? ongWrapper : imgBenTrong;

      if (phanTuDeXoay && phanTuDeXoay.dataset.dangXoay !== "true") {
        if (typeof phatAmThanhXoay === "function") phatAmThanhXoay();
        phanTuDeXoay.dataset.dangXoay = "true";
        const gocHienTai = parseInt(phanTuDeXoay.dataset.rotation || "0", 10);
        const gocMoi = (gocHienTai + 90) % 360;
        phanTuDeXoay.style.transform = `rotate(${gocMoi}deg)`;
        phanTuDeXoay.dataset.rotation = gocMoi;
        window.PvpMatch?.onPlayerAction({ action: "rotate", row: Number(oDuocClick.dataset.row),
          col: Number(oDuocClick.dataset.col), degrees: 90 });

        setTimeout(() => {
          phanTuDeXoay.dataset.dangXoay = "false";
          capNhatLuongNuoc();
        }, 220);
      }
    }
    // 2. TRÁO ĐỔI VỊ TRÍ ỐNG VÀNG: chỉ đổi được với ô vàng liền kề (khoảng cách manhattan = 1)
    else if (oDuocClick.classList.contains("co-the-trao-doi")) {
      // Lần click 1: Chọn ống vàng thứ nhất → phát sáng viền vàng
      if (!oVuongVangDuocChon) {
        oVuongVangDuocChon = oDuocClick;
        oVuongVangDuocChon.classList.add("dang-chon-de-trao-doi");
        oVuongVangDuocChon.style.boxShadow = "0 0 18px 5px #ffd600";
        oVuongVangDuocChon.style.border = "3px solid #ffd600";
        oVuongVangDuocChon.style.borderRadius = "8px";
        oVuongVangDuocChon.style.zIndex = "15";
      }
      // Click lại chính nó: hủy chọn & tắt sáng
      else if (oVuongVangDuocChon === oDuocClick) {
        huyChonOVang();
      }
      // Lần click 2: chọn ô vàng thứ hai → kiểm tra liền kề trực tiếp
      else {
        const r1 = parseInt(oVuongVangDuocChon.dataset.row, 10);
        const c1 = parseInt(oVuongVangDuocChon.dataset.col, 10);
        const r2 = parseInt(oDuocClick.dataset.row, 10);
        const c2 = parseInt(oDuocClick.dataset.col, 10);
        const manhattan = Math.abs(r1 - r2) + Math.abs(c1 - c2);

        if (manhattan === 1) {
          // Liền kề trực tiếp (không bị cản trở bởi bất kỳ ô nào ở giữa) → hoán đổi hợp lệ
          const oVuongCu = oVuongVangDuocChon;
          huyChonOVang();
          swapPipeContents(oVuongCu, oDuocClick);
          window.PvpMatch?.onPlayerAction({ action: "swap", rowA: r1, colA: c1, rowB: r2, colB: c2 });
          if (typeof phatAmThanhDoiOng === "function") phatAmThanhDoiOng();
          capNhatLuongNuoc();
        } else {
          // Không liền kề: bỏ chọn ô cũ, chuyển sang chọn ô mới
          huyChonOVang();
          oVuongVangDuocChon = oDuocClick;
          oVuongVangDuocChon.classList.add("dang-chon-de-trao-doi");
          oVuongVangDuocChon.style.boxShadow = "0 0 18px 5px #ffd600";
          oVuongVangDuocChon.style.border = "3px solid #ffd600";
          oVuongVangDuocChon.style.borderRadius = "8px";
          oVuongVangDuocChon.style.zIndex = "15";
        }
      }
    }
    // 3. Ống đỏ / ô trống / không tương tác: hủy chọn ô vàng đang chọn
    else {
      huyChonOVang();
    }
  });

  capNhatLuongNuoc();
});


// ============================================================================================================================
// THUẬT TOÁN SINH BẢN ĐỒ ĐA LUỒNG (2 -> 4 ĐƯỜNG ỐNG) VỚI ĐỘ KHÓ LŨY TIẾN THEO KÍCH THƯỚC MA TRẬN
// ============================================================================================================================
function taoDuLieuMotBanDo(SO_HANG_LUOI, SO_COT_LUOI, mapRows, mapCols, difficulty, numPairs, seedKey) {
  const rng = taoBoSinhSoTheoSeed(seedKey);
  const info = tinhThongSoManChoi(`${mapRows}x${mapCols}`, difficulty);

  const rowOffset = Math.floor((SO_HANG_LUOI - mapRows) / 2);
  const colOffset = Math.floor((SO_COT_LUOI - mapCols) / 2);
  const minR = rowOffset + 1;
  const maxR = rowOffset + mapRows;
  const minC = colOffset + 1;
  const maxC = colOffset + mapCols;

  // Cấu hình tỉ lệ ống theo độ khó và kích thước ma trận:
  // - Dễ (easy): 100% Ống Xanh, đường đi uốn lượn vừa phải
  // - Trung bình (medium): Ống Xanh + Cụm Ống Vàng hoán đổi (38%), nhiều khúc cua hơn
  // - Khó (hard): Ống Xanh + Cụm Ống Vàng hoán đổi (45%) + Ống Đỏ khóa cứng (22%) + Tường chắn, đường đi cực kỳ lắt léo!
  const scaleFactor = (mapRows * mapCols) / 24;
  const config = {
    minTurnsPerPipe:
      difficulty === "hard"
        ? Math.max(4, Math.round(4 * scaleFactor))
        : difficulty === "medium"
        ? Math.max(3, Math.round(2.5 * scaleFactor))
        : Math.max(2, Math.round(1.5 * scaleFactor)),
    yellowProb: difficulty === "hard" ? 0.45 : difficulty === "medium" ? 0.36 : 0.0,
    redProb: difficulty === "hard" ? 0.22 : 0.0,
    crossDecoyProb: difficulty === "hard" ? 0.35 : difficulty === "medium" ? 0.22 : 0.12,
    wallProb: difficulty === "hard" ? 0.16 : 0.0,
  };

  const cellsMap = {};
  const pipesData = [];

  // Chia không gian ma trận thành numPairs phân vùng (theo cột hoặc theo hàng) để K đường ống không đè lên nhau
  // nhưng vẫn uốn lượn zíc-zắc tối đa bên trong phân vùng của mình!
  for (let p = 0; p < numPairs; p++) {
    const colorMeta = PIPE_PAIR_COLORS[p % PIPE_PAIR_COLORS.length];
    let zMinR = minR,
      zMaxR = maxR,
      zMinC = minC,
      zMaxC = maxC;

    if (mapCols >= numPairs * 2) {
      // Chia theo dải cột
      const baseWidth = Math.floor(mapCols / numPairs);
      const rem = mapCols % numPairs;
      const cOffset = p * baseWidth + Math.min(p, rem);
      const width = baseWidth + (p < rem ? 1 : 0);
      zMinC = minC + cOffset;
      zMaxC = zMinC + width - 1;
    } else {
      // Chia theo dải hàng
      const baseHeight = Math.floor(mapRows / numPairs);
      zMinR = minR + p * baseHeight;
      zMaxR = p === numPairs - 1 ? maxR : zMinR + baseHeight - 1;
    }

    // Đặt Start ở góc trên/trái phân vùng và End ở góc đối diện chéo xa nhất để ép đường đi dài nhất
    const flip = p % 2 === 1;
    const startPos = {
      r: flip ? zMaxR : zMinR,
      c: zMinC,
      rot: flip ? 270 : 90, // 90: mở xuống (2), 270: mở lên (0)
      dir: flip ? 0 : 2,
    };
    const endPos = {
      r: flip ? zMinR : zMaxR,
      c: zMaxC,
      rot: flip ? 90 : 270, // Hướng cổng chờ đón dòng nước
      dir: flip ? 2 : 0,
    };

    // Tìm đường đi uốn lượn zíc-zắc phức tạp trong phân vùng bằng thuật toán Winding DFS
    const windingPath = timDuongUonLuonZicZacDFS(
      startPos,
      endPos,
      zMinR,
      zMaxR,
      zMinC,
      zMaxC,
      config.minTurnsPerPipe,
      difficulty,
      rng
    );

    // Ghi nhận điểm Start & End
    cellsMap[`${startPos.r}-${startPos.c}`] = {
      r: startPos.r,
      c: startPos.c,
      file: "OngBatDau.svg",
      initialRot: startPos.rot,
      correctPipe: "OngBatDau.svg",
      correctRot: startPos.rot,
      pairId: p + 1,
      role: "start",
      endpointColor: colorMeta,
    };

    cellsMap[`${endPos.r}-${endPos.c}`] = {
      r: endPos.r,
      c: endPos.c,
      file: "OngKetThuc.svg",
      initialRot: endPos.rot,
      correctPipe: "OngKetThuc.svg",
      correctRot: endPos.rot,
      pairId: p + 1,
      role: "end",
      endpointColor: colorMeta,
    };

    // Xác định loại ống chuẩn cho từng bước trên đường đi
    const steps = [];
    for (let i = 1; i < windingPath.length - 1; i++) {
      const prev = windingPath[i - 1];
      const curr = windingPath[i];
      const next = windingPath[i + 1];

      const inDir = layHuongGiua2O(curr, prev);
      const outDir = layHuongGiua2O(curr, next);
      const { shape, correctRot } = xacDinhHinhDangVaGocDung(inDir, outDir);

      steps.push({
        r: curr.r,
        c: curr.c,
        shape,
        correctRot,
      });

      cellsMap[`${curr.r}-${curr.c}`] = {
        r: curr.r,
        c: curr.c,
        shape,
        correctRot,
        isSolutionCell: true,
        pairId: p + 1,
      };
    }

    pipesData.push({
      pairId: p + 1,
      color: colorMeta,
      start: startPos,
      end: endPos,
      shortestLength: windingPath.length,
      steps,
    });
  }

  // Phân phối màu ống (OngXanh, OngVang, OngDo) và Tường chắn trên toàn bộ ma trận
  let listYellowCandidates = [];

  for (let r = minR; r <= maxR; r++) {
    for (let c = minC; c <= maxC; c++) {
      const key = `${r}-${c}`;
      const existing = cellsMap[key];

      // Nếu là điểm Start hoặc End thì giữ nguyên
      if (existing && existing.role) continue;

      if (existing && existing.isSolutionCell) {
        // Ô nằm trên đường giải
        let colorFolder = "OngXanh";
        if (difficulty === "medium") {
          if (rng() < config.yellowProb) colorFolder = "OngVang";
        } else if (difficulty === "hard") {
          const roll = rng();
          if (roll < config.yellowProb) colorFolder = "OngVang";
          else if (roll < config.yellowProb + config.redProb) colorFolder = "OngDo";
        }

        const correctFile = `${colorFolder}/${colorFolder}-${existing.shape}.svg`;
        let startRot = existing.correctRot;

        if (colorFolder === "OngXanh") {
          // Ống Xanh trên đường giải luôn bị xoay lệch đi 90, 180 hoặc 270 độ để người chơi phải tự xoay
          const offset = existing.shape === "Thang" ? 90 : [90, 180, 270][Math.floor(rng() * 3)];
          startRot = (existing.correctRot + offset) % 360;
        }

        existing.file = correctFile;
        existing.initialRot = startRot;
        existing.correctPipe = correctFile;
        existing.correctRotation = existing.correctRot;
        existing.colorFolder = colorFolder;

        if (colorFolder === "OngVang") {
          listYellowCandidates.push(existing);
        }
      } else {
        // Ô ngoài đường giải: tạo tường chắn (ở mức Khó) hoặc các nhánh ống bẫy (decoy branches)
        if (difficulty === "hard" && rng() < config.wallProb) {
          cellsMap[key] = {
            r,
            c,
            file: "Tuong.png",
            initialRot: 0,
            correctPipe: "Tuong.png",
            correctRotation: 0,
          };
          continue;
        }

        let colorFolder = "OngXanh";
        if (difficulty === "medium" && rng() < config.yellowProb * 0.8) {
          colorFolder = "OngVang";
        } else if (difficulty === "hard") {
          const roll = rng();
          if (roll < config.yellowProb * 0.85) colorFolder = "OngVang";
          else if (roll < config.yellowProb * 0.85 + config.redProb) colorFolder = "OngDo";
        }

        const shape =
          rng() < config.crossDecoyProb ? "ChuThap" : rng() < 0.55 ? "ChuL" : "Thang";
        const rot = Math.floor(rng() * 4) * 90;
        const file = `${colorFolder}/${colorFolder}-${shape}.svg`;

        const cellObj = {
          r,
          c,
          shape,
          file,
          initialRot: rot,
          correctPipe: file,
          correctRotation: rot,
          colorFolder,
          isSolutionCell: false,
        };
        cellsMap[key] = cellObj;

        if (colorFolder === "OngVang") {
          listYellowCandidates.push(cellObj);
        }
      }
    }
  }

  // Đảm bảo mọi ống Vàng trên đường giải đều có ít nhất 1 ống Vàng kề cạnh để hoán đổi vị trí thực sự!
  for (let i = 0; i < listYellowCandidates.length; i++) {
    const yCell = listYellowCandidates[i];
    const neighbors = [
      cellsMap[`${yCell.r - 1}-${yCell.c}`],
      cellsMap[`${yCell.r + 1}-${yCell.c}`],
      cellsMap[`${yCell.r}-${yCell.c - 1}`],
      cellsMap[`${yCell.r}-${yCell.c + 1}`],
    ].filter((n) => n && !n.role && n.file !== "Tuong.png");

    const hasYellowNeighbor = neighbors.some((n) => n.colorFolder === "OngVang");
    if (!hasYellowNeighbor && neighbors.length > 0) {
      // Biến 1 ô Xanh kề cạnh thành ống Vàng khác kiểu để tạo cặp hoán đổi bắt buộc
      const targetNeighbor = neighbors[0];
      targetNeighbor.colorFolder = "OngVang";
      const altShape = yCell.shape === "Thang" ? "ChuL" : "Thang";
      targetNeighbor.shape = altShape;
      targetNeighbor.file = `OngVang/OngVang-${altShape}.svg`;
      targetNeighbor.correctPipe = targetNeighbor.file;
      listYellowCandidates.push(targetNeighbor);
    } else if (!hasYellowNeighbor) {
      // Nếu cô lập hoàn toàn thì chuyển thành ống Xanh xoay lệch
      yCell.colorFolder = "OngXanh";
      yCell.file = `OngXanh/OngXanh-${yCell.shape}.svg`;
      yCell.correctPipe = yCell.file;
      yCell.initialRot = (yCell.correctRotation + 90) % 360;
    }
  }

  // Gom cụm các ống Vàng kề nhau và hoán đổi vị trí ban đầu của chúng
  const validYellows = Object.values(cellsMap).filter((c) => c.colorFolder === "OngVang");
  const visitedYellow = new Set();

  for (const startY of validYellows) {
    const startKey = `${startY.r}-${startY.c}`;
    if (visitedYellow.has(startKey)) continue;

    const cluster = [];
    const q = [startY];
    visitedYellow.add(startKey);

    while (q.length > 0) {
      const curr = q.shift();
      cluster.push(curr);
      for (const cand of validYellows) {
        const cKey = `${cand.r}-${cand.c}`;
        if (!visitedYellow.has(cKey) && Math.abs(curr.r - cand.r) + Math.abs(curr.c - cand.c) === 1) {
          visitedYellow.add(cKey);
          q.push(cand);
        }
      }
    }

    if (cluster.length >= 2) {
      // Dịch vòng tròn nội dung (file + initialRot) trong cụm để người chơi bắt buộc phải tráo đổi lại
      const snapshot = cluster.map((item) => ({
        file: item.correctPipe,
        rot: item.correctRotation,
      }));
      snapshot.push(snapshot.shift());
      cluster.forEach((item, idx) => {
        item.file = snapshot[idx].file;
        item.initialRot = snapshot[idx].rot;
      });
    }
  }

  return {
    sizeKey: `${mapRows}x${mapCols}`,
    difficulty,
    numPairs,
    thinkingTimeMs: info.totalThinkingTimeMs,
    cells: Object.values(cellsMap),
    pipes: pipesData,
  };
}

/**
 * Thuật toán Winding DFS: Tạo đường đi uốn lượn nhiều khúc cua giữa Start và End
 * Độ khó càng cao và ma trận càng lớn thì đường đi càng dài và lắt léo hơn.
 */
function timDuongUonLuonZicZacDFS(startPos, endPos, minR, maxR, minC, maxC, targetTurns, difficulty, rng) {
  const dirs = [
    [-1, 0],
    [1, 0],
    [0, -1],
    [0, 1],
  ];

  let bestPath = null;
  let bestScore = -1;
  let attempts = 0;
  const maxSearchAttempts = 350;

  // Điểm đầu tiên ngay sau ống Start và điểm ngay trước ống End
  const firstStepR = startPos.r + (startPos.dir === 0 ? -1 : startPos.dir === 2 ? 1 : 0);
  const firstStepC = startPos.c + (startPos.dir === 1 ? 1 : startPos.dir === 3 ? -1 : 0);
  const preEndR = endPos.r + (endPos.dir === 0 ? -1 : endPos.dir === 2 ? 1 : 0);
  const preEndC = endPos.c + (endPos.dir === 1 ? 1 : endPos.dir === 3 ? -1 : 0);

  let visited = new Set([`${startPos.r}-${startPos.c}`, `${endPos.r}-${endPos.c}`]);
  let currentStack = [{ r: startPos.r, c: startPos.c }];

  function dfs(r, c, prevDr, prevDc, turns) {
    if (attempts > maxSearchAttempts) return;
    if (r < minR || r > maxR || c < minC || c > maxC) return;
    const key = `${r}-${c}`;
    if (visited.has(key)) return;

    // Tránh tự chạm (Chordless check) để không tạo đường tắt quá ngắn làm dễ bài toán
    for (let i = 1; i < currentStack.length - 1; i++) {
      const node = currentStack[i];
      if (Math.abs(r - node.r) + Math.abs(c - node.c) === 1) {
        if (difficulty !== "easy" || currentStack.length > 4) {
          return;
        }
      }
    }

    visited.add(key);
    currentStack.push({ r, c });

    if (r === preEndR && c === preEndC) {
      attempts++;
      const candidate = [...currentStack, { r: endPos.r, c: endPos.c }];
      const complexityScore = turns * 10 + candidate.length;
      if (complexityScore > bestScore) {
        bestScore = complexityScore;
        bestPath = candidate;
      }
      visited.delete(key);
      currentStack.pop();
      return;
    }

    let nextDirs = [...dirs];
    for (let i = nextDirs.length - 1; i > 0; i--) {
      const j = Math.floor(rng() * (i + 1));
      [nextDirs[i], nextDirs[j]] = [nextDirs[j], nextDirs[i]];
    }

    // Ưu tiên đổi hướng (rẽ góc 90 độ) để tạo đường đi zíc-zắc khó đoán
    if (prevDr !== null) {
      nextDirs.sort((a, b) => {
        const turnA = a[0] !== prevDr || a[1] !== prevDc ? 0 : 1;
        const turnB = b[0] !== prevDr || b[1] !== prevDc ? 0 : 1;
        return turnA - turnB;
      });
    }

    for (const [dr, dc] of nextDirs) {
      const nr = r + dr;
      const nc = c + dc;
      const newTurns = prevDr !== null && (dr !== prevDr || dc !== prevDc) ? turns + 1 : turns;
      dfs(nr, nc, dr, dc, newTurns);
      if (bestPath && bestScore >= targetTurns * 10 + 8 && attempts > 60) break;
    }

    visited.delete(key);
    currentStack.pop();
  }

  dfs(firstStepR, firstStepC, firstStepR - startPos.r, firstStepC - startPos.c, 0);

  // Nếu lưới quá hẹp chưa tìm được đường Chordless thì dựng đường Manhattan Zíc-Zắc đảm bảo 100% có lời giải
  if (!bestPath) {
    const fallback = [{ r: startPos.r, c: startPos.c }];
    let cr = firstStepR;
    let cc = firstStepC;
    fallback.push({ r: cr, c: cc });

    while (cc !== preEndC || cr !== preEndR) {
      if (cc < preEndC) cc++;
      else if (cc > preEndC) cc--;
      else if (cr < preEndR) cr++;
      else if (cr > preEndR) cr--;
      fallback.push({ r: cr, c: cc });
    }
    fallback.push({ r: endPos.r, c: endPos.c });
    bestPath = fallback;
  }

  return bestPath;
}

function layHuongGiua2O(fromCell, toCell) {
  if (toCell.r < fromCell.r) return 0; // Trên
  if (toCell.c > fromCell.c) return 1; // Phải
  if (toCell.r > fromCell.r) return 2; // Dưới
  return 3;                            // Trái
}

function xacDinhHinhDangVaGocDung(inDir, outDir) {
  const dirs = [inDir, outDir].sort((a, b) => a - b);
  if (dirs[0] === 0 && dirs[1] === 2) return { shape: "Thang", correctRot: 0 };
  if (dirs[0] === 1 && dirs[1] === 3) return { shape: "Thang", correctRot: 90 };
  if (dirs[0] === 1 && dirs[1] === 2) return { shape: "ChuL", correctRot: 0 };
  if (dirs[0] === 2 && dirs[1] === 3) return { shape: "ChuL", correctRot: 90 };
  if (dirs[0] === 0 && dirs[1] === 3) return { shape: "ChuL", correctRot: 180 };
  return { shape: "ChuL", correctRot: 270 };
}

function napBanDoTuDatabaseLenUI(mapData, themAnhVaoO) {
  window.danhSachDuongOng = mapData.pipes.map((p) => ({
    ...p,
    isConnected: false,
    currentLength: 0,
  }));

  mapData.cells.forEach((cell) => {
    themAnhVaoO(cell.r, cell.c, cell.file, cell.initialRot, {
      pairId: cell.pairId,
      role: cell.role,
      endpointColor: cell.endpointColor || (cell.role ? mapData.pipes.find(p => p.pairId === cell.pairId)?.color : null),
      correctPipe: cell.correctPipe,
      correctRotation: cell.correctRotation,
    });
  });
}

// ============================================================================================================================
// THANH HUD THÔNG TIN, ĐỒNG HỒ THỜI GIAN TĂNG THEO ĐỘ KHÓ & NÚT GỢI Ý (TỐI ĐA 3 LẦN / TRẬN)
// ============================================================================================================================
function khoiTaoDongHoThoiGianTheoDoKho(thinkingTimeMs) {
  const nuocRects = document.querySelectorAll(".OngThoiGian_Nuoc");
  if (!nuocRects || nuocRects.length === 0) return;

  if (typeof thoiGianInterval !== "undefined") {
    clearInterval(thoiGianInterval);
  }
  mucNuocThoiGianHienTai = 0;
  nuocRects.forEach((r) => {
    r.style.opacity = 0;
  });

  const soMuc = nuocRects.length;
  const buocMs = thinkingTimeMs / soMuc;

  thoiGianInterval = setInterval(() => {
    if (window.isGamePaused || daKetNoiThanhCong || window.isGameOver) return;
    if (mucNuocThoiGianHienTai < soMuc) {
      nuocRects[mucNuocThoiGianHienTai].style.opacity = 1;
      mucNuocThoiGianHienTai++;
    } else {
      clearInterval(thoiGianInterval);
      if (typeof xuLyThuaGame === "function") xuLyThuaGame();
    }
  }, buocMs);
}

function khoiTaoThanhHUDVaNutGoiY3Lan(sizeKey, difficulty, mapData) {
  window.soLuotGoiYConLai = 3;

  // Tạo hoặc cập nhật thanh HUD phía trên màn hình
  let hud = document.getElementById("hudMultiPipePanel");
  if (!hud) {
    hud = document.createElement("div");
    hud.id = "hudMultiPipePanel";
    hud.style.cssText =
      "position:fixed; top:10px; left:50%; transform:translateX(-50%); z-index:900; background:rgba(15,23,42,0.92); border:2px solid #00e5ff; border-radius:12px; padding:6px 16px; color:#fff; font-family:'Chakra Petch',sans-serif; display:flex; align-items:center; gap:14px; box-shadow:0 4px 15px rgba(0,0,0,0.55);";
    document.body.appendChild(hud);
  }

  const secondsTotal = Math.round(mapData.thinkingTimeMs / 1000);
  const diffLabel =
    difficulty === "hard" ? "KHÓ" : difficulty === "medium" ? "TRUNG BÌNH" : "DỄ";

  hud.innerHTML = `
    <div style="font-size:13px;">
      <span style="color:#38bdf8; font-weight:700;">Bản đồ:</span> <strong>${sizeKey}</strong>
      <span style="margin-left:10px; padding:2px 7px; border-radius:6px; background:#1e293b; color:#fde047; font-weight:700;">${diffLabel}</span>
      <span style="margin-left:10px; color:#86efac;">⏱ ${secondsTotal}s</span>
    </div>
    <div style="background:#1e293b; padding:3px 10px; border-radius:8px; border:1px solid #fde047; font-size:13px;">
      Điểm: <strong id="valCurrentShortestScore" style="color:#fde047; font-size:16px;">0</strong>/100đ
    </div>
  `;

  // Tạo hoặc cập nhật nút Gợi ý (Tối đa 3 lần mỗi trận)
  let nutGoiY = document.getElementById("nutGoiY");
  if (!nutGoiY) {
    nutGoiY = document.createElement("div");
    nutGoiY.id = "nutGoiY";
    nutGoiY.className = "am-thanh-click";
    nutGoiY.style.cssText =
      "position:fixed; bottom:22px; right:30px; z-index:900; background:linear-gradient(135deg,#0284c7,#00e5ff); color:#000; font-family:'Chakra Petch',sans-serif; font-weight:800; font-size:17px; padding:10px 18px; border-radius:12px; border:3px solid #fff; cursor:pointer; box-shadow:0 4px 12px rgba(0,0,0,0.45); user-select:none;";
    document.body.appendChild(nutGoiY);
  }

  function capNhatNhanNutGoiY() {
    nutGoiY.innerText = `💡 Gợi ý (${window.soLuotGoiYConLai}/3)`;
    if (window.soLuotGoiYConLai <= 0) {
      nutGoiY.style.opacity = "0.5";
      nutGoiY.style.pointerEvents = "none";
      nutGoiY.style.background = "#475569";
      nutGoiY.style.color = "#cbd5e1";
    }
  }
  capNhatNhanNutGoiY();

  // Gỡ event cũ (nếu có) bằng cloneNode và gắn event mới
  const freshNutGoiY = nutGoiY.cloneNode(true);
  nutGoiY.parentNode.replaceChild(freshNutGoiY, nutGoiY);
  freshNutGoiY.addEventListener("click", function () {
    if (window.soLuotGoiYConLai <= 0 || daKetNoiThanhCong) return;
    const daGoiY = thucHienGoiY1Buoc();
    if (daGoiY) {
      window.soLuotGoiYConLai--;
      freshNutGoiY.innerText = `💡 Gợi ý (${window.soLuotGoiYConLai}/3)`;
      if (window.soLuotGoiYConLai <= 0) {
        freshNutGoiY.style.opacity = "0.5";
        freshNutGoiY.style.pointerEvents = "none";
        freshNutGoiY.style.background = "#475569";
        freshNutGoiY.style.color = "#cbd5e1";
      }
    }
  });

  if (sessionStorage.getItem("gameMode") === "pvp") {
    freshNutGoiY.style.display = "none";
    freshNutGoiY.style.pointerEvents = "none";
  }
}

/**
 * Thực hiện gợi ý đúng 1 bước tiếp theo trên đường ống chưa hoàn thành (Tối đa 3 lần/trận):
 * - Nếu ô tiếp theo trên đường giải đang bị tráo bởi ống Vàng khác -> Tự động hoán đổi đúng ống Vàng về vị trí đó
 * - Nếu ô tiếp theo đang bị xoay sai góc -> Tự động xoay về đúng góc chuẩn và khóa sáng ô đó
 */
function thucHienGoiY1Buoc() {
  for (const pipe of window.danhSachDuongOng) {
    for (const step of pipe.steps) {
      const cellDiv = document.querySelector(
        `.KhungGame-OVuong[data-row="${step.r}"][data-col="${step.c}"]`
      );
      if (!cellDiv) continue;

      const img = cellDiv.querySelector(".AnhOngNuoc");
      const elXoay = cellDiv.querySelector(".Ong-Wrapper") || img;
      if (!img || !elXoay) continue;

      const currentRot = parseInt(elXoay.dataset.rotation || "0", 10) % 360;
      const expectedRot = parseInt(cellDiv.dataset.correctRotation || String(step.correctRot), 10) % 360;
      const expectedPipe = cellDiv.dataset.correctPipe || "";

      const hasCorrectShape = img.src.includes(`-${step.shape}.svg`);
      const isRotCorrect =
        step.shape === "Thang"
          ? currentRot % 180 === expectedRot % 180
          : currentRot === expectedRot;

      if (!hasCorrectShape || !isRotCorrect) {
        // Nếu là ô ống Vàng bị tráo đổi hình dáng, tìm ô ống Vàng lân cận để đặt lại đúng ống chuẩn
        if (!hasCorrectShape && expectedPipe) {
          const allYellows = Array.from(document.querySelectorAll(".KhungGame-OVuong.co-the-trao-doi"));
          const donor = allYellows.find((other) => {
            if (other === cellDiv || other.classList.contains("da-khoa-goi-y")) return false;
            const otherImg = other.querySelector(".AnhOngNuoc");
            return otherImg && otherImg.src.includes(`-${step.shape}.svg`);
          });
          if (donor) {
            const tmp = cellDiv.innerHTML;
            cellDiv.innerHTML = donor.innerHTML;
            donor.innerHTML = tmp;
          }
        }

        // Xoay về đúng góc chuẩn của đường đi ngắn nhất
        const updatedElXoay = cellDiv.querySelector(".Ong-Wrapper") || cellDiv.querySelector(".AnhOngNuoc");
        if (updatedElXoay) {
          updatedElXoay.style.transform = `rotate(${expectedRot}deg)`;
          updatedElXoay.dataset.rotation = expectedRot;
        }

        // Đánh dấu khóa ô đã gợi ý và phát sáng theo màu của đường ống đó
        cellDiv.classList.add("da-khoa-goi-y");
        cellDiv.style.boxShadow = `0 0 18px 4px ${pipe.color.hex}`;
        cellDiv.style.border = `2px solid ${pipe.color.hex}`;

        if (typeof phatAmThanhXoay === "function") phatAmThanhXoay();
        capNhatLuongNuoc();
        return true;
      }
    }
  }

  alert("💡 Các đoạn ống trong gợi ý đã đúng hướng. Điểm chất lượng dựa trên đường đang nối và chuẩn đã chứng minh.");
  return false;
}

// ============================================================================================================================
// KIỂM TRA ĐƯỜNG ĐI BẰNG BFS, TÍNH ĐIỂM CHUẨN 100Đ & HIỆU ỨNG DÒNG NƯỚC CHẢY
// ============================================================================================================================

function batDauDongHoOngThang(ongWrapper, huongChay = "duoi-len", colorHex = "#01caee") {
  let nuocRects = Array.from(ongWrapper.querySelectorAll(".OngThang_Nuoc"));
  if (nuocRects.length === 0) return;

  nuocRects.forEach((r) => {
    r.style.fill = colorHex;
  });

  if (huongChay && huongChay.includes("tren-xuong")) {
    nuocRects.reverse();
  }

  const TONG_THOI_GIAN_DAY = 520;
  const soMucNuoc = nuocRects.length;
  const thoiGianMoiBuoc = TONG_THOI_GIAN_DAY / soMucNuoc;
  let mucNuocHienTai = 0;

  const nuocInterval = setInterval(() => {
    if (mucNuocHienTai < soMucNuoc) {
      nuocRects[mucNuocHienTai].style.opacity = 1;
      mucNuocHienTai++;
    } else {
      clearInterval(nuocInterval);
    }
  }, thoiGianMoiBuoc);
}

function batDauDongHoChuThapPhanNhanh(ongWrapper, huongDauVao = "duoi-len", colorHex = "#01caee") {
  const allRects = ongWrapper.querySelectorAll("rect");
  allRects.forEach((r) => {
    r.style.fill = colorHex;
  });

  const nhanhDuoi = Array.from(ongWrapper.querySelectorAll(".Nuoc_NhanhDuoi"));
  const nhanhTren = Array.from(ongWrapper.querySelectorAll(".Nuoc_NhanhTren"));
  const nhanhTrai = Array.from(ongWrapper.querySelectorAll(".Nuoc_NhanhTrai"));
  const nhanhPhai = Array.from(ongWrapper.querySelectorAll(".Nuoc_NhanhPhai"));
  const nuocTam = ongWrapper.querySelector(".Nuoc_Tam");

  let dauVao = [];
  let cacDauRa = [];

  switch (huongDauVao) {
    case "duoi-len":
      dauVao = nhanhDuoi;
      cacDauRa = [nhanhTren.reverse(), nhanhTrai.reverse(), nhanhPhai.reverse()];
      break;
    case "tren-xuong":
      dauVao = nhanhTren;
      cacDauRa = [nhanhDuoi.reverse(), nhanhTrai.reverse(), nhanhPhai.reverse()];
      break;
    case "trai-qua":
      dauVao = nhanhTrai;
      cacDauRa = [nhanhPhai.reverse(), nhanhTren.reverse(), nhanhDuoi.reverse()];
      break;
    case "phai-qua":
      dauVao = nhanhPhai;
      cacDauRa = [nhanhTrai.reverse(), nhanhTren.reverse(), nhanhDuoi.reverse()];
      break;
    default:
      return;
  }

  const TONG_THOI_GIAN = 420;
  chayDongNuoc(dauVao, TONG_THOI_GIAN / 2, () => {
    if (nuocTam) nuocTam.style.opacity = 1;
    cacDauRa.forEach((nhomNuoc) => chayDongNuoc(nhomNuoc, TONG_THOI_GIAN / 2));
  });
}

function chayDongNuoc(mangRect, thoiGianTong, callback) {
  if (mangRect.length === 0) {
    if (callback) callback();
    return;
  }
  const thoiGianMoiBuoc = thoiGianTong / mangRect.length;
  let index = 0;
  const interval = setInterval(() => {
    if (index < mangRect.length) {
      mangRect[index].style.opacity = 1;
      index++;
    } else {
      clearInterval(interval);
      if (callback) callback();
    }
  }, thoiGianMoiBuoc);
}

function batDauDongHoOngChuL(ongWrapper, huongChay = "duoi-qua-phai", colorHex = "#01caee") {
  ongWrapper.querySelectorAll(".OngChuL_Nuoc").forEach((r) => {
    r.style.fill = colorHex;
  });

  const rectsCum1 = Array.from(ongWrapper.querySelectorAll("#OngChuL_Cum1 .OngChuL_Nuoc"));
  const rectsCum2 = Array.from(ongWrapper.querySelectorAll("#OngChuL_Cum2 .OngChuL_Nuoc"));
  const rectsCum3 = Array.from(ongWrapper.querySelectorAll("#OngChuL_Cum3 .OngChuL_Nuoc"));
  const rectsCum4 = Array.from(ongWrapper.querySelectorAll("#OngChuL_Cum4 .OngChuL_Nuoc"));

  if (!rectsCum1.length || !rectsCum2.length || !rectsCum3.length || !rectsCum4.length) return;

  const order_DuoiQuaPhai = [...rectsCum1, ...rectsCum2, ...rectsCum4, ...rectsCum3];
  const order_PhaiXuongDuoi = [
    ...[...rectsCum3].reverse(),
    ...[...rectsCum4].reverse(),
    ...[...rectsCum2].reverse(),
    ...[...rectsCum1].reverse(),
  ];

  const rectsToAnimate =
    huongChay && huongChay.includes("phai-xuong-duoi") ? order_PhaiXuongDuoi : order_DuoiQuaPhai;

  const TONG_THOI_GIAN_DAY = 520;
  const soMucNuoc = rectsToAnimate.length;
  const thoiGianMoiBuoc = TONG_THOI_GIAN_DAY / soMucNuoc;
  let mucNuocHienTai = 0;

  const nuocInterval = setInterval(() => {
    if (mucNuocHienTai < soMucNuoc) {
      rectsToAnimate[mucNuocHienTai].style.opacity = 1;
      mucNuocHienTai++;
    } else {
      clearInterval(nuocInterval);
    }
  }, thoiGianMoiBuoc);
}

function resetNuoc() {
  cacTimeoutChoNuoc.forEach((id) => clearTimeout(id));
  cacTimeoutChoNuoc = [];

  const tatCaNuoc = document.querySelectorAll(".Ong-Nuoc rect, .Ong-Nuoc path, .Nuoc_Tam");
  tatCaNuoc.forEach((el) => {
    el.style.opacity = 0;
    el.style.transition = "none";
  });

  void document.body.offsetWidth;

  tatCaNuoc.forEach((el) => {
    el.style.transition = "opacity 0.35s ease-in-out";
  });
}

function layThongTinOngTaiO(div) {
  if (!div) return null;
  const img = div.querySelector(".AnhOngNuoc");
  if (!img || img.src.includes("Tuong.png")) return null;
  const wrapper = div.querySelector(".Ong-Wrapper");
  const phanTuXoay = wrapper || img;
  const gocXoay = parseInt(phanTuXoay.dataset.rotation || "0", 10);

  let loaiOng = "";
  if (img.src.includes("Thang")) loaiOng = "Thang";
  else if (img.src.includes("ChuL")) loaiOng = "ChuL";
  else if (img.src.includes("ChuThap")) loaiOng = "ChuThap";
  else if (img.src.includes("BatDau")) loaiOng = "BatDau";
  else if (img.src.includes("KetThuc")) loaiOng = "KetThuc";
  else return null;

  return {
    loaiOng,
    gocXoay,
    wrapper,
    huongMo: layHuongMoThucTe(loaiOng, gocXoay),
  };
}

/**
 * Duyệt BFS kiểm tra đường đi từ điểm Start (S_k) đến đúng điểm End tương ứng (E_k) của cặp đường ống đó
 */
function kiemTraKetNoiCapDuongOngBFS(pipePair) {
  const startDiv = document.querySelector(
    `.KhungGame-OVuong[data-row="${pipePair.start.r}"][data-col="${pipePair.start.c}"]`
  );
  if (!startDiv) return { connected: false, bfsDistance: 0, totalFlowCells: 0, flowTrace: [] };

  let queue = [{ div: startDiv, r: pipePair.start.r, c: pipePair.start.c, tuHuong: null, dist: 0, delayMs: 0 }];
  let visited = new Set([`${pipePair.start.r}-${pipePair.start.c}`]);
  let flowTrace = [];
  let connectedToCorrectEnd = false;
  let shortestDistFound = 0;

  while (queue.length > 0) {
    const curr = queue.shift();
    const info = layThongTinOngTaiO(curr.div);
    if (!info) continue;

    flowTrace.push({
      div: curr.div,
      wrapper: info.wrapper,
      loaiOng: info.loaiOng,
      gocXoay: info.gocXoay,
      tuHuong: curr.tuHuong,
      delayMs: curr.delayMs,
    });

    if (curr.r === pipePair.end.r && curr.c === pipePair.end.c && info.loaiOng === "KetThuc") {
      connectedToCorrectEnd = true;
      if (shortestDistFound === 0) shortestDistFound = curr.dist;
      continue;
    }

    // Không cho nước chảy xuyên qua điểm Kết thúc của cặp khác
    if (info.loaiOng === "KetThuc" && (curr.r !== pipePair.end.r || curr.c !== pipePair.end.c)) {
      continue;
    }

    const stepAnimMs = THOI_GIAN_ANIMATION[info.loaiOng] || 0;

    for (const huong of info.huongMo) {
      let nr = curr.r + (huong === 0 ? -1 : huong === 2 ? 1 : 0);
      let nc = curr.c + (huong === 1 ? 1 : huong === 3 ? -1 : 0);
      const nKey = `${nr}-${nc}`;
      if (visited.has(nKey)) continue;

      const neighborDiv = document.querySelector(`.KhungGame-OVuong[data-row="${nr}"][data-col="${nc}"]`);
      const neighborInfo = layThongTinOngTaiO(neighborDiv);
      if (!neighborInfo) continue;
      if (["BatDau", "KetThuc"].includes(neighborInfo.loaiOng) &&
          (nr !== pipePair.end.r || nc !== pipePair.end.c)) continue;

      const oppositeDir = (huong + 2) % 4;
      if (neighborInfo.huongMo.includes(oppositeDir)) {
        visited.add(nKey);
        queue.push({
          div: neighborDiv,
          r: nr,
          c: nc,
          tuHuong: oppositeDir,
          dist: curr.dist + 1,
          delayMs: curr.delayMs + stepAnimMs,
        });
      }
    }
  }

  return {
    connected: connectedToCorrectEnd,
    bfsDistance: shortestDistFound,
    totalFlowCells: visited.size,
    flowTrace,
  };
}

function kichHoatHieuUngNuoc(wrapper, loaiOng, tuHuong, gocXoay, colorHex = "#01caee") {
  const soLanXoay = Math.round((((gocXoay % 360) + 360) % 360) / 90) % 4;
  const congVaoSVG = (((tuHuong - soLanXoay) % 4) + 4) % 4;
  let strHuong = "";

  if (loaiOng === "Thang") {
    strHuong = congVaoSVG === 0 ? "tren-xuong" : "duoi-len";
    batDauDongHoOngThang(wrapper, strHuong, colorHex);
  } else if (loaiOng === "ChuThap") {
    strHuong =
      congVaoSVG === 0
        ? "tren-xuong"
        : congVaoSVG === 1
        ? "phai-qua"
        : congVaoSVG === 2
        ? "duoi-len"
        : "trai-qua";
    batDauDongHoChuThapPhanNhanh(wrapper, strHuong, colorHex);
  } else if (loaiOng === "ChuL") {
    strHuong = congVaoSVG === 2 ? "duoi-qua-phai" : "phai-xuong-duoi";
    batDauDongHoOngChuL(wrapper, strHuong, colorHex);
  }
}

/**
 * Hàm điều phối chính:
 * - Kiểm tra từng cặp đường ống (S1->E1 .. SK->EK)
 * - Đo số bước BFS tới đúng đích; điểm chất lượng độc lập với hoàn thành.
 * - PvP chỉ chốt bằng nút nộp bài, cho phép cải thiện sau khi nối đủ.
 */
function capNhatLuongNuoc() {
  if (daKetNoiThanhCong) return;
  resetNuoc();

  const numPairs = window.danhSachDuongOng.length;
  if (numPairs === 0) return;

  const isPvP = sessionStorage.getItem("gameMode") === "pvp";
  let connectedCount = 0;
  const allTraces = [];
  const lengths = [];
  const hudPairsStatus = document.getElementById("hudPairsStatus");
  if (hudPairsStatus) hudPairsStatus.innerHTML = "";
  window.danhSachDuongOng.forEach((pipe) => {
    const res = kiemTraKetNoiCapDuongOngBFS(pipe);
    pipe.isConnected = res.connected;
    pipe.currentLength = res.connected ? res.bfsDistance : 0;
    lengths.push(res.connected ? res.bfsDistance : null);
    if (res.connected) {
      connectedCount++;
      allTraces.push({ colorHex: pipe.color.hex, trace: res.flowTrace });
    }
    if (hudPairsStatus) {
      const badge = document.createElement("span");
      badge.style.cssText = `padding:2px 8px; border:1px solid ${pipe.color.hex}; color:${pipe.color.hex};`;
      badge.textContent = `S${pipe.pairId}→E${pipe.pairId}: ${res.connected ? "✅ " + res.bfsDistance + " bước" : "⏳"}`;
      hudPairsStatus.appendChild(badge);
    }
  });
  const evaluation = isPvP ? (window.danhGiaServer || { quality_score: null, score_status: "server" })
    : PipeScore.score(lengths, window.chuanDiem);
  evaluation.is_all_completed = connectedCount === numPairs;
  window.danhGiaDiem = evaluation;
  window.diemHienTai100 = evaluation.quality_score;
  const elScore = document.getElementById("valCurrentShortestScore");
  if (elScore) {
    elScore.innerText = evaluation.quality_score ?? "—";
    elScore.style.color = evaluation.is_optimal ? "#00e676" : "#fde047";
    elScore.title = evaluation.quality_score === null ? "Chưa xác định điểm chất lượng: " + evaluation.score_status
      : (evaluation.is_optimal ? "Đã chứng minh tối ưu" : "Điểm chất lượng đường đi");
  }
  let status = document.getElementById("qualityScoreStatus");
  if (!status && elScore) {
    status = document.createElement("span"); status.id = "qualityScoreStatus";
    elScore.parentElement.appendChild(status);
  }
  if (status) status.textContent = evaluation.quality_score === null ? " • Chưa xác định điểm chất lượng"
    : (evaluation.is_optimal ? " • Tối ưu" : "");
  if (typeof capNhatGiaoDienDiem === "function") capNhatGiaoDienDiem(evaluation.quality_score ?? "—");
  if (!isPvP && evaluation.score_status === "incomparable_lengths" && !window.daDoiChieuChuan) {
    window.daDoiChieuChuan = true;
    napChuanDiemBanDau(true);
  }
  if (isPvP) {
    window.PvpMatch?.onPlayerProgress({ connectedPairs: connectedCount, totalPairs: numPairs });
    // Connection is editable until the explicit submission (or server deadline).
    return;
  }

  // Completion depends on connectivity, including a legal detour.
  if (connectedCount === numPairs && window.chuanDiem?.status !== "pending" && !daKetNoiThanhCong && !window.isGameOver) {
    daKetNoiThanhCong = true;
    if (typeof tamDungDongHoThoiGian === "function") {
      tamDungDongHoThoiGian();
    }

    const btnDung = document.getElementById("nutDung");
    if (btnDung) {
      btnDung.style.pointerEvents = "none";
      btnDung.style.opacity = "0.7";
    }

    // Chạy hiệu ứng nước chảy tuần tự theo màu sắc riêng của từng đường ống
    let maxFinishMs = 0;
    allTraces.forEach(({ colorHex, trace }) => {
      trace.forEach((step) => {
        if (step.wrapper && step.loaiOng !== "BatDau" && step.loaiOng !== "KetThuc") {
          const tid = setTimeout(() => {
            kichHoatHieuUngNuoc(step.wrapper, step.loaiOng, step.tuHuong, step.gocXoay, colorHex);
          }, step.delayMs);
          cacTimeoutChoNuoc.push(tid);
        }
        const finishAt = step.delayMs + (THOI_GIAN_ANIMATION[step.loaiOng] || 0);
        if (finishAt > maxFinishMs) maxFinishMs = finishAt;
      });
    });

    const timeoutWin = setTimeout(() => {
      // Legacy accumulated rewards use tongDiem; quality remains a separate 0–100 value.
      if (typeof congDiem === "function") congDiem(100);
      if (typeof capNhatGiaoDienDiem === "function") capNhatGiaoDienDiem(window.diemHienTai100 ?? "—");
      if (typeof capNhatThongKeUser === "function") {
        capNhatThongKeUser("thang");
      }
      alert(`Hoàn thành! Điểm chất lượng: ${window.diemHienTai100 ?? "chưa xác định"}${window.diemHienTai100 === null ? "" : "/100"}`);

      // Chế độ 1 Player mặc định
      if (
        typeof amThanhBat !== "undefined" &&
        amThanhBat &&
        typeof audioChienThang !== "undefined"
      ) {
        audioChienThang.currentTime = 0;
        audioChienThang.play();
        audioChienThang.onended = function () {
          location.reload();
        };
      } else {
        setTimeout(() => {
          location.reload();
        }, 600);
      }
    }, maxFinishMs + 500);

    cacTimeoutChoNuoc.push(timeoutWin);
  }
}
// One baseline for the initial map. The endpoint shares the exact PHP proof engine
// with PvP; no dependency on the dormant AI solutionPath flow.
async function napChuanDiemBanDau(recheck = false) {
  window.chuanDiem = { status: "pending" };
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 8000);
  try {
    const response = await fetch("/api/game/scoring-baseline", {
      method: "POST", headers: { "Content-Type": "application/json" }, signal: controller.signal,
      body: JSON.stringify({ ...window.banDoBanDau, _recheck: recheck }),
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error("Không có chuẩn điểm");
    window.chuanDiem = result.data;
  } catch (error) {
    window.chuanDiem = { status: "unavailable" };
  } finally {
    clearTimeout(timer);
    capNhatLuongNuoc();
  }
}
