// Node's built-in WebSocket drives Edge over localhost CDP; no package install.
import { setTimeout as delay } from 'node:timers/promises';

let page;
for (let attempt = 0; attempt < 60; attempt++) {
    try {
        const pages = await (await fetch(`http://127.0.0.1:${process.argv[2]}/json/list`)).json();
        page = pages.find(item => item.type === 'page');
        if (page) break;
    } catch {}
    await delay(100);
}
if (!page) throw new Error('Edge CDP did not start');
const socket = new WebSocket(page.webSocketDebuggerUrl);
await new Promise((resolve, reject) => {
    socket.addEventListener('open', resolve, { once: true });
    socket.addEventListener('error', reject, { once: true });
});
let sequence = 0;
const pending = new Map();
socket.addEventListener('message', event => {
    const message = JSON.parse(event.data);
    const handler = pending.get(message.id);
    if (handler) {
        pending.delete(message.id);
        if (message.error) handler.reject(new Error(message.error.message));
        else handler.resolve(message.result);
    }
});
const send = (method, params) => new Promise((resolve, reject) => {
    const id = ++sequence;
    pending.set(id, { resolve, reject });
    socket.send(JSON.stringify({ id, method, params }));
});
try {
    // Wall-clock polling allows real PHP/network requests to finish. A virtual
    // time budget can expire while a DB fallback request is still in flight.
    const started = Date.now();
    let label;
    while (Date.now() - started < 40000) {
        try {
            const response = await send('Runtime.evaluate', {
                expression: "document.getElementById('result')?.textContent", returnByValue: true,
            });
            label = response.result?.value;
            if (label && label !== 'WAIT') break;
        } catch (error) {
            // CDP may attach while the initial about:blank page is navigating.
            if (!error.message.includes('context')) throw error;
        }
        await delay(100);
    }
    console.log(label && label !== 'WAIT' ? label : 'FAIL: browser timed out');
} finally {
    socket.send(JSON.stringify({ id: ++sequence, method: 'Browser.close' }));
    await delay(300);
    socket.close();
}
