/**
 * =========================================================================================
 * PVP MATCH — ĐỒNG BỘ THỜI GIAN THỰC, BẢNG ĐIỂM TRONG TRẬN VÀ XÁC MINH KẾT QUẢ ĐỐI KHÁNG
 * =========================================================================================
 * - Đồng bộ tiến trình giải của 2-4 người chơi qua WebSocket presence channel
 * - Bảng xếp hạng trực tiếp (Live In-match Leaderboard): Rank, Tên, Cặp đã nối, Điểm, Trạng thái
 * - HỖ TRỢ KÉO THẢ (DRAGGABLE) & THU GỌN / MỞ RỘNG (COLLAPSIBLE) KHÔNG CHE MÀN HÌNH CHƠI
 * - Cập nhật tức thời tiến trình người chơi ngay trên máy cục bộ và đồng bộ lên server
 * - Báo cáo nước đi và gửi lời giải chuẩn xác về Backend (/api/pvp/submit-solution)
 * - Tự động đồng bộ và bảo vệ chống gian lận (Anti-cheat): Chặn Hint, Chặn AI Solve
 * - Hiển thị Modal Kết Quả Trận Đấu, biến động Rank Elo và Cấp bậc mới sau trận
 * =========================================================================================
 */

(function (window, document) {
  'use strict';

  // Kiểm tra nếu không phải chế độ PvP thì bỏ qua
  const gameMode = sessionStorage.getItem('gameMode');
  if (gameMode !== 'pvp') {
    return;
  }

  // State quản lý trận đấu PvP
  const matchState = {
    roomCode: sessionStorage.getItem('pvpRoomCode') || '',
    matchData: null,
    channel: null,
    userChannel: null,
    myUserId: null,
    matchDurationSeconds: 60,
    elapsedSeconds: 0,
    timerInterval: null,
    isCompleted: false,
    isMatchEnded: false,
    pollInterval: null,
    lastSubmitTime: 0,
    leaderboard: [],
    startTime: Date.now(),
    isCollapsed: false,
  };

  try {
    const rawMatch = sessionStorage.getItem('pvpMatchData');
    if (rawMatch) {
      matchState.matchData = JSON.parse(rawMatch);
      if (matchState.matchData.room_code) {
        matchState.roomCode = matchState.matchData.room_code;
      }
      if (matchState.matchData.duration_seconds || matchState.matchData.time_limit_seconds) {
        matchState.matchDurationSeconds = matchState.matchData.duration_seconds || matchState.matchData.time_limit_seconds;
      }
    }
  } catch (e) {
    console.error('[PvP Match] Lỗi đọc pvpMatchData:', e);
  }

  /**
   * Khởi tạo hệ thống thi đấu PvP khi trang tải xong
   */
  function initPvpMatch() {
    // 1. Xác định User ID hiện tại
    const user = window.HUIT_AUTH_USER || null;
    const storedUid = sessionStorage.getItem('huit_user_id');
    matchState.myUserId = user ? user.id : (storedUid ? parseInt(storedUid, 10) : null);

    // 2. Chống gian lận: Ẩn nút Gợi ý và Giải đáp
    chongGianLanPvP();

    // 3. Hiển thị bảng điểm PvP
    hienThiBangDiemPvP();

    // 4. Kích hoạt tính năng Kéo thả (Draggable) & Thu gọn (Collapsible)
    bindScoreboardControls();

    // 5. Tạo Modal hiển thị kết quả cuối trận
    taoModalKetQuaPvP();

    // 6. Kết nối WebSocket và subscribe các kênh
    khoiTaoWebSocketPvP();

    // 7. Bắt đầu đếm ngược thời gian thi đấu
    batDauDongHoPvP();

    // 8. TẢI NGAY dữ liệu người chơi từ server không chờ đợi
    taiBangDiemMoiNhat();

    // 9. Polling an toàn dự phòng mỗi 1.5 giây
    batDauPollingDuPhong();

    console.log('[PvP Match] Hệ thống PvP Real-time đã kích hoạt cho phòng:', matchState.roomCode, 'User:', matchState.myUserId);
  }

  /**
   * Chặn hoàn toàn các chức năng gợi ý hoặc giải đáp để đảm bảo tính công bằng
   */
  function chongGianLanPvP() {
    const nutGoiY = document.getElementById('nutGoiY');
    if (nutGoiY) {
      nutGoiY.style.display = 'none';
      nutGoiY.style.pointerEvents = 'none';
    }

    const nutDauHang = document.querySelectorAll('#nutDauHang, .NutDauHang');
    nutDauHang.forEach(el => {
      el.style.display = 'none';
      el.style.pointerEvents = 'none';
    });

    const khungDauHang = document.getElementById('khungDauHang');
    if (khungDauHang) khungDauHang.style.display = 'none';

    const lopPhuDauHang = document.getElementById('lopPhu_BangDauHang');
    if (lopPhuDauHang) lopPhuDauHang.style.display = 'none';
  }

  /**
   * Hiển thị bảng xếp hạng trực tiếp trong trận
   */
  function hienThiBangDiemPvP() {
    const scoreboard = document.getElementById('pvpScoreboardPanel');
    if (!scoreboard) return;

    scoreboard.style.display = 'block';
    const title = document.getElementById('pvpRoomTitle');
    if (title) {
      title.innerHTML = `
        <span style="opacity:0.65; cursor:grab; font-size:14px;" title="Giữ chuột/chạm để kéo di chuyển">⠿</span>
        <span>⚔️ PHÒNG: ${escapeHtml(matchState.roomCode || 'PVP')}</span>
      `;
    }

    // Nếu đã có danh sách người chơi từ matchData, render sơ bộ trước
    if (matchState.matchData && Array.isArray(matchState.matchData.players)) {
      matchState.leaderboard = matchState.matchData.players.map((p, idx) => ({
        user_id: p.user_id,
        player_name: p.player_name || ('Người chơi ' + p.user_id),
        rating: p.rating || 1000,
        tier: p.tier || { name: 'Bạc', badge: '🥈' },
        score: p.current_score || 0,
        pairs_connected: p.completed_pairs || 0,
        total_pairs: p.total_pairs || (matchState.matchData.map_data?.numPairs || 2),
        status: p.solve_status || 'playing',
        finish_time: null,
        rank: idx + 1,
      }));
      renderInMatchLeaderboard(matchState.leaderboard);
    }
  }

  /**
   * Gắn sự kiện KÉO THẢ (DRAGGABLE) VÀ THU GỌN / MỞ RỘNG (COLLAPSIBLE)
   */
  function bindScoreboardControls() {
    const panel = document.getElementById('pvpScoreboardPanel');
    const header = document.getElementById('pvpScoreboardHeader');
    const btnToggle = document.getElementById('btnToggleCollapsePvp');
    const body = document.getElementById('pvpScoreboardBody');
    const miniBar = document.getElementById('pvpScoreboardMiniBar');

    if (!panel || !header) return;

    // 1. CHỨC NĂNG THU GỌN / MỞ RỘNG
    btnToggle?.addEventListener('click', (e) => {
      e.stopPropagation();
      matchState.isCollapsed = !matchState.isCollapsed;

      if (matchState.isCollapsed) {
        body.style.display = 'none';
        miniBar.style.display = 'block';
        btnToggle.textContent = '➕';
        btnToggle.title = 'Mở rộng bảng xếp hạng';
        panel.style.width = '240px';
        renderMiniBarText();
      } else {
        body.style.display = 'block';
        miniBar.style.display = 'none';
        btnToggle.textContent = '➖';
        btnToggle.title = 'Thu gọn bảng xếp hạng';
        panel.style.width = '280px';
      }
    });

    // 2. CHỨC NĂNG KÉO THẢ DI CHUYỂN (MOUSE & TOUCH)
    let isDragging = false;
    let startX = 0;
    let startY = 0;
    let startLeft = 0;
    let startTop = 0;

    function onPointerDown(clientX, clientY, target) {
      if (target === btnToggle || btnToggle?.contains(target)) return;

      isDragging = true;
      header.style.cursor = 'grabbing';

      const rect = panel.getBoundingClientRect();
      startX = clientX;
      startY = clientY;
      startLeft = rect.left;
      startTop = rect.top;

      // Chuyển panel sang dùng left/top tuyệt đối
      panel.style.right = 'auto';
      panel.style.bottom = 'auto';
      panel.style.left = startLeft + 'px';
      panel.style.top = startTop + 'px';
    }

    function onPointerMove(clientX, clientY) {
      if (!isDragging) return;

      const deltaX = clientX - startX;
      const deltaY = clientY - startY;

      let newLeft = startLeft + deltaX;
      let newTop = startTop + deltaY;

      // Giới hạn biên màn hình
      const maxLeft = window.innerWidth - panel.offsetWidth - 6;
      const maxTop = window.innerHeight - panel.offsetHeight - 6;

      newLeft = Math.max(6, Math.min(newLeft, maxLeft));
      newTop = Math.max(6, Math.min(newTop, maxTop));

      panel.style.left = newLeft + 'px';
      panel.style.top = newTop + 'px';
    }

    function onPointerUp() {
      if (isDragging) {
        isDragging = false;
        header.style.cursor = 'grab';
      }
    }

    // Sự kiện Chuột
    header.addEventListener('mousedown', (e) => {
      onPointerDown(e.clientX, e.clientY, e.target);
    });

    window.addEventListener('mousemove', (e) => {
      onPointerMove(e.clientX, e.clientY);
    });

    window.addEventListener('mouseup', onPointerUp);

    // Sự kiện Cảm ứng (Touch)
    header.addEventListener('touchstart', (e) => {
      if (e.touches && e.touches[0]) {
        onPointerDown(e.touches[0].clientX, e.touches[0].clientY, e.target);
      }
    }, { passive: true });

    window.addEventListener('touchmove', (e) => {
      if (isDragging && e.touches && e.touches[0]) {
        onPointerMove(e.touches[0].clientX, e.touches[0].clientY);
      }
    }, { passive: true });

    window.addEventListener('touchend', onPointerUp);
  }

  /**
   * Cập nhật nội dung hiển thị thanh tóm tắt khi thu gọn
   */
  function renderMiniBarText() {
    const miniBar = document.getElementById('pvpScoreboardMiniBar');
    if (!miniBar) return;

    if (!matchState.leaderboard || matchState.leaderboard.length === 0) {
      miniBar.innerHTML = '⚔️ Đang kết nối trận đấu...';
      return;
    }

    const items = matchState.leaderboard.map((p, idx) => {
      const isMe = (p.user_id === matchState.myUserId);
      const badge = idx === 0 ? '🥇' : idx === 1 ? '🥈' : '🥉';
      const name = isMe ? 'Bạn' : escapeHtml(p.player_name.slice(0, 8));
      return `<span style="${isMe ? 'color:#38bdf8; font-weight:800;' : ''}">${badge} ${name}: ${p.score || 0}đ</span>`;
    });

    miniBar.innerHTML = items.join(' | ');
  }

  /**
   * Render danh sách người chơi lên bảng Live Leaderboard
   */
  function renderInMatchLeaderboard(players) {
    const container = document.getElementById('pvpPlayersList');
    if (!container) return;

    container.innerHTML = '';

    players.forEach((p, idx) => {
      const isMe = (p.user_id === matchState.myUserId);
      const isDone = (p.status === 'completed' || p.score >= 100);
      const isDisconn = (p.status === 'disconnected');

      const rankBadge = idx === 0 ? '🥇' : idx === 1 ? '🥈' : idx === 2 ? '🥉' : `#${idx + 1}`;
      const statusText = isDone
        ? `<span style="color:#00e676; font-weight:700;">✅ ${p.finish_time ? p.finish_time + 's' : 'Xong'}</span>`
        : isDisconn
        ? `<span style="color:#ef4444; font-weight:700;">🔴 Rời mạng</span>`
        : `<span style="color:#fde047;">⏳ Đang nối (${p.pairs_connected || 0}/${p.total_pairs || 2})</span>`;

      const borderStyle = isMe
        ? 'border: 2px solid #00e5ff; background: rgba(0, 229, 255, 0.14); box-shadow: 0 0 10px rgba(0,229,255,0.25);'
        : 'border: 1px solid #334155; background: #1e293b;';

      container.innerHTML += `
        <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 8px; border-radius:8px; margin-bottom:4px; ${borderStyle}">
          <div style="display:flex; align-items:center; gap:6px; min-width:0;">
            <span style="font-weight:900; font-size:14px; min-width:20px; text-align:center;">${rankBadge}</span>
            <div style="min-width:0;">
              <div style="font-weight:700; font-size:12.5px; color:#fff; text-overflow:ellipsis; overflow:hidden; white-space:nowrap; max-width:115px;">
                ${escapeHtml(p.player_name)} ${isMe ? '<strong style="color:#38bdf8;">(Bạn)</strong>' : ''}
              </div>
              <div style="font-size:10.5px;">${statusText}</div>
            </div>
          </div>
          <div style="text-align:right;">
            <div style="font-size:15px; font-weight:900; color:${p.score >= 100 ? '#00e676' : '#fde047'};">
              ${p.score || 0}đ
            </div>
            <div style="font-size:10px; color:#94a3b8;">${p.tier?.badge || '👤'} ${p.rating || 1000}</div>
          </div>
        </div>
      `;
    });

    renderMiniBarText();
  }

  /**
   * Khởi tạo kết nối WebSocket Real-time
   */
  function khoiTaoWebSocketPvP() {
    if (!window.PvpSocket) {
      console.warn('[PvP Match] PvpSocket chưa tải.');
      return;
    }

    window.PvpSocket.connect();

    // 1. Tham gia presence-room.{roomCode}
    const channelName = 'presence-room.' + matchState.roomCode;
    matchState.channel = window.PvpSocket.join(channelName);

    // Lắng nghe cập nhật tiến trình của người chơi khác
    matchState.channel.listen('PlayerProgressUpdated', (data) => {
      capNhatTienTrinhNguoiChoi(data);
    });

    // Lắng nghe cập nhật điểm số
    matchState.channel.listen('PlayerScoreUpdated', (data) => {
      capNhatDiemNguoiChoi(data);
    });

    // Lắng nghe sự kiện người chơi hoàn thành bản đồ
    matchState.channel.listen('PlayerCompleted', (data) => {
      console.log('[PvP Match] Người chơi hoàn thành:', data);
      thongBaoNguoiChoiXong(data);
      taiBangDiemMoiNhat();
    });

    // Lắng nghe trạng thái kết nối
    matchState.channel.listen('PlayerConnectionStatusUpdated', (data) => {
      capNhatKetNoiNguoiChoi(data);
    });

    // Lắng nghe cập nhật bảng xếp hạng trận đấu
    matchState.channel.listen('MatchLeaderboardUpdated', (data) => {
      if (Array.isArray(data.leaderboard)) {
        matchState.leaderboard = data.leaderboard;
        renderInMatchLeaderboard(data.leaderboard);
      }
    });

    // Lắng nghe trận đấu kết thúc
    matchState.channel.listen('MatchEnded', (data) => {
      console.log('[PvP Match] Trận đấu kết thúc:', data);
      ketThucTranDau(data);
    });

    // Lắng nghe công bố kết quả trận đấu và biến động Rank Elo
    matchState.channel.listen('MatchResultsPublished', (data) => {
      console.log('[PvP Match] Công bố kết quả và Elo:', data);
      hienThiModalKetQua(data);
    });

    // 2. Kênh cá nhân private-user.{userId} để nhận thông báo Rank riêng
    if (matchState.myUserId) {
      const userChannelName = 'private-user.' + matchState.myUserId;
      matchState.userChannel = window.PvpSocket.private(userChannelName);
      matchState.userChannel.listen('PlayerRankUpdated', (data) => {
        console.log('[PvP Match] Rank cá nhân cập nhật:', data);
      });
    }
  }

  /**
   * Cập nhật tiến trình của một người chơi trong mảng leaderboard
   */
  function capNhatTienTrinhNguoiChoi(data) {
    if (!data || !data.user_id) return;
    const player = matchState.leaderboard.find(p => p.user_id === data.user_id);
    if (player) {
      player.pairs_connected = data.pairs_connected;
      player.total_pairs = data.total_pairs || player.total_pairs;
      if (typeof data.current_score === 'number') {
        player.score = data.current_score;
      }
      sapXepVaRenderLeaderboard();
    }
  }

  function capNhatDiemNguoiChoi(data) {
    if (!data || !data.user_id) return;
    const player = matchState.leaderboard.find(p => p.user_id === data.user_id);
    if (player) {
      player.score = data.score;
      sapXepVaRenderLeaderboard();
    }
  }

  function capNhatKetNoiNguoiChoi(data) {
    if (!data || !data.user_id) return;
    const player = matchState.leaderboard.find(p => p.user_id === data.user_id);
    if (player) {
      player.status = data.status;
      renderInMatchLeaderboard(matchState.leaderboard);
    }
  }

  function thongBaoNguoiChoiXong(data) {
    const isMe = (data.user_id === matchState.myUserId);
    const msg = isMe
      ? `🎉 Bạn đã hoàn thành bản đồ trong ${data.finish_time}s! Đang chờ kết quả chung cuộc...`
      : `⚡ ${escapeHtml(data.player_name || 'Đối thủ')} đã hoàn thành trong ${data.finish_time}s!`;

    hienThongBaoNhanh(msg, isMe ? '#22c55e' : '#f59e0b');
  }

  function sapXepVaRenderLeaderboard() {
    matchState.leaderboard.sort((a, b) => {
      // 1. Hoàn thành trước xếp trên
      if (a.status === 'completed' && b.status !== 'completed') return -1;
      if (b.status === 'completed' && a.status !== 'completed') return 1;

      // 2. Điểm cao hơn xếp trên
      if ((b.score || 0) !== (a.score || 0)) {
        return (b.score || 0) - (a.score || 0);
      }

      // 3. Số cặp nối xong nhiều hơn xếp trên
      if ((b.pairs_connected || 0) !== (a.pairs_connected || 0)) {
        return (b.pairs_connected || 0) - (a.pairs_connected || 0);
      }

      // 4. Thời gian hoàn thành nhanh hơn xếp trên
      if (a.finish_time && b.finish_time) {
        return a.finish_time - b.finish_time;
      }

      return 0;
    });

    matchState.leaderboard.forEach((p, idx) => { p.rank = idx + 1; });
    renderInMatchLeaderboard(matchState.leaderboard);
  }

  /**
   * Đồng hồ đếm ngược thời gian thi đấu
   */
  function batDauDongHoPvP() {
    const badge = document.getElementById('pvpTimerBadge');
    let rem = matchState.matchDurationSeconds;

    clearInterval(matchState.timerInterval);
    matchState.timerInterval = setInterval(() => {
      if (matchState.isMatchEnded) {
        clearInterval(matchState.timerInterval);
        return;
      }

      matchState.elapsedSeconds++;
      rem = Math.max(0, matchState.matchDurationSeconds - matchState.elapsedSeconds);

      if (badge) {
        badge.textContent = `⏱  ${rem}s`;
        if (rem <= 10) {
          badge.className = 'badge bg-danger animate-pulse';
        } else if (rem <= 30) {
          badge.className = 'badge bg-warning text-dark';
        }
      }

      if (rem <= 0) {
        clearInterval(matchState.timerInterval);
        xuLyHetGioThiDau();
      }
    }, 1000);
  }

  /**
   * Xử lý khi hết giờ: tự động gửi kết quả hiện tại
   */
  function xuLyHetGioThiDau() {
    if (matchState.isCompleted || matchState.isMatchEnded) return;
    console.log('[PvP Match] Hết giờ thi đấu! Gửi điểm hiện tại...');

    const currentScore = window.diemHienTai100 || 0;
    const connectedCount = (window.danhSachDuongOng || []).filter(p => p.isConnected).length;
    const totalPairs = (window.danhSachDuongOng || []).length || 2;

    guiLoiGiaiVeServer({
      score: currentScore,
      duration: matchState.matchDurationSeconds,
      pairs_connected: connectedCount,
      total_pairs: totalPairs,
      is_timeout: true,
    });
  }

  /**
   * Polling an toàn dự phòng mỗi 1.5 giây
   */
  function batDauPollingDuPhong() {
    clearInterval(matchState.pollInterval);
    matchState.pollInterval = setInterval(() => {
      if (matchState.isMatchEnded) {
        clearInterval(matchState.pollInterval);
        return;
      }
      taiBangDiemMoiNhat();
    }, 1500);
  }

  async function taiBangDiemMoiNhat() {
    if (!matchState.roomCode) return;
    try {
      const res = await fetch(`/api/pvp/match-status?room_code=${encodeURIComponent(matchState.roomCode)}`);
      if (!res.ok) return;
      const json = await res.json();
      if (json.success && json.data) {
        if (Array.isArray(json.data.leaderboard)) {
          matchState.leaderboard = json.data.leaderboard;
          renderInMatchLeaderboard(json.data.leaderboard);
        }
        if (json.data.match_status === 'completed' && !matchState.isMatchEnded) {
          ketThucTranDau(json.data);
        }
      }
    } catch (e) {}
  }

  /**
   * Gửi báo cáo tiến trình (Throttle mỗi 600ms)
   */
  function baoCaoTienTrinhLenServer(progress) {
    const now = Date.now();
    if (now - matchState.lastSubmitTime < 600) return;
    matchState.lastSubmitTime = now;

    fetch('/api/pvp/submit-move', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        user_id: matchState.myUserId,
        room_code: matchState.roomCode,
        current_score: progress.currentScore || 0,
        tentative_score: progress.currentScore || 0,
        pairs_connected: progress.connectedPairs || 0,
        completed_pairs: progress.connectedPairs || 0,
        total_pairs: progress.totalPairs || 2,
        total_flow_cells: progress.totalFlowCells || 0,
      }),
    }).catch(() => {});
  }

  /**
   * Gửi lời giải chính thức lên Backend để xác minh và tính điểm chuẩn
   */
  async function guiLoiGiaiVeServer(result) {
    if (matchState.isCompleted) return;
    matchState.isCompleted = true;

    try {
      const res = await fetch('/api/pvp/submit-solution', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          user_id: matchState.myUserId,
          room_code: matchState.roomCode,
          score: result.score,
          duration: result.duration,
          pairs_connected: result.pairs_connected,
          total_pairs: result.total_pairs,
          total_flow_cells: result.total_flow_cells || 0,
        }),
      });

      const json = await res.json();
      console.log('[PvP Match] Phản hồi submit-solution:', json);

      if (json.success && json.match_completed && json.results) {
        hienThiModalKetQua(json);
      } else {
        taiBangDiemMoiNhat();
      }
    } catch (e) {
      console.error('[PvP Match] Lỗi gửi submit-solution:', e);
    }
  }

  /**
   * Kết thúc trận đấu
   */
  async function ketThucTranDau(data) {
    matchState.isMatchEnded = true;
    clearInterval(matchState.timerInterval);
    clearInterval(matchState.pollInterval);

    // Lấy bảng kết quả chính thức đầy đủ
    if (data.results) {
      hienThiModalKetQua(data);
    } else {
      try {
        const res = await fetch(`/api/pvp/match-results?room_code=${encodeURIComponent(matchState.roomCode)}`);
        const json = await res.json();
        if (json.success && json.data) {
          hienThiModalKetQua(json.data);
        }
      } catch (e) {}
    }
  }

  /**
   * Tạo Modal Kết Quả Trận Đấu (HTML & CSS)
   */
  function taoModalKetQuaPvP() {
    if (document.getElementById('pvpMatchResultsModal')) return;

    const modalHtml = `
      <div id="pvpMatchResultsModal" style="display:none; position:fixed; inset:0; z-index:999999; background:rgba(0,0,0,0.88); backdrop-filter:blur(10px); font-family:'Chakra Petch',sans-serif; justify-content:center; align-items:center;">
        <div style="background:#0f172a; border:2px solid #00e5ff; border-radius:20px; width:92%; max-width:680px; padding:28px 24px; box-shadow:0 0 45px rgba(0,229,255,0.45); color:#fff; position:relative; max-height:90vh; overflow-y:auto;">
          
          <!-- Tiêu đề Modal -->
          <div style="text-align:center; margin-bottom:20px;">
            <div style="font-size:13px; color:#ff80ab; font-weight:800; text-transform:uppercase; letter-spacing:1px;">KẾT QUẢ TRẬN ĐẤU REAL-TIME</div>
            <h2 id="pvpResultHeaderTitle" style="font-size:32px; font-weight:900; color:#00e5ff; margin:4px 0; text-shadow:0 0 15px rgba(0,229,255,0.6);">
              🏆 BẢNG TỔNG SẮP THI ĐẤU
            </h2>
            <div id="pvpResultRoomSubtitle" style="font-size:13px; color:#94a3b8;">Phòng: HUIT01 • 3 Người chơi</div>
          </div>

          <!-- Bảng xếp hạng chi tiết -->
          <div style="background:#1e293b; border-radius:14px; padding:12px; margin-bottom:20px; border:1px solid #334155;">
            <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13.5px;">
              <thead>
                <tr style="border-bottom:1px solid #475569; color:#94a3b8; font-size:12px; text-transform:uppercase;">
                  <th style="padding:8px 6px;">Hạng</th>
                  <th style="padding:8px 6px;">Đấu Thủ</th>
                  <th style="padding:8px 6px; text-align:center;">Điểm</th>
                  <th style="padding:8px 6px; text-align:center;">Thời gian</th>
                  <th style="padding:8px 6px; text-align:center;">Rank Elo</th>
                  <th style="padding:8px 6px; text-align:right;">Cấp bậc</th>
                </tr>
              </thead>
              <tbody id="pvpResultTableBody">
                <!-- Render động -->
              </tbody>
            </table>
          </div>

          <!-- Khung tổng kết riêng cho người chơi hiện tại -->
          <div id="pvpMyPersonalResultBox" style="background:linear-gradient(135deg, rgba(0,229,255,0.15), rgba(255,42,133,0.15)); border:2px solid #38bdf8; border-radius:12px; padding:14px 18px; margin-bottom:22px; text-align:center;">
            <!-- Render động bằng JS -->
          </div>

          <!-- Nút điều hướng -->
          <div style="display:flex; justify-content:center; gap:16px;">
            <button type="button" id="btnPvpReturnHome" class="btn btn-outline-light px-4 py-2 fw-bold" style="font-size:15px; border-radius:10px;">
              🏠 Về Trang Chủ
            </button>
            <button type="button" id="btnPvpPlayAgain" class="btn btn-primary px-4 py-2 fw-bold" style="font-size:15px; border-radius:10px; background:linear-gradient(135deg,#0284c7,#00e5ff); border:none; color:#000;">
              ⚔️ Đấu Trận Mới
            </button>
          </div>

        </div>
      </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);

    document.getElementById('btnPvpReturnHome')?.addEventListener('click', () => {
      sessionStorage.removeItem('pvpMatchData');
      window.location.href = '/index.html';
    });

    document.getElementById('btnPvpPlayAgain')?.addEventListener('click', () => {
      sessionStorage.removeItem('pvpMatchData');
      window.location.href = '/index.html';
    });
  }

  /**
   * Hiển thị Modal Kết Quả chính thức
   */
  function hienThiModalKetQua(data) {
    const modal = document.getElementById('pvpMatchResultsModal');
    if (!modal) return;

    modal.style.display = 'flex';

    const results = data.standings || data.results || matchState.leaderboard || [];
    const tbody = document.getElementById('pvpResultTableBody');
    const myBox = document.getElementById('pvpMyPersonalResultBox');

    if (tbody) tbody.innerHTML = '';

    const subtitle = document.getElementById('pvpResultRoomSubtitle');
    if (subtitle) {
      subtitle.textContent = `Phòng: ${matchState.roomCode} • ${results.length} Người chơi`;
    }

    let myResult = null;

    results.forEach((r, idx) => {
      const isMe = (r.user_id === matchState.myUserId);
      if (isMe) myResult = r;

      const rankBadge = idx === 0 ? '🥇 #1' : idx === 1 ? '🥈 #2' : idx === 2 ? '🥉 #3' : `#${idx + 1}`;
      const eloDelta = r.rating_change || 0;
      const eloDeltaStr = eloDelta > 0 ? `+${eloDelta}` : `${eloDelta}`;
      const eloColor = eloDelta > 0 ? '#22c55e' : eloDelta < 0 ? '#ef4444' : '#94a3b8';

      const rowBg = isMe ? 'background:rgba(0, 229, 255, 0.12); font-weight:700;' : '';

      if (tbody) {
        tbody.innerHTML += `
          <tr style="border-bottom:1px solid #334155; ${rowBg}">
            <td style="padding:10px 6px; font-weight:900; color:#fde047;">${rankBadge}</td>
            <td style="padding:10px 6px;">
              <span style="color:#fff;">${escapeHtml(r.player_name || ('User ' + r.user_id))}</span>
              ${isMe ? '<span style="color:#38bdf8; font-size:11px;"> (Bạn)</span>' : ''}
            </td>
            <td style="padding:10px 6px; text-align:center; font-weight:800; color:${r.score >= 100 ? '#00e676' : '#fde047'};">
              ${r.score || 0}đ
            </td>
            <td style="padding:10px 6px; text-align:center; color:#cbd5e1;">
              ${r.finish_time ? r.finish_time + 's' : 'Hết giờ'}
            </td>
            <td style="padding:10px 6px; text-align:center; font-weight:800; color:${eloColor};">
              ${r.new_rating || (r.rating ? r.rating + eloDelta : 1000)} (${eloDeltaStr})
            </td>
            <td style="padding:10px 6px; text-align:right;">
              <span class="badge" style="background:#334155; color:#fde047; font-size:11px;">
                ${r.new_tier?.badge || r.tier?.badge || '👤'} ${r.new_tier?.name || r.tier?.name || 'Bạc'}
              </span>
            </td>
          </tr>
        `;
      }
    });

    // Render thông điệp riêng cho người chơi
    if (myBox && myResult) {
      const myRank = myResult.placement || myResult.rank || 1;
      const myDelta = myResult.rating_change || 0;
      const isWin = myRank === 1;

      myBox.innerHTML = `
        <div style="font-size:18px; font-weight:800; color:${isWin ? '#00e676' : '#fde047'}; margin-bottom:6px;">
          ${isWin ? '👑 CHÚC MỪNG BẠN ĐÃ GIÀNH CHIẾN THẮNG!' : `BẠN ĐẠT HẠNG #${myRank} TRONG TRẬN ĐẤU`}
        </div>
        <div style="font-size:14px; color:#e2e8f0;">
          Điểm số: <strong style="color:#fde047;">${myResult.score || 0}đ</strong> • 
          Thời gian: <strong style="color:#38bdf8;">${myResult.finish_time ? myResult.finish_time + 's' : 'Hết giờ'}</strong> • 
          Biến động Elo: <strong style="color:${myDelta >= 0 ? '#22c55e' : '#ef4444'};">${myDelta >= 0 ? '+' + myDelta : myDelta} Điểm</strong>
        </div>
      `;
    }
  }

  function hienThongBaoNhanh(msg, color = '#38bdf8') {
    let box = document.getElementById('pvpQuickNotification');
    if (!box) {
      box = document.createElement('div');
      box.id = 'pvpQuickNotification';
      box.style.cssText =
        'position:fixed; top:75px; left:50%; transform:translateX(-50%); z-index:99999; padding:8px 18px; border-radius:10px; font-family:"Chakra Petch",sans-serif; font-size:14px; font-weight:700; color:#000; box-shadow:0 4px 15px rgba(0,0,0,0.5); pointer-events:none; transition:all 0.3s;';
      document.body.appendChild(box);
    }
    box.style.background = color;
    box.textContent = msg;
    box.style.display = 'block';
    box.style.opacity = '1';

    setTimeout(() => {
      box.style.opacity = '0';
      setTimeout(() => { box.style.display = 'none'; }, 300);
    }, 3500);
  }

  function escapeHtml(str) {
    return String(str || '').replace(/[&<>"']/g, s => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[s]);
  }

  // =========================================================================================
  // GIAO TIẾP VỚI BanDoGame.js QUA WINDOW.PvpMatch
  // =========================================================================================
  window.PvpMatch = {
    /**
     * Được gọi từ BanDoGame.js mỗi khi người chơi xoay ống hoặc tráo ống Vàng
     */
    onPlayerProgress: function (progress) {
      // 1. Cập nhật tức thời dòng của người chơi hiện tại trên bảng điểm cục bộ
      if (matchState.myUserId && Array.isArray(matchState.leaderboard)) {
        const me = matchState.leaderboard.find(p => p.user_id === matchState.myUserId);
        if (me) {
          me.score = progress.currentScore || 0;
          me.pairs_connected = progress.connectedPairs || 0;
          me.total_pairs = progress.totalPairs || me.total_pairs;
          sapXepVaRenderLeaderboard();
        }
      }

      // 2. Gửi tiến trình lên server
      baoCaoTienTrinhLenServer(progress);
    },

    /**
     * Được gọi từ BanDoGame.js khi người chơi nối đủ 100đ tất cả các đường ống
     */
    onPlayerVictory: function (victoryData) {
      console.log('[PvP Match] Người chơi đạt 100đ qua màn!', victoryData);
      const finishTime = ((Date.now() - matchState.startTime) / 1000).toFixed(1);

      guiLoiGiaiVeServer({
        score: victoryData.finalScore || 100,
        duration: parseFloat(finishTime),
        pairs_connected: (victoryData.pipes || []).length,
        total_pairs: (victoryData.pipes || []).length,
        total_flow_cells: victoryData.traces?.reduce((sum, t) => sum + (t.trace?.length || 0), 0) || 0,
      });
    },

    getState: function () {
      return matchState;
    },
  };

  // Khởi chạy khi DOM sẵn sàng
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPvpMatch);
  } else {
    initPvpMatch();
  }

})(window, document);
