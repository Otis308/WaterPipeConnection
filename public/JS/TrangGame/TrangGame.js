// LOGIC VÀ ANIMATION ỐNG THỜI GIAN

let thoiGianInterval;
let mucNuocThoiGianHienTai = 0;
let nuocRectsThoiGian = [];
let mocBatDauManChoi = performance.now();
let tongThoiGianTamDung = 0;
let mocBatDauPause = 0;


function khoiTaoDongHoThoiGian() {
  nuocRectsThoiGian = document.querySelectorAll(".OngThoiGian_Nuoc");

  if (nuocRectsThoiGian.length === 0) {
    console.warn("Không tìm thấy các phần tử .OngThoiGian_Nuoc");
    return;
  }

  chayDongHoThoiGian();
}

function layTongThoiGianCotNuoc() {
  const numPairs = Math.min(4, Math.max(1, parseInt(sessionStorage.getItem("numPairs") || "2", 10)));
  const difficulty = sessionStorage.getItem("difficulty") || "easy";
  const basePerPairMs = 30000; // 30 giây cho mỗi cặp điểm bắt đầu - kết thúc
  let totalMs = Math.max(45000, numPairs * basePerPairMs);

  // Cộng thêm thời gian hỗ trợ nếu bản đồ kích thước lớn ở chế độ Trung bình / Khó
  if (difficulty === "medium") totalMs += 5000;
  if (difficulty === "hard") totalMs += 10000;
  return totalMs;
}

// Hàm lấy thời gian hoàn thành thực tế của người chơi (completion_time tính bằng ms)
window.getPlayerCompletionTimeMs = function () {
  const elapsed = performance.now() - mocBatDauManChoi - tongThoiGianTamDung;
  return Math.max(1000, Math.round(elapsed));
};

function khoiTaoDongHoThoiGian() {
  nuocRectsThoiGian = document.querySelectorAll(".OngThoiGian_Nuoc");
  mocBatDauManChoi = performance.now();
  tongThoiGianTamDung = 0;

  if (nuocRectsThoiGian.length === 0) {
    console.warn("Không tìm thấy các phần tử .OngThoiGian_Nuoc");
    return;
  }

  chayDongHoThoiGian();
}

function chayDongHoThoiGian() {
  if (mucNuocThoiGianHienTai >= nuocRectsThoiGian.length) return;

  const TONG_THOI_GIAN_DAY = layTongThoiGianCotNuoc();
  const soMucNuoc = nuocRectsThoiGian.length;
  if (soMucNuoc === 0) return;

  const thoiGianMoiBuoc = TONG_THOI_GIAN_DAY / soMucNuoc;

  clearInterval(thoiGianInterval);

  thoiGianInterval = setInterval(() => {
    if (mucNuocThoiGianHienTai < soMucNuoc) {
      nuocRectsThoiGian[mucNuocThoiGianHienTai].style.opacity = 1;
      mucNuocThoiGianHienTai++;
    } else {
      clearInterval(thoiGianInterval);
      console.log("Ống thời gian đã đầy!");
      xuLyThuaGame();
    }
  }, thoiGianMoiBuoc);
}

function chayDongHoThoiGian() {
  if (mucNuocThoiGianHienTai >= nuocRectsThoiGian.length) return;

  // Thời gian dòng nước dâng
  const TONG_THOI_GIAN_DAY = 60000;
  const soMucNuoc = nuocRectsThoiGian.length;

  if (soMucNuoc === 0) return;

  const thoiGianMoiBuoc = TONG_THOI_GIAN_DAY / soMucNuoc;

  clearInterval(thoiGianInterval);

  thoiGianInterval = setInterval(() => {
    if (mucNuocThoiGianHienTai < soMucNuoc) {
      nuocRectsThoiGian[mucNuocThoiGianHienTai].style.opacity = 1;
      mucNuocThoiGianHienTai++;
    } else {
      clearInterval(thoiGianInterval);
      console.log("Ống thời gian đã đầy!");
      xuLyThuaGame();
    }
  }, thoiGianMoiBuoc);
}

// Hàm tạm dừng ống thời gian
function tamDungDongHoThoiGian() {
  clearInterval(thoiGianInterval);
  mocBatDauPause = performance.now();
}

