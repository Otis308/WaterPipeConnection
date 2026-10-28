<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Game Nối Ống Nước</title>

    <!-- Bootstrap -->
    <link rel="stylesheet" href="Bootstrap/CSS/bootstrap.min.css" />
    <script src="Bootstrap/JS/bootstrap.bundle.min.js"></script>

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Chakra+Petch:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet"
    />

    <link rel="stylesheet" type="text/css" href="CSS/TrangGame.css" />
    <link rel="stylesheet" type="text/css" href="CSS/PvpChat.css" />
  </head>
  <body>
    <!-- BẢN ĐỒ GAME -->
    <div class="KhungGame"></div>

    <section id="pvpChat" class="pvp-chat" hidden aria-label="Chat trong trận PvP">
      <button id="pvpChatToggle" class="pvp-chat-toggle" type="button" aria-expanded="true" aria-controls="pvpChatBody">Chat trong trận ▾</button>
      <div id="pvpChatBody">
        <ol id="pvpChatMessages" class="pvp-chat-messages" aria-label="Tin nhắn trong trận" tabindex="0"></ol>
        <button id="pvpChatNew" type="button" hidden>Có tin mới ↓</button>
        <p id="pvpChatStatus" role="status" aria-live="polite">Đang kết nối chat…</p>
        <form id="pvpChatForm">
          <label for="pvpChatInput">Tin nhắn (tối đa 500 ký tự)</label>
          <div class="pvp-chat-compose">
            <textarea id="pvpChatInput" rows="2" placeholder="Enter để gửi · Shift+Enter xuống dòng" disabled></textarea>
            <button id="pvpChatSend" type="submit" disabled>Gửi</button>
          </div>
        </form>
      </div>
    </section>

    <!-- NÚT DỪNG -->
    <div class="NutDung am-thanh-click" id="nutDung">
      <img src="Images/svg/Nut/Dung.svg" />
    </div>

    <!-- BẢNG DỪNG -->
    <div class="KhungDung" id="khungDung">
      <div class="BangDung">
        <!-- Nút Tiếng -->
        <div class="Dung-NutTieng" id="nutTieng">
          <img src="Images/svg/Nut/MoTieng.svg" id="imgTieng" />
        </div>

        <!-- Nút Nhạc -->
        <div class="Dung-NutNhac" id="nutNhac">
          <img src="Images/svg/Nut/MoNhac.svg" id="imgNhac" />
        </div>

        <!-- Nút Trang chủ -->
        <div class="Dung-NutTrangChu am-thanh-click">
          <img src="Images/svg/Nut/TrangChu.svg" />
        </div>

        <!-- Nút Tải lại -->
        <a href="TrangGame.html" class="Dung-NutTaiLai">
          <img src="Images/svg/Nut/TaiLai.svg" />
        </a>
      </div>

      <div class="Dung-NutThoat am-thanh-click" id="dung_NutThoat">
        <img src="Images/svg/Nut/BatDau.svg" />
      </div>
    </div>

    <!-- NÚT ĐẦU HÀNG -->
    <div class="NutDauHang am-thanh-click" id="nutDauHang">Give Up</div>

    <!-- THANH TRẠNG THÁI ĐA LUỒNG ỐNG & BẢNG ĐẤU ĐỐI KHÁNG PVP (2-4 NGƯỜI) -->
    <div id="hudMultiPipePanel"
         style="position:fixed; top:10px; left:50%; transform:translateX(-50%); z-index:900; background:rgba(15,23,42,0.92); border:2px solid #00e5ff; border-radius:12px; padding:6px 16px; color:#fff; font-family:'Chakra Petch',sans-serif; display:flex; align-items:center; gap:16px; box-shadow:0 4px 15px rgba(0,0,0,0.5);">
      <div>
        <span style="color:#fde047; font-weight:700;">Mục tiêu qua màn:</span>
        <strong style="color:#00e676; font-size:18px;">100đ</strong>
        <span style="font-size:12px; color:#cbd5e1;">(Đường ngắn nhất)</span>
      </div>
      <div id="hudPairsStatus" style="display:flex; gap:10px; align-items:center; font-size:13px; font-weight:700;">
        <!-- JS sẽ tự động render màu các cặp S1->E1, S2->E2... tại đây -->
      </div>
      <div id="hudScoreLive" style="background:#1e293b; padding:3px 10px; border-radius:8px; border:1px solid #fde047;">
        Điểm hiện tại: <strong id="valCurrentShortestScore" style="color:#fde047; font-size:17px;">0</strong>/100đ
      </div>
    </div>

    <!-- NÚT GỢI Ý (HINT) - GỢI Ý NƯỚC KẾ TIẾP CỦA ĐƯỜNG ỐNG CÒN LẠI -->
    <div class="am-thanh-click" id="nutGoiY"
         style="position:fixed; bottom:22px; right:195px; z-index:900; background:linear-gradient(135deg,#0284c7,#00e5ff); color:#000; font-family:'Chakra Petch',sans-serif; font-weight:800; font-size:17px; padding:10px 18px; border-radius:12px; border:3px solid #fff; cursor:pointer; box-shadow:0 4px 12px rgba(0,0,0,0.4);">
      💡 Gợi ý
    </div>

    <!-- NÚT GIẢI ĐÁP (AI SOLVE) -->
    <div class="NutDauHang am-thanh-click" id="nutDauHang" title="Nhờ AI tự động hoàn thành các đường ống ngắn nhất">
      🤖 Đáp án
    </div>

    <!-- BẢNG ĐỐI KHÁNG 2-4 NGƯỜI CHƠI (HIỂN THỊ KHI CHỌN CHẾ ĐỘ PVP) -->
    <div id="pvpScoreboardPanel"
         style="display:none; position:fixed; top:70px; right:14px; width:280px; z-index:890; background:rgba(15,23,42,0.94); border:2px solid #ff2a85; border-radius:14px; padding:10px 12px; color:#fff; font-family:'Chakra Petch',sans-serif; box-shadow:0 8px 24px rgba(0,0,0,0.65); touch-action:none;">
      
      <!-- HEADER BẢNG: KÉO THẢ DI CHUYỂN & NÚT THU GỌN / MỞ RỘNG -->
      <div id="pvpScoreboardHeader" style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #334155; padding-bottom:6px; margin-bottom:6px; cursor:grab; user-select:none; -webkit-user-select:none;">
        <span style="font-weight:800; color:#ff80ab; font-size:13.5px; display:flex; align-items:center; gap:6px;" id="pvpRoomTitle">
          <span style="opacity:0.65; cursor:grab; font-size:14px;" title="Giữ chuột/chạm để kéo di chuyển">⠿</span>
          <span>⚔️ PHÒNG PVP</span>
        </span>
        <div style="display:flex; align-items:center; gap:6px;">
          <span class="badge bg-warning text-dark" id="pvpTimerBadge" style="font-size:11.5px; font-weight:800;">⏱ 60s</span>
          <button type="button" id="btnToggleCollapsePvp" title="Thu gọn / Mở rộng"
                  style="background:rgba(255,42,133,0.18); border:1px solid #ff2a85; color:#ff80ab; border-radius:6px; padding:1px 7px; font-size:12px; font-weight:900; cursor:pointer; line-height:18px;">
            ➖
          </button>
        </div>
      </div>

      <!-- THANH TÓM TẮT MINI KHI THU GỌN (1 DÒNG GỌN GÀNG) -->
      <div id="pvpScoreboardMiniBar" style="display:none; font-size:12px; color:#fde047; font-weight:700; padding:2px 4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
        <!-- JS tự động cập nhật tóm tắt khi thu gọn -->
      </div>

      <!-- NỘI DUNG CHI TIẾT BẢNG ĐIỂM -->
      <div id="pvpScoreboardBody">
        <div style="font-size:11px; color:#94a3b8; margin-bottom:6px; display:flex; justify-content:space-between;">
          <span>Nối ngắn nhất: <strong style="color:#00e676;">100đ</strong></span>
          <span style="color:#cbd5e1; font-style:italic;">Kéo để dời vị trí</span>
        </div>
        <div id="pvpPlayersList" style="display:flex; flex-direction:column; gap:5px; font-size:13px; max-height:260px; overflow-y:auto;">
          <!-- Danh sách 2-4 đấu thủ và điểm số realtime -->
        </div>
      </div>

    </div>
    
    <!-- BẢNG ĐẦU HÀNG -->
    <div class="KhungDauHang" id="khungDauHang">
      <div class="BangDauHang">Would you like to view the solution?</div>

      <!-- Nút Thoát -->
      <div class="DauHang-NutThoat am-thanh-click" id="dauHang_NutThoat">
        <img src="Images/svg/Nut/Thoat.svg" />
      </div>

      <!-- Nút Yes/No -->
      <div class="DauHang-Yes am-thanh-click">Yes</div>
      <div class="DauHang-No am-thanh-click" id="dauHang_No">No</div>
    </div>

    <!-- NÚT CHI TIẾT -->
    <div class="NutChiTiet am-thanh-click" id="nutChiTiet">
      <img src="Images/svg/Nut/ChiTiet.svg" />
    </div>

    <!-- BẢNG CHI TIẾT -->
    <div class="KhungChiTiet" id="khungChiTiet">
      <div class="BangChiTiet">
        <ul>
          <li>Status:</li>
          <li class="ChiTiet-ChuThuong">...</li>
        </ul>
        <ul>
          <li>Number of steps: <span class="ChiTiet-ChuThuong">...</span></li>
          <li>Number of retries: <span class="ChiTiet-ChuThuong">...</span></li>
        </ul>
        <ul>
          <li>Current score: <span class="ChiTiet-ChuThuong">...</span></li>
          <li>Highest score: <span class="ChiTiet-ChuThuong">...</span></li>
        </ul>
      </div>
    </div>

    <!-- ỐNG SAO -->
    <div class="OngSao">
      <img src="Images/svg/Ong/OngSao.svg" />

      <!-- Số sao -->
      <div class="OngSao-Sao">
        <img src="Images/Sao.svg" />
        0
      </div>
    </div>

    <!-- ỐNG PHỤ -->
    <div>
      <div class="OngPhuTrai">
        <img src="Images/svg/Ong/OngPhu.svg" />
      </div>
      <div class="OngPhuPhai">
        <img src="Images/svg/Ong/OngPhu.svg" />
      </div>
    </div>

    <!-- ỐNG THỜI GIAN -->
    <div class="OngThoiGian">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 1100">
        <g>
          <g id="svg_80">
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="895.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="885.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="875.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="865.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="855.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="845.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="835.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="825.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="815.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="805.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="795.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="785.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="775.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="765.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="755.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="745.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="735.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="725.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="715.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="705.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="695.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="685.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="675.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="665.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="655.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="645.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="635.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="625.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="615.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="605.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="595.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="585.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="575.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="565.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="555.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="545.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="535.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="525.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="515.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="505.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="495.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="485.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="475.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="465.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="455.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="445.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="435.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="425.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="415.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="405.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="395.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="385.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="375.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="365.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="355.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="345.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="335.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="325.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="315.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="305.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="295.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="285.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="275.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="265.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="255.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="245.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="235.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="225.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="215.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="205.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="195.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
            <rect
              stroke-width="0"
              height="11"
              width="60"
              y="185.33333"
              x="70"
              stroke="#000"
              fill="#01caee"
              class="OngThoiGian_Nuoc"
            />
          </g>
          <g id="svg_37">
            <rect
              stroke="#d3fbfeff"
              stroke-width="4"
              id="svg_1"
              height="730.81818"
              width="61.72414"
              y="183.92423"
              x="69.13794"
              fill="none"
            />
            <g id="svg_33">
              <g id="svg_34">
                <g id="svg_30">
                  <path
                    transform="rotate(90 150.818 34.0157)"
                    fill="#1c5463"
                    d="m177.25995,-17.96168c0,14.35317 -11.8379,25.9887 -26.44069,25.9887m26.44069,-25.9887l0,0c0,14.35317 -11.8379,25.9887 -26.44069,25.9887c-14.60278,0 -26.44067,-11.6355 -26.44067,-25.9887m0,0l0,0c0,-14.35314 11.83789,-25.98868 26.44067,-25.98868c14.60279,0 26.44069,11.63554 26.44069,25.98868l0,103.95484c0,14.35314 -11.8379,25.98865 -26.44069,25.98865c-14.60278,0 -26.44067,-11.6355 -26.44067,-25.98865l0,-103.95484z"
                    id="svg_32"
                    stroke-width="0"
                    stroke="#1c5463"
                  />
                  <path
                    transform="rotate(90 144.115 34.243)"
                    stroke="#1c5463"
                    stroke-width="0"
                    id="svg_31"
                    d="m175.32302,28.67657c0,1.53714 -13.97181,2.78325 -31.2069,2.78325m31.2069,-2.78325l0,0c0,1.53714 -13.97181,2.78325 -31.2069,2.78325c-17.23509,0 -31.20688,-1.2461 -31.20688,-2.78325m0,0l0,0c0,-1.53715 13.97179,-2.78325 31.20688,-2.78325c17.23509,0 31.2069,1.2461 31.2069,2.78325l0,11.13301c0,1.53715 -13.97181,2.78324 -31.2069,2.78324c-17.23509,0 -31.20688,-1.24609 -31.20688,-2.78324l0,-11.13301z"
                    fill="#1c5463"
                  />
                </g>
                <g transform="rotate(180 178.845 156.676)" id="svg_23">
                  <g id="svg_27">
                    <line
                      stroke-width="8"
                      id="svg_29"
                      y2="171.98315"
                      x2="212.11877"
                      y1="171.98315"
                      x1="146.60716"
                      stroke="#bd7332"
                      fill="none"
                    />
                    <line
                      stroke-width="8"
                      id="svg_28"
                      y2="169.15884"
                      x2="211.08429"
                      y1="169.15884"
                      x1="145.57267"
                      stroke="#edb45e"
                      fill="none"
                    />
                  </g>
                  <g id="svg_24">
                    <line
                      stroke-width="8"
                      id="svg_26"
                      y2="144.22805"
                      x2="212.11877"
                      y1="144.22805"
                      x1="146.60716"
                      stroke="#bd7332"
                      fill="none"
                    />
                    <line
                      stroke-width="8"
                      id="svg_25"
                      y2="141.3709"
                      x2="212.11877"
                      y1="141.3709"
                      x1="146.60716"
                      stroke="#edb45e"
                      fill="none"
                    />
                  </g>
                </g>
                <g transform="rotate(180 99.5258 97.9537)" id="svg_5">
                  <g id="svg_18">
                    <path
                      stroke="#000"
                      fill="#bd7332"
                      stroke-width="0"
                      d="m139.11848,43.96615c0,5.2272 -17.72608,9.46468 -39.59231,9.46468m39.59231,-9.46468l0,0c0,5.2272 -17.72608,9.46468 -39.59231,9.46468c-21.86621,0 -39.59229,-4.23747 -39.59229,-9.46468m0,0l0,0c0,-5.22719 17.72607,-9.46468 39.59229,-9.46468c21.86623,0 39.59231,4.23749 39.59231,9.46468l0,37.85875c0,5.22719 -17.72608,9.46467 -39.59231,9.46467c-21.86621,0 -39.59229,-4.23747 -39.59229,-9.46467l0,-37.85875z"
                      id="svg_22"
                    />
                    <g id="svg_19">
                      <rect
                        stroke="#000"
                        fill="#bd7332"
                        x="42.30165"
                        y="17.9701"
                        width="114.59684"
                        height="45.98509"
                        id="svg_21"
                        stroke-width="0"
                      />
                      <path
                        stroke="#000"
                        fill="#bd7332"
                        stroke-width="0"
                        d="m42.00068,18.01484l21.57206,-10.21591l71.90688,0l21.57205,10.21591l-115.05099,0z"
                        id="svg_20"
                      />
                    </g>
                  </g>
                  <path
                    fill="#58aeb6"
                    d="m126.55226,81.34973c0,12.13193 -11.8379,21.9668 -26.44068,21.9668m26.44068,-21.9668l0,0c0,12.13193 -11.8379,21.9668 -26.44068,21.9668c-14.60278,0 -26.44067,-9.83485 -26.44067,-21.9668m0,0l0,0c0,-12.13192 11.83789,-21.9668 26.44067,-21.9668c14.60279,0 26.44068,9.83488 26.44068,21.9668l0,87.86725c0,12.13192 -11.8379,21.96677 -26.44068,21.96677c-14.60278,0 -26.44067,-9.83485 -26.44067,-21.96677l0,-87.86725z"
                    id="svg_17"
                    stroke-width="0"
                    stroke="#000"
                  />
                  <g id="svg_14">
                    <path
                      stroke="#000"
                      stroke-width="0"
                      id="svg_16"
                      d="m130.73307,95.84157c0,1.14266 -13.97181,2.06897 -31.2069,2.06897m31.2069,-2.06897l0,0c0,1.14266 -13.97181,2.06897 -31.2069,2.06897c-17.23509,0 -31.20688,-0.92631 -31.20688,-2.06897m0,0l0,0c0,-1.14265 13.97179,-2.06896 31.20688,-2.06896c17.23509,0 31.2069,0.92631 31.2069,2.06896l0,8.27587c0,1.14265 -13.97181,2.06896 -31.2069,2.06896c-17.23509,0 -31.20688,-0.92631 -31.20688,-2.06896l0,-8.27587z"
                      fill="#1c5463"
                    />
                    <path
                      stroke="#000"
                      stroke-width="0"
                      id="svg_15"
                      d="m130.73307,93.03455c0,1.14266 -13.9718,2.06897 -31.2069,2.06897m31.2069,-2.06897l0,0c0,1.14266 -13.9718,2.06897 -31.2069,2.06897c-17.23509,0 -31.20689,-0.92631 -31.20689,-2.06897m0,0l0,0c0,-1.14266 13.9718,-2.06896 31.20689,-2.06896c17.23509,0 31.2069,0.92631 31.2069,2.06896l0,8.27586c0,1.14266 -13.9718,2.06896 -31.2069,2.06896c-17.23509,0 -31.20689,-0.9263 -31.20689,-2.06896l0,-8.27586z"
                      fill="#286e87"
                    />
                  </g>
                  <g id="svg_11">
                    <path
                      stroke="#000"
                      stroke-width="0"
                      id="svg_13"
                      d="m130.73307,114.50405c0,1.53714 -13.97181,2.78325 -31.2069,2.78325m31.2069,-2.78325l0,0c0,1.53714 -13.97181,2.78325 -31.2069,2.78325c-17.23509,0 -31.20688,-1.2461 -31.20688,-2.78325m0,0l0,0c0,-1.53715 13.97179,-2.78325 31.20688,-2.78325c17.23509,0 31.2069,1.2461 31.2069,2.78325l0,11.13301c0,1.53715 -13.97181,2.78324 -31.2069,2.78324c-17.23509,0 -31.20688,-1.24609 -31.20688,-2.78324l0,-11.13301z"
                      fill="#1c5463"
                    />
                    <path
                      stroke="#000"
                      stroke-width="0"
                      id="svg_12"
                      d="m130.73307,111.65207c0,1.53714 -13.97181,2.78324 -31.2069,2.78324m31.2069,-2.78324l0,0c0,1.53714 -13.97181,2.78324 -31.2069,2.78324c-17.23509,0 -31.20688,-1.24609 -31.20688,-2.78324m0,0l0,0c0,-1.53715 13.97179,-2.78326 31.20688,-2.78326c17.23509,0 31.2069,1.24611 31.2069,2.78326l0,11.133c0,1.53715 -13.97181,2.78324 -31.2069,2.78324c-17.23509,0 -31.20688,-1.24609 -31.20688,-2.78324l0,-11.133z"
                      fill="#286e87"
                    />
                  </g>
                  <g id="svg_6">
                    <path
                      stroke="#000"
                      fill="#edb45e"
                      stroke-width="0"
                      d="m139.11848,40.89038c0,5.2272 -17.72608,9.46468 -39.59231,9.46468m39.59231,-9.46468l0,0c0,5.2272 -17.72608,9.46468 -39.59231,9.46468c-21.86621,0 -39.59229,-4.23747 -39.59229,-9.46468m0,0l0,0c0,-5.22719 17.72607,-9.46468 39.59229,-9.46468c21.86623,0 39.59231,4.23749 39.59231,9.46468l0,37.85875c0,5.22719 -17.72608,9.46467 -39.59231,9.46467c-21.86621,0 -39.59229,-4.23747 -39.59229,-9.46467l0,-37.85875z"
                      id="svg_10"
                    />
                    <g id="svg_7">
                      <rect
                        stroke="#000"
                        fill="#f4c271"
                        x="42.30165"
                        y="14.89433"
                        width="114.59684"
                        height="45.98509"
                        id="svg_9"
                        stroke-width="0"
                      />
                      <path
                        stroke="#000"
                        fill="#ffd896"
                        stroke-width="0"
                        d="m42.00068,14.93907l21.57206,-10.21591l71.90688,0l21.57205,10.21591l-115.05099,0z"
                        id="svg_8"
                      />
                    </g>
                  </g>
                </g>
                <g id="svg_2">
                  <path
                    transform="rotate(90 150.817 30.3123)"
                    fill="#58aeb6"
                    d="m177.25995,-21.66538c0,14.35317 -11.8379,25.9887 -26.44069,25.9887m26.44069,-25.9887l0,0c0,14.35317 -11.8379,25.9887 -26.44069,25.9887c-14.60278,0 -26.44067,-11.6355 -26.44067,-25.9887m0,0l0,0c0,-14.35314 11.83789,-25.98868 26.44067,-25.98868c14.60279,0 26.44069,11.63554 26.44069,25.98868l0,103.95484c0,14.35314 -11.8379,25.98865 -26.44069,25.98865c-14.60278,0 -26.44067,-11.6355 -26.44067,-25.98865l0,-103.95484z"
                    id="svg_4"
                    stroke-width="0"
                    stroke="#000"
                  />
                  <path
                    transform="rotate(90 144.115 30.5393)"
                    stroke="#000"
                    stroke-width="0"
                    id="svg_3"
                    d="m175.32302,24.97287c0,1.53714 -13.97181,2.78325 -31.2069,2.78325m31.2069,-2.78325l0,0c0,1.53714 -13.97181,2.78325 -31.2069,2.78325c-17.23509,0 -31.20688,-1.2461 -31.20688,-2.78325m0,0l0,0c0,-1.53715 13.97179,-2.78325 31.20688,-2.78325c17.23509,0 31.2069,1.2461 31.2069,2.78325l0,11.13301c0,1.53715 -13.97181,2.78324 -31.2069,2.78324c-17.23509,0 -31.20688,-1.24609 -31.20688,-2.78324l0,-11.13301z"
                    fill="#286e87"
                  />
                </g>
              </g>
            </g>
            <g id="svg_35">
              <g id="svg_36">
                <g id="svg_67">
                  <line
                    fill="none"
                    stroke="#bd7332"
                    x1="150.56802"
                    y1="955.60362"
                    x2="216.07963"
                    y2="955.60362"
                    id="svg_69"
                    stroke-width="8"
                  />
                  <line
                    fill="none"
                    stroke="#edb45e"
                    x1="149.53353"
                    y1="952.77931"
                    x2="215.04515"
                    y2="952.77931"
                    id="svg_68"
                    stroke-width="8"
                  />
                </g>
                <g id="svg_64">
                  <line
                    fill="none"
                    stroke="#bd7332"
                    x1="150.56802"
                    y1="927.84852"
                    x2="216.07963"
                    y2="927.84852"
                    id="svg_66"
                    stroke-width="8"
                  />
                  <line
                    fill="none"
                    stroke="#edb45e"
                    x1="150.56802"
                    y1="924.99137"
                    x2="216.07963"
                    y2="924.99137"
                    id="svg_65"
                    stroke-width="8"
                  />
                </g>
                <g id="svg_59">
                  <path
                    id="svg_63"
                    d="m139.1178,944.78796c0,5.2272 -17.72608,9.46468 -39.59231,9.46468m39.59231,-9.46468l0,0c0,5.2272 -17.72608,9.46468 -39.59231,9.46468c-21.86621,0 -39.59229,-4.23747 -39.59229,-9.46468m0,0l0,0c0,-5.22719 17.72607,-9.46468 39.59229,-9.46468c21.86623,0 39.59231,4.23749 39.59231,9.46468l0,37.85875c0,5.22719 -17.72608,9.46467 -39.59231,9.46467c-21.86621,0 -39.59229,-4.23747 -39.59229,-9.46467l0,-37.85875z"
                    stroke-width="0"
                    fill="#bd7332"
                    stroke="#000"
                  />
                  <g id="svg_60">
                    <rect
                      stroke-width="0"
                      id="svg_62"
                      height="45.98509"
                      width="114.59684"
                      y="918.79191"
                      x="42.30097"
                      fill="#bd7332"
                      stroke="#000"
                    />
                    <path
                      id="svg_61"
                      d="m42,918.83665l21.57206,-10.21591l71.90688,0l21.57205,10.21591l-115.05099,0z"
                      stroke-width="0"
                      fill="#bd7332"
                      stroke="#000"
                    />
                  </g>
                </g>
                <g id="svg_52">
                  <g id="svg_56">
                    <path
                      stroke="#000"
                      stroke-width="0"
                      id="svg_58"
                      d="m126.55158,985.34173c0,12.13193 -11.8379,21.9668 -26.44068,21.9668m26.44068,-21.9668l0,0c0,12.13193 -11.8379,21.9668 -26.44068,21.9668c-14.60278,0 -26.44067,-9.83485 -26.44067,-21.9668m0,0l0,0c0,-12.13192 11.83789,-21.9668 26.44067,-21.9668c14.60279,0 26.44068,9.83488 26.44068,21.9668l0,87.86725c0,12.13192 -11.8379,21.96677 -26.44068,21.96677c-14.60278,0 -26.44067,-9.83485 -26.44067,-21.96677l0,-87.86725z"
                      fill="#12595f"
                    />
                    <path
                      transform="rotate(90 152.301 1068.48)"
                      stroke="#000"
                      stroke-width="0"
                      id="svg_57"
                      d="m178.74075,1016.51041c0,14.35317 -11.8379,25.9887 -26.44069,25.9887m26.44069,-25.9887l0,0c0,14.35317 -11.8379,25.9887 -26.44069,25.9887c-14.60278,0 -26.44067,-11.6355 -26.44067,-25.9887m0,0l0,0c0,-14.35314 11.83789,-25.98868 26.44067,-25.98868c14.60279,0 26.44069,11.63554 26.44069,25.98868l0,103.95484c0,14.35314 -11.8379,25.98865 -26.44069,25.98865c-14.60278,0 -26.44067,-11.6355 -26.44067,-25.98865l0,-103.95484z"
                      fill="#12595f"
                    />
                  </g>
                  <g id="svg_53">
                    <path
                      stroke="#000"
                      stroke-width="0"
                      id="svg_55"
                      d="m126.55158,982.17154c0,12.13193 -11.8379,21.9668 -26.44068,21.9668m26.44068,-21.9668l0,0c0,12.13193 -11.8379,21.9668 -26.44068,21.9668c-14.60278,0 -26.44067,-9.83485 -26.44067,-21.9668m0,0l0,0c0,-12.13192 11.83789,-21.9668 26.44067,-21.9668c14.60279,0 26.44068,9.83488 26.44068,21.9668l0,87.86725c0,12.13192 -11.8379,21.96677 -26.44068,21.96677c-14.60278,0 -26.44067,-9.83485 -26.44067,-21.96677l0,-87.86725z"
                      fill="#58aeb6"
                    />
                    <path
                      transform="rotate(90 152.3 1065.31)"
                      stroke="#000"
                      stroke-width="0"
                      id="svg_54"
                      d="m178.74075,1013.34022c0,14.35317 -11.8379,25.9887 -26.44069,25.9887m26.44069,-25.9887l0,0c0,14.35317 -11.8379,25.9887 -26.44069,25.9887c-14.60278,0 -26.44067,-11.6355 -26.44067,-25.9887m0,0l0,0c0,-14.35314 11.83789,-25.98868 26.44067,-25.98868c14.60279,0 26.44069,11.63554 26.44069,25.98868l0,103.95484c0,14.35314 -11.8379,25.98865 -26.44069,25.98865c-14.60278,0 -26.44067,-11.6355 -26.44067,-25.98865l0,-103.95484z"
                      fill="#58aeb6"
                    />
                  </g>
                </g>
                <g id="svg_49">
                  <path
                    fill="#1c5463"
                    d="m130.73239,996.66338c0,1.14266 -13.97181,2.06897 -31.2069,2.06897m31.2069,-2.06897l0,0c0,1.14266 -13.97181,2.06897 -31.2069,2.06897c-17.23509,0 -31.20688,-0.92631 -31.20688,-2.06897m0,0l0,0c0,-1.14265 13.97179,-2.06896 31.20688,-2.06896c17.23509,0 31.2069,0.92631 31.2069,2.06896l0,8.27587c0,1.14265 -13.97181,2.06896 -31.2069,2.06896c-17.23509,0 -31.20688,-0.92631 -31.20688,-2.06896l0,-8.27587z"
                    id="svg_51"
                    stroke-width="0"
                    stroke="#000"
                  />
                  <path
                    fill="#286e87"
                    d="m130.73239,993.85636c0,1.14266 -13.9718,2.06897 -31.2069,2.06897m31.2069,-2.06897l0,0c0,1.14266 -13.9718,2.06897 -31.2069,2.06897c-17.23509,0 -31.20689,-0.92631 -31.20689,-2.06897m0,0l0,0c0,-1.14266 13.9718,-2.06896 31.20689,-2.06896c17.23509,0 31.2069,0.92631 31.2069,2.06896l0,8.27586c0,1.14266 -13.9718,2.06896 -31.2069,2.06896c-17.23509,0 -31.20689,-0.9263 -31.20689,-2.06896l0,-8.27586z"
                    id="svg_50"
                    stroke-width="0"
                    stroke="#000"
                  />
                </g>
                <g id="svg_46">
                  <path
                    fill="#1c5463"
                    d="m130.73239,1015.32586c0,1.53714 -13.97181,2.78325 -31.2069,2.78325m31.2069,-2.78325l0,0c0,1.53714 -13.97181,2.78325 -31.2069,2.78325c-17.23509,0 -31.20688,-1.2461 -31.20688,-2.78325m0,0l0,0c0,-1.53715 13.97179,-2.78325 31.20688,-2.78325c17.23509,0 31.2069,1.2461 31.2069,2.78325l0,11.13301c0,1.53715 -13.97181,2.78324 -31.2069,2.78324c-17.23509,0 -31.20688,-1.24609 -31.20688,-2.78324l0,-11.13301z"
                    id="svg_48"
                    stroke-width="0"
                    stroke="#000"
                  />
                  <path
                    fill="#286e87"
                    d="m130.73239,1012.47388c0,1.53714 -13.97181,2.78324 -31.2069,2.78324m31.2069,-2.78324l0,0c0,1.53714 -13.97181,2.78324 -31.2069,2.78324c-17.23509,0 -31.20688,-1.24609 -31.20688,-2.78324m0,0l0,0c0,-1.53715 13.97179,-2.78326 31.20688,-2.78326c17.23509,0 31.2069,1.24611 31.2069,2.78326l0,11.133c0,1.53715 -13.97181,2.78324 -31.2069,2.78324c-17.23509,0 -31.20688,-1.24609 -31.20688,-2.78324l0,-11.133z"
                    id="svg_47"
                    stroke-width="0"
                    stroke="#000"
                  />
                </g>
                <g id="svg_43">
                  <path
                    transform="rotate(90 145.597 1068.12)"
                    fill="#1c5463"
                    d="m176.80382,1062.55912c0,1.53714 -13.97181,2.78325 -31.2069,2.78325m31.2069,-2.78325l0,0c0,1.53714 -13.97181,2.78325 -31.2069,2.78325c-17.23509,0 -31.20688,-1.2461 -31.20688,-2.78325m0,0l0,0c0,-1.53715 13.97179,-2.78325 31.20688,-2.78325c17.23509,0 31.2069,1.2461 31.2069,2.78325l0,11.13301c0,1.53715 -13.97181,2.78324 -31.2069,2.78324c-17.23509,0 -31.20688,-1.24609 -31.20688,-2.78324l0,-11.13301z"
                    id="svg_45"
                    stroke-width="0"
                    stroke="#000"
                  />
                  <path
                    transform="rotate(90 145.598 1065.54)"
                    fill="#286e87"
                    d="m176.80382,1059.97847c0,1.53714 -13.97181,2.78325 -31.2069,2.78325m31.2069,-2.78325l0,0c0,1.53714 -13.97181,2.78325 -31.2069,2.78325c-17.23509,0 -31.20688,-1.2461 -31.20688,-2.78325m0,0l0,0c0,-1.53715 13.97179,-2.78325 31.20688,-2.78325c17.23509,0 31.2069,1.2461 31.2069,2.78325l0,11.13301c0,1.53715 -13.97181,2.78324 -31.2069,2.78324c-17.23509,0 -31.20688,-1.24609 -31.20688,-2.78324l0,-11.13301z"
                    id="svg_44"
                    stroke-width="0"
                    stroke="#000"
                  />
                </g>
                <g id="svg_38">
                  <path
                    id="svg_42"
                    d="m139.1178,941.71219c0,5.2272 -17.72608,9.46468 -39.59231,9.46468m39.59231,-9.46468l0,0c0,5.2272 -17.72608,9.46468 -39.59231,9.46468c-21.86621,0 -39.59229,-4.23747 -39.59229,-9.46468m0,0l0,0c0,-5.22719 17.72607,-9.46468 39.59229,-9.46468c21.86623,0 39.59231,4.23749 39.59231,9.46468l0,37.85875c0,5.22719 -17.72608,9.46467 -39.59231,9.46467c-21.86621,0 -39.59229,-4.23747 -39.59229,-9.46467l0,-37.85875z"
                    stroke-width="0"
                    fill="#edb45e"
                    stroke="#000"
                  />
                  <g id="svg_39">
                    <rect
                      stroke-width="0"
                      id="svg_41"
                      height="45.98509"
                      width="114.59684"
                      y="915.71614"
                      x="42.30097"
                      fill="#f4c271"
                      stroke="#000"
                    />
                    <path
                      id="svg_40"
                      d="m42,915.76088l21.57206,-10.21591l71.90688,0l21.57205,10.21591l-115.05099,0z"
                      stroke-width="0"
                      fill="#ffd896"
                      stroke="#000"
                    />
                  </g>
                </g>
              </g>
            </g>
          </g>
        </g>
      </svg>
    </div>

    <!-- BẢNG KẾT THÚC -->
    <div class="KhungKetThuc" id="khungKetThuc">
      <div class="BangKetThuc">
        <!-- Số sao Cao nhất -->
        <div class="KetThuc-CupSao">
          <img src="Images/CupSao.png" />
          0
        </div>

        <!-- Số sao -->
        <div class="KetThuc-Sao">
          <img src="Images/Sao.svg" />
          0
        </div>

        <!-- Bảng thông tin chế độ AI -->
        <div class="KetThuc-ThongTinAI">
          <p>Completion status: <span id="thongTinAI_Status">...</span></p>
          <p>Processing time: <span id="thongTinAI_Time">...s</span></p>
          <p>Current path length: <span id="thongTinAI_Length">...</span></p>
          <p>Current highest score: <span id="thongTinAI_Score">...</span></p>
          <p>Current retry count: <span id="thongTinAI_Retry">...</span></p>
        </div>
      </div>

      <!-- Nút trở về Trang Chủ -->
      <a
        href="index.html"
        class="KetThuc-NutTrangChu am-thanh-click"
        id="ketThuc_NutTrangChu"
      >
        <img src="Images/svg/Nut/TrangChu.svg" />
      </a>

      <!-- Chơi lại -->
      <a
        href="TrangGame.html"
        class="KetThuc-NutChoiLai am-thanh-click"
        id="ketThuc_NutChoiLai"
      >
        <img src="Images/svg/Nut/TaiLai.svg" />
      </a>
    </div>

    <!-- LỚP PHỦ -->
    <div class="LopPhu_BangDung" id="lopPhu_BangDung"></div>
    <div class="LopPhu_BangDauHang" id="lopPhu_BangDauHang"></div>
    <div class="LopPhu_BangKetThuc" id="lopPhu_BangKetThuc"></div>

    <!-- ÂM THANH -->
    <audio id="nhacNen" loop>
      <source src="Sounds/NhacNen.mp3" type="audio/mpeg" />
    </audio>
    <audio id="amThanhClick">
      <source src="Sounds/TiengClick.mp3" type="audio/mpeg" />
    </audio>
    <audio id="amThanhXoay">
      <source src="Sounds/XoayOng.mp3" type="audio/mpeg" />
    </audio>
    <audio id="amThanhThua">
      <source src="Sounds/Thua.mp3" type="audio/mpeg" />
    </audio>
    <audio id="amThanhChienThang">
      <source src="Sounds/ChienThang.mp3" type="audio/mpeg" />
    </audio>
    <audio id="amThanhDoiOng">
      <source src="Sounds/DoiOng.mp3" type="audio/mpeg" />
    </audio>

    <script src="JS/TrangGame/TrangGame.js"></script>
    <script src="JS/TrangGame/SVGnuoc.js"></script>
    <script src="JS/TrangGame/PipeScore.js"></script>
    <script src="JS/TrangGame/BanDoGame.js"></script>
    <script src="JS/TrangGame/CheDoAI.js"></script>
  
    <script id="huit-game-auth-data" type="application/json">
      {
        "user": <?php echo json_encode($currentUser ?? null, JSON_UNESCAPED_UNICODE); ?>,
        "csrf": "<?php echo csrf_token(); ?>"
      }
    </script>
    <script>
      (function() {
        var el = document.getElementById('huit-game-auth-data');
        var data = el ? JSON.parse(el.textContent || '{}') : {};
        window.HUIT_AUTH_USER = data.user || null;
        window.HUIT_CSRF_TOKEN = data.csrf || '';
      })();
    </script>
    <script src="JS/TrangGame/PvpSocket.js"></script>
    <script src="JS/TrangGame/PvpActionQueue.js"></script>
    <script src="JS/TrangGame/PvpMatch.js"></script>
    <script src="JS/TrangGame/PvpChat.js"></script>

  </body>
</html>
