<!DOCTYPE html>
<html lang="vi">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Game Nối Ống Nước</title>

    <!-- Bootstrap -->
    <link rel="stylesheet" href="Bootstrap/CSS/bootstrap.min.css" />
    <script src="Bootstrap/JS/bootstrap.bundle.min.js"></script>
    <script src="JS/custom-modal.js"></script>

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Chakra+Petch:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet"
    />

    <link rel="stylesheet" type="text/css" href="CSS/TrangChu.css" />

    <!-- CSS ĐỒNG BỘ CHO CÁC MÀN HÌNH ĐĂNG NHẬP / ĐĂNG KÝ / QUÊN MẬT KHẨU THEO TỪNG BƯỚC -->
    <style>
      .KhungDangNhap {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 390px;
        height: auto !important;
        min-height: 460px;
        max-height: 92vh;
        background-color: #f3f3f3;
        border: 8px solid #0059ff;
        border-radius: 12px;
        z-index: 1000;
        box-sizing: border-box;
        padding-bottom: 22px;
        box-shadow: 0 12px 35px rgba(0, 0, 0, 0.65);
      }

      /* Đảm bảo chỉ hiện đúng 1 màn hình tại 1 thời điểm */
      .Auth-ScreenPanel {
        display: none;
        flex-direction: column;
        align-items: center;
        justify-content: flex-start;
        width: 100%;
        background-color: #f3f3f3;
        border-radius: 6px;
        padding: 0 18px;
        box-sizing: border-box;
      }

      .Auth-ScreenPanel.active-screen {
        display: flex !important;
      }

      .DangNhap-Ten,
      .DangKy-Ten {
        margin: 26px 0 14px 0 !important;
        font-size: 28px !important;
        font-weight: 700 !important;
        color: #0059ff;
        text-align: center;
      }

      .DangNhap-TenTK,
      .DangNhap-MatKhau,
      .DangKy-TenTK,
      .DangKy-Email,
      .DangKy-MatKhau,
      .DangKy-XnMatKhau {
        display: flex;
        flex-direction: column;
        width: 300px;
        margin-top: 10px !important;
        font-size: 17px !important;
        font-weight: 600;
        color: #0059ff;
      }

      .DangNhap-TenTK input,
      .DangKy-TenTK input,
      .DangKy-Email input {
        box-sizing: border-box;
        padding: 5px 10px;
        width: 100%;
        font-size: 16px;
        color: #000000;
        background-color: #ffffff;
        border: 2px solid #0059ff;
        border-radius: 10px;
        margin-top: 4px;
      }

      .DangNhap-MatKhau input,
      .DangKy-MatKhau input,
      .DangKy-XnMatKhau input {
        box-sizing: border-box;
        padding: 5px 40px 5px 10px;
        width: 100%;
        font-size: 16px;
        color: #000000;
        background-color: #ffffff;
        border: 2px solid #0059ff;
        border-radius: 10px;
        margin-top: 4px;
      }

      .DangNhap-Nut,
      .DangKy-Nut {
        margin-top: 22px !important;
        padding: 7px 0 !important;
        width: 300px;
        font-size: 19px;
        font-weight: 700;
        border-radius: 10px;
        user-select: none;
      }

      /* Hộp thông báo lỗi / thành công ngay trong khung */
      .Auth-AlertBox {
        display: none;
        width: 300px;
        margin: 18px auto 0 auto;
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 13.5px;
        font-weight: 600;
        text-align: center;
        box-sizing: border-box;
      }
      .Auth-AlertBox.error {
        display: block;
        background-color: #fee2e2;
        color: #b91c1c;
        border: 2px solid #ef4444;
      }
      .Auth-AlertBox.success {
        display: block;
        background-color: #dcfce7;
        color: #15803d;
        border: 2px solid #22c55e;
      }
      .Auth-AlertBox.info {
        display: block;
        background-color: #e0f2fe;
        color: #0369a1;
        border: 2px solid #0059ff;
      }

      /* 6 Ô nhập số OTP nằm ngang chuẩn thiết kế */
      .OTP-Grid6 {
        display: flex;
        flex-direction: row;
        gap: 8px;
        justify-content: center;
        align-items: center;
        margin: 16px 0 12px 0;
        width: 300px;
      }

      .OTP-DigitInput {
        width: 43px !important;
        height: 48px !important;
        text-align: center;
        font-size: 22px;
        font-weight: 700;
        color: #0059ff;
        background-color: #ffffff;
        border: 2px solid #0059ff;
        border-radius: 10px;
        outline: none;
        padding: 0 !important;
        box-sizing: border-box;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
      }

      .OTP-DigitInput:focus {
        transform: scale(1.06);
        box-shadow: 0 0 8px rgba(0, 89, 255, 0.5);
      }

      .OTP-TimerBox {
        font-size: 15px;
        font-weight: 700;
        color: #dc2626;
        margin-bottom: 4px;
        text-align: center;
      }

      .Auth-ButtonDisabled {
        opacity: 0.55;
        pointer-events: none;
      }
    </style>
  </head>
  <body>
    <!-- TÊN GAME -->
    <div class="TenGame">WATER PIPE CONNECTION</div>

    <!-- NÚT ĐĂNG NHẬP/ĐĂNG KÝ -->
    <div class="NutDangNhap am-thanh-click" id="nutDangNhap">
      Login | Register
    </div>
     <a class="NutDangNhap am-thanh-click" id="myProfileLink" href="/profile"
       style="top: 62px; text-decoration: none;" {{ empty($currentUser) ? 'hidden' : '' }}>Hồ sơ của tôi</a>

    <!-- BẢNG ĐĂNG NHẬP / ĐĂNG KÝ / QUÊN MẬT KHẨU (HIỂN THỊ LẦN LƯỢT TỪNG GIAO DIỆN) -->
    <div class="KhungDangNhap" id="khungDangNhap">
      <!-- Hộp thông báo trạng thái chung -->
      <div id="authAlertBox" class="Auth-AlertBox"></div>

      <!-- 1. MÀN HÌNH ĐĂNG NHẬP (MẶC ĐỊNH HIỆN KHI MỞ BẢNG) -->
      <div class="BangDangNhap Auth-ScreenPanel active-screen" id="screenLogin">
        <div class="DangNhap-Ten">ĐĂNG NHẬP</div>
        <div class="DangNhap-TenTK">
          Tên đăng nhập hoặc Email
          <input id="dn_Identifier" placeholder="Nhập username hoặc email" autocomplete="username" />
        </div>
        <div class="DangNhap-MatKhau">
          Mật khẩu
          <div class="MatKhau">
            <input type="password" placeholder="Nhập mật khẩu" id="matKhau_DnPass" autocomplete="current-password" />
            <img src="Images/svg/Eye/EyeClose.svg" class="MatKhau-Eye" id="matKhau_DnEye" />
          </div>
        </div>
        <div class="DangNhap-Nut am-thanh-click" id="btnSignIn">Đăng nhập</div>
        <div class="DangNhap-ThongTin" style="margin-left:0; width:300px; margin-top:14px;">
          <a href="#" id="linkQuenMatKhau">Quên mật khẩu?</a>
          <div>Chưa có tài khoản? <a href="#" id="linkDangKy">Đăng ký ngay</a></div>
        </div>
      </div>

      <!-- 2. MÀN HÌNH ĐĂNG KÝ (CHỈ HIỆN KHI BẤM "ĐĂNG KÝ NGAY") -->
      <div class="BangDangKy Auth-ScreenPanel" id="screenRegister" style="display:none;">
        <div class="DangKy-Ten">ĐĂNG KÝ</div>
        <div class="DangKy-TenTK">
          Tên đăng nhập (Username)
          <input id="dk_Username" placeholder="3-30 ký tự (chữ, số, dấu _)" autocomplete="username" />
        </div>
        <div class="DangKy-Email">
          Địa chỉ Email
          <input type="email" id="dk_Email" placeholder="Nhập địa chỉ email của bạn" autocomplete="email" />
        </div>
        <div class="DangKy-MatKhau">
          Mật khẩu
          <div class="MatKhau">
            <input type="password" placeholder="Tối thiểu 8 ký tự (chữ & số)" id="matKhau_DkPass" autocomplete="new-password" />
            <img src="Images/svg/Eye/EyeClose.svg" class="MatKhau-Eye" id="matKhau_DkEye" />
          </div>
        </div>
        <div class="DangKy-XnMatKhau">
          Xác nhận mật khẩu
          <div class="MatKhau">
            <input type="password" placeholder="Nhập lại mật khẩu" id="matKhau_XnPass" autocomplete="new-password" />
            <img src="Images/svg/Eye/EyeClose.svg" class="MatKhau-Eye" id="matKhau_XnEye" />
          </div>
        </div>
        <div class="DangKy-Nut am-thanh-click" id="btnSignUp">Đăng ký</div>
        <div class="DangKy-ThongTin" style="margin-left:0; width:300px; text-align:center; margin-top:12px;">
          <div>Đã có tài khoản? <a href="#" id="linkDangNhap">Đăng nhập</a></div>
        </div>
      </div>

      <!-- 3. QUÊN MẬT KHẨU BƯỚC 1: NHẬP EMAIL (CHỈ HIỆN KHI BẤM "QUÊN MẬT KHẨU?") -->
      <div class="BangQuenMatKhau-Email Auth-ScreenPanel" id="screenForgotEmail" style="display:none;">
        <div class="DangNhap-Ten" style="font-size:24px !important;">QUÊN MẬT KHẨU</div>
        <div style="width:300px; font-size:13.5px; color:#334155; text-align:center; margin-bottom:10px;">
          Nhập địa chỉ email đã đăng ký tài khoản để nhận mã xác thực OTP.
        </div>
        <div class="DangNhap-TenTK">
          Email đã đăng ký
          <input type="email" id="qp_EmailInput" placeholder="Nhập email của bạn" />
        </div>
        <div class="DangNhap-Nut am-thanh-click" id="btnSendOtp">Gửi mã OTP</div>
        <div class="DangNhap-ThongTin" style="margin-left:0; width:300px; text-align:center; margin-top:14px;">
          <a href="#" id="linkBackToLoginFromEmail">← Quay lại màn hình Đăng nhập</a>
        </div>
      </div>

      <!-- 4. QUÊN MẬT KHẨU BƯỚC 2: NHẬP 6 SỐ OTP (CHỈ HIỆN SAU KHI GỬI EMAIL OTP THÀNH CÔNG) -->
      <div class="BangQuenMatKhau-OTP Auth-ScreenPanel" id="screenForgotOtp" style="display:none;">
        <div class="DangNhap-Ten" style="font-size:24px !important;">XÁC MINH MÃ OTP</div>
        <div style="width:300px; font-size:13.5px; color:#334155; text-align:center;">
          Nhập mã OTP:<br />
          <strong id="otpTargetEmailLabel" style="color:#0059ff;"></strong>
        </div>
        <div class="OTP-Grid6" id="otpInputsContainer">
          <input type="text" maxlength="1" inputmode="numeric" class="OTP-DigitInput" data-idx="0" />
          <input type="text" maxlength="1" inputmode="numeric" class="OTP-DigitInput" data-idx="1" />
          <input type="text" maxlength="1" inputmode="numeric" class="OTP-DigitInput" data-idx="2" />
          <input type="text" maxlength="1" inputmode="numeric" class="OTP-DigitInput" data-idx="3" />
          <input type="text" maxlength="1" inputmode="numeric" class="OTP-DigitInput" data-idx="4" />
          <input type="text" maxlength="1" inputmode="numeric" class="OTP-DigitInput" data-idx="5" />
        </div>
        <div class="OTP-TimerBox" id="otpCountdownText">⏱ Thời gian còn lại: 60s</div>
        <div class="DangNhap-Nut am-thanh-click" id="btnVerifyOtp">Xác nhận OTP</div>
        <div class="DangNhap-ThongTin" style="margin-left:0; width:300px; text-align:center; margin-top:12px;">
          <div>Chưa nhận được mã? <a href="#" id="linkResendOtp" class="Auth-ButtonDisabled">Gửi lại mã</a></div>
          <div style="margin-top:6px;"><a href="#" id="linkBackToEmailFromOtp">← Đổi địa chỉ Email khác</a></div>
        </div>
      </div>

      <!-- 5. QUÊN MẬT KHẨU BƯỚC 3: ĐẶT LẠI MẬT KHẨU MỚI (CHỈ HIỆN SAU KHI XÁC MINH ĐÚNG OTP) -->
      <div class="BangQuenMatKhau-Reset Auth-ScreenPanel" id="screenForgotReset" style="display:none;">
        <div class="DangNhap-Ten" style="font-size:24px !important;">ĐẶT LẠI MẬT KHẨU</div>
        <div class="DangKy-MatKhau">
          Mật khẩu mới
          <div class="MatKhau">
            <input type="password" placeholder="Tối thiểu 8 ký tự (chữ & số)" id="matKhau_ResetPass" autocomplete="new-password" />
            <img src="Images/svg/Eye/EyeClose.svg" class="MatKhau-Eye" id="matKhau_ResetEye" />
          </div>
        </div>
        <div class="DangKy-XnMatKhau">
          Xác nhận mật khẩu mới
          <div class="MatKhau">
            <input type="password" placeholder="Nhập lại mật khẩu mới" id="matKhau_ResetConfirmPass" autocomplete="new-password" />
            <img src="Images/svg/Eye/EyeClose.svg" class="MatKhau-Eye" id="matKhau_ResetConfirmEye" />
          </div>
        </div>
        <div class="DangNhap-Nut am-thanh-click" id="btnResetPasswordSubmit">Cập nhật mật khẩu</div>
      </div>

      <!-- Nút Thoát Modal -->
      <div class="DangNhap-NutThoat am-thanh-click" id="dangNhap_NutThoat">
        <img src="Images/svg/Nut/Thoat.svg" />
      </div>
    </div>

    <!-- NÚT PLAY -->
    <div class="NutPlay am-thanh-click" id="nutPlay">
      <img src="Images/svg/Nut/BatDau.svg" />
    </div>

    <!-- BẢNG PLAY (CHẾ ĐỘ 1 PLAYER ĐA ĐIỂM ĐẦU-CUỐI & ĐỐI KHÁNG 2-4 NGƯỜI) -->
    <div class="KhungPlay" id="khungPlay">
      <div class="BangPlay" style="overflow-y:auto; max-height:82vh; padding-bottom:15px;">
        <!-- Chế Độ: 1 Player vs 2-4P PvP -->
        <div class="BangPlay-CheDo">
          <div class="CheDo-Ten">Chế Độ Chơi</div>
          <div class="CheDo-TuyChon">
            <div class="CheDo-Player active" id="cheDoPlayer" style="width:auto; padding:0 16px;">🛡️ 1P</div>
            <div class="CheDo-AI" id="cheDoPvP" style="width:auto; padding:0 16px;">⚔️ PvP</div>
          </div>
        </div>

        <!-- TÙY CHỌN RIÊNG CHO 1 PLAYER: CHỌN SỐ CẶP ĐIỂM BẮT ĐẦU & KẾT THÚC CÙNG MÀU -->
        <div id="panel1PlayerConfig" style="margin:10px auto; width:88%; background:rgba(0,0,0,0.55); border:2px solid #00e5ff; border-radius:12px; padding:10px 14px; color:#fff; font-family:'Chakra Petch',sans-serif;">
          <div style="font-weight:700; color:#ffffff; font-size:15px; margin-bottom:6px;">
            🎯 Số Điểm Bắt Đầu - Kết Thúc:
          </div>
          <div style="display:flex; gap:14px; justify-content:center; align-items:center; flex-wrap:wrap;">
            <label style="cursor:pointer; font-weight:600;">
              <input type="radio" name="soCapDauCuoi" value="2" checked /> 2
            </label>
            <label style="cursor:pointer; font-weight:600;">
              <input type="radio" name="soCapDauCuoi" value="3" /> 3
            </label>
            <label style="cursor:pointer; font-weight:600;">
              <input type="radio" name="soCapDauCuoi" value="4" /> 4
            </label>
          </div>
          <div style="font-size:12px; color:#86efac; text-align:center; line-height:1.35;">
            ⏱ <strong>Luật chơi</strong><br /> Nối đúng các đường ngắn nhất đạt <strong>100đ</strong> để qua màn.
          </div>
        </div>

        <!-- TÙY CHỌN RIÊNG CHO 2-4P PVP: TẠO PHÒNG HOẶC RANDOM CHỌN SỐ ĐỐI THỦ -->
        <div id="panelPvPConfig" style="display:none; margin:10px auto; width:88%; background:rgba(0,0,0,0.68); border:2px solid #ff2a85; border-radius:12px; padding:10px 14px; color:#fff; font-family:'Chakra Petch',sans-serif;">
          <div style="display:flex; gap:16px; justify-content:center; margin-bottom:8px;">
            <label style="cursor:pointer; font-weight:600; color:#ffffff;">
              <input type="radio" name="pvpModeType" value="random" checked id="pvpTypeRandom" /> Ngẫu nhiên
            </label>
            <label style="cursor:pointer; font-weight:600; color:#ffffff;">
              <input type="radio" name="pvpModeType" value="room" id="pvpTypeRoom" /> Tạo / Vào Phòng
            </label>
          </div>

          <div id="pvpRandomBox" style="text-align:center; margin-bottom:6px;">
            <span>Số lượng đối thủ: </span>
            <select id="pvpOpponentCount" style="background:#111827; color:#fff; border:1px solid #00e5ff; border-radius:6px; padding:3px 8px; font-weight:600;">
              <option value="1">Đấu 2 người</option>
              <option value="2">Đấu 3 người</option>
              <option value="3">Đấu 4 người</option>
            </select>
          </div>

          <div id="pvpRoomBox" style="display:none; text-align:center; margin-bottom:8px;">
            <!-- 1. PHẦN TẠO PHÒNG MỚI -->
            <div style="background:rgba(255,255,255,0.06); border:1px solid #475569; border-radius:10px; padding:8px 10px; margin-bottom:10px;">
              <div style="font-size:12px; font-weight:700; color:#fde047; margin-bottom:6px; text-transform:uppercase;">
                Tạo Phòng Mới
              </div>
              <div style="display:flex; gap:8px; justify-content:center; align-items:center; flex-wrap:wrap;">
                <span>Số người:</span>
                <select id="pvpRoomMaxPlayers" style="background:#111827; color:#fff; border:1px solid #fde047; border-radius:6px; padding:4px 8px; font-weight:700;">
                  <option value="2" selected>2 người</option>
                  <option value="3" >3 người</option>
                  <option value="4">4 người</option>
                </select>
                <button type="button" id="btnCreateRoomCode" class="btn btn-sm btn-warning fw-bold px-3" style="font-size:13px; border-radius:8px; box-shadow:0 2px 8px rgba(234,179,8,0.35);">
                  Tạo Phòng Mới
                </button>
              </div>
            </div>

            <!-- 2. PHẦN THAM GIA PHÒNG ĐÃ CÓ -->
            <div style="background:rgba(0,229,255,0.06); border:1px solid #00e5ff; border-radius:10px; padding:8px 10px; margin-bottom:6px;">
              <div style="font-size:12px; font-weight:700; color:#38bdf8; margin-bottom:6px; text-transform:uppercase;">
                Tham Gia Bằng Mã Phòng
              </div>
              <div style="display:flex; gap:8px; justify-content:center; align-items:center; flex-wrap:wrap;">
                <input type="text" id="pvpJoinRoomInput" placeholder="Nhập mã phòng"
                       style="background:#111827; color:#00e5ff; border:1.5px solid #00e5ff; border-radius:6px; padding:5px 12px; width:180px; text-transform:uppercase; font-weight:800; text-align:center; letter-spacing:1px;" />
                <button type="button" id="btnJoinRoomCode" class="btn btn-sm btn-info fw-bold px-3" style="font-size:13px; border-radius:8px; color:#000; background:#00e5ff; border:none; box-shadow:0 2px 8px rgba(0,229,255,0.35);">
                  Tham Gia
                </button>
              </div>
              <!-- Ẩn input cũ để giữ tính tương thích nếu có hàm đọc pvpRoomCodeInput -->
              <input type="hidden" id="pvpRoomCodeInput" value="" />
            </div>
          </div>

          <div style="font-size:12px; color:#86efac; text-align:center; line-height:1.35;">
            ⏱ <strong>Luật chơi</strong><br /> Đấu thủ đạt <strong>100đ</strong> và hoàn thành nhanh nhất sẽ chiến thắng!
          </div>
        </div>

        <!-- Bản Đồ & Độ Khó -->
        <div class="BangPlay-BanDo-DoKho">
          <div class="BangPlay-BanDo">
            <div class="BanDo-Ten">Bản đồ</div>
            <div class="BanDo-TuyChon">
              <div class="BanDo-Random">
                <input type="radio" id="banDo_Random" name="banDo_TuyChon" checked />
                <label for="banDo_Random">Ngẫu nhiên</label>
              </div>
              <div class="BanDo-Custom">
                <input type="radio" id="banDo_Custom" name="banDo_TuyChon" />
                <label for="banDo_Custom">Ma trận</label>
                <div class="Custom-DanhSach" id="customMapDropdown">
                  <button
                    class="btn btn-secondary dropdown-toggle"
                    type="button"
                    id="dropdownMapSize"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                  >
                    5x6
                  </button>
                  <ul class="dropdown-menu" aria-labelledby="dropdownMapSize">
                    <li class="dropdown-item">3x8</li>
                    <li class="dropdown-item">3x10</li>
                    <li class="dropdown-item">3x12</li>
                    <li class="dropdown-item">5x6</li>
                    <li class="dropdown-item">5x8</li>
                    <li class="dropdown-item">5x10</li>
                    <li class="dropdown-item">5x12</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>

          <div class="BangPlay-DoKho">
            <div class="DoKho-Ten">Độ khó</div>
            <div class="DoKho-TuyChon">
              <div class="DoKho-Easy">
                <input type="radio" id="doKho_Easy" name="doKho_TuyChon" checked />
                <label for="doKho_Easy">Dễ</label>
              </div>
              <div class="DoKho-Medium">
                <input type="radio" id="doKho_Medium" name="doKho_TuyChon" />
                <label for="doKho_Medium">Trung bình</label>
              </div>
              <div class="DoKho-Hard">
                <input type="radio" id="doKho_Hard" name="doKho_TuyChon" />
                <label for="doKho_Hard">Khó</label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Nút Thoát -->
      <div class="Play-NutThoat am-thanh-click" id="play-NutThoat">
        <img src="Images/svg/Nut/Thoat.svg" />
      </div>

      <!-- Nút Play -->
      <a
        href="TrangGame.html"
        class="Play-NutPlay am-thanh-click"
        id="play-NutBatDau"
      >
        <img src="Images/svg/Nut/BatDau.svg" />
      </a>
    </div>

    <!-- NÚT CÀI ĐẶT -->
    <div class="NutCaiDat am-thanh-click" id="nutCaiDat">
      <img src="Images/svg/Nut/CaiDat.svg" />
    </div>

    <!-- BẢNG CÀI ĐẶT -->
    <div class="KhungCaiDat" id="khungCaiDat">
      <div class="BangCaiDat">
        <div class="CaiDat-NutTieng" id="nutTieng">
          <img src="Images/svg/Nut/MoTieng.svg" id="imgTieng" />
        </div>
        <div class="CaiDat-NutNhac" id="nutNhac">
          <img src="Images/svg/Nut/MoNhac.svg" id="imgNhac" />
        </div>
      </div>
      <div class="CaiDat-NutThoat am-thanh-click" id="caiDat_NutThoat">
        <img src="Images/svg/Nut/Thoat.svg" />
      </div>
    </div>

    <!-- NÚT XẾP HẠNG -->
    <div class="NutXepHang am-thanh-click" id="nutXepHang">
      <img src="Images/svg/Nut/XepHang.svg" />
    </div>

    <!-- BẢNG XẾP HẠNG -->
    <!-- BẢNG XẾP HẠNG -->
    <div class="KhungXepHang" id="khungXepHang">
      <div class="BangXepHang" style="position:relative; overflow:hidden;">
        <div class="XepHang-Ten" style="margin-bottom:8px;">LEADERBOARD</div>

        <!-- TABS CHUYỂN ĐỔI: 1P VS PVP RANKED ELO -->
        <div style="display:flex; justify-content:center; gap:8px; margin-bottom:12px; z-index:10;">
          <button type="button" id="tabLbSingle" class="btn btn-sm btn-info fw-bold" style="border-radius:20px; padding:3px 14px; font-size:12.5px;">⭐ Điểm Cao (1P)</button>
          <button type="button" id="tabLbPvp" class="btn btn-sm btn-outline-warning fw-bold" style="border-radius:20px; padding:3px 14px; font-size:12.5px; color:#fde047; border-color:#fde047;">⚔️ Xếp Hạng PvP</button>
        </div>

        <!-- 1. BẢNG XẾP HẠNG 1 PLAYER (MẶC ĐỊNH) -->
        <div class="XepHang-ThongTin" id="boxLbSingle">
          <div class="XepHang-Hang">
            <div class="Hang-Ten">Rank</div>
            <p>1</p>
            <p>2</p>
          </div>
          <div class="XepHang-TenNguoiChoi">
            <div class="TenNguoiChoi-Ten">Player</div>
            <p>...</p>
            <p>...</p>
          </div>
          <div class="XepHang-DiemCaoNhat">
            <div class="DiemCaoNhat-Ten">High Score</div>
            <p>...</p>
            <p>...</p>
          </div>
          <div class="XepHang-TiLeThang">
            <div class="TiLeThang-Ten">Win Rate</div>
            <p>...%</p>
            <p>...%</p>
          </div>
        </div>

        <!-- 2. BẢNG XẾP HẠNG RANKED PVP (ELO & TIERS) -->
        <div id="boxLbPvp" style="display:none; width:92%; max-height:330px; overflow-y:auto; background:rgba(15,23,42,0.92); border:2px solid #fde047; border-radius:12px; padding:10px; color:#fff; font-family:'Chakra Petch',sans-serif; box-shadow:0 4px 20px rgba(0,0,0,0.6);">
          <table style="width:100%; border-collapse:collapse; font-size:13px; text-align:left;">
            <thead>
              <tr style="border-bottom:1px solid #475569; color:#fde047; font-size:11.5px; text-transform:uppercase;">
                <th style="padding:6px 4px;">Hạng</th>
                <th style="padding:6px 4px;">Đấu Thủ</th>
                <th style="padding:6px 4px; text-align:center;">Elo</th>
                <th style="padding:6px 4px; text-align:center;">Thắng/Trận</th>
                <th style="padding:6px 4px; text-align:right;">Bậc Rank</th>
              </tr>
            </thead>
            <tbody id="pvpRankedLeaderboardTbody">
              <tr><td colspan="5" style="text-align:center; padding:18px; color:#94a3b8;">Đang tải bảng xếp hạng PvP...</td></tr>
            </tbody>
          </table>
        </div>

      </div>
      <div class="XepHang-NutThoat am-thanh-click" id="xepHang_NutThoat">
        <img src="Images/svg/Nut/Thoat.svg" />
      </div>
    </div>

    <!-- SỐ SAO CAO NHẤT -->
    <div class="CupSao">
      0
      <img src="Images/CupSao.png" />
    </div>

    <!-- LỚP PHỦ -->
    <div class="LopPhu_BangDangNhap" id="lopPhu_BangDangNhap"></div>
    <div class="LopPhu_BangPlay" id="lopPhu_BangPlay"></div>
    <div class="LopPhu_BangCaiDat" id="lopPhu_BangCaiDat"></div>
    <div class="LopPhu_BangXepHang" id="lopPhu_BangXepHang"></div>

    <!-- ÂM THANH -->
    <audio id="amThanhClick">
      <source src="Sounds/TiengClick.mp3" type="audio/mpeg" />
    </audio>

    <!-- DỮ LIỆU KHỞI TẠO TỪ SERVER -->
    <script id="huit-server-data" type="application/json">
      {
        "leaderboard": <?php echo json_encode($leaderboard ?? [], JSON_UNESCAPED_UNICODE); ?>,
        "user": <?php echo json_encode($currentUser ?? null, JSON_UNESCAPED_UNICODE); ?>,
        "csrf": "<?php echo csrf_token(); ?>"
      }
    </script>
    <script>
      (function () {
        var el = document.getElementById("huit-server-data");
        var data = el ? JSON.parse(el.textContent || "{}") : {};
        window.HUIT_SERVER_LEADERBOARD = data.leaderboard || [];
        window.HUIT_AUTH_USER = data.user || null;
        window.HUIT_CSRF_TOKEN = data.csrf || "";
      })();
    </script>

    <script src="JS/TrangChu/TrangChu.js"></script>

    <!-- LOGIC ĐIỀU KHIỂN LẦN LƯỢT TỪNG MÀN HÌNH: ĐĂNG NHẬP -> ĐĂNG KÝ -> QUÊN MẬT KHẨU (EMAIL -> OTP 60S -> ĐẶT LẠI MẬT KHẨU) -->
    <script>
      (function () {
        const nutDangNhap = document.getElementById("nutDangNhap");
        const dangNhap_NutThoat = document.getElementById("dangNhap_NutThoat");
        const khungDangNhap = document.getElementById("khungDangNhap");
        const lopPhu_BangDangNhap = document.getElementById("lopPhu_BangDangNhap");
        const authAlertBox = document.getElementById("authAlertBox");

        const screenLogin = document.getElementById("screenLogin");
        const screenRegister = document.getElementById("screenRegister");
        const screenForgotEmail = document.getElementById("screenForgotEmail");
        const screenForgotOtp = document.getElementById("screenForgotOtp");
        const screenForgotReset = document.getElementById("screenForgotReset");
        const allScreens = [screenLogin, screenRegister, screenForgotEmail, screenForgotOtp, screenForgotReset];

        window.currentAuthenticatedUser = window.HUIT_AUTH_USER || null;

        let otpState = {
          email: "",
          requestToken: "",
          resetToken: "",
          timerInterval: null,
          secondsRemaining: 0
        };

        // Hàm chỉ hiển thị duy nhất 1 màn hình được chọn, ẩn toàn bộ các màn hình còn lại
        function showOnlyScreen(targetScreen) {
          allScreens.forEach((sc) => {
            if (!sc) return;
            if (sc === targetScreen) {
              sc.classList.add("active-screen");
              sc.style.display = "flex";
            } else {
              sc.classList.remove("active-screen");
              sc.style.display = "none";
            }
          });
        }

        function showAlert(message, type = "error") {
          if (!authAlertBox) return;
          if (!message) {
            authAlertBox.style.display = "none";
            authAlertBox.className = "Auth-AlertBox";
            authAlertBox.textContent = "";
            return;
          }
          authAlertBox.textContent = message;
          authAlertBox.className = "Auth-AlertBox " + type;
          authAlertBox.style.display = "block";
        }

        function openAuthModal() {
          showAlert("");
          showOnlyScreen(screenLogin);
          khungDangNhap.style.display = "block";
          lopPhu_BangDangNhap.style.display = "block";
        }

        function closeAuthModal() {
          khungDangNhap.style.display = "none";
          lopPhu_BangDangNhap.style.display = "none";
          stopOtpTimer();
          setTimeout(() => {
            showAlert("");
            showOnlyScreen(screenLogin);
          }, 150);
        }

        // Khởi tạo ban đầu: đảm bảo chỉ màn hình Đăng nhập được bật sẵn
        showOnlyScreen(screenLogin);

        nutDangNhap?.addEventListener("click", function () {
          if (window.currentAuthenticatedUser && (window.currentAuthenticatedUser.user_id || window.currentAuthenticatedUser.id)) {
            if (typeof customConfirm !== 'undefined') {
              customConfirm("Bạn có chắc chắn muốn đăng xuất khỏi tài khoản '" + window.currentAuthenticatedUser.username + "' không?", "Xác nhận đăng xuất").then((result) => {
                if (result) {
                  performLogout();
                }
              });
            } else {
              if (confirm("Bạn có chắc chắn muốn đăng xuất khỏi tài khoản '" + window.currentAuthenticatedUser.username + "' không?")) {
                performLogout();
              }
            }
          } else {
            openAuthModal();
          }
        });

        dangNhap_NutThoat?.addEventListener("click", closeAuthModal);

        // Chuyển sang màn hình Đăng ký
        document.getElementById("linkDangKy")?.addEventListener("click", function (e) {
          e.preventDefault();
          showAlert("");
          showOnlyScreen(screenRegister);
        });

        // Chuyển về màn hình Đăng nhập
        document.getElementById("linkDangNhap")?.addEventListener("click", function (e) {
          e.preventDefault();
          showAlert("");
          showOnlyScreen(screenLogin);
        });

        // Khi nhấn "Quên mật khẩu?" -> Mở Bước 1: Giao diện Nhập Email
        document.getElementById("linkQuenMatKhau")?.addEventListener("click", function (e) {
          e.preventDefault();
          showAlert("");
          showOnlyScreen(screenForgotEmail);
        });

        // Quay lại Đăng nhập từ màn hình Nhập Email
        document.getElementById("linkBackToLoginFromEmail")?.addEventListener("click", function (e) {
          e.preventDefault();
          showAlert("");
          showOnlyScreen(screenLogin);
        });

        // Quay lại màn hình Nhập Email từ màn hình OTP
        document.getElementById("linkBackToEmailFromOtp")?.addEventListener("click", function (e) {
          e.preventDefault();
          stopOtpTimer();
          showAlert("");
          showOnlyScreen(screenForgotEmail);
        });

        // Ẩn / Hiện mật khẩu (Icon con mắt)
        function bindEye(inputId, eyeId) {
          const inp = document.getElementById(inputId);
          const eye = document.getElementById(eyeId);
          if (!inp || !eye) return;
          eye.addEventListener("click", function () {
            if (inp.type === "password") {
              inp.type = "text";
              eye.src = "Images/svg/Eye/EyeOpen.svg";
            } else {
              inp.type = "password";
              eye.src = "Images/svg/Eye/EyeClose.svg";
            }
          });
        }
        bindEye("matKhau_DnPass", "matKhau_DnEye");
        bindEye("matKhau_DkPass", "matKhau_DkEye");
        bindEye("matKhau_XnPass", "matKhau_XnEye");
        bindEye("matKhau_ResetPass", "matKhau_ResetEye");
        bindEye("matKhau_ResetConfirmPass", "matKhau_ResetConfirmEye");

        // Hàm gọi API chung kèm hiệu ứng Loading chống nhấn đúp
        async function callApi(url, method, payload, btnEl, loadingLabel) {
          let origText = "";
          if (btnEl) {
            if (btnEl.classList.contains("Auth-ButtonDisabled")) return null;
            origText = btnEl.textContent;
            btnEl.textContent = loadingLabel;
            btnEl.classList.add("Auth-ButtonDisabled");
          }
          try {
            const headers = {
              "Accept": "application/json",
              "X-CSRF-TOKEN": window.HUIT_CSRF_TOKEN || ""
            };
            const token = sessionStorage.getItem("huit_auth_token");
            if (token) headers["Authorization"] = "Bearer " + token;
            if (payload !== null) headers["Content-Type"] = "application/json";

            const res = await fetch(url, {
              method: method,
              headers: headers,
              body: payload !== null ? JSON.stringify(payload) : null
            });
            const data = await res.json();
            return { status: res.status, ...data };
          } catch (err) {
            return { status: 500, success: false, message: "Không thể kết nối máy chủ. Vui lòng thử lại." };
          } finally {
            if (btnEl) {
              btnEl.textContent = origText;
              btnEl.classList.remove("Auth-ButtonDisabled");
            }
          }
        }

        // 1. Xử lý Đăng ký (4 trường)
        const btnSignUp = document.getElementById("btnSignUp");
        btnSignUp?.addEventListener("click", async function () {
          const username = document.getElementById("dk_Username")?.value.trim() || "";
          const email = document.getElementById("dk_Email")?.value.trim() || "";
          const password = document.getElementById("matKhau_DkPass")?.value || "";
          const confirmPassword = document.getElementById("matKhau_XnPass")?.value || "";

          if (!username || !email || !password || !confirmPassword) {
            showAlert("Vui lòng nhập đầy đủ cả 4 trường thông tin bắt buộc.", "error");
            return;
          }
          if (!/^[a-zA-Z0-9_]{3,30}$/.test(username)) {
            showAlert("Tên đăng nhập phải từ 3–30 ký tự (chữ cái, số hoặc dấu _).", "error");
            return;
          }
          if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showAlert("Địa chỉ email không đúng định dạng hợp lệ.", "error");
            return;
          }
          if (password.length < 8 || !/[A-Za-z]/.test(password) || !/[0-9]/.test(password)) {
            showAlert("Mật khẩu phải từ 8 ký tự trở lên, gồm cả chữ cái và chữ số.", "error");
            return;
          }
          if (password !== confirmPassword) {
            showAlert("Mật khẩu xác nhận không trùng khớp.", "error");
            return;
          }

          const res = await callApi("/api/auth/register", "POST", { username, email, password, confirmPassword }, btnSignUp, "Đang tạo...");
          if (!res) return;

          if (res.success) {
            document.getElementById("dk_Username").value = "";
            document.getElementById("dk_Email").value = "";
            document.getElementById("matKhau_DkPass").value = "";
            document.getElementById("matKhau_XnPass").value = "";
            showOnlyScreen(screenLogin);
            document.getElementById("dn_Identifier").value = username;
            showAlert(res.message || "Đăng ký thành công! Vui lòng đăng nhập.", "success");
          } else {
            showAlert(res.message || "Đăng ký thất bại.", "error");
          }
        });

        // 2. Xử lý Đăng nhập (Username hoặc Email)
        const btnSignIn = document.getElementById("btnSignIn");
        btnSignIn?.addEventListener("click", async function () {
          const identifier = document.getElementById("dn_Identifier")?.value.trim() || "";
          const password = document.getElementById("matKhau_DnPass")?.value || "";

          if (!identifier || !password) {
            showAlert("Vui lòng nhập Tên đăng nhập/Email và Mật khẩu.", "error");
            return;
          }

          const res = await callApi("/api/auth/login", "POST", { identifier, password }, btnSignIn, "Đang đăng nhập...");
          if (!res) return;

          if (res.success && res.data) {
            window.currentAuthenticatedUser = res.data;
            if (res.auth_token) sessionStorage.setItem("huit_auth_token", res.auth_token);
            sessionStorage.setItem("huit_user_id", String(res.data.id || res.data.user_id));
            sessionStorage.setItem("huit_username", res.data.username);
            localStorage.setItem("userHienTai", res.data.username);
            updateLoggedInUi(res.data);
            document.getElementById("dn_Identifier").value = "";
            document.getElementById("matKhau_DnPass").value = "";
            closeAuthModal();
          } else {
            showAlert(res.message || "Tên đăng nhập/Email hoặc mật khẩu không chính xác.", "error");
          }
        });

        async function performLogout() {
          await callApi("/api/auth/logout", "POST", {});
          window.currentAuthenticatedUser = null;
          document.getElementById("myProfileLink").style.display = "none";
          sessionStorage.removeItem("huit_auth_token");
          sessionStorage.removeItem("huit_user_id");
          sessionStorage.removeItem("huit_username");
          localStorage.removeItem("userHienTai");
          nutDangNhap.innerText = "Login | Register";
          if (typeof window.capNhatHienThiDiemCao === "function") window.capNhatHienThiDiemCao(0);
        }

        function updateLoggedInUi(userObj) {
          if (!userObj) return;
          document.getElementById("myProfileLink").style.display = "flex";
          const uid = userObj.user_id || userObj.id;
          nutDangNhap.innerText = userObj.username + (uid ? " (#" + uid + ")" : "");
          if (typeof window.capNhatHienThiDiemCao === "function") {
            window.capNhatHienThiDiemCao(userObj.highScore ?? userObj.high_score ?? 0);
          }
        }

        // 3. Xử lý 6 ô nhập OTP & Đồng hồ đếm ngược 60 giây
        const otpDigits = Array.from(document.querySelectorAll(".OTP-DigitInput"));
        const otpCountdownText = document.getElementById("otpCountdownText");
        const linkResendOtp = document.getElementById("linkResendOtp");
        const btnSendOtp = document.getElementById("btnSendOtp");
        const btnVerifyOtp = document.getElementById("btnVerifyOtp");
        const btnResetPasswordSubmit = document.getElementById("btnResetPasswordSubmit");

        otpDigits.forEach((input, idx) => {
          input.addEventListener("input", (e) => {
            const val = e.target.value.replace(/[^0-9]/g, "");
            e.target.value = val;
            if (val && idx < otpDigits.length - 1) otpDigits[idx + 1].focus();
          });
          input.addEventListener("keydown", (e) => {
            if (e.key === "Backspace" && !input.value && idx > 0) otpDigits[idx - 1].focus();
          });
          input.addEventListener("paste", (e) => {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData("text").replace(/[^0-9]/g, "").slice(0, 6);
            for (let i = 0; i < otpDigits.length; i++) otpDigits[i].value = pasted[i] || "";
            if (pasted.length > 0) otpDigits[Math.min(5, pasted.length - 1)].focus();
          });
        });

        function clearOtpInputs() {
          otpDigits.forEach((el) => { el.value = ""; });
          if (otpDigits[0]) otpDigits[0].focus();
        }

        function stopOtpTimer() {
          if (otpState.timerInterval) {
            clearInterval(otpState.timerInterval);
            otpState.timerInterval = null;
          }
        }

        function startOtpCountdown(seconds = 60) {
          stopOtpTimer();
          otpState.secondsRemaining = seconds;
          if (linkResendOtp) {
            linkResendOtp.classList.add("Auth-ButtonDisabled");
            linkResendOtp.textContent = "Gửi lại mã (" + seconds + "s)";
          }

          const renderTimer = () => {
            if (!otpCountdownText) return;
            if (otpState.secondsRemaining > 0) {
              otpCountdownText.textContent = "⏱  Thời gian còn lại: " + otpState.secondsRemaining + "s";
              if (linkResendOtp) linkResendOtp.textContent = "Gửi lại mã (" + otpState.secondsRemaining + "s)";
            } else {
              otpCountdownText.textContent = "⚠️ Mã OTP đã hết hiệu lực (60 giây). Vui lòng gửi lại mã mới!";
              if (linkResendOtp) {
                linkResendOtp.classList.remove("Auth-ButtonDisabled");
                linkResendOtp.textContent = "Gửi lại mã OTP mới";
              }
            }
          };

          renderTimer();
          otpState.timerInterval = setInterval(() => {
            otpState.secondsRemaining = Math.max(0, otpState.secondsRemaining - 1);
            renderTimer();
            if (otpState.secondsRemaining <= 0) stopOtpTimer();
          }, 1000);
        }

        // Bước 1 -> Bước 2: Gửi mã OTP 6 chữ số đến Email -> Chuyển sang màn hình Nhập OTP
        btnSendOtp?.addEventListener("click", async function () {
          const email = document.getElementById("qp_EmailInput")?.value.trim() || "";
          if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showAlert("Vui lòng nhập đúng định dạng địa chỉ email đã đăng ký.", "error");
            return;
          }

          const res = await callApi("/api/auth/forgot-password/send-otp", "POST", { email }, btnSendOtp, "Đang gửi OTP...");
          if (!res) return;

          if (res.success && res.data) {
            otpState.email = email;
            otpState.requestToken = res.data.request_token;
            otpState.resetToken = "";
            const lbl = document.getElementById("otpTargetEmailLabel");
            if (lbl) lbl.textContent = email;

            clearOtpInputs();
            showOnlyScreen(screenForgotOtp);
            startOtpCountdown(res.data.expires_in || 60);
            showAlert(res.message || "Đã gửi mã OTP 6 chữ số tới email của bạn.", "info");
          } else {
            showAlert(res.message || "Không thể gửi mã OTP lúc này.", "error");
          }
        });

        // Gửi lại mã OTP khi hết 60 giây
        linkResendOtp?.addEventListener("click", async function (e) {
          e.preventDefault();
          if (linkResendOtp.classList.contains("Auth-ButtonDisabled") || !otpState.email) return;

          const res = await callApi("/api/auth/forgot-password/resend-otp", "POST", { email: otpState.email }, linkResendOtp, "Đang gửi...");
          if (!res) return;

          if (res.success && res.data) {
            otpState.requestToken = res.data.request_token;
            otpState.resetToken = "";
            clearOtpInputs();
            startOtpCountdown(res.data.expires_in || 60);
            showAlert("Đã gửi mã OTP mới (hiệu lực 60 giây). Mã OTP cũ đã bị vô hiệu hóa.", "info");
          } else {
            showAlert(res.message || "Không thể gửi lại mã OTP.", "error");
          }
        });

        // Bước 2 -> Bước 3: Xác minh 6 chữ số OTP -> Chuyển sang màn hình Đặt lại mật khẩu
        btnVerifyOtp?.addEventListener("click", async function () {
          const otp = otpDigits.map((el) => el.value.trim()).join("");
          if (otp.length !== 6) {
            showAlert("Vui lòng nhập đầy đủ 6 chữ số OTP.", "error");
            return;
          }
          if (otpState.secondsRemaining <= 0) {
            showAlert("Mã OTP đã hết hiệu lực (60 giây). Vui lòng nhấn Gửi lại mã mới.", "error");
            return;
          }

          const res = await callApi("/api/auth/forgot-password/verify-otp", "POST", {
            email: otpState.email,
            request_token: otpState.requestToken,
            otp: otp
          }, btnVerifyOtp, "Đang xác minh...");
          if (!res) return;

          if (res.success && res.data && res.data.reset_token) {
            stopOtpTimer();
            otpState.resetToken = res.data.reset_token;
            document.getElementById("matKhau_ResetPass").value = "";
            document.getElementById("matKhau_ResetConfirmPass").value = "";
            showOnlyScreen(screenForgotReset);
            showAlert("Xác minh OTP thành công! Vui lòng nhập mật khẩu mới.", "success");
          } else {
            showAlert(res.message || "Mã OTP không đúng hoặc đã hết hạn.", "error");
          }
        });

        // Bước 3 -> Hoàn tất: Đặt lại mật khẩu mới -> Chuyển về màn hình Đăng nhập
        btnResetPasswordSubmit?.addEventListener("click", async function () {
          const newPassword = document.getElementById("matKhau_ResetPass")?.value || "";
          const confirmPassword = document.getElementById("matKhau_ResetConfirmPass")?.value || "";

          if (!otpState.requestToken || !otpState.resetToken) {
            showAlert("Vui lòng hoàn thành bước xác minh OTP trước khi đặt lại mật khẩu.", "error");
            showOnlyScreen(screenForgotEmail);
            return;
          }
          if (newPassword.length < 8 || !/[A-Za-z]/.test(newPassword) || !/[0-9]/.test(newPassword)) {
            showAlert("Mật khẩu mới phải có ít nhất 8 ký tự, bao gồm cả chữ cái và chữ số.", "error");
            return;
          }
          if (newPassword !== confirmPassword) {
            showAlert("Xác nhận mật khẩu mới không trùng khớp.", "error");
            return;
          }

          const res = await callApi("/api/auth/forgot-password/reset-password", "POST", {
            request_token: otpState.requestToken,
            reset_token: otpState.resetToken,
            password: newPassword,
            confirmPassword: confirmPassword
          }, btnResetPasswordSubmit, "Đang cập nhật...");
          if (!res) return;

          if (res.success) {
            const emailDone = otpState.email;
            otpState = { email: "", requestToken: "", resetToken: "", timerInterval: null, secondsRemaining: 0 };
            showOnlyScreen(screenLogin);
            document.getElementById("dn_Identifier").value = emailDone;
            showAlert(res.message || "Đổi mật khẩu thành công! Hãy đăng nhập bằng mật khẩu mới.", "success");
          } else {
            showAlert(res.message || "Không thể cập nhật mật khẩu.", "error");
          }
        });

        // Đồng bộ trạng thái đăng nhập khi mở trang
        if (window.currentAuthenticatedUser && window.currentAuthenticatedUser.username) {
          updateLoggedInUi(window.currentAuthenticatedUser);
        }
      })();
    </script>

    <!-- REAL-TIME PVP WEBSOCKET VÀ LOBBY MANAGER -->
    <script src="JS/TrangGame/PvpSocket.js"></script>
    <script src="JS/TrangChu/PvpLobby.js"></script>

    <!-- LOGIC TAB BẢNG XẾP HẠNG PVP REAL-TIME -->
    <script>
      (function() {
        const tabSingle = document.getElementById('tabLbSingle');
        const tabPvp = document.getElementById('tabLbPvp');
        const boxSingle = document.getElementById('boxLbSingle');
        const boxPvp = document.getElementById('boxLbPvp');
        const pvpTbody = document.getElementById('pvpRankedLeaderboardTbody');

        if (!tabSingle || !tabPvp) return;

        tabSingle.addEventListener('click', () => {
          tabSingle.className = 'btn btn-sm btn-info fw-bold';
          tabPvp.className = 'btn btn-sm btn-outline-warning fw-bold';
          if (boxSingle) boxSingle.style.display = 'flex';
          if (boxPvp) boxPvp.style.display = 'none';
        });

        tabPvp.addEventListener('click', async () => {
          tabPvp.className = 'btn btn-sm btn-warning fw-bold text-dark';
          tabSingle.className = 'btn btn-sm btn-outline-info fw-bold';
          if (boxSingle) boxSingle.style.display = 'none';
          if (boxPvp) boxPvp.style.display = 'block';

          // Tải danh sách Rank Elo từ Backend
          try {
            const res = await fetch('/api/pvp/leaderboard');
            const json = await res.json();
            if (json.success && Array.isArray(json.data) && pvpTbody) {
              pvpTbody.innerHTML = '';
              if (json.data.length === 0) {
                pvpTbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:15px; color:#94a3b8;">Chưa có đấu thủ nào thi đấu PvP.</td></tr>';
                return;
              }
              json.data.forEach((p, idx) => {
                const rankBadge = idx === 0 ? '🥇 #1' : idx === 1 ? '🥈 #2' : idx === 2 ? '🥉 #3' : `#${idx + 1}`;
                const winRate = p.win_rate !== undefined ? p.win_rate + '%' : '0%';
                pvpTbody.innerHTML += `
                  <tr style="border-bottom:1px solid #334155;">
                    <td style="padding:6px 4px; font-weight:800; color:#fde047;">${rankBadge}</td>
                    <td style="padding:6px 4px; font-weight:700; color:#fff;">${escapeHtml(p.player_name || p.username)}</td>
                    <td style="padding:6px 4px; text-align:center; font-weight:800; color:#00e5ff;">${p.rating || 1000}</td>
                    <td style="padding:6px 4px; text-align:center; color:#cbd5e1; font-size:12px;">${p.wins || 0}/${p.matches || 0} (${winRate})</td>
                    <td style="padding:6px 4px; text-align:right;">
                      <span class="badge" style="background:#1e293b; color:#fde047; border:1px solid #eab308; font-size:11px;">
                        ${p.tier?.badge || '👤'} ${p.tier?.name || 'Bạc'}
                      </span>
                    </td>
                  </tr>
                `;
              });
            }
          } catch (e) {
            if (pvpTbody) pvpTbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:15px; color:#ef4444;">Không thể tải bảng xếp hạng lúc này.</td></tr>';
          }
        });

        function escapeHtml(str) {
          return String(str || '').replace(/[&<>"']/g, s => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
          })[s]);
        }
      })();
    </script>
  </body>
</html>
