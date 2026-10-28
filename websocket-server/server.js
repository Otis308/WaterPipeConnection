/**
 * =========================================================================================
 * WEBSOCKET BROADCAST SERVER — GAME NỐI ỐNG NƯỚC (HUIT)
 * Triển khai chuẩn RFC 6455 WebSockets thuần (Zero External Dependencies)
 * Tương thích giao thức Laravel Echo / Pusher / Reverb Channels
 * =========================================================================================
 */

const http = require('http');
const crypto = require('crypto');

const PORT = parseInt(process.env.PORT || process.env.WEBSOCKET_PORT || '8080', 10);
const HOST = '0.0.0.0';

// Lưu trữ các kênh: channelName -> Set<ClientSocket>
const channels = new Map();
// Lưu trữ các client: ClientSocket -> { id, channels: Set<string>, lastPing: number, userId: number|null }
const clients = new Map();

/**
 * Tạo frame WebSocket Text (Opcode 0x1) theo chuẩn RFC 6455
 */
function encodeWebSocketFrame(textPayload) {
  const payloadBuffer = Buffer.from(textPayload, 'utf8');
  const payloadLength = payloadBuffer.length;

  let headerBuffer;
  if (payloadLength < 126) {
    headerBuffer = Buffer.alloc(2);
    headerBuffer[0] = 0x81; // FIN = 1, Opcode = 1 (Text)
    headerBuffer[1] = payloadLength;
  } else if (payloadLength <= 0xFFFF) {
    headerBuffer = Buffer.alloc(4);
    headerBuffer[0] = 0x81;
    headerBuffer[1] = 126;
    headerBuffer.writeUInt16BE(payloadLength, 2);
  } else {
    headerBuffer = Buffer.alloc(10);
    headerBuffer[0] = 0x81;
    headerBuffer[1] = 127;
    headerBuffer.writeBigUInt64BE(BigInt(payloadLength), 2);
  }

  return Buffer.concat([headerBuffer, payloadBuffer]);
}

/**
 * Gửi tin nhắn tới 1 Client Socket
 */
function sendToClient(socket, messageObj) {
  if (socket.destroyed || !socket.writable) return;
  try {
    const jsonStr = typeof messageObj === 'string' ? messageObj : JSON.stringify(messageObj);
    const frame = encodeWebSocketFrame(jsonStr);
    socket.write(frame);
  } catch (err) {
    // Socket error
  }
}

/**
 * Broadcast tin nhắn tới tất cả client trong 1 hoặc nhiều kênh
 */
function broadcastToChannels(targetChannels, eventName, data) {
  if (!Array.isArray(targetChannels)) {
    targetChannels = [targetChannels];
  }

  let totalDelivered = 0;
  const notifiedSockets = new Set();

  for (const chName of targetChannels) {
    const subscriberSet = channels.get(chName);
    if (!subscriberSet || subscriberSet.size === 0) continue;

    const message = {
      event: eventName,
      channel: chName,
      data: data,
      timestamp: Date.now(),
    };

    const frame = encodeWebSocketFrame(JSON.stringify(message));

    for (const clientSocket of subscriberSet) {
      if (!notifiedSockets.has(clientSocket) && !clientSocket.destroyed && clientSocket.writable) {
        notifiedSockets.add(clientSocket);
        try {
          clientSocket.write(frame);
          totalDelivered++;
        } catch (e) {
          // Socket write failed
        }
      }
    }
  }

  return totalDelivered;
}

/**
 * HTTP Server xử lý API Broadcast và WebSocket Upgrade
 */
