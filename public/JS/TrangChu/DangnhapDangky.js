// ẨN/HIỆN BẢNG ĐĂNG NHẬP/ĐĂNG KÝ

const nutDangNhap = document.getElementById("nutDangNhap");
const dangNhap_NutThoat = document.getElementById("dangNhap_NutThoat");
const khungDangNhap = document.getElementById("khungDangNhap");
const lopPhu_BangDangNhap = document.getElementById("lopPhu_BangDangNhap");

// Hàm để mở bảng đăng nhập/đăng ký
function moDangNhap() {
  khungDangNhap.style.display = "block";
  lopPhu_BangDangNhap.style.display = "block";
}

// Hàm để đóng bảng đăng nhập/đăng ký
function dongDangNhap() {
  khungDangNhap.style.display = "none";
  lopPhu_BangDangNhap.style.display = "none";

  // Reset về bảng đăng nhập khi đóng, đặt tgian để tránh bị giật hình
  setTimeout(() => {
    bangDangKy.style.display = "none";
    bangDangNhap.style.display = "flex";
  }, 200);
}

// Gán sự kiện click
nutDangNhap.addEventListener("click", function () {
  const userHienTai = localStorage.getItem("userHienTai");
  if (userHienTai) {
    // Nếu đang đăng nhập thì hỏi đăng xuất
    if (typeof customConfirm !== 'undefined') {
      customConfirm("Bạn có chắc chắn muốn đăng xuất không?", "Xác nhận đăng xuất").then((xacNhan) => {
        if (xacNhan) {
          dangXuat();
        }
      });
    } else {
      const xacNhan = confirm("Bạn có chắc chắn muốn đăng xuất không?");
      if (xacNhan) {
        dangXuat();
      }
    }
  } else {
    // Nếu chưa đăng nhập thì mở bảng
    moDangNhap();
  }
});
dangNhap_NutThoat.addEventListener("click", dongDangNhap);

// ----------------------------------------------------------------------------------------------------------------------------
// CHUYỂN ĐỔI GIỮA BẢNG ĐĂNG NHẬP VÀ ĐĂNG KÝ

const bangDangNhap = document.querySelector(".BangDangNhap");
const bangDangKy = document.querySelector(".BangDangKy");
const linkDangKy = document.getElementById("linkDangKy");
const linkDangNhap = document.getElementById("linkDangNhap");

// Hàm chuyển sang bảng Đăng Ký
function chuyenSangDangKy(event) {
  event.preventDefault();
  bangDangNhap.style.display = "none";
  bangDangKy.style.display = "flex";
}

// Hàm chuyển sang bảng Đăng Nhập
function chuyenSangDangNhap(event) {
  event.preventDefault();
  bangDangKy.style.display = "none";
  bangDangNhap.style.display = "flex";
}

// Gán sự kiện click
linkDangKy.addEventListener("click", chuyenSangDangKy);
linkDangNhap.addEventListener("click", chuyenSangDangNhap);

// ----------------------------------------------------------------------------------------------------------------------------
// QUÊN MẬT KHẨU

const linkQuenMatKhau = document.getElementById("linkQuenMatKhau");

