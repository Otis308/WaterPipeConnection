// ẨN/HIỆN BẢNG PLAY
const nutPlay = document.getElementById("nutPlay");
const play_NutThoat = document.getElementById("play-NutThoat");
const khungPlay = document.getElementById("khungPlay");
const lopPhu_BangPlay = document.getElementById("lopPhu_BangPlay");

function moPlay() {
  khungPlay.style.display = "block";
  lopPhu_BangPlay.style.display = "block";
}

function dongPlay() {
  khungPlay.style.display = "none";
  lopPhu_BangPlay.style.display = "none";
}

nutPlay.addEventListener("click", moPlay);
play_NutThoat.addEventListener("click", dongPlay);

// ----------------------------------------------------------------------------------------------------------------------------
// CHẾ ĐỘ MỚI: 1 PLAYER (ĐA CẶP ĐẦU-CUỐI) VS 2-4P PVP (TẠO PHÒNG / RANDOM ĐỐI THỦ)
const cheDoPlayer = document.getElementById("cheDoPlayer");
const cheDoPvP = document.getElementById("cheDoPvP");
const panel1PlayerConfig = document.getElementById("panel1PlayerConfig");
const panelPvPConfig = document.getElementById("panelPvPConfig");

const pvpTypeRandom = document.getElementById("pvpTypeRandom");
const pvpTypeRoom = document.getElementById("pvpTypeRoom");
const pvpRandomBox = document.getElementById("pvpRandomBox");
const pvpRoomBox = document.getElementById("pvpRoomBox");
const btnCreateRoomCode = document.getElementById("btnCreateRoomCode");
const pvpRoomCodeInput = document.getElementById("pvpRoomCodeInput");

if (cheDoPlayer && cheDoPvP) {
  cheDoPlayer.addEventListener("click", () => {
    cheDoPlayer.classList.add("active");
    cheDoPvP.classList.remove("active");
    if (panel1PlayerConfig) panel1PlayerConfig.style.display = "block";
    if (panelPvPConfig) panelPvPConfig.style.display = "none";
  });

  cheDoPvP.addEventListener("click", () => {
    cheDoPvP.classList.add("active");
    cheDoPlayer.classList.remove("active");
    if (panel1PlayerConfig) panel1PlayerConfig.style.display = "none";
    if (panelPvPConfig) panelPvPConfig.style.display = "block";
  });
}

if (pvpTypeRandom && pvpTypeRoom) {
  pvpTypeRandom.addEventListener("change", () => {
    if (pvpTypeRandom.checked) {
      pvpRandomBox.style.display = "block";
      pvpRoomBox.style.display = "none";
    }
  });
  pvpTypeRoom.addEventListener("change", () => {
    if (pvpTypeRoom.checked) {
      pvpRandomBox.style.display = "none";
      pvpRoomBox.style.display = "block";
    }
  });
}

if (btnCreateRoomCode && pvpRoomCodeInput) {
  btnCreateRoomCode.addEventListener("click", () => {
    const randomCode = "HUIT" + Math.floor(10 + Math.random() * 90);
    pvpRoomCodeInput.value = randomCode;
  });
}

// ----------------------------------------------------------------------------------------------------------------------------
// BẢN ĐỒ
const banDo_Random = document.getElementById("banDo_Random");
const banDo_Custom = document.getElementById("banDo_Custom");
const dropdownButton = document.getElementById("dropdownMapSize");
const dropdownItems = document.querySelectorAll("#customMapDropdown .dropdown-item");

banDo_Random.addEventListener("click", () => {
  dropdownButton.disabled = true;
});

banDo_Custom.addEventListener("click", () => {
  dropdownButton.disabled = false;
});

dropdownButton.disabled = true;

dropdownItems.forEach((item) => {
  item.addEventListener("click", (e) => {
    e.preventDefault();
    dropdownButton.textContent = e.target.textContent.trim();
  });
});

// ----------------------------------------------------------------------------------------------------------------------------
// XỬ LÝ NÚT BẮT ĐẦU VÀO GAME
const play_NutBatDau = document.getElementById("play-NutBatDau");

