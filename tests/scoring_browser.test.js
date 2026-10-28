'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { score } = require('../public/JS/TrangGame/PipeScore.js');
const Queue = require('../public/JS/TrangGame/PvpActionQueue.js');
const sandbox = { window: {}, document: { addEventListener() {} }, sessionStorage: { getItem() { return '2'; } }, console };
vm.createContext(sandbox);
vm.runInContext(fs.readFileSync(require.resolve('../public/JS/TrangGame/BanDoGame.js'), 'utf8'), sandbox);
if (process.argv.includes('--maps')) {
  const maps = [];
  for (const size of ['3x8','3x10','3x12','5x4','5x6','5x8','5x10','5x12']) {
    for (const difficulty of ['easy','medium','hard']) {
      const [rows,cols] = size.split('x').map(Number);
      const pairs = cols <= 4 ? 2 : cols <= 6 ? Math.min(3, difficulty === 'easy' ? 2 : 3) : difficulty === 'easy' ? 2 : difficulty === 'medium' ? 3 : 4;
      maps.push({ label: `Solo ${size} ${difficulty} P${pairs}`,
        map: sandbox.taoDuLieuMotBanDo(5,12,rows,cols,difficulty,pairs,`SCORING_BENCH_${size}_${difficulty}_${pairs}`) });
    }
  }
  process.stdout.write(JSON.stringify(maps));
  return;
}
(async () => {
  let assertions = 0;
  function check(condition) { assertions++; assert.ok(condition); }
  for (const n of [2,3,4]) check(score(Array(n).fill(4), { status:'proven', vectors:[Array(n).fill(4)] }).quality_score === 100);
  check(score([6],{status:'proven',vectors:[[4]]}).quality_score === 44);
  check(score([1001],{status:'proven',vectors:[[1000]]}).is_optimal === false);
  check(score([3,10],{status:'proven',vectors:[[5,6]]}).quality_score === null);
  check(score([null],{status:'proven',vectors:[[4]]}).quality_score === 0);
  check(score([null],{status:'unproven'}).quality_score === 0);
  check(score([0],{status:'proven',vectors:[[4]]}).score_status === 'invalid_lengths');
  check(score([4],{status:'unproven'}).quality_score === null);
  for (const lengths of [[4,6],[6,4]]) check(score(lengths,{status:'proven',vectors:[[4,6],[6,4]]}).is_optimal);
  const sent = [], replies = [];
  let release;
  const gate = new Promise(resolve => { release = resolve; });
  const queue = new Queue(async (endpoint,payload) => {
    sent.push({ endpoint, ...payload });
    if (sent.length === 1) await gate;
    return { success:true, board_state_version: endpoint === 'submit-move' ? payload.expected_version+1 : payload.expected_version, is_completed: endpoint === 'submit-solution' };
  }, result => replies.push(result));
  const a = queue.push({action:'rotate',row:2,col:2,degrees:90});
  const b = queue.push({action:'swap',rowA:2,colA:3,rowB:2,colB:4});
  const submitted = queue.submit();
  await new Promise(resolve => setImmediate(resolve));
  check(sent.length === 1); release();
  await Promise.all([a,b,submitted]);
  check(sent.map(p=>p.endpoint).join(',')==='submit-move,submit-move,submit-solution');
  check(sent.map(p=>p.expected_version).join(',')==='0,1,2');
  check(queue.version===2 && queue.pending===0 && replies.length===3);
  const attempts = [];
  const retry = new Queue(async (_,payload) => {
    attempts.push(payload);
    if (attempts.length===1) throw new Error('lost response');
    return {success:true,board_state_version:1};
  },()=>{});
  await retry.push({action:'rotate'});
  check(attempts.length===2 && JSON.stringify(attempts[0])===JSON.stringify(attempts[1]));
  let submitSent = false;
  const failed = new Queue(async (endpoint) => {
    if(endpoint==='submit-solution') submitSent=true;
    return {success:false,message:'version mismatch'};
  },()=>{});
  await failed.push({action:'rotate'}).catch(()=>{});
  await assert.rejects(failed.submit()); check(!submitSent);
  // Real BanDoGame BFS with a tiny DOM adapter: branches, multiple live routes,
  // wrong endpoints and edge counts, independent of score formula implementation.
  const nodes = new Map();
  for(let r=1;r<=3;r++) for(let c=1;c<=5;c++) nodes.set(`${r}-${c}`, {r,c,shape:'ChuThap',rot:0});
  nodes.set('2-1',{r:2,c:1,shape:'BatDau',rot:0});
  nodes.set('2-5',{r:2,c:5,shape:'KetThuc',rot:180});
  nodes.get('2-3').shape='Thang';
  sandbox.document.querySelector = selector => {
    const match = selector.match(/data-row="(\d+)".*data-col="(\d+)"/);
    const node=match && nodes.get(`${match[1]}-${match[2]}`);
    if(!node)return null;
    return {querySelector: q => q==='.Ong-Wrapper' ? null : {src:node.shape+'.svg',dataset:{rotation:String(node.rot)}}};
  };
  const pair={start:{r:2,c:1},end:{r:2,c:5}};
  check(sandbox.kiemTraKetNoiCapDuongOngBFS(pair).bfsDistance===6);
  nodes.get('2-3').rot=90;
  const res=sandbox.kiemTraKetNoiCapDuongOngBFS(pair);
  check(res.bfsDistance===4 && res.totalFlowCells>5);
  nodes.get('2-5').shape='BatDau';
  check(!sandbox.kiemTraKetNoiCapDuongOngBFS(pair).connected);
  console.log(`Browser scoring/queue/BFS: ${assertions} assertions passed`);
})().catch(error=>{ console.error(error); process.exitCode=1; });