// Hàm bắt đầu quá trình quên mật khẩu
function thucHienQuenMatKhau() {
  // Nhập Username và kiểm tra tồn tại
  async function promptUsername() {
    const username = typeof customPrompt !== 'undefined'
      ? await customPrompt("Please enter your username:", "Nhập username", "Username")
      : prompt("Please enter your username:");
    if (username === null) return;
    if (username.trim() === "") {
      if (typeof customAlert !== 'undefined') {
        await customAlert("Please enter your username!", "Lỗi");
      } else {
        alert("Please enter your username!");
      }
      promptUsername();
      return;
    }
    const danhSachUser = JSON.parse(localStorage.getItem("danhSachUser")) || [];
    const userTonTai = danhSachUser.find((user) => user.username === username);
    if (!userTonTai) {
      if (typeof customAlert !== 'undefined') {
        await customAlert("Username not found! Please try again.", "Lỗi");
      } else {
        alert("Username not found! Please try again.");
      }
      promptUsername();
      return;
    }
    promptNewPassword(username);
  }

  // Nhập Mật khẩu mới
  async function promptNewPassword(username) {
    const newPassword = typeof customPrompt !== 'undefined'
      ? await customPrompt("Please enter your new password:", "Nhập mật khẩu mới", "Mật khẩu mới")
      : prompt("Please enter your new password:");
    if (newPassword === null) return;
    if (newPassword.trim() === "") {
      if (typeof customAlert !== 'undefined') {
        await customAlert("Password cannot be empty!", "Lỗi");
      } else {
        alert("Password cannot be empty!");
      }
      promptNewPassword(username);
      return;
    }
    promptConfirmPassword(username, newPassword);
  }

  // Xác nhận và Lưu mật khẩu mới
  async function promptConfirmPassword(username, newPassword) {
    const confirmPassword = typeof customPrompt !== 'undefined'
      ? await customPrompt("Please confirm your new password:", "Xác nhận mật khẩu", "Xác nhận mật khẩu")
      : prompt("Please confirm your new password:");
    if (confirmPassword === null) return;
    if (newPassword !== confirmPassword) {
      if (typeof customAlert !== 'undefined') {
        await customAlert("Passwords do not match! Please try again.", "Lỗi");
      } else {
        alert("Passwords do not match! Please try again.");
      }
      promptNewPassword(username);
      return;
    }
    const danhSachUser = JSON.parse(localStorage.getItem("danhSachUser")) || [];
    const index = danhSachUser.findIndex((user) => user.username === username);
    if (index !== -1) {
      danhSachUser[index].password = newPassword;
      localStorage.setItem("danhSachUser", JSON.stringify(danhSachUser));
      if (typeof customAlert !== 'undefined') {
        await customAlert("Password changed successfully! Please login with your new password.", "Thành công");
      } else {
        alert("Password changed successfully! Please login with your new password.");
      }
    }
  }
  promptUsername();
}

// Gán sự kiện click cho link "Forgot your password?"
linkQuenMatKhau.addEventListener("click", (event) => {
  event.preventDefault();
  thucHienQuenMatKhau();
});

// ----------------------------------------------------------------------------------------------------------------------------
// ICON MẮT (HIỆN/ẨN MẬT KHẨU)

const matKhau_DnPass = document.getElementById("matKhau_DnPass");
const matKhau_DnEye = document.getElementById("matKhau_DnEye");

const matKhau_DkPass = document.getElementById("matKhau_DkPass");
const matKhau_DkEye = document.getElementById("matKhau_DkEye");

const matKhau_XnPass = document.getElementById("matKhau_XnPass");
const matKhau_XnEye = document.getElementById("matKhau_XnEye");

const eyeOpen = "Images/svg/Eye/EyeOpen.svg";
const eyeClose = "Images/svg/Eye/EyeClose.svg";

// Hàm chuyển đổi loại ẩn/hiện mật khẩu
function togglePasswordVisibility(input, icon) {
  if (input.type === "password") {
    input.type = "text";
    icon.src = eyeOpen;
  } else {
    input.type = "password";
    icon.src = eyeClose;
  }
}

// Gán sự kiện click cho từng icon
matKhau_DnEye.addEventListener("click", () => {
  togglePasswordVisibility(matKhau_DnPass, matKhau_DnEye);
});
matKhau_DkEye.addEventListener("click", () => {
  togglePasswordVisibility(matKhau_DkPass, matKhau_DkEye);
});
matKhau_XnEye.addEventListener("click", () => {
  togglePasswordVisibility(matKhau_XnPass, matKhau_XnEye);
});

// ============================================================================================================================
// LOGIC ĐĂNG KÝ - ĐĂNG NHẬP - ĐĂNG XUẤT

const inputUserDK = document.querySelector(".DangKy-TenTK input");
const inputPassDK = document.getElementById("matKhau_DkPass");
const inputPassXnDK = document.getElementById("matKhau_XnPass");
const btnDangKy = document.querySelector(".DangKy-Nut");

const inputUserDN = document.querySelector(".DangNhap-TenTK input");
const inputPassDN = document.getElementById("matKhau_DnPass");
const btnDangNhap = document.querySelector(".DangNhap-Nut");

