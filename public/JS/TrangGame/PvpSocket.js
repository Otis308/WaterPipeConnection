/**
 * =========================================================================================
 * PVP SOCKET CLIENT — GIAO TIẾP REAL-TIME WEBSOCKET (LARAVEL ECHO COMPATIBLE)
 * Hỗ trợ Auto-Reconnect, Presence Channel, Heartbeat, Snapshot Recovery & Polling Fallback
 * =========================================================================================
 */

(function (window) {
  'use strict';

  class PvpChannel {
    constructor(channelName, socketClient) {
      this.name = channelName;
      this.client = socketClient;
      this.listeners = new Map(); // eventName -> Array<callback>
    }

    /**
     * Lắng nghe sự kiện (Laravel Echo compatible: .listen('RoomPlayerJoined', callback))
     */
    listen(eventName, callback) {
      if (!this.listeners.has(eventName)) {
        this.listeners.set(eventName, []);
      }
      this.listeners.get(eventName).push(callback);
      return this;
    }

    trigger(eventName, data) {
      const cbs = this.listeners.get(eventName);
      if (cbs) {
        cbs.forEach(cb => {
          try { cb(data); } catch (e) { console.error('[PvpSocket] Listener error:', e); }
        });
      }
    }
  }

  class PvpSocketClient {
    constructor() {
      this.socket = null;
      this.channels = new Map(); // channelName -> PvpChannel
      this.isConnected = false;
      this.reconnectAttempts = 0;
      this.maxReconnectAttempts = 20;
      this.reconnectTimer = null;
      this.pingInterval = null;
      this.fallbackPollingInterval = null;
      this.activeRoomCode = null;
      this.lastSyncSnapshot = null;

      // Xác định WebSocket URL
      const host = window.location.hostname || 'localhost';
      const port = '8080';
      this.wsUrl = `ws://${host}:${port}`;
    }

    /**
     * Khởi tạo kết nối WebSocket
     */
    connect() {
      if (this.socket && (this.socket.readyState === WebSocket.OPEN || this.socket.readyState === WebSocket.CONNECTING)) {
        return;
      }

      try {
        this.socket = new WebSocket(this.wsUrl);

        this.socket.onopen = () => {
          console.log('[PvpSocket] Đã kết nối WebSocket thành công:', this.wsUrl);
          this.isConnected = true;
          this.reconnectAttempts = 0;
          this.stopFallbackPolling();
          this.startHeartbeat();

          // Re-subscribe vào tất cả các kênh hiện tại
          for (const chName of this.channels.keys()) {
            this.sendSubscribe(chName);
          }

          // Kích hoạt phục hồi snapshot nếu đang trong trận
          if (this.activeRoomCode && typeof this.onReconnectSync === 'function') {
            this.onReconnectSync(this.activeRoomCode);
          }
        };

        this.socket.onmessage = (event) => {
          this.handleIncomingMessage(event.data);
        };

        this.socket.onclose = (event) => {
          console.warn('[PvpSocket] WebSocket ngắt kết nối. Mã:', event.code);
          this.isConnected = false;
          this.stopHeartbeat();
          this.scheduleReconnect();
        };

        this.socket.onerror = (err) => {
          console.warn('[PvpSocket] Lỗi kết nối WebSocket:', err);
          this.startFallbackPolling();
        };
      } catch (err) {
        console.warn('[PvpSocket] Trình duyệt không thể mở WebSocket, chuyển sang chế độ Polling Fallback:', err);
        this.startFallbackPolling();
      }
    }

    /**
     * Tham gia vào Presence / Private Channel
     */
    join(channelName) {
      if (!this.channels.has(channelName)) {
        const ch = new PvpChannel(channelName, this);
        this.channels.set(channelName, ch);

        if (channelName.includes('presence-room.')) {
          this.activeRoomCode = channelName.replace('presence-room.', '');
        }

        if (this.isConnected) {
          this.sendSubscribe(channelName);
        } else {
          this.connect();
        }
      }
      return this.channels.get(channelName);
    }

    leave(channelName) {
      if (this.channels.has(channelName)) {
        this.channels.delete(channelName);
        if (this.isConnected) {
          this.send({
            event: 'pusher:unsubscribe',
            channel: channelName,
          });
        }
      }
      if (channelName.includes('presence-room.') && this.activeRoomCode === channelName.replace('presence-room.', '')) {
        this.activeRoomCode = null;
        this.stopFallbackPolling();
      }
    }

    sendSubscribe(channelName) {
      const user = window.currentAuthenticatedUser || {};
      this.send({
        event: 'pusher:subscribe',
        channel: channelName,
        data: {
          channel: channelName,
          user_id: user.id || null,
          player_name: user.name || user.username || null,
        }
      });
    }

    send(dataObj) {
      if (this.socket && this.socket.readyState === WebSocket.OPEN) {
        this.socket.send(JSON.stringify(dataObj));
      }
    }

    handleIncomingMessage(rawText) {
      try {
        const msg = JSON.parse(rawText);
        const eventName = msg.event;
        const channelName = msg.channel;
        const eventData = msg.data;

        if (channelName && this.channels.has(channelName)) {
          const channel = this.channels.get(channelName);
          channel.trigger(eventName, eventData);
        }
      } catch (e) {
        // Parse error
      }
    }

    startHeartbeat() {
      this.stopHeartbeat();
      this.pingInterval = setInterval(() => {
        if (this.isConnected) {
          this.send({ event: 'pusher:ping', data: {} });
        }
      }, 20000);
    }

    stopHeartbeat() {
      if (this.pingInterval) {
        clearInterval(this.pingInterval);
        this.pingInterval = null;
      }
    }

    scheduleReconnect() {
      if (this.reconnectAttempts >= this.maxReconnectAttempts) {
        console.warn('[PvpSocket] Quá số lần thử kết nối lại. Kích hoạt Polling Fallback.');
        this.startFallbackPolling();
        return;
      }

      this.startFallbackPolling(); // Vẫn poll dữ liệu trong thời gian đợi reconnect
      const delay = Math.min(10000, 1000 * Math.pow(1.5, this.reconnectAttempts));
      this.reconnectAttempts++;

      clearTimeout(this.reconnectTimer);
      this.reconnectTimer = setTimeout(() => {
        console.log(`[PvpSocket] Đang thử kết nối lại (Lần ${this.reconnectAttempts})...`);
        this.connect();
      }, delay);
    }

    /**
     * Fallback HTTP Polling nếu WebSocket bị chặn
     */
    startFallbackPolling() {
      if (this.fallbackPollingInterval || !this.activeRoomCode) return;

      this.fallbackPollingInterval = setInterval(async () => {
        if (!this.activeRoomCode) return;
        try {
          const res = await fetch(`/api/pvp/room-status?room_code=${encodeURIComponent(this.activeRoomCode)}`);
          if (!res.ok) return;
          const json = await res.json();
          if (json.success && json.data) {
            const room = json.data;
            const channel = this.channels.get('presence-room.' + this.activeRoomCode);
            if (!channel) return;

            // Kiểm tra trạng thái trận đấu
            if (room.status === 'in_progress' && (!this.lastSyncSnapshot || this.lastSyncSnapshot.status !== 'in_progress')) {
              channel.trigger('MatchStarted', {
                room_code: room.room_code,
                map_data: room.map_data,
                players: room.players,
                time_limit_seconds: room.time_limit_seconds,
              });
            }

            // Đồng bộ danh sách người chơi & điểm
            channel.trigger('MatchLeaderboardUpdated', room.players);
            this.lastSyncSnapshot = room;
          }
        } catch (e) {}
      }, 1500);
    }

    stopFallbackPolling() {
      if (this.fallbackPollingInterval) {
        clearInterval(this.fallbackPollingInterval);
        this.fallbackPollingInterval = null;
      }
    }
  }

  // Khởi tạo Singleton client
  window.PvpSocket = new PvpSocketClient();

})(window);
