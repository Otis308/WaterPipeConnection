const assert=require('assert/strict');
const {solvePortalBfs:solve}=require('../public/JS/TrangGame/PortalMapBfsWorker.js');
const cell=(r,c,file,rot=0,role='')=>({r,c,file,initialRot:rot,role});
const pair=(id,r,c,er,ec)=>({pairId:id,start:{r,c},end:{r:er,c:ec}});
const start=(r,c)=>cell(r,c,'OngBatDau.svg',0,'start');
const end=(r,c)=>cell(r,c,'OngKetThuc.svg',180,'end');
let map={cells:[start(1,1),cell(1,2,'OngXanh/OngXanh-Thang.svg'),end(1,3)],pipes:[pair(1,1,1,1,3)]};
let result=solve(map);
assert.equal(result.type,'solved');assert.equal(result.moves.length,1);assert.equal(result.moves[0].degrees,90);
assert.deepEqual(result.paths[0].cells,['1-1','1-2','1-3']);
// Deliberately poison any witness fields: solver must ignore them.
assert.deepEqual(solve({...map,solution:{moves:[{action:'bad'}]},paths:['wrong']}),result);
map={cells:[start(1,1),cell(1,2,'OngVang/OngVang-ChuL.svg',270),cell(2,2,'OngVang/OngVang-ChuL.svg',90),end(2,3)],pipes:[pair(1,1,1,2,3)]};
result=solve(map);assert.equal(result.type,'solved');assert.equal(result.moves.length,1);
assert.equal(result.moves[0].action,'swap');assert.deepEqual([result.moves[0].a,result.moves[0].b].sort(),['1-2','2-2']);
map={cells:[start(1,1),cell(1,2,'OngDo/OngDo-Thang.svg'),end(1,3)],pipes:[pair(1,1,1,1,3)]};
assert.equal(solve(map).type,'unsatisfiable');
map={cells:[start(1,1),cell(1,2,'OngXanh/OngXanh-Thang.svg'),end(1,3),start(2,1),cell(2,2,'OngXanh/OngXanh-Thang.svg'),end(2,3)],pipes:[pair(1,1,1,1,3),pair(2,2,1,2,3)]};
result=solve(map);assert.equal(result.moves.length,2);assert.equal(result.paths.length,2);
map.cells[1].initialRot=90;map.cells[4].initialRot=90;
assert.equal(solve(map).moves.length,0);
// Two individually reachable pairs cannot demand incompatible rotations of one pipe.
const crossing={cells:[start(2,1),end(2,3),cell(1,2,'OngBatDau.svg',90,'start'),
    cell(3,2,'OngKetThuc.svg',270,'end'),cell(2,2,'OngXanh/OngXanh-Thang.svg')],
    pipes:[pair(1,2,1,2,3),pair(2,1,2,3,2)]};
assert.equal(solve(crossing).type,'unsatisfiable');
crossing.cells[4].file='OngXanh/OngXanh-ChuThap.svg';
assert.equal(solve(crossing).type,'solved');
function validateReplay(map,result) {
    assert.equal(result.type,'solved');
    const cells=new Map(map.cells.map(c=>[`${c.r}-${c.c}`,{...c}]));
    const original=JSON.stringify(map);
    for(const m of result.moves){
        if(m.action==='rotate'){
            const c=cells.get(m.at);assert.ok(c.file.includes('OngXanh/')&&!c.role);
            c.initialRot=(c.initialRot+m.degrees)%360;
        }else{
            const a=cells.get(m.a),b=cells.get(m.b);
            assert.ok(a.file.includes('OngVang/')&&b.file.includes('OngVang/'));
            assert.equal(Math.abs(a.r-b.r)+Math.abs(a.c-b.c),1);
            for(const f of ['file','initialRot'])[a[f],b[f]]=[b[f],a[f]];
        }
    }
    const ports=c=>({BatDau:[1],KetThuc:[1],ChuThap:[0,1,2,3],ChuL:[1,2],Thang:[0,2]}[
        ['BatDau','KetThuc','ChuThap','ChuL','Thang'].find(s=>c.file.includes(s))]||[]).map(d=>(d+c.initialRot/90)%4);
    for(const pair of map.pipes){
        const start=`${pair.start.r}-${pair.start.c}`,end=`${pair.end.r}-${pair.end.c}`;
        const q=[start],seen=new Set(q);
        for(let h=0;h<q.length;h++){
            const at=cells.get(q[h]);
            [[-1,0],[0,1],[1,0],[0,-1]].forEach(([dr,dc],d)=>{
                const k=`${at.r+dr}-${at.c+dc}`,b=cells.get(k);
                if(!b||seen.has(k)||(b.role&&k!==end))return;
                if(ports(at).includes(d)&&ports(b).includes((d+2)%4)){seen.add(k);q.push(k);}
            });
        }assert.ok(seen.has(end),'Final board does not connect the pair');
    }
    assert.equal(JSON.stringify(map),original);
}
if(process.argv.includes('--maps')){
    const maps=JSON.parse(require('fs').readFileSync(0,'utf8'));
    for(const map of maps){
        const began=performance.now();const result=solve(map);validateReplay(map,result);
        const poisoned={...map,solution:{moves:[{action:'bad'}]},steps:['bad']};
        assert.deepEqual(solve(poisoned),result);
        console.log(`${map.mapSizeKey} ${map.difficulty} ${map.numPairs} pairs ${map.seedKey}: ${result.visited} path visits, ${result.moves.length} moves, ${Math.round(performance.now()-began)} ms`);
    }
}
console.log('Path solver: legal replay, fixed red, multiple pairs and poisoned witness passed (no minimum-action guarantee).');