// Hàm khởi động lại ống thời gian
function khoiDongLaiDongHo() {
  clearInterval(thoiGianInterval);
  mucNuocThoiGianHienTai = 0;
  mocBatDauManChoi = performance.now();
  tongThoiGianTamDung = 0;
  if (nuocRectsThoiGian && nuocRectsThoiGian.length > 0) {
    nuocRectsThoiGian.forEach((rect) => {
      rect.style.opacity = 0;
    });
  }
  chayDongHoThoiGian();
}

// ============================================================================================================================
// ẨN/HIỆN BẢNG DỪNG & XỬ LÝ THỜI GIAN

window.isGamePaused = false;
window.isGameOver = false;
const nutDung = document.getElementById("nutDung");
const dung_NutThoat = document.getElementById("dung_NutThoat");
const khungDung = document.getElementById("khungDung");
const lopPhu_BangDung = document.getElementById("lopPhu_BangDung");

// Hàm để mở bảng dừng (PAUSE GAME)
function moDung() {
  khungDung.style.display = "block";
  lopPhu_BangDung.style.display = "block";

  tamDungDongHoThoiGian();

  window.isGamePaused = true;
  console.log("Game Paused: AI sẽ đợi...");
}

// Hàm để đóng bảng dừng (RESUME GAME)
function dongDung() {
  khungDung.style.display = "none";
  lopPhu_BangDung.style.display = "none";

  chayDongHoThoiGian();

  window.isGamePaused = false;
  console.log("Game Resumed: AI chạy tiếp...");
}

// Gán sự kiện click
nutDung.addEventListener("click", moDung);
dung_NutThoat.addEventListener("click", dongDung);

// ----------------------------------------------------------------------------------------------------------------------------
// BẬT/TẮT ÂM THANH VÀ NHẠC

const audioNhacNen = document.getElementById("nhacNen");
const audioClick = document.getElementById("amThanhClick");
const audioXoayOng = document.getElementById("amThanhXoay");
const audioThua = document.getElementById("amThanhThua");
const audioChienThang = document.getElementById("amThanhChienThang");
const audioDoiOng = document.getElementById("amThanhDoiOng");

const nutTieng = document.getElementById("nutTieng");
const imgTieng = document.getElementById("imgTieng");
const nutNhac = document.getElementById("nutNhac");
const imgNhac = document.getElementById("imgNhac");

const imgMoTieng = "Images/svg/Nut/MoTieng.svg";
const imgTatTieng = "Images/svg/Nut/TatTieng.svg";
const imgMoNhac = "Images/svg/Nut/MoNhac.svg";
const imgTatNhac = "Images/svg/Nut/TatNhac.svg";

// Đọc và so sánh với SessionStorage khi tải trang
let amThanhBat = sessionStorage.getItem("amThanhBat") !== "false";
let nhacBat = sessionStorage.getItem("nhacBat") !== "false";

// Hàm phát âm thanh click
function phatAmThanhClick() {
  if (amThanhBat) {
    audioClick.currentTime = 0;
    audioClick.play();
  }
}

// Hàm phát âm thanh xoay ống
function phatAmThanhXoay() {
  if (amThanhBat) {
    audioXoayOng.currentTime = 0;
    audioXoayOng.play();
  }
}

// Hàm phát âm thanh đổi ống
function phatAmThanhDoiOng() {
  if (amThanhBat) {
    audioDoiOng.currentTime = 0;
    audioDoiOng.play();
  }
}

// Hàm cập nhật ảnh của nút
function capNhatUI_Tieng() {
  if (amThanhBat) {
    imgTieng.src = imgMoTieng;
  } else {
    imgTieng.src = imgTatTieng;
  }
}
function capNhatUI_Nhac() {
  if (nhacBat) {
    imgNhac.src = imgMoNhac;

    let playPromise = audioNhacNen.play();
    if (playPromise !== undefined) {
      playPromise.catch((error) => {
        console.log("Trình duyệt đã chặn tự động phát nhạc.");
      });
    }
  } else {
    imgNhac.src = imgTatNhac;
    audioNhacNen.pause();
  }
}