play_NutBatDau.addEventListener("click", (event) => {
  const doKho_Easy = document.getElementById("doKho_Easy");
  const doKho_Medium = document.getElementById("doKho_Medium");
  const doKho_Hard = document.getElementById("doKho_Hard");

  const isSinglePlayer = cheDoPlayer && cheDoPlayer.classList.contains("active");
  const isPvP = cheDoPvP && cheDoPvP.classList.contains("active");
  const banDoDaChon = banDo_Random.checked || banDo_Custom.checked;
  const doKhoDaChon = doKho_Easy.checked || doKho_Medium.checked || doKho_Hard.checked;

  if ((!isSinglePlayer && !isPvP) || !banDoDaChon || !doKhoDaChon) {
    event.preventDefault();
    alert("Vui lòng chọn đầy đủ Chế độ chơi, Bản đồ và Độ khó!");
    return;
  }

  // Lấy số lượng cặp điểm Bắt đầu - Kết thúc (2, 3 hoặc 4 cặp)
  const pairRadio = document.querySelector('input[name="soCapDauCuoi"]:checked');
  const numPairs = pairRadio ? parseInt(pairRadio.value, 10) : 2;
  sessionStorage.setItem("numPairs", String(numPairs));

  if (isSinglePlayer) {
    sessionStorage.setItem("gameMode", "player");
  } else {
    // Chặn chuyển trang trực tiếp — PvP bắt buộc phải qua phòng chờ hoặc hàng đợi ghép trận
    event.preventDefault();
    if (window.PvpLobby && typeof window.PvpLobby.handleStartPvP === "function") {
      window.PvpLobby.handleStartPvP();
    }
    return;
  }

  // Lưu Bản đồ (Đảm bảo lưới đủ rộng cho nhiều cặp ống)
  if (banDo_Random.checked) {
    sessionStorage.setItem("mapOption", "random");
    sessionStorage.setItem("mapSize", "5x10");
  } else {
    sessionStorage.setItem("mapOption", "custom");
    sessionStorage.setItem("mapSize", dropdownButton.textContent.trim());
  }

  // Lưu Độ khó
  if (doKho_Easy.checked) sessionStorage.setItem("difficulty", "easy");
  else if (doKho_Medium.checked) sessionStorage.setItem("difficulty", "medium");
  else sessionStorage.setItem("difficulty", "hard");

  localStorage.setItem("tongDiem", "0");
});
// ============================================================================================================================
// ẨN/HIỆN BẢNG CÀI ĐẶT

const nutCaiDat = document.getElementById("nutCaiDat");
const caiDat_NutThoat = document.getElementById("caiDat_NutThoat");
const khungCaiDat = document.getElementById("khungCaiDat");
const lopPhu_BangCaiDat = document.getElementById("lopPhu_BangCaiDat");

// Hàm để mở bảng cài đặt
function moCaiDat() {
  khungCaiDat.style.display = "block";
  lopPhu_BangCaiDat.style.display = "block";
}

// Hàm để đóng bảng cài đặt
function dongCaiDat() {
  khungCaiDat.style.display = "none";
  lopPhu_BangCaiDat.style.display = "none";
}

// Gán sự kiện click
nutCaiDat.addEventListener("click", moCaiDat);
caiDat_NutThoat.addEventListener("click", dongCaiDat);

// ----------------------------------------------------------------------------------------------------------------------------
// BẬT/TẮT ÂM THANH VÀ NHẠC

const audioClick = document.getElementById("amThanhClick");

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
  } else {
    imgNhac.src = imgTatNhac;
  }
}

