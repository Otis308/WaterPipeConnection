/* Independent breadth-first search of legal board states. No generator witness. */
'use strict';
function solvePortalBfs(map, report = () => {}) {
    const cells = map.cells;
    const index = new Map(cells.map((c, i) => [`${c.r}-${c.c}`, i]));
    const offsets = [[-1,0],[0,1],[1,0],[0,-1]];
    const neighbors = cells.map(c => offsets.map(([dr,dc]) => index.get(`${c.r+dr}-${c.c+dc}`)));
    const mask = c => {
        const s = ['BatDau','KetThuc','ChuThap','ChuL','Thang'].find(s => c.file.includes(s));
        return ({BatDau:[1],KetThuc:[1],ChuThap:[0,1,2,3],ChuL:[1,2],Thang:[0,2]}[s] || [])
            .reduce((m,d) => m | (1 << ((d+c.initialRot/90)%4)), 0);
    };
    const green = [], swaps = [];
    cells.forEach((c,i) => {
        if (!c.role && c.file.includes('OngXanh/')) green.push(i);
        if (!c.role && c.file.includes('OngVang/')) neighbors[i].forEach(j => {
            if (j > i && !cells[j].role && cells[j].file.includes('OngVang/')) swaps.push([i,j]);
        });
    });
    const pairs = map.pipes.map(p => ({id:p.pairId, start:index.get(`${p.start.r}-${p.start.c}`), end:index.get(`${p.end.r}-${p.end.c}`)}));
    if (!pairs.length || pairs.some(p => p.start === undefined || p.end === undefined)) throw new Error('Thiếu đầu hoặc cuối.');
    function goal(state) {
        const paths = [];
        for (const pair of pairs) {
            const parent = new Map([[pair.start, null]]), queue = [pair.start];
            for (let h=0; h<queue.length && !parent.has(pair.end); h++) {
                const at=queue[h], ports=state.charCodeAt(at);
                neighbors[at].forEach((next,d) => {
                    if (next === undefined || parent.has(next) || (cells[next].role && next !== pair.end)) return;
                    if ((ports & (1<<d)) && (state.charCodeAt(next) & (1<<((d+2)%4)))) {
                        parent.set(next,at); queue.push(next);
                    }
                });
            }
            if (!parent.has(pair.end)) return null;
            const path=[];
            for (let at=pair.end;at!==null;at=parent.get(at)) path.push(`${cells[at].r}-${cells[at].c}`);
            paths.push({pairId:pair.id, cells:path.reverse()});
        }
        return paths;
    }
    const start = String.fromCharCode(...cells.map(mask));
    const queue=[{state:start, parent:-1, move:null}], seen=new Set([start]);
    for (let h=0;h<queue.length;h++) {
        const current=queue[h], paths=goal(current.state);
        if (paths) {
            const moves=[];
            for (let n=h;queue[n].parent!==-1;n=queue[n].parent) moves.push(queue[n].move);
            return {type:'solved',moves:moves.reverse(),paths,visited:h+1,discovered:queue.length};
        }
        const add=(state,move) => { if (!seen.has(state)) {seen.add(state);queue.push({state,parent:h,move});} };
        for (const i of green) {
            const old=current.state.charCodeAt(i), rotated=((old<<1)&15)|(old>>3);
            if (old!==rotated) add(current.state.slice(0,i)+String.fromCharCode(rotated)+current.state.slice(i+1),
                {action:'rotate',at:`${cells[i].r}-${cells[i].c}`,degrees:90});
        }
        for (const [i,j] of swaps) {
            if (current.state[i]===current.state[j]) continue;
            const next=current.state.split(''); [next[i],next[j]]=[next[j],next[i]];
            add(next.join(''),{action:'swap',a:`${cells[i].r}-${cells[i].c}`,b:`${cells[j].r}-${cells[j].c}`});
        }
        if (h%2048===0) report({type:'progress',visited:h+1,discovered:queue.length});
    }
    return {type:'unsatisfiable',visited:queue.length};
}
if (typeof self !== 'undefined') self.onmessage = event => {
    try { self.postMessage(solvePortalBfs(event.data, message => self.postMessage(message))); }
    catch(error) { self.postMessage({type:'error',message:error.message}); }
};
if (typeof module !== 'undefined') module.exports = {solvePortalBfs};