// Gán sự kiện click cho nút tiếng
nutTieng.addEventListener("click", () => {
  amThanhBat = !amThanhBat;
  capNhatUI_Tieng();
  sessionStorage.setItem("amThanhBat", amThanhBat);

  if (amThanhBat) {
    phatAmThanhClick();
  }
});

// Gán sự kiện click cho nút nhạc
nutNhac.addEventListener("click", () => {
  nhacBat = !nhacBat;
  capNhatUI_Nhac();
  sessionStorage.setItem("nhacBat", nhacBat);
  phatAmThanhClick();
});

capNhatUI_Tieng();
capNhatUI_Nhac();

// Bộ lắng nghe sự kiện click toàn trang
document.body.addEventListener("click", function (event) {
  const nutDuocClick = event.target.closest(".am-thanh-click");
  if (nutDuocClick) {
    phatAmThanhClick();
  }
});

// ============================================================================================================================
// ẨN/HIỆN BẢNG ĐẦU HÀNG

const nutDauHang = document.getElementById("nutDauHang");
const dauHang_NutThoat = document.getElementById("dauHang_NutThoat");
const khungDauHang = document.getElementById("khungDauHang");
const lopPhu_BangDauHang = document.getElementById("lopPhu_BangDauHang");

// Hàm để mở bảng đầu hàng (PAUSE GAME)
function moDauHang() {
  khungDauHang.style.display = "block";
  lopPhu_BangDauHang.style.display = "block";

  tamDungDongHoThoiGian();
}

// Hàm để đóng bảng đầu hàng (RESUME GAME)
function dongDauHang() {
  khungDauHang.style.display = "none";
  lopPhu_BangDauHang.style.display = "none";

  chayDongHoThoiGian();
}

// Gán sự kiện click
nutDauHang.addEventListener("click", moDauHang);
dauHang_NutThoat.addEventListener("click", dongDauHang);

// ----------------------------------------------------------------------------------------------------------------------------
// XỬ LÝ THUA GAME & NÚT NO (ĐẦU HÀNG)

// Hàm xử lý chung khi thua
function xuLyThuaGame() {
  if (sessionStorage.getItem("gameMode") === "pvp") {
    window.PvpMatch?.onTimeout();
    return;
  }
  window.isGameOver = true;
  capNhatThongKeUser("thua");
  console.log("Game Over!");
  const diemHienTai = window.danhGiaDiem?.quality_score ?? "—";
  let diemCaoNhat = 0;
  const userHienTai = localStorage.getItem("userHienTai");

  // Phân loại trường hợp đã đăng nhập hay chưa để lấy điểm
  if (userHienTai) {
    const danhSachUser = JSON.parse(localStorage.getItem("danhSachUser")) || [];
    const user = danhSachUser.find((u) => u.username === userHienTai);
    diemCaoNhat = user ? user.highScore || 0 : 0;
  } else {
    diemCaoNhat = parseInt(localStorage.getItem("diemCaoNhat") || "0");
  }

  // Cập nhật giao diện
  if (typeof capNhatGiaoDienDiem === "function") {
    capNhatGiaoDienDiem(diemHienTai, diemCaoNhat);
  }

  // Dừng ống thời gian
  if (typeof tamDungDongHoThoiGian === "function") {
    tamDungDongHoThoiGian();
  }

  // Ẩn bảng đầu hàng (nếu đang mở)
  const khungDauHang = document.getElementById("khungDauHang");
  const lopPhu_BangDauHang = document.getElementById("lopPhu_BangDauHang");
  if (khungDauHang) khungDauHang.style.display = "none";
  if (lopPhu_BangDauHang) lopPhu_BangDauHang.style.display = "none";

  // Phát âm thanh thua
  if (
    typeof amThanhBat !== "undefined" &&
    amThanhBat &&
    typeof audioThua !== "undefined"
  ) {
    audioThua.currentTime = 0;
    audioThua.play();
  }

  // Hiện bảng kết thúc
  const khungKetThuc = document.getElementById("khungKetThuc");
  const lopPhu_BangKetThuc = document.getElementById("lopPhu_BangKetThuc");
  if (khungKetThuc) khungKetThuc.style.display = "block";
  if (lopPhu_BangKetThuc) lopPhu_BangKetThuc.style.display = "block";
  const gameMode = sessionStorage.getItem("gameMode");

  // Ẩn/Hiện bảng thông tin chế độ AI
  if (gameMode === "ai") {
    khungKetThuc.classList.add("CheDoAI");

    // Cập nhật thông tin bảng kết thúc
    if (typeof aiStats !== "undefined") {
      const elStatus = document.getElementById("thongTinAI_Status");
      if (aiStats.status === "Solution Found!") {
        elStatus.innerText = "Success!";
        elStatus.style.color = "#00ff00";
      } else {
        elStatus.innerText = "Failed!";
        elStatus.style.color = "#fffb00ff";
      }

      // Hiển thị thời gian thực tế đã tính toán của AI
      let thoiGianHienThi = 0;
      if (typeof aiThoiGianChayThuc !== "undefined") {
        thoiGianHienThi = aiThoiGianChayThuc / 1000;
      }
      document.getElementById("thongTinAI_Time").innerText =
        thoiGianHienThi.toFixed(3) + "s";

      // Lấy độ dài chuỗi hiện tại
      if (
        typeof aiStats !== "undefined" &&
        typeof aiStats.maxConnectedLength !== "undefined"
      ) {
        document.getElementById("thongTinAI_Length").innerText =
          aiStats.maxConnectedLength;
      } else {
        if (typeof tinhTongSoOngKetNoi === "function") {
          document.getElementById("thongTinAI_Length").innerText =
            tinhTongSoOngKetNoi();
        } else {
          document.getElementById("thongTinAI_Length").innerText = "0";
        }
      }

      document.getElementById("thongTinAI_Score").innerText = aiStats.highScore;

      document.getElementById("thongTinAI_Retry").innerText =
        aiStats.retryCount;
    }
  } else {
    khungKetThuc.classList.remove("CheDoAI");
  }
}

