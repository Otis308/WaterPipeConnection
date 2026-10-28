/* Portal only. Colour/port rules copied from BanDoGame; no game globals changed. */
(() => {
    'use strict';
    const clone = value => JSON.parse(JSON.stringify(value));
    const key = cell => `${cell.r}-${cell.c}`;
    let original = null, cells = null, moves = [], timer = null, step = 0;
    let worker = null, generation = 0, solved = false;
    const el = id => document.getElementById(id);
    const status = text => { el('portalReplayStatus').textContent = text; };
    function stop() { clearTimeout(timer); timer = null; }
    function cancelSearch() { generation++; worker?.terminate(); worker=null; }
    const grid = () => el('seedPreviewContainer').lastElementChild;
    function paint(highlight = []) {
        const grid = el('seedPreviewContainer').lastElementChild;
        if (!original || !grid) return;
        original.cells.forEach((cell, i) => {
            const tile = grid.children[i];
            if (!tile) return;
            const current = cells[key(cell)];
            const image = tile.querySelector('img');
            image.src = '/Images/svg/Ong/' + current.file;
            image.style.transform = `rotate(${current.initialRot}deg)`;
            tile.style.boxShadow = highlight.includes(key(cell)) ? 'inset 0 0 0 3px #facc15' : '';
        });
    }
    function reset() {
        stop(); cancelSearch(); window.PortalMapWater.clear(grid()); step = 0;
        if (!original) return;
        cells = Object.fromEntries(original.cells.map(c => [key(c), clone(c)]));
        paint(); el('portalReplay').textContent = 'Xem cách giải';
        status('Bản đồ ban đầu. Khi lưu sẽ luôn dùng bản đồ này.');
    }
    const ports = cell => {
        // BanDoGame CAU_HINH_ONG: 0 up, 1 right, 2 down, 3 left.
        const shape = ['BatDau', 'KetThuc', 'ChuThap', 'ChuL', 'Thang'].find(s => cell.file.includes(s));
        const base = {BatDau:[1], KetThuc:[1], ChuThap:[0,1,2,3], ChuL:[1,2], Thang:[0,2]}[shape] || [];
        return base.map(d => (d + cell.initialRot / 90) % 4);
    };
    function connected() {
        const traces = [];
        for (const pair of original.pipes) {
            const start = key(pair.start), end = key(pair.end);
            const queue = [start], parent = new Map([[start, null]]);
            for (let h = 0; h < queue.length && !parent.has(end); h++) {
                const at = cells[queue[h]];
                [[-1,0],[0,1],[1,0],[0,-1]].forEach(([dr,dc], d) => {
                    const next = `${at.r + dr}-${at.c + dc}`, other = cells[next];
                    if (!other || parent.has(next) || (other.role && next !== end)) return;
                    if (ports(at).includes(d) && ports(other).includes((d + 2) % 4)) {
                        parent.set(next, queue[h]); queue.push(next);
                    }
                });
            }
            if (!parent.has(end)) return null;
            const path=[];
            for (let at = end; at !== null; at = parent.get(at)) path.push(at);
            traces.push({pairId:pair.pairId,cells:path.reverse()});
        }
        return traces;
    }
    function tick() {
        timer = null;
        if (step === moves.length) {
            const trace = connected(); paint();
            if (trace) window.PortalMapWater.play(grid(),original,cells,trace);
            status(trace ? `Giải thành công: ${original.numPairs}/${original.numPairs} cặp đã nối (BFS kiểm tra).` : 'Kiểm tra thất bại: chưa nối đủ các cặp.');
            el('portalReplay').textContent = 'Xem lại cách giải'; return;
        }
        const move = moves[step++]; let selected;
        if (move.action === 'rotate') {
            const cell = cells[move.at];
            if (!cell || !cell.file.includes('OngXanh/')) throw new Error('Thao tác xoay không hợp lệ.');
            cell.initialRot = (cell.initialRot + move.degrees) % 360; selected = [move.at];
        } else {
            const a = cells[move.a], b = cells[move.b];
            if (!a || !b || !a.file.includes('OngVang/') || !b.file.includes('OngVang/')
                || Math.abs(a.r-b.r) + Math.abs(a.c-b.c) !== 1) throw new Error('Đổi ống vàng không hợp lệ.');
            ['file','shape','initialRot'].forEach(field => { [a[field], b[field]] = [b[field], a[field]]; });
            selected = [move.a, move.b];
        }
        paint(selected); status(`Bước ${step}/${moves.length}: ${move.action === 'rotate' ? 'xoay ống xanh' : 'đổi hai ống vàng kề nhau'}.`);
        timer = setTimeout(run, 450);
    }
    function run() {
        try { tick(); } catch (error) { stop(); status(error.message); el('portalReplay').textContent = 'Xem lại cách giải'; }
    }
    window.PortalMapPreview = {
        clear() {
            stop(); cancelSearch(); window.PortalMapWater.clear(grid()); original = null; moves = []; step = 0; solved=false;
            ['portalReplay','portalReset','portalSave'].forEach(id => { el(id).disabled = true; });
            status('');
        },
        load(data) {
            original = clone(data.map); moves = []; solved=false; reset();
            ['portalReplay','portalReset','portalSave'].forEach(id => { el(id).disabled = false; });
        }
    };
    document.addEventListener('DOMContentLoaded', () => {
        el('portalReplay').addEventListener('click', () => {
            if (worker) { cancelSearch(); status('Đã dừng BFS.'); el('portalReplay').textContent='Tìm lời giải bằng BFS'; return; }
            if (timer !== null) { stop(); el('portalReplay').textContent = 'Tiếp tục'; return; }
            if (!solved) {
                reset();
                const token=++generation;
                try { worker=new Worker('/JS/TrangGame/PortalMapBfsWorker.js'); }
                catch(error) { status(error.message); return; }
                status('Đang tìm đường bằng BFS và kiểm tra ràng buộc ống, không đọc đáp án sinh map...'); el('portalReplay').textContent='Dừng tìm';
                worker.onerror=()=>{if(token!==generation)return;cancelSearch();status('Worker BFS gặp lỗi hoặc hết bộ nhớ.');el('portalReplay').textContent='Tìm lại bằng BFS';};
                worker.onmessage=event=>{
                    if(token!==generation)return;
                    const result=event.data;
                    if(result.type==='progress') {status(`Đã xét ${result.visited.toLocaleString()} bước tìm đường; đang kiểm tra các cặp và cụm ống vàng.`);return;}
                    cancelSearch();
                    if(result.type!=='solved') {status(result.message || 'Đã duyệt các phương án đường nối: không có lời giải hợp lệ.');el('portalReplay').textContent='Tìm lại lời giải';return;}
                    moves=result.moves; solved=true;step=0;
                    el('portalReplay').textContent='Tạm dừng';run();
                };
                // Explicit allowlist: never send generator paths, steps or witness.
                worker.postMessage({cells:original.cells.map(c=>({r:c.r,c:c.c,file:c.file,initialRot:c.initialRot,role:c.role})),
                    pipes:original.pipes.map(p=>({pairId:p.pairId,start:{r:p.start.r,c:p.start.c},end:{r:p.end.r,c:p.end.c}}))});
                return;
            }
            if (step === moves.length) reset();
            el('portalReplay').textContent = 'Tạm dừng'; run();
        });
        el('portalReset').addEventListener('click', reset);
        el('portalSave').addEventListener('click', () => {
            if (!original) return;
            const form = el('portalCreateLevelForm');
            form.reset();
            form.elements.portal_generated_hash.value = original.configHash;
            form.elements.title.value = 'Bản đồ ' + original.seedKey;
            form.elements.map_size.value = original.mapSizeKey;
            form.elements.difficulty.value = original.difficulty;
            form.elements.description.value = `Bản đồ sinh theo seed ${original.seedKey}, ${original.numPairs} cặp đầu–cuối, đã kiểm tra lời giải.`;
            form.elements.time_limit_seconds.value = original.thinkingTimeMs / 1000;
            el('portalGeneratedNotice').textContent = 'Lưu nguyên bản đồ ban đầu. Giữ nguyên kích thước và độ khó; bạn có thể sửa tiêu đề, danh mục, mô tả và thời gian.';
            bootstrap.Modal.getOrCreateInstance(el('createLevelModal')).show();
        });
        el('createLevelModal').addEventListener('hidden.bs.modal', () => {
            el('portalCreateLevelForm').elements.portal_generated_hash.value = '';
            el('portalGeneratedNotice').textContent = '';
        });
        window.addEventListener('pagehide', () => {stop();cancelSearch();window.PortalMapWater.stop();});
    });
})();
