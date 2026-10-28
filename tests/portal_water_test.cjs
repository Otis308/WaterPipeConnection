const fs=require('fs'), vm=require('vm'), assert=require('assert/strict');
let jobs=new Map(), next=0, layers=[];
const tiles=Array.from({length:4},()=>({style:{},appendChild(layer){layers.push(layer);}}));
const grid={children:tiles,querySelectorAll(){return layers;}};
const context={window:{},setTimeout(fn,ms){jobs.set(++next,{fn,ms});return next;},clearTimeout(id){jobs.delete(id);},
    document:{createElement(){return {style:{},remove(){layers=layers.filter(x=>x!==this);},querySelectorAll(selector){
        if(selector==='[id]')return [];
        return this.rects ||= [...this.innerHTML.matchAll(/<rect\b([^>]+)>/g)].map(([,attrs])=>({style:{},getAttribute(name){return attrs.match(new RegExp('\\b'+name+'="([^"]+)"'))?.[1] || '0';}}));
    }};}}};
vm.runInNewContext(fs.readFileSync('public/JS/TrangGame/PortalMapWater.js','utf8'),context);
const api=context.window.PortalMapWater;
const cells={'1-1':{r:1,c:1,role:'start'},'1-2':{r:1,c:2,file:'OngXanh/OngXanh-Thang.svg',initialRot:90},
    '1-3':{r:1,c:3,file:'OngXanh/OngXanh-ChuL.svg',initialRot:90},'2-3':{r:2,c:3,role:'end'}};
const map={cells:Object.values(cells),pipes:[{pairId:1,color:{hex:'#00e5ff'}}]};
api.play(grid,map,cells,[{pairId:1,cells:Object.keys(cells)}]);
assert.equal(jobs.size,4);
while(jobs.size){const [id,job]=[...jobs.entries()].sort((a,b)=>a[1].ms-b[1].ms)[0];jobs.delete(id);job.fn();}
assert.equal(layers.length,2);
assert.ok(layers.every(layer=>layer.rects.length>0 && layer.rects.every(r=>r.style.opacity===1 && r.style.fill==='#00e5ff')));
api.play(grid,map,cells,[{pairId:1,cells:Object.keys(cells)}]);api.clear(grid);
assert.equal(jobs.size,0);assert.equal(layers.length,0);
console.log('Copied SVG water: sequential scheduling, straight/corner fill, pair colour and cancellation passed (DOM simulation).');