// Gán sự kiện cho nút Yes/No trong bảng
document.addEventListener("DOMContentLoaded", function () {
  // Gán sự kiện cho nút No
  const btnNo = document.getElementById("dauHang_No");
  if (btnNo) {
    btnNo.addEventListener("click", function () {
      xuLyThuaGame();
    });
  }

  // Gán sự kiện cho nút Yes
  const btnYes = document.querySelector(".DauHang-Yes");
  if (btnYes) {
    btnYes.addEventListener("click", function () {
      // Kiểm tra độ khó
      const doKhoHienTai = sessionStorage.getItem("difficulty") || "easy";
      if (
        doKhoHienTai !== "easy" &&
        doKhoHienTai !== "medium" &&
        doKhoHienTai !== "hard"
      ) {
        console.log(
          "Chức năng đầu hàng chưa hỗ trợ độ khó lạ: " + doKhoHienTai
        );
        return;
      }

      console.log("Người chơi đầu hàng. Kích hoạt AI giải hộ...");

      // Đóng bảng đầu hàng
      dongDauHang();

      // Vô hiệu hóa nút Đầu hàng
      const nutDauHang = document.getElementById("nutDauHang");
      if (nutDauHang) {
        nutDauHang.style.pointerEvents = "none";
        nutDauHang.style.opacity = "0.7";
      }

      // Bật cờ chặn click của người chơi và tắt con trỏ
      window.dangChayCheDoDauHang = true;
      const khungGame = document.querySelector(".KhungGame");
      if (khungGame) khungGame.classList.add("dang-chay-dau-hang");

      // Reset và chạy lại đồng hồ thời gian từ đầu
      khoiDongLaiDongHo();

      // Gọi hàm AI chạy chế độ Easy
      if (typeof kichHoatAI_DauHang === "function") {
        kichHoatAI_DauHang();
      }
    });
  }
});

// ============================================================================================================================
// ẨN/HIỆN BẢNG CHI TIẾT

const nutChiTiet = document.getElementById("nutChiTiet");
const khungChiTiet = document.getElementById("khungChiTiet");

// Gán sự kiện click để bật/tắt bảng chi tiết
nutChiTiet.addEventListener("click", () => {
  if (khungChiTiet.style.display === "block") {
    khungChiTiet.style.display = "none";
  } else {
    khungChiTiet.style.display = "block";
  }
});

