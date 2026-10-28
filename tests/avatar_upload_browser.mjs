// Real Chromium file-chooser event + file selection through CDP, no npm packages.
import { setTimeout as delay } from 'node:timers/promises';
let input='';
for await (const chunk of process.stdin) input+=chunk;
const config=JSON.parse(input);
let page;
for(let i=0;i<80;i++) {
    try { page=(await(await fetch(`http://127.0.0.1:${config.port}/json/list`)).json()).find(item=>item.type==='page'); if(page)break; } catch {}
    await delay(100);
}
if(!page)throw Error('Edge did not start');
const ws=new WebSocket(page.webSocketDebuggerUrl);
await new Promise((resolve,reject)=>{ws.addEventListener('open',resolve);ws.addEventListener('error',reject);});
let sequence=0, loads=0, chooser=null, uploadStatus=null, checks=0, phase='start';
const pending=new Map();
ws.addEventListener('message',event=>{
    const message=JSON.parse(event.data);
    if(message.method==='Page.loadEventFired')loads++;
    if(message.method==='Page.fileChooserOpened')chooser=message.params;
    if(message.method==='Runtime.bindingCalled' && message.params.name==='avatarUploadStatusObserved') uploadStatus=JSON.parse(message.params.payload);
    const handler=pending.get(message.id);
    if(handler){pending.delete(message.id);message.error?handler.reject(Error(message.error.message)):handler.resolve(message.result);}
});
const send=(method,params={})=>new Promise((resolve,reject)=>{
    const id=++sequence;
    const timer=setTimeout(()=>{pending.delete(id);reject(Error('CDP timeout: '+method+' at '+phase));},10000);
    pending.set(id,{resolve:value=>{clearTimeout(timer);resolve(value);},reject:error=>{clearTimeout(timer);reject(error);}});
    ws.send(JSON.stringify({id,method,params}));
});
const evaluate=async expression=>{
    const result=await send('Runtime.evaluate',{expression,returnByValue:true});
    if(result.exceptionDetails)throw Error('Browser expression failed');
    return result.result.value;
};
const wait=async predicate=>{
    for(let i=0;i<150;i++) {try{if(await predicate())return;}catch(error){if(!error.message.includes('context'))throw error;}await delay(100);}
    throw Error('Browser condition timed out');
};
const check=(condition,message)=>{checks++;if(!condition)throw Error(message);};
const click=async selector=>{
    const point=await evaluate(`(()=>{const e=document.querySelector(${JSON.stringify(selector)});e.scrollIntoView({block:'center',behavior:'instant'});const r=e.getBoundingClientRect();return{x:r.left+Math.min(30,r.width/2),y:r.top+r.height/2};})()`);
    await send('Input.dispatchMouseEvent',{type:'mouseMoved',...point});
    await send('Input.dispatchMouseEvent',{type:'mousePressed',...point,button:'left',buttons:1,clickCount:1});
    await send('Input.dispatchMouseEvent',{type:'mouseReleased',...point,button:'left',buttons:0,clickCount:1});
};
try {
    await send('Page.enable');
    await send('Runtime.enable');
    await send('Network.enable');
    await send('Network.setCookie',{name:config.cookie,value:config.sid,url:config.base+'/',sameSite:'Lax'});
    const initialLoads=loads;
    await send('Page.navigate',{url:config.base+'/profile'});
    await wait(async()=>loads>initialLoads && await evaluate("!!document.getElementById('profile-avatar')"));
    check(await evaluate("!document.getElementById('profile-avatar').disabled && document.getElementById('profile-form').enctype==='multipart/form-data'"),'Upload is enabled with multipart');
    const oldUrl=await evaluate("document.getElementById('profile-avatar-preview').src");
    await send('Page.setInterceptFileChooserDialog',{enabled:true});
    await click('#profile-avatar');
    await wait(()=>chooser!==null);
    check(chooser.mode==='selectSingle','Trusted mouse click requested the native single-file chooser');
    await send('DOM.setFileInputFiles',{files:[config.image],backendNodeId:chooser.backendNodeId});
    await wait(()=>evaluate("document.getElementById('profile-avatar-preview').src.startsWith('blob:') && document.getElementById('profile-avatar-preview').naturalWidth>0"));
    check(await evaluate("document.getElementById('profile-avatar').files.length===1 && document.getElementById('avatar-status').textContent.includes('xem trước')"),'Selected PNG previews in the actual page');
    // Observe the status synchronously after the app's submit listener. Pausing
    // a navigation request would block Runtime.evaluate in the old document.
    await send('Runtime.addBinding',{name:'avatarUploadStatusObserved'});
    await evaluate(`document.getElementById('profile-form').addEventListener('submit',()=>window.avatarUploadStatusObserved(JSON.stringify({disabled:document.getElementById('profile-save').disabled,message:document.getElementById('profile-save-status').textContent})))`);
    phase='submit';
    const beforeSave=loads;
    await click('#profile-save');
    await wait(()=>uploadStatus!==null);
    check(uploadStatus.disabled && uploadStatus.message.includes('Đang tải ảnh'),'Upload progress state is visible before request completes');
    phase='save response';
    await wait(async()=>loads>beforeSave && await evaluate("!!document.getElementById('profile-avatar-preview') && !!document.querySelector('.alert-success')"));
    const newUrl=await evaluate("document.getElementById('profile-avatar-preview').src");
    check(newUrl!==oldUrl && /\/storage\/avatars\/\d+\/[a-f0-9]{40}\.png$/.test(newUrl),'Committed URL uses a new managed image');
    check(await evaluate("document.getElementById('profile-avatar-preview').naturalWidth>0"),'Saved image loads from the public Storage URL');
    const beforeReload=loads;
    phase='reload';
    await send('Page.reload',{ignoreCache:true});
    await wait(()=>loads>beforeReload);
    check(await evaluate(`document.getElementById('profile-avatar-preview').src===${JSON.stringify(newUrl)} && document.getElementById('profile-avatar-preview').naturalWidth>0`),'Reload preserves and loads the saved image');
    console.log(JSON.stringify({passed:true,checks,chooser_event:true,selection:'CDP DOM.setFileInputFiles',preview:true,upload_status:true,saved:true,reloaded:true}));
} finally {
    ws.send(JSON.stringify({id:++sequence,method:'Browser.close'}));await delay(300);ws.close();
}
