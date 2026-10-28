// Two separate Chromium browser contexts (separate cookies/storage), real page + AJAX.
import { setTimeout as delay } from 'node:timers/promises';
let raw = ''; for await (const chunk of process.stdin) raw += chunk;
const config = JSON.parse(raw);
let version;
for (let i=0; i<80; i++) {
  try { version = await (await fetch(`http://127.0.0.1:${config.port}/json/version`)).json(); break; } catch { await delay(100); }
}
const socket = new WebSocket(version.webSocketDebuggerUrl);
await new Promise((resolve,reject) => { socket.addEventListener('open',resolve); socket.addEventListener('error',reject); });
let seq=0, checks=0;
const pending=new Map(), browserErrors=[], metrics=new Map();
socket.addEventListener('message', event => {
  const message=JSON.parse(event.data), handler=pending.get(message.id);
  const metric=metrics.get(message.sessionId);
  if(metric && message.method==='Network.requestWillBeSent' && message.params.request.url.includes('/api/pvp/chat?')) {
    metric.active.add(message.params.requestId); metric.times.push(Date.now()); metric.max=Math.max(metric.max,metric.active.size);
  }
  if(metric && ['Network.loadingFinished','Network.loadingFailed'].includes(message.method)) metric.active.delete(message.params.requestId);
  if(message.method==='Runtime.exceptionThrown') browserErrors.push(message.params.exceptionDetails.exception?.description || message.params.exceptionDetails.text);
  if (handler) { pending.delete(message.id); message.error ? handler.reject(Error(message.error.message)) : handler.resolve(message.result); }
});
function call(method, params={}, sessionId) {
  return new Promise((resolve,reject)=>{
    const id=++seq;
    const timer=setTimeout(()=>{pending.delete(id);reject(Error('CDP timeout '+method));},15000);
    pending.set(id,{resolve:value=>{clearTimeout(timer);resolve(value);},reject:error=>{clearTimeout(timer);reject(error);}});
    socket.send(JSON.stringify({id,method,params,...(sessionId?{sessionId}:{})}));
  });
}
async function evaluate(session, expression) {
  const data=await call('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true},session);
  if (data.exceptionDetails) throw Error(data.exceptionDetails.text + ': ' + expression.slice(0,100));
  return data.result.value;
}
async function wait(condition, label) {
  for(let i=0;i<150;i++) { try { if(await condition())return; } catch {} await delay(100); }
  throw Error('Timed out: '+label);
}
function check(condition,label) { checks++; if(!condition)throw Error(label); }
async function type(session,text) {
  await evaluate(session,"document.getElementById('pvpChatInput').focus()");
  await call('Input.insertText',{text},session);
}
async function key(session,key,modifiers=0) {
  await call('Input.dispatchKeyEvent',{type:'keyDown',key,code:key==='Enter'?'Enter':key,windowsVirtualKeyCode:key==='Enter'?13:32,modifiers,text:key==='Enter'?'\r':key,unmodifiedText:key==='Enter'?'\r':key},session);
  await call('Input.dispatchKeyEvent',{type:'keyUp',key,modifiers},session);
}
const count=(s,text)=>evaluate(s,`Array.from(document.querySelectorAll('#pvpChatMessages p')).filter(e=>e.textContent===${JSON.stringify(text)}).length`);
const contexts=[], sessions=[];
try {
  for (const fixture of config.sessions) {
    const {browserContextId}=await call('Target.createBrowserContext'); contexts.push(browserContextId);
    const {targetId}=await call('Target.createTarget',{url:'about:blank',browserContextId});
    const {sessionId}=await call('Target.attachToTarget',{targetId,flatten:true}); sessions.push(sessionId);
    metrics.set(sessionId,{active:new Set(),times:[],max:0});
    await call('Page.enable',{},sessionId); await call('Network.enable',{},sessionId); await call('Runtime.enable',{},sessionId);
    // Existing PvpMatch calls missing PvpSocket.private(). Exercise its HTTP fallback
    // with the WS script unavailable; do not patch production WebSocket code.
    await call('Network.setBlockedURLs',{urls:['*/JS/TrangGame/PvpSocket.js','ws://*','wss://*','https://fonts.googleapis.com/*']},sessionId);
    await call('Network.setCookie',{name:'PHPSESSID',value:fixture.sid,url:config.base+'/'},sessionId);
    const matchData={room_code:'CHAT01',map_data:config.map,duration_seconds:3600};
    await call('Page.addScriptToEvaluateOnNewDocument',{source:`sessionStorage.setItem('gameMode','pvp');sessionStorage.setItem('mapOption','custom');sessionStorage.setItem('pvpRoomCode','CHAT01');sessionStorage.setItem('pvpMatchData',${JSON.stringify(JSON.stringify(matchData))});sessionStorage.setItem('huit_user_id',${JSON.stringify(String(fixture.uid))});`},sessionId);
    await call('Page.navigate',{url:config.base+'/TrangGame.html'},sessionId);
    try {
      await wait(()=>evaluate(sessionId,"!!document.getElementById('pvpChatInput') && !document.getElementById('pvpChatInput').disabled && window.pvpRestoring===false"),'game and chat restored');
    } catch(error) {
      throw Error(error.message+' '+JSON.stringify({errors:browserErrors,state:await evaluate(sessionId,"({url:location.href,status:document.getElementById('pvpChatStatus')?.textContent,restoring:window.pvpRestoring,failed:window.pvpSyncFailed,auth:window.HUIT_AUTH_USER?.id,body:document.body.innerText.slice(-500)})")}));
    }
  }
  const [a,b]=sessions;
  check(await evaluate(a,'window.HUIT_AUTH_USER.id')===1 && await evaluate(b,'window.HUIT_AUTH_USER.id')===2,'Separate authenticated contexts');
  check(await evaluate(a,"document.querySelectorAll('#pvpChatMessages li').length")===50,'Initial latest 50 rendered');
  const attack='<img src=x onerror=window.chatXss=1><script>window.chatXss=2</script>';
  await type(a,attack); await key(a,'Enter');
  await wait(async()=>await count(b,attack)===1,'A to B');
  check(await count(a,attack)===1 && await evaluate(b,"!window.chatXss && !document.querySelector('#pvpChatMessages img, #pvpChatMessages script')"),'Plain text, no XSS, single copy');
  check(await evaluate(a,"document.querySelector('#pvpChatMessages li:last-child').classList.contains('pvp-chat-mine')") && !await evaluate(b,"document.querySelector('#pvpChatMessages li:last-child').classList.contains('pvp-chat-mine')"),'Own/opponent styles differ');
  await type(b,'dòng 1'); await key(b,'Enter',8); await type(b,'dòng 2');
  const multiline=await evaluate(b,"document.getElementById('pvpChatInput').value");
  check(multiline==='dòng 1\ndòng 2','Shift Enter inserts a newline: '+JSON.stringify(multiline));
  await key(b,'Enter'); await wait(async()=>await count(a,'dòng 1\ndòng 2')===1,'B to A');
  await evaluate(a,"document.getElementById('pvpChatMessages').scrollTop=0");
  await type(b,'Tin mới khi đang đọc'); await key(b,'Enter');
  await wait(()=>evaluate(a,"!document.getElementById('pvpChatNew').hidden"),'unread notice');
  check(await evaluate(a,"document.getElementById('pvpChatMessages').scrollTop")<20,'Reading old messages preserves scroll');
  await evaluate(a,"document.getElementById('pvpChatNew').click()");
  check(await evaluate(a,"document.getElementById('pvpChatNew').hidden && document.getElementById('pvpChatMessages').scrollTop>0"),'New message button jumps to end');
  await evaluate(a,"document.getElementById('pvpChatToggle').click()");
  check(await evaluate(a,"document.getElementById('pvpChatBody').hidden"),'Chat collapses');
  await evaluate(a,"document.getElementById('pvpChatToggle').click()");
  await evaluate(a,"window.chatGameKeys=0;document.addEventListener('keydown',()=>window.chatGameKeys++);window.beforeChatBoard=Array.from(document.querySelectorAll('.KhungGame [data-rotation]')).map(e=>e.dataset.rotation).join(',')");
  await type(a,'Bàn không đổi'); await key(a,' ');
  check(await evaluate(a,"window.chatGameKeys===0 && window.beforeChatBoard===Array.from(document.querySelectorAll('.KhungGame [data-rotation]')).map(e=>e.dataset.rotation).join(',')"),'Chat keyboard stays out of document/game handlers');
  await evaluate(a,"document.getElementById('pvpChatInput').value=''");
  await fetch(config.base+'/__fault',{method:'POST',body:JSON.stringify({drop_post:true,fail_get:true})});
  await type(a,'Retry không trùng'); await key(a,'Enter');
  await wait(()=>evaluate(a,"document.getElementById('pvpChatSend').textContent==='Thử lại'"),'lost POST response');
  await delay(1800);
  await evaluate(a,"document.getElementById('pvpChatSend').click();document.getElementById('pvpChatSend').click()");
  await wait(()=>evaluate(a,"document.getElementById('pvpChatInput').value===''") ,'retry completed');
  await fetch(config.base+'/__fault',{method:'POST',body:JSON.stringify({fail_get:false})});
  await wait(async()=>await count(b,'Retry không trùng')===1,'retry visible');
  check(await count(a,'Retry không trùng')===1 && await count(b,'Retry không trùng')===1,'Lost response retry + double click renders one ID');
  await fetch(config.base+'/__fault',{method:'POST',body:JSON.stringify({fail_get:true})});
  await wait(()=>evaluate(a,"document.getElementById('pvpChatStatus').textContent.includes('kết nối')"),'polling failure');
  await fetch(config.base+'/__fault',{method:'POST',body:JSON.stringify({fail_get:false})});
  await wait(()=>evaluate(a,"document.getElementById('pvpChatStatus').textContent.includes('Enter gửi')"),'polling recovery');
  check(true,'Polling recovers from network errors');
  const ids=await evaluate(a,"Array.from(document.querySelectorAll('#pvpChatMessages li')).map(e=>Number(e.dataset.messageId))");
  check(new Set(ids).size===ids.length && ids.every((id,i)=>!i || id>ids[i-1]),'IDs stay sorted and unique after POST/poll races');
  // Actual board click and server snapshot/version, not a simulated scoring result.
  const before=await evaluate(a,"fetch('/api/pvp/reconnect?room_code=CHAT01').then(r=>r.json()).then(j=>j.data.player_snapshot.board_state_version)");
  await evaluate(a,"document.querySelector('.KhungGame-OVuong.co-the-xoay').click()");
  await wait(async()=>await evaluate(a,"fetch('/api/pvp/reconnect?room_code=CHAT01').then(r=>r.json()).then(j=>j.data.player_snapshot.board_state_version)")>before,'board action persists');
  check(await evaluate(a,"window.danhGiaServer && typeof window.danhGiaServer.completed_pairs==='number'"),'Game action still reaches server scoring');
  for(const width of [1440,390]) {
    await call('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<600},a);
    check(await evaluate(a,"(()=>{const b=document.querySelector('.KhungGame').getBoundingClientRect(),c=document.getElementById('pvpChat').getBoundingClientRect();return c.top>=b.bottom && c.width<=innerWidth && c.left>=0})()"),'Chat does not cover board at '+width+'px');
  }
  await delay(300);
  await evaluate(a,"Object.defineProperty(document,'hidden',{configurable:true,get:()=>window.chatTestHidden});window.chatTestHidden=true;document.dispatchEvent(new Event('visibilitychange'))");
  const hiddenStart=metrics.get(a).times.length;
  await delay(10500);
  check(metrics.get(a).times.length-hiddenStart<=2,'Hidden tab polls about once per 10 seconds');
  await evaluate(a,"delete document.hidden;document.dispatchEvent(new Event('visibilitychange'))");
  check(sessions.every(s=>metrics.get(s).max===1),'No overlapping chat GET requests per player');
  const retained=await evaluate(a,"document.querySelectorAll('#pvpChatMessages li').length");
  await fetch(config.base+'/__finish',{method:'POST'});
  await wait(()=>evaluate(a,"document.getElementById('pvpChatInput').disabled && document.getElementById('pvpChatStatus').textContent.includes('chỉ đọc')"),'finished readonly');
  check(await evaluate(a,"document.querySelectorAll('#pvpChatMessages li').length")===retained,'Finished match retains visible messages in readonly mode');
  await wait(()=>evaluate(a,"document.getElementById('pvpMatchResultsModal').style.display==='flex'"),'results shown');
  await evaluate(a,"document.getElementById('btnPvpViewChat').click()");
  check(await evaluate(a,"document.getElementById('pvpMatchResultsModal').style.display==='none' && window.isGameOver"),'Read chat after results without resuming game');
  await call('Page.navigate',{url:'about:blank'},a);
  await delay(300);
  const exited=metrics.get(a).times.length;
  await delay(1800);
  check(metrics.get(a).times.length===exited,'Leaving the match stops chat polling');
  console.log(JSON.stringify({passed:true,checks,two_separate_browser_contexts:true,real_page:true,websocket:'script blocked in fixture; existing private() incompatibility'}));
} finally {
  for(const browserContextId of contexts) await call('Target.disposeBrowserContext',{browserContextId}).catch(()=>{});
  await call('Browser.close').catch(()=>{}); socket.close();
}