// ============================================================================================================================
// XỬ LÝ GIAO DIỆN THEO CHẾ ĐỘ GAME (PLAYER / AI)

document.addEventListener("DOMContentLoaded", function () {
  const gameMode = sessionStorage.getItem("gameMode");
  const nutChiTiet = document.getElementById("nutChiTiet");
  const nutDauHang = document.getElementById("nutDauHang");

  if (gameMode === "player") {
    // Chế độ Player: Ẩn nút Chi tiết
    if (nutChiTiet) {
      nutChiTiet.style.display = "none";
    }
  } else if (gameMode === "ai") {
    // Chế độ AI: Ẩn nút Đầu hàng
    if (nutDauHang) {
      nutDauHang.style.display = "none";
    }
  }
});

// ============================================================================================================================
// QUẢN LÝ ĐIỂM SỐ

// Hàm cập nhật giao diện điểm số
function capNhatGiaoDienDiem(diem, diemCaoNhat) {
  const divOngSao = document.querySelector(".OngSao-Sao");
  const divKetThucSao = document.querySelector(".KetThuc-Sao");
  const divKetThucCupSao = document.querySelector(".KetThuc-CupSao");

  if (window.danhGiaDiem) {
    if (divOngSao) divOngSao.title = "Điểm chất lượng bàn (0–100)";
    if (divKetThucSao) divKetThucSao.title = "Điểm chất lượng bàn (0–100)";
    if (divKetThucCupSao) divKetThucCupSao.title = "Kỷ lục điểm thưởng tích lũy";
  }

  // Cập nhật số sao hiện tại
  const htmlSao = `<img src="Images/Sao.svg" /> ${diem}`;
  if (divOngSao) divOngSao.innerHTML = htmlSao;
  if (divKetThucSao) divKetThucSao.innerHTML = htmlSao;

  // Cập nhật số sao cao nhất
  if (diemCaoNhat !== undefined && divKetThucCupSao) {
    divKetThucCupSao.innerHTML = `<img src="Images/CupSao.png" /> ${diemCaoNhat}`;
  }
}

// Hàm cộng điểm
function congDiem(diemCong) {
  const gameMode = sessionStorage.getItem("gameMode");

  // Lấy điểm hiện tại của lượt chơi này
  let diemHienTai = parseInt(localStorage.getItem("tongDiem") || "0");

  // Cộng điểm mới
  let diemMoi = diemHienTai + diemCong;
  localStorage.setItem("tongDiem", diemMoi);

  // Biến để hiển thị ra giao diện
  let diemCaoHienThi = 0;

  // Lưu điểm ở chế độ Player, còn AI thì không lưu
  if (gameMode === "player") {
    const userHienTai = localStorage.getItem("userHienTai");

    // Phân loại trường hợp đã đăng nhập và chưa đăng nhập
    if (userHienTai) {
      let danhSachUser = JSON.parse(localStorage.getItem("danhSachUser")) || [];
      const index = danhSachUser.findIndex((u) => u.username === userHienTai);

      if (index !== -1) {
        let kyLucCu = danhSachUser[index].highScore || 0;

        if (diemMoi > kyLucCu) {
          danhSachUser[index].highScore = diemMoi;
          localStorage.setItem("danhSachUser", JSON.stringify(danhSachUser));
          kyLucCu = diemMoi;
          console.log(`Kỷ lục mới cho ${userHienTai}: ${diemMoi}`);
        }
        diemCaoHienThi = kyLucCu;
      }
    } else {
      let diemCaoCu = parseInt(localStorage.getItem("diemCaoNhat") || "0");

      if (diemMoi > diemCaoCu) {
        localStorage.setItem("diemCaoNhat", diemMoi);
        diemCaoCu = diemMoi;
        console.log(`Kỷ lục mới cho Khách: ${diemMoi}`);
      }
      diemCaoHienThi = diemCaoCu;
    }
  } else {
    console.log("Chế độ AI: Không cập nhật điểm cao nhất.");
  }

  // Cập nhật giao diện
  capNhatGiaoDienDiem(diemMoi, diemCaoHienThi);
  console.log(`Đã cộng ${diemCong} điểm. Tổng: ${diemMoi}.`);
}