const server = http.createServer((req, res) => {
  // Bật CORS cho mọi origin (cho phép browser gọi API)
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');

  if (req.method === 'OPTIONS') {
    res.writeHead(204);
    res.end();
    return;
  }

  const urlPath = req.url ? req.url.split('?')[0] : '/';

  // 1. Health check
  if (req.method === 'GET' && urlPath === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({
      status: 'ok',
      service: 'HUIT Water Pipe PvP WebSocket Server',
      clients_connected: clients.size,
      active_channels: channels.size,
      uptime_seconds: Math.floor(process.uptime()),
    }));
    return;
  }

  // 2. HTTP POST /broadcast (nhận lệnh broadcast từ backend PHP)
  if (req.method === 'POST' && (urlPath === '/broadcast' || urlPath === '/api/broadcast')) {
    let body = '';
    req.on('data', chunk => { body += chunk; });
    req.on('end', () => {
      try {
        const payload = JSON.parse(body || '{}');
        const targetChannels = payload.channels || (payload.channel ? [payload.channel] : []);
        const eventName = payload.event || 'message';
        const eventData = payload.data || {};

        const deliveredCount = broadcastToChannels(targetChannels, eventName, eventData);

        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({
          success: true,
          delivered_to: deliveredCount,
          channels: targetChannels,
          event: eventName,
        }));
      } catch (err) {
        res.writeHead(400, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ success: false, error: err.message }));
      }
    });
    return;
  }

  // Default response
  res.writeHead(200, { 'Content-Type': 'text/plain; charset=utf-8' });
  res.end('HUIT PvP WebSocket Server is running on port ' + PORT);
});

/**
 * Xử lý WebSocket Upgrade Handshake
 */
server.on('upgrade', (req, socket, head) => {
  const upgradeHeader = req.headers['upgrade'];
  if (!upgradeHeader || upgradeHeader.toLowerCase() !== 'websocket') {
    socket.destroy();
    return;
  }

  const secWsKey = req.headers['sec-websocket-key'];
  if (!secWsKey) {
    socket.destroy();
    return;
  }

  // Tính Sec-WebSocket-Accept
  const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
  const sha1 = crypto.createHash('sha1').update(secWsKey + GUID).digest('base64');

  const responseHeaders = [
    'HTTP/1.1 101 Switching Protocols',
    'Upgrade: websocket',
    'Connection: Upgrade',
    `Sec-WebSocket-Accept: ${sha1}`,
    '',
    ''
  ].join('\r\n');

  socket.write(responseHeaders);

  // Đăng ký client mới
  const clientId = crypto.randomUUID ? crypto.randomUUID() : Math.random().toString(36).substring(2);
  const clientInfo = {
    id: clientId,
    channels: new Set(),
    lastPing: Date.now(),
    userId: null,
    playerName: null,
    roomCode: null,
  };
  clients.set(socket, clientInfo);

  // Gửi thông điệp chào mừng (Pusher/Echo compatible)
  sendToClient(socket, {
    event: 'pusher:connection_established',
    data: {
      socket_id: clientId,
      activity_timeout: 120,
    }
  });

  // Xử lý dữ liệu đến từ client
  let messageBuffer = Buffer.alloc(0);

  socket.on('data', chunk => {
    messageBuffer = Buffer.concat([messageBuffer, chunk]);

    while (messageBuffer.length >= 2) {
      const firstByte = messageBuffer[0];
      const secondByte = messageBuffer[1];

      const fin = (firstByte & 0x80) !== 0;
      const opcode = firstByte & 0x0F;
      const isMasked = (secondByte & 0x80) !== 0;
      let payloadLength = secondByte & 0x7F;

      let offset = 2;

      if (payloadLength === 126) {
        if (messageBuffer.length < 4) break;
        payloadLength = messageBuffer.readUInt16BE(2);
        offset = 4;
      } else if (payloadLength === 127) {
        if (messageBuffer.length < 10) break;
        payloadLength = Number(messageBuffer.readBigUInt64BE(2));
        offset = 10;
      }

      const maskLength = isMasked ? 4 : 0;
      if (messageBuffer.length < offset + maskLength + payloadLength) {
        // Chưa đủ dữ liệu frame, đợi chunk tiếp theo
        break;
      }

      let maskKey = null;
      if (isMasked) {
        maskKey = messageBuffer.subarray(offset, offset + 4);
        offset += 4;
      }

      const rawPayload = messageBuffer.subarray(offset, offset + payloadLength);
      messageBuffer = messageBuffer.subarray(offset + payloadLength);

      // Giải mã Mask
      let unmasked = Buffer.alloc(rawPayload.length);
      if (isMasked && maskKey) {
        for (let i = 0; i < rawPayload.length; i++) {
          unmasked[i] = rawPayload[i] ^ maskKey[i % 4];
        }
      } else {
        unmasked = rawPayload;
      }

      // Xử lý Opcode
      if (opcode === 0x8) {
        // Close frame
        socket.end();
        return;
      } else if (opcode === 0x9) {
        // Ping -> Gửi Pong (Opcode 0xA)
        const pong = Buffer.alloc(2);
        pong[0] = 0x8A;
        pong[1] = 0x00;
        socket.write(pong);
      } else if (opcode === 0x1) {
        // Text frame
        const textMessage = unmasked.toString('utf8');
        handleClientMessage(socket, clientInfo, textMessage);
      }
    }
  });

  socket.on('close', () => {
    handleClientDisconnect(socket, clientInfo);
  });

  socket.on('error', () => {
    handleClientDisconnect(socket, clientInfo);
  });
});