// Hàm Đăng Ký
if (btnDangKy) {
  btnDangKy.addEventListener("click", async function () {
    const inputUser = document.querySelector(".DangKy-TenTK input") || document.getElementById("dk_Username");
    const inputEmail = document.querySelector(".DangKy-Email input") || document.getElementById("dk_Email");
    const inputPass = document.getElementById("matKhau_DkPass");
    const inputPassXn = document.getElementById("matKhau_XnPass");

    const username = inputUser ? inputUser.value.trim() : "";
    const email = inputEmail ? inputEmail.value.trim() : (username + "@huit.edu.vn");
    const password = inputPass ? inputPass.value.trim() : "";
    const confirmPassword = inputPassXn ? inputPassXn.value.trim() : "";

    if (!username || !password) {
      if (typeof customAlert !== 'undefined') {
        await customAlert("Vui lòng nhập đầy đủ Tên đăng nhập và Mật khẩu!", "Lỗi");
      } else {
        alert("Vui lòng nhập đầy đủ Tên đăng nhập và Mật khẩu!");
      }
      return;
    }
    if (confirmPassword && password !== confirmPassword) {
      if (typeof customAlert !== 'undefined') {
        await customAlert("Mật khẩu xác nhận không trùng khớp!", "Lỗi");
      } else {
        alert("Mật khẩu xác nhận không trùng khớp!");
      }
      return;
    }

    try {
      const res = await fetch("/api/auth/register", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": window.HUIT_CSRF_TOKEN || ""
        },
        body: JSON.stringify({ username, email, password, confirmPassword })
      });
      const data = await res.json();

      if (data.success) {
        if (typeof customAlert !== 'undefined') {
          await customAlert("Đăng ký thành công! Dữ liệu đã được lưu vào MySQL.", "Thành công");
        } else {
          alert("Đăng ký thành công! Dữ liệu đã được lưu vào MySQL.");
        }
        // Chuyển sang bảng Đăng nhập và điền sẵn username
        const linkDn = document.getElementById("linkDangNhap");
        if (linkDn) linkDn.click();
        const dnInput = document.querySelector(".DangNhap-TenTK input") || document.getElementById("dn_Identifier");
        if (dnInput) dnInput.value = username;
      } else {
        if (typeof customAlert !== 'undefined') {
          await customAlert(data.message || "Đăng ký thất bại.", "Lỗi");
        } else {
          alert(data.message || "Đăng ký thất bại.");
        }
      }
    } catch (err) {
      if (typeof customAlert !== 'undefined') {
        await customAlert("Lỗi kết nối máy chủ khi đăng ký: " + err.message, "Lỗi");
      } else {
        alert("Lỗi kết nối máy chủ khi đăng ký: " + err.message);
      }
    }
  });
}

// Hàm Đăng Nhập
if (btnDangNhap) {
  btnDangNhap.addEventListener("click", async function () {
    const inputDn = document.querySelector(".DangNhap-TenTK input") || document.getElementById("dn_Identifier");
    const inputPass = document.getElementById("matKhau_DnPass");

    const identifier = inputDn ? inputDn.value.trim() : "";
    const password = inputPass ? inputPass.value.trim() : "";

    if (!identifier || !password) {
      if (typeof customAlert !== 'undefined') {
        await customAlert("Vui lòng nhập Tên đăng nhập/Email và Mật khẩu!", "Lỗi");
      } else {
        alert("Vui lòng nhập Tên đăng nhập/Email và Mật khẩu!");
      }
      return;
    }

    try {
      const res = await fetch("/api/auth/login", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": window.HUIT_CSRF_TOKEN || ""
        },
        body: JSON.stringify({ identifier, username: identifier, password })
      });
      const data = await res.json();

      if (data.success && data.data) {
        localStorage.setItem("userHienTai", data.data.username);
        if (typeof capNhatGiaoDienDaDangNhap === "function") {
          capNhatGiaoDienDaDangNhap(data.data.username);
        }
        if (typeof dongDangNhap === "function") dongDangNhap();
        if (typeof customAlert !== 'undefined') {
          await customAlert("Đăng nhập thành công! Xin chào " + data.data.username, "Thành công");
        } else {
          alert("Đăng nhập thành công! Xin chào " + data.data.username);
        }
      } else {
        if (typeof customAlert !== 'undefined') {
          await customAlert(data.message || "Tên đăng nhập hoặc mật khẩu không chính xác.", "Lỗi");
        } else {
          alert(data.message || "Tên đăng nhập hoặc mật khẩu không chính xác.");
        }
      }
    } catch (err) {
      if (typeof customAlert !== 'undefined') {
        await customAlert("Lỗi kết nối máy chủ khi đăng nhập: " + err.message, "Lỗi");
      } else {
        alert("Lỗi kết nối máy chủ khi đăng nhập: " + err.message);
      }
    }
  });
}

// Hàm Đăng Xuất
function dangXuat() {
  localStorage.removeItem("userHienTai");
  nutDangNhap.innerText = "Login | Register";
  if (window.capNhatHienThiDiemCao) window.capNhatHienThiDiemCao();
}

// Hàm Cập nhật giao diện
function capNhatGiaoDienDaDangNhap(username) {
  nutDangNhap.innerText = username;
}