// Khi tải trang lấy điểm cũ và điểm cao nhất hiển thị lên
document.addEventListener("DOMContentLoaded", function () {
  const gameMode = sessionStorage.getItem("gameMode");

  // Điểm vừa đạt được ở màn trước
  let diemHienTai = parseInt(localStorage.getItem("tongDiem") || "0");

  // Biến để quyết định số nào sẽ hiện lên ô High Score
  let diemCaoHienThi = 0;

  // Kiểm tra chế độ Player hay AI để cập nhật điểm cao nhất
  if (gameMode === "player") {
    const userHienTai = localStorage.getItem("userHienTai");

    // Phân loại trường hợp đã đăng nhập và chưa đăng nhập để lưu điểm
    if (userHienTai) {
      let danhSachUser = JSON.parse(localStorage.getItem("danhSachUser")) || [];
      const index = danhSachUser.findIndex((u) => u.username === userHienTai);

      if (index !== -1) {
        let kyLucUser = danhSachUser[index].highScore || 0;

        // Kiểm tra nếu điểm hiện tại cao hơn kỷ lục riêng của User
        if (diemHienTai > kyLucUser) {
          kyLucUser = diemHienTai;
          // Cập nhật lại vào mảng user
          danhSachUser[index].highScore = kyLucUser;
          localStorage.setItem("danhSachUser", JSON.stringify(danhSachUser));
        }
        diemCaoHienThi = kyLucUser;
      }
    } else {
      let kyLucMay = parseInt(localStorage.getItem("diemCaoNhat") || "0");

      // Kiểm tra nếu điểm hiện tại cao hơn kỷ lục của Máy
      if (diemHienTai > kyLucMay) {
        kyLucMay = diemHienTai;
        // Cập nhật biến chung của máy
        localStorage.setItem("diemCaoNhat", kyLucMay);
      }
      diemCaoHienThi = kyLucMay;
    }
  } else {
    diemCaoHienThi = 0;
  }

  // Hiển thị ra giao diện
  capNhatGiaoDienDiem(diemHienTai, diemCaoHienThi);
});

// ----------------------------------------------------------------------------------------------------------------------------
// XỬ LÝ NÚT TRANG CHỦ & TẢI LẠI TRANG (RESET ĐIỂM)

document.addEventListener("DOMContentLoaded", function () {
  const homeDung = document.querySelector(".Dung-NutTrangChu");
  const reloadDung = document.querySelector(".Dung-NutTaiLai");
  const homeKetThuc = document.querySelector(".KetThuc-NutTrangChu");
  const reloadKetThuc = document.querySelector(".KetThuc-NutChoiLai");

  // Hàm reset điểm về 0
  function resetDiemVeZero() {
    localStorage.setItem("tongDiem", "0");
    console.log("Đã reset điểm về 0!");
  }

  // Logic nút trang chủ ở bảng dừng
  if (homeDung) {
    homeDung.addEventListener("click", function (event) {
      const khungDung = document.getElementById("khungDung");
      const lopPhu_BangDung = document.getElementById("lopPhu_BangDung");
      if (khungDung) khungDung.style.display = "none";
      if (lopPhu_BangDung) lopPhu_BangDung.style.display = "none";
      if (typeof xuLyThuaGame === "function") {
        xuLyThuaGame();
      }
    });
  }

  // Các nút còn lại giữ nguyên logic reset điểm
  if (reloadDung) {
    reloadDung.addEventListener("click", resetDiemVeZero);
  }
  if (homeKetThuc) {
    homeKetThuc.addEventListener("click", resetDiemVeZero);
  }
  if (reloadKetThuc) {
    reloadKetThuc.addEventListener("click", resetDiemVeZero);
  }
});

// ============================================================================================================================
// HÀM CẬP NHẬT THỐNG KÊ THẮNG/THUA CHO USER (CHẾ ĐỘ PLAYER)