/**
 * Xử lý tin nhắn WebSocket nhận từ Client
 */
function handleClientMessage(socket, clientInfo, rawText) {
  try {
    const msg = JSON.parse(rawText);
    const event = msg.event || '';
    const chName = msg.channel || (msg.data && msg.data.channel) || '';

    // 1. Subscribe vào Channel (Pusher / Echo compatible)
    if (event === 'pusher:subscribe' || event === 'subscribe') {
      if (chName) {
        clientInfo.channels.add(chName);
        if (!channels.has(chName)) {
          channels.set(chName, new Set());
        }
        channels.get(chName).add(socket);

        // Lưu thông tin người dùng nếu có gửi kèm
        if (msg.data && msg.data.user_id) {
          clientInfo.userId = parseInt(msg.data.user_id, 10);
        }
        if (msg.data && msg.data.player_name) {
          clientInfo.playerName = msg.data.player_name;
        }
        if (chName.includes('presence-room.')) {
          clientInfo.roomCode = chName.replace('presence-room.', '');
        }

        // Phản hồi đăng ký thành công
        sendToClient(socket, {
          event: 'pusher_internal:subscription_succeeded',
          channel: chName,
          data: {}
        });
      }
      return;
    }

    // 2. Unsubscribe khỏi Channel
    if (event === 'pusher:unsubscribe' || event === 'unsubscribe') {
      if (chName && channels.has(chName)) {
        channels.get(chName).delete(socket);
        clientInfo.channels.delete(chName);
      }
      return;
    }

    // 3. Ping / Heartbeat
    if (event === 'pusher:ping' || event === 'ping') {
      clientInfo.lastPing = Date.now();
      sendToClient(socket, { event: 'pusher:pong', data: {} });
      return;
    }

    // 4. Client whisper hoặc tin nhắn tùy chỉnh trong phòng
    if (event.startsWith('client-') && chName) {
      // Relay cho các client khác trong cùng phòng (trừ người gửi)
      const subscribers = channels.get(chName);
      if (subscribers) {
        const relayFrame = encodeWebSocketFrame(JSON.stringify(msg));
        for (const sub of subscribers) {
          if (sub !== socket && !sub.destroyed && sub.writable) {
            sub.write(relayFrame);
          }
        }
      }
    }
  } catch (err) {
    // Malformed JSON
  }
}

/**
 * Dọn dẹp khi Client ngắt kết nối
 */
function handleClientDisconnect(socket, clientInfo) {
  // Xóa khỏi danh sách channels
  for (const chName of clientInfo.channels) {
    if (channels.has(chName)) {
      channels.get(chName).delete(socket);
      if (channels.get(chName).size === 0) {
        channels.delete(chName);
      }
    }
  }

  // Nếu người chơi ngắt kết nối trong phòng, phát thông báo connection_status
  if (clientInfo.roomCode && clientInfo.userId) {
    broadcastToChannels(['presence-room.' + clientInfo.roomCode], 'PlayerConnectionStatusUpdated', {
      room_code: clientInfo.roomCode,
      user_id: clientInfo.userId,
      player_name: clientInfo.playerName,
      status: 'disconnected',
      timestamp: Date.now(),
    });
  }

  clients.delete(socket);
}

// Dọn dẹp socket chết mỗi 30 giây
setInterval(() => {
  const now = Date.now();
  for (const [socket, info] of clients.entries()) {
    if (now - info.lastPing > 120000) { // 2 phút không ping -> đóng
      try { socket.destroy(); } catch (e) {}
      clients.delete(socket);
    }
  }
}, 30000);

// Khởi chạy server
server.listen(PORT, HOST, () => {
  console.log(`[PVP WebSocket Server] Dang chay tai ws://${HOST}:${PORT}`);
  console.log(`[PVP WebSocket Server] HTTP Broadcast API: http://${HOST}:${PORT}/broadcast`);
});
