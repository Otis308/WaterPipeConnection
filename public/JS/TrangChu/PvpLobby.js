/**
 * =========================================================================================
 * PVP LOBBY — QUẢN LÝ PHÒNG CHỜ, GHÉP TRẬN NGẪU NHIÊN VÀ BẢNG XẾP HẠNG RANK
 * =========================================================================================
 * - Phân chia rõ ràng: "Tạo Phòng Mới" và "Tham Gia Bằng Mã Phòng"
 * - Vào phòng chờ: Chỉ khi ĐỦ NGƯỜI, khách mới thấy nút "Sẵn Sàng"
 * - Chủ phòng chỉ có thể "Bắt Đầu Trận Đấu" khi ĐỦ NGƯỜI và TẤT CẢ KHÁCH ĐÃ SẴN SÀNG
 * - Đồng bộ real-time qua WebSocket kết hợp Polling 1.5s dự phòng bảo đảm chắc chắn
 * =========================================================================================
 */

(function (window, document) {
  'use strict';

  // Trạng thái Lobby
  const pvpState = {
    currentRoom: null,
    isHost: false,
    isReady: false,
    channel: null,
    roomPollInterval: null,
    matchmakingTimer: null,
    matchmakingSeconds: 0,
    matchmakingPoll: null,
    matchmakingRun: 0,
  };

  /**
   * Khởi tạo giao diện phòng chờ PvP và gắn các sự kiện
   */
  function initPvpLobby() {
    createWaitingRoomModal();
    createMatchmakingModal();
    bindHomeButtons();

    // Kết nối WebSocket sẵn sàng
    if (window.PvpSocket) {
      window.PvpSocket.connect();
    }
  }

  /**
   * Tạo Modal Phòng Chờ (Waiting Room Overlay)
   */
  function createWaitingRoomModal() {
    if (document.getElementById('pvpWaitingRoomModal')) return;

    const modalHtml = `
      <div id="pvpWaitingRoomModal" style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(0,0,0,0.85); backdrop-filter:blur(8px); font-family:'Chakra Petch',sans-serif; justify-content:center; align-items:center;">
        <div style="background:#0f172a; border:2px solid #ff2a85; border-radius:18px; width:92%; max-width:680px; padding:24px; box-shadow:0 0 35px rgba(255,42,133,0.4); color:#fff; position:relative;">
          
          <!-- Header Phòng -->
          <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #334155; padding-bottom:12px; margin-bottom:16px;">
            <div>
              <div style="font-size:13px; color:#ff80ab; font-weight:700; text-transform:uppercase;">Phòng Đấu PvP</div>
              <div style="display:flex; align-items:center; gap:8px;">
                <span style="font-size:26px; font-weight:900; color:#fde047;" id="pvpModalRoomCode">HUIT01</span>
                <button type="button" id="btnCopyRoomCode" class="btn btn-sm btn-outline-warning" style="font-size:12px; padding:2px 8px;">Sao chép</button>
              </div>
            </div>
            <div style="text-align:right;">
              <span id="pvpPlayerCountBadge" class="badge" style="background:#ff2a85; font-size:15px; padding:6px 12px;">2/3 Người chơi</span>
              <div id="pvpRoomConfigSummary" style="font-size:12px; color:#94a3b8; margin-top:4px;">5x8 • Trung bình • 2 cặp SE</div>
            </div>
          </div>

          <!-- Trạng thái phòng thông báo -->
          <div id="pvpRoomStatusAlert" style="text-align:center; padding:10px 14px; border-radius:10px; background:rgba(255,193,7,0.15); border:1.5px solid #fde047; color:#fde047; font-weight:700; margin-bottom:16px; font-size:14px; transition:all 0.3s ease;">
            ⏳ Đang chờ người chơi khác tham gia...
          </div>

          <!-- Danh sách Slots người chơi (2 - 4 người) -->
          <div id="pvpPlayerSlotsGrid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(135px, 1fr)); gap:12px; margin-bottom:20px;">
            <!-- Render động bằng JS -->
          </div>

          <!-- Bộ đếm ngược Countdown khi bắt đầu -->
          <div id="pvpCountdownBox" style="display:none; text-align:center; margin-bottom:16px;">
            <div style="font-size:14px; color:#38bdf8; font-weight:700;">TRẬN ĐẤU SẼ BẮT ĐẦU SAU</div>
            <div id="pvpCountdownNumber" style="font-size:56px; font-weight:900; color:#00e5ff; text-shadow:0 0 20px #00e5ff;">3</div>
          </div>

          <!-- Footer Nút thao tác -->
          <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #334155; padding-top:16px;">
            <button type="button" id="btnLeavePvpRoom" class="btn btn-outline-danger px-4 fw-bold">🚪 Rời Phòng</button>
            <div style="display:flex; gap:10px;">
              <!-- Nút Sẵn Sàng cho Khách (Chỉ hiện khi ĐỦ NGƯỜI) -->
              <button type="button" id="btnTogglePvpReady" class="btn btn-info px-4 fw-bold" style="display:none;">
                ✅ Sẵn Sàng
              </button>
              <!-- Nút Bắt Đầu cho Chủ Phòng (Chỉ hiện khi ĐỦ NGƯỜI và TẤT CẢ KHÁCH ĐÃ SẴN SÀNG) -->
              <button type="button" id="btnStartPvpMatch" class="btn btn-success px-4 fw-bold" style="background:linear-gradient(135deg, #22c55e, #16a34a); border:none; display:none;">
                ⚔️ BẮT ĐẦU TRẬN ĐẤU
              </button>
            </div>
          </div>

        </div>
      </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Gắn sự kiện các nút trong modal
    document.getElementById('btnCopyRoomCode')?.addEventListener('click', () => {
      const code = document.getElementById('pvpModalRoomCode')?.textContent;
      if (code) {
        navigator.clipboard.writeText(code);
        alert('Đã sao chép mã phòng: ' + code);
      }
    });

    document.getElementById('btnLeavePvpRoom')?.addEventListener('click', leaveCurrentRoom);
    document.getElementById('btnTogglePvpReady')?.addEventListener('click', toggleReadyStatus);
    document.getElementById('btnStartPvpMatch')?.addEventListener('click', startCurrentMatch);
  }

  /**
   * Tạo Modal Ghép Trận Ngẫu Nhiên (Random Matchmaking Overlay)
   */
  function createMatchmakingModal() {
    if (document.getElementById('pvpMatchmakingModal')) return;

    const modalHtml = `
      <div id="pvpMatchmakingModal" style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(0,0,0,0.85); backdrop-filter:blur(8px); font-family:'Chakra Petch',sans-serif; justify-content:center; align-items:center;">
        <div style="background:#0f172a; border:2px solid #00e5ff; border-radius:18px; width:90%; max-width:440px; padding:28px 20px; box-shadow:0 0 30px rgba(0,229,255,0.35); color:#fff; text-align:center;">
          
          <div style="width:64px; height:64px; border:4px solid #00e5ff; border-top-color:transparent; border-radius:50%; margin:0 auto 16px; animation:spin 1s linear infinite;"></div>
          
          <h4 style="color:#00e5ff; font-weight:800; margin-bottom:6px;">ĐANG TÌM TRẬN NGẪU NHIÊN</h4>
          <div id="mmPlayerCountText" style="color:#fde047; font-size:16px; font-weight:700; margin-bottom:4px;">Đã ghép: 1/3 người chơi</div>
          <div id="mmTimerText" style="color:#94a3b8; font-size:13px; margin-bottom:20px;">⏱  Thời gian chờ: 00:00</div>

          <div style="font-size:12px; color:#cbd5e1; background:rgba(255,255,255,0.06); padding:8px 12px; border-radius:8px; margin-bottom:20px; text-align:left;">
            • Đối thủ là <strong>người chơi thực</strong> đang trực tuyến.<br/>
            • Trận đấu chỉ bắt đầu khi đủ đúng số người yêu cầu.<br/>
            • Bản đồ thi đấu sẽ được đồng bộ cùng điều kiện 100%.
          </div>

          <button type="button" id="btnCancelMatchmaking" class="btn btn-outline-danger px-4 fw-bold">❌ Hủy Tìm Trận</button>
        </div>
      </div>
      <style>
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
      </style>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);

    document.getElementById('btnCancelMatchmaking')?.addEventListener('click', cancelMatchmaking);
  }

  /**
   * Gắn sự kiện các nút ở Trang Chủ
   */
  function bindHomeButtons() {
    // 1. Nút Tạo Phòng Mới
    const btnCreateRoomCode = document.getElementById('btnCreateRoomCode');
    btnCreateRoomCode?.addEventListener('click', async (e) => {
      e.preventDefault();
      if (!ensureLoggedIn()) return;

      const maxPlayers = parseInt(document.getElementById('pvpRoomMaxPlayers')?.value || '3', 10);
      const mapSize = getSelectedMapSize();
      const difficulty = getSelectedDifficulty();

      try {
        btnCreateRoomCode.disabled = true;
        btnCreateRoomCode.textContent = 'Đang tạo...';

        const myId = getMyUserId();
        const res = await fetch('/api/pvp/create-room', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            user_id: myId,
            max_players: maxPlayers,
            map_size: mapSize,
            difficulty: difficulty,
            num_pairs: 2,
            room_type: 'custom',
          }),
        });

        const json = await res.json();
        if (json.success && json.room_code) {
          const joinInput = document.getElementById('pvpJoinRoomInput');
          if (joinInput) joinInput.value = json.room_code;
          const oldInput = document.getElementById('pvpRoomCodeInput');
          if (oldInput) oldInput.value = json.room_code;

          openWaitingRoom(json.data || json.room);
        } else {
          alert(json.message || 'Không thể tạo phòng lúc này.');
        }
      } catch (err) {
        alert('Lỗi kết nối máy chủ khi tạo phòng.');
      } finally {
        btnCreateRoomCode.disabled = false;
        btnCreateRoomCode.textContent = '➕ Tạo Phòng Mới';
      }
    });

    // 2. Nút Tham Gia Bằng Mã Phòng
    const btnJoinRoomCode = document.getElementById('btnJoinRoomCode');
    btnJoinRoomCode?.addEventListener('click', async (e) => {
      e.preventDefault();
      if (!ensureLoggedIn()) return;

      const codeInput = document.getElementById('pvpJoinRoomInput');
      const code = (codeInput?.value || '').trim().toUpperCase();

      if (!code) {
        alert('Vui lòng nhập mã phòng (Ví dụ: HUIT01) để tham gia!');
        codeInput?.focus();
        return;
      }

      btnJoinRoomCode.disabled = true;
      btnJoinRoomCode.textContent = 'Đang vào...';

      try {
        await joinRoomByCode(code);
      } finally {
        btnJoinRoomCode.disabled = false;
        btnJoinRoomCode.textContent = '🚪 Tham Gia';
      }
    });

    // Cho phép ấn Enter tại ô nhập mã phòng
    const pvpJoinRoomInput = document.getElementById('pvpJoinRoomInput');
    pvpJoinRoomInput?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        btnJoinRoomCode?.click();
      }
    });

    // 3. Đánh chặn nút Bắt Đầu ở Trang Chủ nếu đang ở Tab PvP
    const nutBatDau = document.getElementById('play-NutBatDau') || document.querySelector('.Play-NutPlay');
    if (nutBatDau) {
      nutBatDau.addEventListener('click', function (e) {
        const isPvP = document.getElementById('cheDoPvP')?.classList.contains('active');
        if (!isPvP) return; // Nếu là 1P thì để TrangChu.js xử lý bình thường

        e.stopImmediatePropagation();
        e.preventDefault();

        handleStartPvP();
      }, true);
    }
  }

  /**
   * Điều hướng bắt đầu PvP khi người chơi nhấn nút Bắt Đầu lớn
   */
  function handleStartPvP() {
    if (!ensureLoggedIn()) return;

    const isRandomMode = document.getElementById('pvpTypeRandom')?.checked;

    if (isRandomMode) {
      // Chế độ Ghép trận ngẫu nhiên
      const opponentCount = parseInt(document.getElementById('pvpOpponentCount')?.value || '2', 10);
      const targetPlayers = opponentCount + 1;
      startMatchmaking(targetPlayers);
    } else {
      // Chế độ Tạo / Vào Phòng
      const codeInput = document.getElementById('pvpJoinRoomInput');
      const code = (codeInput?.value || '').trim().toUpperCase();

      if (code) {
        joinRoomByCode(code);
      } else {
        alert('Vui lòng nhập Mã Phòng và bấm "🚪 Tham Gia", hoặc bấm "➕ Tạo Phòng Mới"!');
        codeInput?.focus();
      }
    }
  }

  function getMyUserId() {
    if (window.currentAuthenticatedUser && (window.currentAuthenticatedUser.id || window.currentAuthenticatedUser.user_id)) {
      return window.currentAuthenticatedUser.id || window.currentAuthenticatedUser.user_id;
    }
    const stored = sessionStorage.getItem('huit_user_id');
    return stored ? parseInt(stored, 10) : null;
  }

  function ensureLoggedIn() {
    const uid = getMyUserId();
    if (!uid) {
      alert('Vui lòng ĐĂNG NHẬP tài khoản trước khi tham gia chế độ PvP!');
      const nutDangNhap = document.getElementById('nutDangNhap');
      if (nutDangNhap) nutDangNhap.click();
      return false;
    }
    return true;
  }

  function getSelectedMapSize() {
    const isCustom = document.getElementById('banDo_Custom')?.checked;
    if (isCustom) {
      return document.getElementById('dropdownMapSize')?.textContent.trim() || '5x8';
    }
    return '5x8';
  }

  function getSelectedDifficulty() {
    return sessionStorage.getItem('difficulty') || 'medium';
  }

  /**
   * Mở phòng chờ PvP và đăng ký lắng nghe WebSocket
   */
  function openWaitingRoom(roomData) {
    if (!roomData) return;
    pvpState.currentRoom = roomData;

    const modal = document.getElementById('pvpWaitingRoomModal');
    if (!modal) return;

    modal.style.display = 'flex';
    updateWaitingRoomUi(roomData);

    // Bắt đầu chu kỳ Polling dự phòng 1.5s bảo đảm luôn đồng bộ
    clearInterval(pvpState.roomPollInterval);
    pvpState.roomPollInterval = setInterval(() => {
      if (pvpState.currentRoom && pvpState.currentRoom.room_code) {
        refreshRoomStatus(pvpState.currentRoom.room_code);
      }
    }, 1500);

    // Subscribe vào presence channel của phòng: presence-room.{roomCode}
    if (window.PvpSocket) {
      const channelName = 'presence-room.' + roomData.room_code;
      pvpState.channel = window.PvpSocket.join(channelName);

      pvpState.channel.listen('RoomPlayerJoined', (data) => {
        console.log('[PvP] Người chơi tham gia phòng:', data);
        refreshRoomStatus(roomData.room_code);
      });

      pvpState.channel.listen('RoomPlayerLeft', (data) => {
        console.log('[PvP] Người chơi rời phòng:', data);
        refreshRoomStatus(roomData.room_code);
      });

      pvpState.channel.listen('RoomPlayerStatusUpdated', (data) => {
        console.log('[PvP] Cập nhật trạng thái người chơi:', data);
        refreshRoomStatus(roomData.room_code);
      });

      pvpState.channel.listen('RoomConfigurationUpdated', (data) => {
        refreshRoomStatus(roomData.room_code);
      });

      pvpState.channel.listen('RoomCountdownStarted', (data) => {
        showCountdown(data.countdown_seconds || 3);
      });

      pvpState.channel.listen('MatchStarted', (data) => {
        clearInterval(pvpState.roomPollInterval);
        launchIntoGame(data);
      });
    }
  }

  /**
   * Cập nhật giao diện phòng chờ từ dữ liệu Room
   * QUY TẮC BẮT BUỘC:
   * 1. Khi CHƯA ĐỦ NGƯỜI: Ẩn nút Sẵn Sàng và ẩn nút Bắt Đầu.
   * 2. Khi ĐỦ NGƯỜI:
   *    - Khách: Hiển thị nút "Sẵn Sàng" / "Hủy Sẵn Sàng".
   *    - Chủ phòng: Hiển thị nút "Bắt Đầu Trận Đấu", NHƯNG chỉ kích hoạt (enable) khi TẤT CẢ KHÁCH ĐÃ SẴN SÀNG!
   */
  function updateWaitingRoomUi(room) {
    const myId = getMyUserId();
    const isHost = (myId && (room.host_user_id === myId || room.host_id === myId));
    pvpState.isHost = isHost;

    document.getElementById('pvpModalRoomCode').textContent = room.room_code;
    document.getElementById('pvpPlayerCountBadge').textContent = `${room.current_players}/${room.max_players} Người chơi`;
    document.getElementById('pvpRoomConfigSummary').textContent = `${room.map_size} • ${room.difficulty?.toUpperCase()} • ${room.num_pairs} cặp ống`;

    const statusAlert = document.getElementById('pvpRoomStatusAlert');
    const btnToggleReady = document.getElementById('btnTogglePvpReady');
    const btnStart = document.getElementById('btnStartPvpMatch');
    const isFull = (room.current_players >= room.max_players);

    const players = room.players || [];
    const guests = players.filter(p => !p.is_host && p.user_id !== room.host_user_id);
    const allGuestsReady = (guests.length > 0 && guests.every(p => p.is_ready));

    const me = players.find(p => p.user_id === myId);
    const myReady = me ? !!me.is_ready : false;
    pvpState.isReady = myReady;

    if (!isFull) {
      // 1. CHƯA ĐỦ NGƯỜI: Tuyệt đối không cho sẵn sàng hay bắt đầu
      if (btnToggleReady) btnToggleReady.style.display = 'none';
      if (btnStart) btnStart.style.display = 'none';

      if (statusAlert) {
        statusAlert.style.background = 'rgba(255,193,7,0.15)';
        statusAlert.style.borderColor = '#fde047';
        statusAlert.style.color = '#fde047';
        statusAlert.textContent = `⏳ Đang chờ người chơi tham gia (${room.current_players}/${room.max_players})...`;
      }
    } else {
      // 2. ĐÃ ĐỦ NGƯỜI:
      if (!isHost) {
        // Luồng của Khách (Guest)
        if (btnStart) btnStart.style.display = 'none';
        if (btnToggleReady) {
          btnToggleReady.style.display = 'inline-block';
          if (myReady) {
            btnToggleReady.textContent = '❌ Hủy Sẵn Sàng';
            btnToggleReady.className = 'btn btn-outline-warning px-4 fw-bold';
          } else {
            btnToggleReady.textContent = '✅ Sẵn Sàng';
            btnToggleReady.className = 'btn btn-info px-4 fw-bold';
          }
        }

        if (statusAlert) {
          if (myReady) {
            statusAlert.style.background = 'rgba(34,197,94,0.2)';
            statusAlert.style.borderColor = '#22c55e';
            statusAlert.style.color = '#86efac';
            statusAlert.textContent = '✅ BẠN ĐÃ SẴN SÀNG! Đang chờ chủ phòng bắt đầu trận đấu...';
          } else {
            statusAlert.style.background = 'rgba(0,229,255,0.2)';
            statusAlert.style.borderColor = '#00e5ff';
            statusAlert.style.color = '#38bdf8';
            statusAlert.textContent = '🔔 PHÒNG ĐÃ ĐỦ NGƯỜI! Vui lòng bấm nút "Sẵn Sàng" bên dưới.';
          }
        }
      } else {
        // Luồng của Chủ Phòng (Host)
        if (btnToggleReady) btnToggleReady.style.display = 'none';
        if (btnStart) {
          btnStart.style.display = 'inline-block';

          if (allGuestsReady) {
            // Tất cả khách đã sẵn sàng -> Kích hoạt nút Bắt Đầu
            btnStart.disabled = false;
            btnStart.style.opacity = '1';
            btnStart.style.cursor = 'pointer';
            btnStart.style.background = 'linear-gradient(135deg, #22c55e, #16a34a)';
            btnStart.innerHTML = '⚔️ BẮT ĐẦU TRẬN ĐẤU';

            if (statusAlert) {
              statusAlert.style.background = 'rgba(34,197,94,0.25)';
              statusAlert.style.borderColor = '#22c55e';
              statusAlert.style.color = '#86efac';
              statusAlert.textContent = '🎉 TẤT CẢ NGƯỜI CHƠI ĐÃ SẴN SÀNG! Bạn có thể bấm "Bắt Đầu Trận Đấu" ngay!';
            }
          } else {
            // Còn người chưa sẵn sàng -> Khóa nút Bắt Đầu
            btnStart.disabled = true;
            btnStart.style.opacity = '0.55';
            btnStart.style.cursor = 'not-allowed';
            btnStart.style.background = '#475569';
            btnStart.innerHTML = '⏳ Chờ người chơi sẵn sàng...';

            const unreadyCount = guests.filter(p => !p.is_ready).length;
            if (statusAlert) {
              statusAlert.style.background = 'rgba(245,158,11,0.18)';
              statusAlert.style.borderColor = '#f59e0b';
              statusAlert.style.color = '#fde047';
              statusAlert.textContent = `⏳ ĐÃ ĐỦ NGƯỜI — Đang chờ ${unreadyCount} người chơi bấm "Sẵn Sàng"...`;
            }
          }
        }
      }
    }

    // Render Slots người chơi
    const grid = document.getElementById('pvpPlayerSlotsGrid');
    if (!grid) return;
    grid.innerHTML = '';

    for (let s = 1; s <= room.max_players; s++) {
      const player = players.find(p => p.seat_number === s);

      if (player) {
        const isMe = (player.user_id === myId);
        const isPlayerHost = !!player.is_host;
        const isPlayerReady = !!player.is_ready;

        let badgeBg = '#64748b';
        let badgeText = '⏳ Chưa sẵn sàng';
        if (isPlayerHost) {
          badgeBg = '#eab308';
          badgeText = '👑 Chủ phòng';
        } else if (isPlayerReady) {
          badgeBg = '#22c55e';
          badgeText = '✅ Đã sẵn sàng';
        }

        grid.innerHTML += `
          <div style="background:#1e293b; border:2px solid ${isPlayerHost ? '#fde047' : (isPlayerReady ? '#22c55e' : '#38bdf8')}; border-radius:12px; padding:12px 8px; text-align:center;">
            <div style="font-size:28px; margin-bottom:4px;">${player.tier?.badge || '👤'}</div>
            <div style="font-weight:700; color:#fff; font-size:13px; text-overflow:ellipsis; overflow:hidden; white-space:nowrap;">
              ${escapeHtml(player.player_name)} ${isMe ? '<span style="color:#86efac;">(Bạn)</span>' : ''}
            </div>
            <div style="font-size:11px; color:#fde047; font-weight:600;">${player.tier?.name || 'Bạc'} (${player.rating || 1000})</div>
            <div style="margin-top:6px;">
              <span class="badge" style="background:${badgeBg}; font-size:10px;">
                ${badgeText}
              </span>
            </div>
          </div>
        `;
      } else {
        grid.innerHTML += `
          <div style="background:rgba(30,41,59,0.4); border:2px dashed #475569; border-radius:12px; padding:16px 8px; text-align:center; color:#64748b;">
            <div style="font-size:26px; margin-bottom:4px; opacity:0.4;">➕</div>
            <div style="font-size:12px; font-weight:600;">Ghế ${s} Trống</div>
            <div style="font-size:10px; color:#475569;">Đang chờ người...</div>
          </div>
        `;
      }
    }
  }

  async function refreshRoomStatus(roomCode) {
    try {
      const myId = getMyUserId();
      const res = await fetch(`/api/pvp/room-status?room_code=${encodeURIComponent(roomCode)}&user_id=${myId || ''}`);
      if (!res.ok) return;
      const json = await res.json();
      if (json.success && json.data) {
        pvpState.currentRoom = json.data;
        updateWaitingRoomUi(json.data);

        // Nếu trận đấu đã bắt đầu trong lúc polling
        if (json.data.status === 'in_progress') {
          clearInterval(pvpState.roomPollInterval);
          launchIntoGame({
            room_code: json.data.room_code,
            map_data: json.data.map_data,
            players: json.data.players,
            duration_seconds: json.data.time_limit_seconds || 60,
          });
        }
      }
    } catch (e) {}
  }

  async function joinRoomByCode(roomCode) {
    try {
      const myId = getMyUserId();
      const res = await fetch('/api/pvp/join-room', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: myId, room_code: roomCode }),
      });
      const json = await res.json();
      if (json.success && (json.data || json.room)) {
        openWaitingRoom(json.data || json.room);
      } else {
        alert(json.message || 'Không thể vào phòng này. Vui lòng kiểm tra lại mã phòng!');
      }
    } catch (e) {
      alert('Lỗi kết nối khi tham gia phòng.');
    }
  }

  async function leaveCurrentRoom() {
    if (!pvpState.currentRoom) return;

    clearInterval(pvpState.roomPollInterval);

    try {
      const myId = getMyUserId();
      await fetch('/api/pvp/leave-room', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: myId, room_code: pvpState.currentRoom.room_code }),
      });
    } catch (e) {}

    if (pvpState.channel && window.PvpSocket) {
      window.PvpSocket.leave('presence-room.' + pvpState.currentRoom.room_code);
      pvpState.channel = null;
    }

    pvpState.currentRoom = null;
    const modal = document.getElementById('pvpWaitingRoomModal');
    if (modal) modal.style.display = 'none';
  }

  async function toggleReadyStatus() {
    if (!pvpState.currentRoom) return;
    try {
      const myId = getMyUserId();
      const res = await fetch('/api/pvp/toggle-ready', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: myId, room_code: pvpState.currentRoom.room_code }),
      });
      const json = await res.json();
      if (json.success) {
        refreshRoomStatus(pvpState.currentRoom.room_code);
      } else {
        alert(json.message || 'Không thể đổi trạng thái sẵn sàng lúc này.');
      }
    } catch (e) {
      alert('Lỗi kết nối khi sẵn sàng.');
    }
  }

  async function startCurrentMatch() {
    if (!pvpState.currentRoom || !pvpState.isHost) return;

    const btn = document.getElementById('btnStartPvpMatch');
    if (btn) btn.disabled = true;

    try {
      const myId = getMyUserId();
      const res = await fetch('/api/pvp/start-match', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: myId, room_code: pvpState.currentRoom.room_code }),
      });
      const json = await res.json();
      if (!json.success) {
        alert(json.message || 'Chưa thể bắt đầu trận đấu. Vui lòng đảm bảo tất cả người chơi đã sẵn sàng!');
        if (btn) btn.disabled = false;
      }
    } catch (e) {
      alert('Lỗi kết nối khi bắt đầu trận.');
      if (btn) btn.disabled = false;
    }
  }

  function showCountdown(seconds = 3) {
    const box = document.getElementById('pvpCountdownBox');
    const num = document.getElementById('pvpCountdownNumber');
    if (!box || !num) return;

    box.style.display = 'block';
    let rem = seconds;
    num.textContent = rem;

    const iv = setInterval(() => {
      rem--;
      if (rem > 0) {
        num.textContent = rem;
      } else {
        clearInterval(iv);
        num.textContent = 'CHIẾN!';
      }
    }, 1000);
  }

  function launchIntoGame(matchData) {
    clearInterval(pvpState.roomPollInterval);

    // Lưu các thông số đồng bộ vào sessionStorage
    sessionStorage.setItem('gameMode', 'pvp');
    sessionStorage.setItem('pvpRoomCode', matchData.room_code);
    sessionStorage.setItem('pvpMatchData', JSON.stringify(matchData));
    sessionStorage.setItem('mapSize', matchData.map_data?.mapSizeKey || matchData.map_data?.sizeKey || '5x8');
    sessionStorage.setItem('difficulty', matchData.map_data?.difficulty || 'medium');
    sessionStorage.setItem('numPairs', String(matchData.map_data?.numPairs || 2));

    // Chuyển sang màn hình chơi game TrangGame.html
    window.location.href = '/TrangGame.html';
  }

  // ==================== TÌM TRẬN NGẪU NHIÊN ====================

  async function requestMatchmaking(action, payload) {
    let res;
    try {
      res = await fetch(`/api/pvp/matchmaking/${action}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
      });
    } catch (e) {
      throw new Error('Không kết nối được máy chủ. Chưa xác nhận trạng thái tìm trận; hãy thử hủy tìm trận.');
    }
    let json;
    try {
      if (!(res.headers.get('Content-Type') || '').toLowerCase().includes('application/json')) throw new Error();
      json = await res.json();
    } catch (e) {
      throw new Error(`Máy chủ trả phản hồi không hợp lệ (HTTP ${res.status}). Vui lòng thử lại sau.`);
    }
    if (!res.ok || json?.success === false) {
      throw new Error(typeof json?.message === 'string' ? json.message : `Yêu cầu tìm trận thất bại (HTTP ${res.status}).`);
    }
    if (json?.success !== true || (action === 'join' &&
        (!['waiting', 'matched'].includes(json.data?.status) || (json.data.status === 'matched' && !json.data.room?.room_code)))) {
      throw new Error(`Dữ liệu tìm trận không hợp lệ (HTTP ${res.status}).`);
    }
    return json;
  }

  function stopMatchmakingTimers() {
    clearInterval(pvpState.matchmakingTimer);
    clearInterval(pvpState.matchmakingPoll);
    pvpState.matchmakingRun++;
  }

  function showMatchmakingError(error) {
    stopMatchmakingTimers();
    document.getElementById('mmPlayerCountText').textContent = error.message;
    alert(error.message);
  }

  async function startMatchmaking(targetPlayers = 3) {
    const modal = document.getElementById('pvpMatchmakingModal');
    if (!modal || modal.style.display === 'flex') return;

    stopMatchmakingTimers();
    const run = pvpState.matchmakingRun;
    modal.style.display = 'flex';
    document.getElementById('mmPlayerCountText').textContent = `Đang tìm trận (${targetPlayers} người chơi)...`;

    pvpState.matchmakingSeconds = 0;
    const timerEl = document.getElementById('mmTimerText');
    if (timerEl) timerEl.textContent = '⏱  Thời gian chờ: 00:00';

    clearInterval(pvpState.matchmakingTimer);
    pvpState.matchmakingTimer = setInterval(() => {
      pvpState.matchmakingSeconds++;
      const m = String(Math.floor(pvpState.matchmakingSeconds / 60)).padStart(2, '0');
      const s = String(pvpState.matchmakingSeconds % 60).padStart(2, '0');
      if (timerEl) timerEl.textContent = `⏱  Thời gian chờ: ${m}:${s}`;
    }, 1000);

    let json;
    try {
      const myId = getMyUserId();
      json = await requestMatchmaking('join', {
        user_id: myId,
        target_players: targetPlayers,
        preferred_size: getSelectedMapSize(),
        preferred_diff: getSelectedDifficulty(),
      });
    } catch (e) {
      if (run === pvpState.matchmakingRun) showMatchmakingError(e);
      return;
    }
    if (run !== pvpState.matchmakingRun) return;
    if (json.data.status === 'matched') {
      stopMatchmakingTimers();
      modal.style.display = 'none';
      openWaitingRoom(json.data.room);
    } else {
      document.getElementById('mmPlayerCountText').textContent = `Đã ghép: ${json.data.matched_count || 1}/${targetPlayers} người chơi`;
      pollMatchmakingQueue(targetPlayers);
    }
  }

  function pollMatchmakingQueue(targetPlayers) {
    const run = pvpState.matchmakingRun;
    let pending = false;
    pvpState.matchmakingPoll = setInterval(async () => {
      const modal = document.getElementById('pvpMatchmakingModal');
      if (!modal || modal.style.display === 'none') {
        stopMatchmakingTimers();
        return;
      }
      if (pending) return;
      pending = true;

      let json;
      try {
        const myId = getMyUserId();
        json = await requestMatchmaking('join', { user_id: myId, target_players: targetPlayers });
      } catch (e) {
        if (run === pvpState.matchmakingRun) showMatchmakingError(e);
        return;
      } finally {
        pending = false;
      }
      if (run !== pvpState.matchmakingRun) return;
      if (json.data.status === 'matched') {
        stopMatchmakingTimers();
        modal.style.display = 'none';
        openWaitingRoom(json.data.room);
      } else if (json.data.matched_count) {
        document.getElementById('mmPlayerCountText').textContent = `Đã ghép: ${json.data.matched_count}/${targetPlayers} người chơi`;
      }
    }, 1500);
  }

  async function cancelMatchmaking() {
    stopMatchmakingTimers();
    const modal = document.getElementById('pvpMatchmakingModal');

    try {
      const myId = getMyUserId();
      await requestMatchmaking('cancel', { user_id: myId });
      if (modal) modal.style.display = 'none';
    } catch (e) {
      showMatchmakingError(e);
    }
  }

  function escapeHtml(str) {
    return String(str || '').replace(/[&<>"']/g, s => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[s]);
  }

  // Xuất API công khai ra window.PvpLobby
  window.PvpLobby = {
    handleStartPvP,
    joinRoomByCode,
    openWaitingRoom,
    refreshRoomStatus,
  };

  // Tự động khởi tạo khi DOM sẵn sàng
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPvpLobby);
  } else {
    initPvpLobby();
  }

})(window, document);
