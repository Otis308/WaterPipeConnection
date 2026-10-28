/* Relaxed BFS + joint path constraints, adapted from PipeScoringService.
 * No generator witness; first feasible solution, not minimum actions. */
'use strict';
function solvePortalBfs(map, report=()=>{}) {
 const cells=map.cells,n=cells.length,key=c=>c.r+'-'+c.c;
 if(!n||n>60)throw new Error('Invalid map');
 const index=new Map(cells.map((c,i)=>[key(c),i]));
 const nb=cells.map(c=>[[-1,0],[0,1],[1,0],[0,-1]].map(([r,k])=>index.get((c.r+r)+'-'+(c.c+k))));
 const rotate=m=>((m<<1)&15)|(m>>3);
 const mask=c=>({BatDau:[1],KetThuc:[1],ChuThap:[0,1,2,3],ChuL:[1,2],Thang:[0,2]}[
  ['BatDau','KetThuc','ChuThap','ChuL','Thang'].find(s=>c.file.includes(s))]||[])
  .reduce((m,d)=>m|(1<<((d+c.initialRot/90)%4)),0);
 const initial=cells.map(mask),domains=initial.map(m=>[m]),green=[],groups=[],grouped=new Set();
 cells.forEach((c,i)=>{
  if(!c.role&&c.file.includes('OngXanh/')){green.push(i);let m=initial[i];const opts=[];for(let a=0;a<4;a++){opts.push(m);m=rotate(m);}domains[i]=[...new Set(opts)];}
  if(c.role||!c.file.includes('OngVang/')||grouped.has(i))return;
  const members=[i];grouped.add(i);
  for(let h=0;h<members.length;h++)for(const j of nb[members[h]])if(j!==undefined&&!cells[j].role&&cells[j].file.includes('OngVang/')&&!grouped.has(j)){grouped.add(j);members.push(j);}
  const tokens=members.map(at=>initial[at]);members.forEach(at=>{domains[at]=[...new Set(tokens)];});groups.push({members,tokens});
 });
 const needs=Array(n).fill(0),fits=(at,need)=>domains[at].some(m=>(m&need)===need);
 function matching() {
  const result=[];
  for(const group of groups){
   const owners=new Map();
   function augment(at,seen){
    for(let t=0;t<group.tokens.length;t++){
     if(seen.has(t)||(group.tokens[t]&needs[at])!==needs[at])continue;
     seen.add(t);if(!owners.has(t)||augment(owners.get(t),seen)){owners.set(t,at);return true;}
    }return false;
   }
   for(const at of group.members)if(needs[at]&&!augment(at,new Set()))return null;
   const desired=new Map([...owners].map(([t,at])=>[at,t]));
   const free=group.members.filter(at=>!desired.has(at));
   for(let t=0;t<group.tokens.length;t++)if(!owners.has(t))desired.set(free.shift(),t);
   result.push(desired);
  }return result;
 }
 const pairs=map.pipes.map(p=>({id:p.pairId,start:index.get(key(p.start)),end:index.get(key(p.end))}));
 if(!pairs.length||pairs.some(p=>p.start===undefined||p.end===undefined||p.start===p.end))throw new Error('Invalid endpoints');
 // Reverse BFS on (cell, entry direction), not on whole-board rotations.
 for(const p of pairs){
  const dist=Array(n*4).fill(Infinity),q=[];
  for(let d=0;d<4;d++)if(fits(p.end,1<<d)){dist[p.end*4+d]=0;q.push([p.end,d]);}
  for(let h=0;h<q.length;h++){
   const [at,entry]=q[h],prev=nb[at][entry];if(prev===undefined||(cells[prev].role&&prev!==p.start))continue;
   for(let d=0;d<4;d++){const slot=prev*4+d;
    if(dist[slot]!==Infinity||!fits(prev,(1<<d)|(1<<((entry+2)%4))))continue;
    dist[slot]=dist[at*4+entry]+1;q.push([prev,d]);
   }
  }p.dist=dist;
 }
 const distance=(p,at,entry)=>{
  if(at===p.end)return 0;if(entry>=0)return p.dist[at*4+entry];
  let best=Infinity;nb[at].forEach((j,d)=>{if(j!==undefined&&fits(at,1<<d))best=Math.min(best,1+p.dist[j*4+(d+2)%4]);});return best;
 };
 if(pairs.some(p=>!Number.isFinite(distance(p,p.start,-1))))return {type:'unsatisfiable',visited:0};
 pairs.sort((a,b)=>a.dist.filter(Number.isFinite).length-b.dist.filter(Number.isFinite).length);
 let visits=0,target=null;const paths=[];
 function search(k){
  if(k===pairs.length){target=matching();return target!==null;}
  const p=pairs[k],seen=new Set([p.start]),path=[p.start];
  function walk(at,entry){
   if(++visits%1024===0)report({type:'progress',visited:visits,discovered:visits,phase:'paths'});
   const old=needs[at],incoming=entry<0?0:1<<entry;
   if(at===p.end){
    if(!fits(at,old|incoming))return false;needs[at]=old|incoming;
    if(matching()!==null){paths[k]={pairId:p.id,cells:path.map(i=>key(cells[i]))};if(search(k+1))return true;}
    needs[at]=old;return false;
   }
   const options=[];
   nb[at].forEach((j,d)=>{
    if(j===undefined||seen.has(j)||(cells[j].role&&j!==p.end))return;
    const need=old|incoming|(1<<d),e=(d+2)%4,dist=distance(p,j,e);
    if(fits(at,need)&&fits(j,needs[j]|(1<<e))&&Number.isFinite(dist))options.push({j,need,e,dist});
   });
   options.sort((a,b)=>a.dist-b.dist);
   for(const o of options){
    needs[at]=o.need;if(matching()===null)continue;
    seen.add(o.j);path.push(o.j);if(walk(o.j,o.e))return true;path.pop();seen.delete(o.j);
   }needs[at]=old;return false;
  }return walk(p.start,-1);
 }
 if(!search(0))return {type:'unsatisfiable',visited:visits};
 const moves=[],actual=[...initial];
 for(const at of green){let m=actual[at],a=0;while((m&needs[at])!==needs[at]&&a<4){m=rotate(m);a++;}
  for(let s=0;s<a;s++)moves.push({action:'rotate',at:key(cells[at]),degrees:90});actual[at]=m;
 }
 // Fix spanning-tree leaves with legal adjacent swaps (no permutation BFS).
 groups.forEach((g,idx)=>{
  const current=new Map(g.members.map((at,t)=>[at,t])),active=new Set(g.members);
  const tree=new Map(g.members.map(at=>[at,[]])),seen=new Set([g.members[0]]),q=[g.members[0]];
  for(let h=0;h<q.length;h++)for(const j of nb[q[h]])if(active.has(j)&&!seen.has(j)){seen.add(j);q.push(j);tree.get(q[h]).push(j);tree.get(j).push(q[h]);}
  while(active.size>1){
   const leaf=[...active].find(at=>tree.get(at).filter(j=>active.has(j)).length===1);
   const source=[...active].find(at=>current.get(at)===target[idx].get(leaf));
   const parent=new Map([[source,null]]),q=[source];
   for(let h=0;h<q.length&&!parent.has(leaf);h++)for(const j of tree.get(q[h]))if(active.has(j)&&!parent.has(j)){parent.set(j,q[h]);q.push(j);}
   const route=[];for(let at=leaf;at!==null;at=parent.get(at))route.push(at);route.reverse();
   for(let i=0;i<route.length-1;i++){
    const a=route[i],b=route[i+1],t=current.get(a);current.set(a,current.get(b));current.set(b,t);
    [actual[a],actual[b]]=[actual[b],actual[a]];moves.push({action:'swap',a:key(cells[a]),b:key(cells[b])});
   }active.delete(leaf);
  }
 });
 if(actual.some((m,i)=>(m&needs[i])!==needs[i]))throw new Error('Invalid constructed solution');
 return {type:'solved',method:'bfs-paths-constraints',moves,paths,visited:visits,discovered:visits};
}
if(typeof self!=='undefined')self.onmessage=event=>{
 try{self.postMessage(solvePortalBfs(event.data,m=>self.postMessage(m)));}
 catch(error){self.postMessage({type:'error',message:error.message});}
};
if(typeof module!=='undefined')module.exports={solvePortalBfs};