// Kiểm tra trạng thái khi tải lại trang
document.addEventListener("DOMContentLoaded", function () {
  const userHienTai = localStorage.getItem("userHienTai");
  if (userHienTai) {
    capNhatGiaoDienDaDangNhap(userHienTai);
  }
});

// ============================================================================================================================


// ============================================================================================================================
// NÂNG CẤP BACKEND JS & FETCH API / AJAX (CHƯƠNG 2.2 - KẾT HỢP PHP & JAVASCRIPT QUA JSON API)
// Đồng bộ hóa tài khoản, đăng nhập, đăng ký, quên mật khẩu và bảng xếp hạng từ CSDL MySQL qua Fetch API
// ============================================================================================================================

(function initHuitAjaxAuthSync() {
  // 1. Nạp danh sách bảng xếp hạng từ MySQL Server (đã truyền qua json_encode hoặc gọi Fetch API)
  async function dongBoDanhSachUserTuServer() {
    try {
      const response = await fetch("/api/leaderboard", {
        method: "GET",
        headers: { "Accept": "application/json" }
      });
      const resJson = await response.json();
      if (resJson && resJson.success && Array.isArray(resJson.data)) {
        const localUsers = JSON.parse(localStorage.getItem("danhSachUser")) || [];
        const mapByUsername = new Map();
        localUsers.forEach((u) => mapByUsername.set(u.username, u));

        resJson.data.forEach((srvUser) => {
          const existing = mapByUsername.get(srvUser.username) || {};
          mapByUsername.set(srvUser.username, {
            username: srvUser.username,
            password: existing.password || "password123",
            role: srvUser.role || "user",
            highScore: Math.max(Number(srvUser.high_score || 0), Number(existing.highScore || 0)),
            totalGames: Math.max(Number(srvUser.total_games || 0), Number(existing.totalGames || 0)),
            totalWins: Math.max(Number(srvUser.total_wins || 0), Number(existing.totalWins || 0))
          });
        });

        localStorage.setItem("danhSachUser", JSON.stringify(Array.from(mapByUsername.values())));
        if (typeof window.capNhatHienThiDiemCao === "function") {
          window.capNhatHienThiDiemCao();
        }
      }
    } catch (err) {
      console.warn("Fetch API Leaderboard fallback to LocalStorage:", err);
    }
  }

  // Nếu có user đang đăng nhập trên PHP Session, đồng bộ vào giao diện Game
  if (window.HUIT_AUTH_USER && window.HUIT_AUTH_USER.username) {
    localStorage.setItem("userHienTai", window.HUIT_AUTH_USER.username);
    if (typeof capNhatGiaoDienDaDangNhap === "function") {
      capNhatGiaoDienDaDangNhap(window.HUIT_AUTH_USER.username);
    }
  }

  dongBoDanhSachUserTuServer();

  // 2. Gửi AJAX POST /api/auth/login khi người dùng nhấn nút Sign In
  if (typeof btnDangNhap !== "undefined" && btnDangNhap) {
    btnDangNhap.addEventListener("click", function () {
      const username = inputUserDN.value.trim();
      const password = inputPassDN.value.trim();
      if (!username || !password) return;

      fetch("/api/auth/login", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": window.HUIT_CSRF_TOKEN || ""
        },
        body: JSON.stringify({ username, password })
      })
        .then((res) => res.json())
        .then((result) => {
          if (result && result.success && result.data) {
            localStorage.setItem("userHienTai", result.data.username);
            capNhatGiaoDienDaDangNhap(result.data.username);
            dongBoDanhSachUserTuServer();
            dongDangNhap();
          }
        })
        .catch((err) => console.log("AJAX Login sync:", err));
    });
  }

  // 3. Gửi AJAX POST /api/auth/register khi người dùng nhấn nút Sign Up
  if (typeof btnDangKy !== "undefined" && btnDangKy) {
    btnDangKy.addEventListener("click", function () {
      const username = inputUserDK.value.trim();
      const password = inputPassDK.value.trim();
      const confirmPassword = inputPassXnDK.value.trim();
      if (!username || !password || password !== confirmPassword) return;

      fetch("/api/auth/register", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": window.HUIT_CSRF_TOKEN || ""
        },
        body: JSON.stringify({ username, password, confirmPassword })
      })
        .then((res) => res.json())
        .then(() => dongBoDanhSachUserTuServer())
        .catch((err) => console.log("AJAX Register sync:", err));
    });
  }
})();
