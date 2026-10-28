// Real home page + two independent Edge contexts; no socket stubs in the normal flow.
import {setTimeout as delay} from 'node:timers/promises';
let raw=''; for await (const chunk of process.stdin) raw+=chunk;
const config=JSON.parse(raw);
let version;
for(let i=0;i<80;i++) {
  try {version=await (await fetch(`http://127.0.0.1:${config.port}/json/version`)).json(); break;} catch {await delay(100);}
}
const socket=new WebSocket(version.webSocketDebuggerUrl);
await new Promise((resolve,reject)=>{socket.addEventListener('open',resolve);socket.addEventListener('error',reject);});
let seq=0,checks=0;
const pending=new Map(), errors=[], responses=[];
socket.addEventListener('message', event=>{
  const message=JSON.parse(event.data),handler=pending.get(message.id);
  if(message.method==='Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.exception?.description || message.params.exceptionDetails.text);
  if(message.method==='Network.responseReceived' && message.params.response.url.includes('/api/pvp/matchmaking/')) {
    const r=message.params.response;responses.push({status:r.status,type:r.mimeType});
  }
  if(handler){pending.delete(message.id);message.error?handler.reject(Error(message.error.message)):handler.resolve(message.result);}
});
function call(method,params={},sessionId){return new Promise((resolve,reject)=>{
  const id=++seq,timer=setTimeout(()=>{pending.delete(id);reject(Error('CDP timeout: '+method));},12000);
  pending.set(id,{resolve:v=>{clearTimeout(timer);resolve(v);},reject:e=>{clearTimeout(timer);reject(e);}});
  socket.send(JSON.stringify({id,method,params,...(sessionId?{sessionId}:{})}));
});}
async function evaluate(session,expression){
  const r=await call('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true},session);
  if(r.exceptionDetails) throw Error(r.exceptionDetails.exception?.description || r.exceptionDetails.text);
  return r.result.value;
}
async function wait(condition,label){for(let i=0;i<100;i++){if(await condition())return;await delay(100);}throw Error('Timed out: '+label);}
function check(ok,label){checks++;if(!ok)throw Error(label);}
const sessions=[],contexts=[];
async function click(s,id){await evaluate(s,`document.getElementById(${JSON.stringify(id)}).click()`);}
async function start(s){await click(s,'play-NutBatDau');}
async function cancel(s){await click(s,'btnCancelMatchmaking');await wait(()=>evaluate(s,"document.getElementById('pvpMatchmakingModal').style.display==='none'"),'cancel closes modal');}
try {
  for(let i=0;i<2;i++){
    const {browserContextId}=await call('Target.createBrowserContext');contexts.push(browserContextId);
    const {targetId}=await call('Target.createTarget',{url:'about:blank',browserContextId});
    const {sessionId:s}=await call('Target.attachToTarget',{targetId,flatten:true});sessions.push(s);
    await call('Runtime.enable',{},s);await call('Page.enable',{},s);await call('Network.enable',{},s);
    await call('Page.navigate',{url:'http://127.0.0.1:8000/'},s);
    await wait(()=>evaluate(s,"!!window.PvpLobby && !!document.getElementById('btnSignIn')"),'home scripts ready');
    await evaluate(s,`window.__alerts=[];window.alert=m=>window.__alerts.push(String(m));
      document.getElementById('nutDangNhap').click();
      document.getElementById('dn_Identifier').value=${JSON.stringify(config.names[i])};
      document.getElementById('matKhau_DnPass').value=${JSON.stringify(config.password)};
      document.getElementById('btnSignIn').click();`);
    await wait(()=>evaluate(s,'!!window.currentAuthenticatedUser?.id'),'real sign-in UI');
    await click(s,'nutPlay');await click(s,'cheDoPvP');await click(s,'pvpTypeRandom');
    await evaluate(s,"document.getElementById('pvpOpponentCount').value='1'");
  }
  check(await evaluate(sessions[0],'window.currentAuthenticatedUser.id')!==await evaluate(sessions[1],'window.currentAuthenticatedUser.id'),'different users');
  await start(sessions[0]);
  await wait(()=>evaluate(sessions[0],"document.getElementById('mmPlayerCountText').textContent.includes('1/2')"),'first user waiting');checks++;
  await cancel(sessions[0]);checks++;
  await start(sessions[1]);
  await wait(()=>evaluate(sessions[1],"document.getElementById('mmPlayerCountText').textContent.includes('1/2')"),'cancelled user excluded');checks++;
  await start(sessions[0]);
  for(const s of sessions) await wait(()=>evaluate(s,"document.getElementById('pvpWaitingRoomModal').style.display==='flex' && document.getElementById('pvpPlayerCountBadge').textContent.includes('2/2')"),'both users in waiting room');
  const roomCodes=await Promise.all(sessions.map(s=>evaluate(s,"document.getElementById('pvpModalRoomCode').textContent")));
  check(roomCodes[0]===roomCodes[1],'same room on both browsers');
  check(responses.length>=4 && responses.every(r=>r.status===200&&r.type==='application/json'),'real matchmaking responses are JSON 200');
  check((await Promise.all(sessions.map(s=>evaluate(s,'window.__alerts.length')))).every(n=>n===0),'no connection alerts in normal flow');
  // Keep the scope at matchmaking; no match start/socket repair.
  for(const s of sessions) {await click(s,'btnLeavePvpRoom');await wait(()=>evaluate(s,"document.getElementById('pvpWaitingRoomModal').style.display==='none'"),'leave room');}
  // Fault responses only in this browser. Production DB/schema are not changed.
  const s=sessions[0];
  await evaluate(s,'window.__realFetch=window.fetch.bind(window)');
  const cases=[
    {label:'network',body:"return Promise.reject(new TypeError('Failed to fetch'))",contains:'Không kết nối'},
    {label:'HTML 200',body:"return Promise.resolve(new Response('<b>fatal</b>',{status:200,headers:{'Content-Type':'text/html'}}))",contains:'phản hồi không hợp lệ (HTTP 200)'},
    {label:'bad JSON',body:"return Promise.resolve(new Response('{',{status:200,headers:{'Content-Type':'application/json'}}))",contains:'phản hồi không hợp lệ (HTTP 200)'},
    {label:'HTTP 500',body:"return Promise.resolve(Response.json({success:false,message:'Máy chủ không thể xử lý tìm trận.'},{status:500}))",contains:'Máy chủ không thể'},
    {label:'business 422',body:"return Promise.resolve(Response.json({success:false,message:'Cấu hình tìm trận không hợp lệ.'},{status:422}))",contains:'Cấu hình'},
    {label:'session 401',body:"return Promise.resolve(Response.json({success:false,message:'Vui lòng đăng nhập lại.'},{status:401}))",contains:'đăng nhập lại'},
  ];
  for(const test of cases){
    await evaluate(s,`window.__alerts=[];window.fetch=(url,options)=>{if(String(url).endsWith('/matchmaking/join')){${test.body};}return window.__realFetch(url,options);}`);
    await start(s);await wait(()=>evaluate(s,'window.__alerts.length===1'),'error '+test.label);
    check((await evaluate(s,'window.__alerts[0]')).includes(test.contains),'specific error '+test.label);
    await cancel(s);
  }
  await evaluate(s,'window.fetch=window.__realFetch');
  const unexpected=errors.filter(e=>!e.includes('at CustomModal.init')&&!e.startsWith('NotAllowedError: play()'));
  check(unexpected.length===0,'unexpected browser errors: '+unexpected.join('; '));
  console.log(JSON.stringify({browser:'Edge headless',checks,contexts:2,room:roomCodes[0],httpResponses:responses.length,
    unexpectedErrors:unexpected,existingPageErrors:[...new Set(errors.filter(e=>e.includes('at CustomModal.init')))],
    headlessAutoplayErrors:errors.filter(e=>e.startsWith('NotAllowedError: play()')).length}));
} finally {
  for(const browserContextId of contexts) await call('Target.disposeBrowserContext',{browserContextId});
  socket.close();
}