function capNhatThongKeUser(ketQua) {
  const gameMode = sessionStorage.getItem("gameMode");
  const userHienTai = localStorage.getItem("userHienTai");

  // Chỉ tính khi ở chế độ Player và đã đăng nhập
  if (gameMode === "player" && userHienTai) {
    let danhSachUser = JSON.parse(localStorage.getItem("danhSachUser")) || [];
    const index = danhSachUser.findIndex((u) => u.username === userHienTai);

    if (index !== -1) {
      // Tăng tổng số ván chơi
      danhSachUser[index].totalGames =
        (danhSachUser[index].totalGames || 0) + 1;

      // Nếu thắng thì tăng số trận thắng
      if (ketQua === "thang") {
        danhSachUser[index].totalWins =
          (danhSachUser[index].totalWins || 0) + 1;
      }

      // Lưu lại vào LocalStorage
      localStorage.setItem("danhSachUser", JSON.stringify(danhSachUser));
    }
  }
}

// ============================================================================================================================


// ============================================================================================================================
// ĐỒNG BỘ KẾT QUẢ MÀN CHƠI & TELEMETRY AI LEO ĐỒI LÊN CSDL MYSQL QUA FETCH API (AJAX POST JSON)
// ============================================================================================================================
window.guiKetQuaManChoiLenServer = function (ketQua) {
  const userHienTai = localStorage.getItem("userHienTai") || "";
  const gameMode = sessionStorage.getItem("gameMode") || "player";
  if (gameMode === "pvp") return;
  const mapOption = sessionStorage.getItem("mapOption") || "random";
  const mapSize = sessionStorage.getItem("mapSize") || "3x8";
  const difficulty = sessionStorage.getItem("difficulty") || "easy";
  const tongDiem = parseInt(localStorage.getItem("tongDiem") || "0", 10);

  let connectedPipes = (window.danhSachDuongOng || []).filter(p => p.isConnected).length;
  if (!window.danhSachDuongOng && typeof tinhTongSoOngKetNoi === "function") {
    connectedPipes = tinhTongSoOngKetNoi();
  }

  const stepsCount = typeof aiStats !== "undefined" ? (aiStats.stepCount || 0) : 0;
  const aiRetries = typeof aiStats !== "undefined" ? (aiStats.retryCount || 0) : 0;
  const durationMs = typeof window.getPlayerCompletionTimeMs === "function"
      ? window.getPlayerCompletionTimeMs()
      : 15000;

  const payload = {
    username: userHienTai,
    game_mode: gameMode,
    map_option: mapOption,
    map_size: mapSize,
    difficulty: difficulty,
    result: ketQua === "thang" ? "win" : "lose",
    score_earned: window.danhGiaDiem?.quality_score ?? 0,
    scoring_version: "joint-paths-v1",
    quality_score: window.danhGiaDiem?.quality_score ?? null,
    total_score: tongDiem,
    connected_pipes: connectedPipes,
    steps_count: stepsCount,
    ai_retries: aiRetries,
    duration_ms: durationMs,
    telemetry_nosql: {
      scoring_version: "joint-paths-v1",
      quality_score: window.danhGiaDiem?.quality_score ?? null,
      score_status: window.danhGiaDiem?.score_status || "unavailable",
      is_optimal: window.danhGiaDiem?.is_optimal === true,
      reward_points: ketQua === "thang" ? 100 : 0,
      aiStatus: typeof aiStats !== "undefined" ? aiStats.status : "player_mode",
      surrenderMode: Boolean(window.dangChayCheDoDauHang),
      timestamp: new Date().toISOString()
    }
  };

  fetch("/api/game/save-session", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": window.HUIT_CSRF_TOKEN || ""
    },
    body: JSON.stringify(payload)
  })
    .then((res) => res.json())
    .then((data) => {
      console.log("[AJAX PHP Sync] Đã lưu phiên chơi vào MySQL:", data);
    })
    .catch((err) => console.warn("[AJAX PHP Sync] Offline mode:", err));
};

// Hook vào hàm capNhatThongKeUser gốc để tự động gửi AJAX mỗi khi thắng hoặc thua
if (typeof capNhatThongKeUser === "function") {
  const _origCapNhatThongKeUser = capNhatThongKeUser;
  capNhatThongKeUser = function (ketQua) {
    _origCapNhatThongKeUser(ketQua);
    if (typeof window.guiKetQuaManChoiLenServer === "function") {
      window.guiKetQuaManChoiLenServer(ketQua);
    }
  };
}
