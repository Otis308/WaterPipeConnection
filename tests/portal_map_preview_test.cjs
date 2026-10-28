// DOM simulation: execute the real preview module using a generated PHP fixture.
const fs = require('fs'), vm = require('vm'), assert = require('assert/strict');
const data = JSON.parse(fs.readFileSync(0, 'utf8'));
data.map.configHash = 'fixture';
const nodes = new Map();
function node() { return {style:{}, textContent:'', disabled:false, children:[], events:{}, addEventListener(n,f){this.events[n]=f;}}; }
for (const id of ['portalReplayStatus','seedPreviewContainer','portalReplay','portalReset','portalSave','portalCreateLevelForm','portalGeneratedNotice','createLevelModal']) nodes.set(id,node());
const tiles = data.map.cells.map(() => { const n=node(), image=node(); n.querySelector=()=>image; return n; });
nodes.get('seedPreviewContainer').lastElementChild={children:tiles};
const form=nodes.get('portalCreateLevelForm');
form.elements=Object.fromEntries(['portal_generated_hash','title','map_size','difficulty','description','time_limit_seconds'].map(k=>[k,{value:''}]));
form.reset=()=>Object.values(form.elements).forEach(n=>n.value='');
let init, pending=null, shown=false, worker=null, waterRuns=0;
const context={console, document:{getElementById:id=>nodes.get(id), addEventListener:(n,f)=>{init=f;}},
    window:{addEventListener(){},PortalMapWater:{clear(){},stop(){},play(){waterRuns++;}}},
    Worker:class {constructor(){worker=this;}postMessage(map){assert.equal(map.solution,undefined);assert.equal(map.cells[0].correctRot,undefined);this.payload=map;}terminate(){this.terminated=true;}},
    bootstrap:{Modal:{getOrCreateInstance:()=>({show(){shown=true;}})}},
    setTimeout:f=>{pending=f;return 1;}, clearTimeout:()=>{pending=null;}};
vm.runInNewContext(fs.readFileSync('public/JS/TrangGame/PortalMapPreview.js','utf8'),context);
init(); const preview=context.window.PortalMapPreview;
const before=JSON.stringify(data.map);
preview.load(data);
assert.equal(nodes.get('portalReplay').disabled,false);
nodes.get('portalReplay').events.click();
assert.ok(worker.payload);assert.equal(worker.payload.pipes[0].steps,undefined);
worker.onmessage({data:{type:'solved',moves:data.solution.moves}});
assert.match(nodes.get('portalReplayStatus').textContent,/Bước 1/);
nodes.get('portalReplay').events.click(); assert.equal(pending,null); // pause
nodes.get('portalReplay').events.click();
let limit=1000;
while(pending && --limit){const f=pending;pending=null;f();}
assert.ok(limit>0); assert.match(nodes.get('portalReplayStatus').textContent,/Giải thành công/);
assert.equal(waterRuns,1);
nodes.get('portalSave').events.click();
assert.ok(shown); assert.equal(form.elements.portal_generated_hash.value,'fixture');
assert.equal(JSON.stringify(data.map),before); // never mutate the saved source
nodes.get('portalReset').events.click();
data.map.cells.forEach((c,i)=>assert.equal(tiles[i].querySelector().style.transform,`rotate(${c.initialRot}deg)`));
nodes.get('createLevelModal').events['hidden.bs.modal']();
assert.equal(form.elements.portal_generated_hash.value,'');
preview.clear(); assert.equal(nodes.get('portalReplay').disabled,true);
preview.load(data);nodes.get('portalReplay').events.click();const old=worker;
nodes.get('portalReplay').events.click();assert.ok(old.terminated);
old.onmessage({data:{type:'solved',moves:data.solution.moves}});
assert.match(nodes.get('portalReplayStatus').textContent,/Đã dừng BFS/);
console.log('Preview replay, pause/resume, BFS, reset, immutable save and modal cleanup passed (DOM simulation).');
