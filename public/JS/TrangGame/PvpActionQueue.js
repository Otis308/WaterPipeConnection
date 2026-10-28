(function (root) {
  'use strict';
  class PvpActionQueue {
    constructor(send, onUpdate, version = 0) {
      this.send = send; this.onUpdate = onUpdate; this.version = version;
      this.tail = Promise.resolve(); this.sequence = 0; this.pending = 0;
      this.prefix = Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
    }
    async request(endpoint, payload) {
      // Retry the identical command if its response was lost. The server remembers
      // action_id + version, so a rotation/swap cannot be applied twice.
      let result;
      try { result = await this.send(endpoint, payload); }
      catch (_) { result = await this.send(endpoint, payload); }
      if (!result.success) throw new Error(result.message || 'Server chưa ghi nhận thao tác.');
      this.version = result.board_state_version ?? this.version;
      this.onUpdate(result);
      return result;
    }
    push(action) {
      const actionId = this.prefix + '-' + (++this.sequence);
      this.pending++;
      const task = this.tail.then(() => this.request('submit-move', {
        ...action, action_id: actionId, expected_version: this.version,
      })).finally(() => { this.pending--; });
      this.tail = task;
      return task;
    }
    submit() {
      return this.tail.then(() => this.request('submit-solution', { expected_version: this.version }));
    }
  }
  root.PvpActionQueue = PvpActionQueue;
  if (typeof module !== 'undefined') module.exports = PvpActionQueue;
})(typeof window === 'undefined' ? globalThis : window);