// Gán sự kiện click cho nút tiếng
nutTieng.addEventListener("click", () => {
  amThanhBat = !amThanhBat;
  capNhatUI_Tieng();
  sessionStorage.setItem("amThanhBat", amThanhBat);

  // Chỉ phát tiếng click khi BẬT âm thanh
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
// BẢNG XẾP HẠNG

const nutXepHang = document.getElementById("nutXepHang");
const khungXepHang = document.getElementById("khungXepHang");
const lopPhu_BangXepHang = document.getElementById("lopPhu_BangXepHang");
const xepHang_NutThoat = document.getElementById("xepHang_NutThoat");

// Hàm tính tỉ lệ thắng
function tinhWinRate(wins, total) {
  if (!total || total === 0) return "0.0%";
  return ((wins / total) * 100).toFixed(1) + "%";
}

// Hàm render dữ liệu lên bảng
function hienThiBangXepHang() {
  const danhSachUser = JSON.parse(localStorage.getItem("danhSachUser")) || [];
  const userHienTai = localStorage.getItem("userHienTai");

  // Sắp xếp danh sách
  danhSachUser.sort((a, b) => {
    if (b.highScore !== a.highScore) {
      return b.highScore - a.highScore;
    }
    // Nếu điểm bằng nhau thì so tỉ lệ thắng
    const rateA = a.totalGames ? a.totalWins / a.totalGames : 0;
    const rateB = b.totalGames ? b.totalWins / b.totalGames : 0;
    return rateB - rateA;
  });

  // Lấy các cột dữ liệu trong HTML
  const cotHang = document.querySelector(".XepHang-Hang");
  const cotTen = document.querySelector(".XepHang-TenNguoiChoi");
  const cotDiem = document.querySelector(".XepHang-DiemCaoNhat");
  const cotRate = document.querySelector(".XepHang-TiLeThang");

  // Hàm xóa các thẻ p cũ trong cột
  function xoaDuLieuCu(cot) {
    const cacTheP = cot.querySelectorAll("p");
    cacTheP.forEach((p) => p.remove());
  }

  xoaDuLieuCu(cotHang);
  xoaDuLieuCu(cotTen);
  xoaDuLieuCu(cotDiem);
  xoaDuLieuCu(cotRate);

  // Duyệt qua danh sách user và tạo phần tử HTML
  danhSachUser.forEach((user, index) => {
    // Tạo rank
    const pHang = document.createElement("p");
    pHang.innerText = index + 1;

    // Tạo tên
    const pTen = document.createElement("p");
    pTen.innerText = user.username;

    // Tạo điểm
    const pDiem = document.createElement("p");
    pDiem.innerText = user.highScore || 0;

    // Tạo tỉ lệ thắng
    const pRate = document.createElement("p");
    pRate.innerText = tinhWinRate(user.totalWins, user.totalGames);

    // Nếu là user hiện tại thì thêm class để đổi màu vàng
    if (userHienTai && user.username === userHienTai) {
      const mauVang = "#fffb00ff";
      pHang.style.color = mauVang;
      pTen.style.color = mauVang;
      pDiem.style.color = mauVang;
      pRate.style.color = mauVang;
    }

    // Thêm vào cột
    cotHang.appendChild(pHang);
    cotTen.appendChild(pTen);
    cotDiem.appendChild(pDiem);
    cotRate.appendChild(pRate);
  });
}

// Hàm mở bảng xếp hạng
function moXepHang() {
  hienThiBangXepHang();
  khungXepHang.style.display = "block";
  lopPhu_BangXepHang.style.display = "block";
}

// Hàm đóng bảng xếp hạng
function dongXepHang() {
  khungXepHang.style.display = "none";
  lopPhu_BangXepHang.style.display = "none";
}

// Gán sự kiện click
nutXepHang.addEventListener("click", moXepHang);
xepHang_NutThoat.addEventListener("click", dongXepHang);

// ============================================================================================================================
// HIỂN THỊ ĐIỂM CAO NHẤT

// Hàm cập nhật số sao lên giao diện
function capNhatHienThiDiemCao() {
  const userHienTai = localStorage.getItem("userHienTai");
  const cupSao = document.querySelector(".CupSao");
  let diemHienThi = 0;

  // Kiểm tra đã đăng nhập hay chưa để lấy điểm theo trường hợp đó
  if (userHienTai) {
    const danhSachUser = JSON.parse(localStorage.getItem("danhSachUser")) || [];
    const user = danhSachUser.find((u) => u.username === userHienTai);
    diemHienThi = user && user.highScore ? user.highScore : 0;
  } else {
    diemHienThi = localStorage.getItem("diemCaoNhat") || "0";
  }

  // Cập nhật HTML
  if (cupSao) {
    cupSao.innerHTML = `${diemHienThi} <img src="Images/CupSao.png" />`;
  }
}

// Gọi hàm khi tải trang
document.addEventListener("DOMContentLoaded", function () {
  capNhatHienThiDiemCao();
});

// Xuất hàm này ra để file khác dùng (nếu cần thiết, hoặc để global)
window.capNhatHienThiDiemCao = capNhatHienThiDiemCao;

// Click 4 lần vào tên game để reset điểm
const tenGame = document.querySelector(".TenGame");
let demClickReset = 0;
if (tenGame) {
  tenGame.addEventListener("click", () => {
    demClickReset++;
    if (demClickReset === 4) {
      // Kiểm tra đăng nhập để reset đúng
      const userHienTai = localStorage.getItem("userHienTai");

      // Reset điểm màn chơi hiện tại (của bất kỳ ai)
      localStorage.setItem("tongDiem", "0");

      // Phân lại trường hợp đã đăng nhập hay chưa để reset điểm
      if (userHienTai) {
        let danhSachUser =
          JSON.parse(localStorage.getItem("danhSachUser")) || [];
        const index = danhSachUser.findIndex((u) => u.username === userHienTai);

        if (index !== -1) {
          danhSachUser[index].highScore = 0;
          localStorage.setItem("danhSachUser", JSON.stringify(danhSachUser));
          alert(`High score for user '${userHienTai}' has been reset to 0!`);
        }
      } else {
        localStorage.setItem("diemCaoNhat", "0");
        alert("Guest high score has been reset to 0!");
      }

      location.reload();
    }
    // Reset đếm nếu không click tiếp trong 1s
    setTimeout(() => {
      demClickReset = 0;
    }, 1000);
  });
}

// ============================================================================================================================


// ============================================================================================================================
// NÂNG CẤP AJAX FETCH API CHO BẢNG XẾP HẠNG (LEADERBOARD) VÀ RESET ĐIỂM (CHƯƠNG 2 SLIDE 23-25)
// ============================================================================================================================
if (typeof nutXepHang !== "undefined" && nutXepHang) {
  nutXepHang.addEventListener("click", function () {
    fetch("/api/leaderboard")
      .then((res) => res.json())
      .then((json) => {
        if (json && json.success && Array.isArray(json.data)) {
          const mapped = json.data.map((u) => ({
            username: u.username,
            highScore: Number(u.high_score || 0),
            totalGames: Number(u.total_games || 0),
            totalWins: Number(u.total_wins || 0)
          }));
          localStorage.setItem("danhSachUser", JSON.stringify(mapped));
          if (typeof hienThiBangXepHang === "function") {
            hienThiBangXepHang();
          }
        }
      })
      .catch((err) => console.warn("Leaderboard Fetch fallback:", err));
  });
}
