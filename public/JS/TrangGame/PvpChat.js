(function (window, document) {
  'use strict';
  if (sessionStorage.getItem('gameMode') !== 'pvp') return;
  const panel = document.getElementById('pvpChat');
  if (!panel) return;
  const input = document.getElementById('pvpChatInput');
  const send = document.getElementById('pvpChatSend');
  const form = document.getElementById('pvpChatForm');
  const list = document.getElementById('pvpChatMessages');
  const status = document.getElementById('pvpChatStatus');
  const toggle = document.getElementById('pvpChatToggle');
  const body = document.getElementById('pvpChatBody');
  const newButton = document.getElementById('pvpChatNew');
  let matchId;
  try { matchId = JSON.parse(sessionStorage.getItem('pvpMatchData') || '{}').map_data?.chat_match_id; } catch {}
  panel.hidden = false;
  document.body.classList.add('pvp-chat-enabled');
  if (!matchId) { status.textContent = 'Chat khả dụng từ trận mới được bắt đầu sau khi cập nhật.'; return; }

  let cursor = 0, myId = null, canSend = false, loaded = false;
  let timer = null, pollFlight = null, postFlight = null, stopped = false, fatal = false;
  let pollFailures = 0, postFailures = 0, retryAt = 0, unread = 0, pending = null;
  const messages = new Map();
  const storageKey = 'pvp-chat-pending:' + matchId + ':' + (window.HUIT_AUTH_USER?.id || 'session');
  try { pending = JSON.parse(sessionStorage.getItem(storageKey) || 'null'); } catch {}
  if (pending) input.value = pending.content;

  const nearEnd = () => list.scrollHeight - list.clientHeight - list.scrollTop < 48;
  function markRead() { unread = 0; newButton.hidden = true; toggle.textContent = 'Chat trong trận ' + (body.hidden ? '▸' : '▾'); }
  function bottom() { list.scrollTop = list.scrollHeight; markRead(); }
  function render(rows, initial = false) {
    const follow = !body.hidden && (initial || nearEnd());
    let added = 0;
    for (const message of rows) {
      if (pending && Number(message.sender_id) === Number(myId) && message.client_message_id === pending.client_message_id) {
        pending = null; input.value = ''; postFailures = 0; retryAt = 0;
        try { sessionStorage.removeItem(storageKey); } catch {}
      }
      if (messages.has(message.id)) continue;
      messages.set(message.id, message);
      const item = document.createElement('li');
      item.dataset.messageId = String(message.id);
      if (Number(message.sender_id) === Number(myId)) item.className = 'pvp-chat-mine';
      const meta = document.createElement('small');
      meta.textContent = message.sender_name + (Number(message.sender_id) === Number(myId) ? ' (Bạn)' : '') + ' · ' + new Date(message.created_at_ms).toLocaleTimeString('vi-VN');
      const text = document.createElement('p');
      text.textContent = message.content;
      item.append(meta, text);
      const next = Array.from(list.children).find(child => Number(child.dataset.messageId) > message.id);
      list.insertBefore(item, next || null);
      added++;
    }
    if (follow) bottom();
    else if (added) {
      unread += added;
      newButton.hidden = false;
      newButton.textContent = `Có ${unread} tin mới ↓`;
      toggle.textContent = `Chat trong trận (${unread} tin mới) ` + (body.hidden ? '▸' : '▾');
    }
  }
  function controls() {
    input.disabled = !loaded || !canSend || stopped;
    input.readOnly = !!pending;
    send.disabled = !loaded || !canSend || stopped || !!postFlight;
    send.textContent = pending && !postFlight ? 'Thử lại' : 'Gửi';
  }
  function schedule(delay) {
    clearTimeout(timer);
    if (!stopped) timer = setTimeout(poll, delay);
  }
  async function request(url, options, controller) {
    const timeout = setTimeout(() => controller.abort(), 12000);
    try {
      const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', ...options, signal: controller.signal });
      let json;
      try { json = await response.json(); } catch { throw new Error('Chat tạm mất kết nối.'); }
      if (!response.ok || !json.success) {
        const error = new Error(json.message || 'Chat tạm mất kết nối.');
        error.status = response.status;
        throw error;
      }
      return json.data;
    } finally { clearTimeout(timeout); }
  }
  async function poll() {
    if (stopped || pollFlight) return;
    const controller = new AbortController();
    pollFlight = controller;
    let delay = document.hidden ? 10000 : 1500;
    try {
      const data = await request(`/api/pvp/chat?match_id=${encodeURIComponent(matchId)}&after_id=${cursor}`, {}, controller);
      if (stopped) return;
      myId = data.my_user_id;
      canSend = data.can_send;
      render(data.messages, !loaded);
      // Only GET advances the cursor: POST can overtake unseen opponent messages.
      cursor = data.after_id;
      loaded = true;
      pollFailures = 0;
      if (!canSend) status.textContent = 'Trận đã kết thúc hoặc bạn đã rời trận. Chat chỉ đọc.';
      else if (!pending && !postFlight) status.textContent = 'Enter gửi · Shift+Enter xuống dòng.';
      if (data.has_more) delay = document.hidden ? 1000 : 50;
    } catch (error) {
      if (stopped) return;
      status.textContent = error.message + ' Đang chờ kết nối lại…';
      delay = Math.min(30000, 1500 * (2 ** Math.min(++pollFailures, 5)));
      if ([401, 403, 419, 422].includes(error.status)) {
        fatal = true; stopped = true; canSend = false; status.textContent = error.message;
      }
    } finally {
      pollFlight = null;
      controls();
      schedule(delay);
    }
  }
  async function submit(event) {
    event?.preventDefault();
    if (stopped || !canSend || !loaded || postFlight) return;
    if (Date.now() < retryAt) { status.textContent = 'Hãy chờ vài giây rồi bấm Thử lại.'; return; }
    const content = input.value.trim();
    if (!pending && (!content || Array.from(content).length > 500)) { status.textContent = 'Tin nhắn phải có từ 1 đến 500 ký tự.'; return; }
    if (!pending) {
      const bytes = new Uint8Array(16); window.crypto.getRandomValues(bytes);
      pending = { content, client_message_id: Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('') };
      try { sessionStorage.setItem(storageKey, JSON.stringify(pending)); } catch {}
    }
    const controller = new AbortController();
    postFlight = controller;
    controls();
    status.textContent = 'Đang gửi…';
    try {
      const data = await request('/api/pvp/chat', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.HUIT_CSRF_TOKEN || '' }, body: JSON.stringify({ match_id: matchId, ...pending }) }, controller);
      if (stopped) return;
      render([data.message]);
      pending = null; postFailures = 0; retryAt = 0;
      try { sessionStorage.removeItem(storageKey); } catch {}
      input.value = '';
      status.textContent = 'Đã gửi.';
    } catch (error) {
      if (stopped) return;
      if ([401, 403, 419].includes(error.status)) { canSend = false; fatal = true; stop(); }
      else if (error.status === 409) { canSend = false; }
      else if (error.status === 422) { pending = null; try { sessionStorage.removeItem(storageKey); } catch {} }
      retryAt = Date.now() + (error.status === 429 ? 10000 : Math.min(30000, 1500 * (2 ** Math.min(postFailures++, 4))));
      status.textContent = error.message + (canSend && pending ? ' Bấm Thử lại để gửi lại cùng tin.' : '');
    } finally { postFlight = null; controls(); }
  }
  function stop() { stopped = true; clearTimeout(timer); pollFlight?.abort(); postFlight?.abort(); controls(); }
  toggle.addEventListener('click', () => { body.hidden = !body.hidden; toggle.setAttribute('aria-expanded', String(!body.hidden)); if (!body.hidden && nearEnd()) bottom(); else toggle.textContent = 'Chat trong trận' + (unread ? ` (${unread} tin mới)` : '') + (body.hidden ? ' ▸' : ' ▾'); });
  newButton.addEventListener('click', bottom);
  list.addEventListener('scroll', () => { if (nearEnd()) markRead(); });
  form.addEventListener('submit', submit);
  // Keep all keyboard events inside chat, including Enter, space and arrow keys.
  for (const type of ['keydown', 'keyup', 'keypress']) panel.addEventListener(type, event => {
    event.stopPropagation();
    if (type === 'keydown' && event.target === input && event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); submit(); }
  });
  document.addEventListener('visibilitychange', () => { if (!pollFlight) schedule(document.hidden ? 10000 : (pollFailures ? 3000 : 0)); });
  window.addEventListener('pagehide', stop);
  window.addEventListener('pageshow', event => { if (event.persisted && !fatal) { stopped = false; poll(); } });
  poll();
})(window, document);
