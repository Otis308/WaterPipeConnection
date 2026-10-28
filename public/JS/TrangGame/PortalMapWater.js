/* SVG templates copied from SVGnuoc.js; rect filling adapted from BanDoGame.js.
 * Scoped to the portal, leaving game globals/assets unchanged. */
(() => {
    'use strict';
    // SVG CHO DÒNG NƯỚC THẲNG
const svgNuocThang = `
<svg width="100%" height="100%" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" class="Ong-Nuoc">
 <g>
  <title>Layer 1</title>
  <g id="svg_6">
   <rect fill="#01caee" stroke="#000" x="72.5" y="179.5" width="55" height="11" class="OngThang_Nuoc" stroke-width="0"/>
   <rect fill="#01caee" stroke="#000" x="72.5" y="169.5" width="55" height="11" class="OngThang_Nuoc" stroke-width="0"/>
   <rect fill="#01caee" stroke="#000" x="72.5" y="159.5" width="55" height="11" class="OngThang_Nuoc" stroke-width="0"/>
   <rect fill="#01caee" stroke="#000" x="72.5" y="149.5" width="55" height="11" class="OngThang_Nuoc" stroke-width="0"/>
   <rect fill="#01caee" stroke="#000" x="72.5" y="139.5" width="55" height="11" class="OngThang_Nuoc" stroke-width="0"/>
   <rect fill="#01caee" stroke="#000" x="72.5" y="129.5" width="55" height="11" class="OngThang_Nuoc" stroke-width="0"/>
   <rect fill="#01caee" stroke="#000" x="72.5" y="119.5" width="55" height="11" class="OngThang_Nuoc" stroke-width="0"/>
   <rect fill="#01caee" stroke="#000" x="72.5" y="109.5" width="55" height="11" class="OngThang_Nuoc" stroke-width="0"/>
   <rect fill="#01caee" stroke="#000" x="72.5" y="99.5" width="55" height="11" class="OngThang_Nuoc" stroke-width="0"/>
   <rect fill="#01caee" stroke="#000" x="72.5" y="89.5" width="55" height="11" class="OngThang_Nuoc" stroke-width="0"/>
   <rect fill="#01caee" stroke="#000" x="72.5" y="79.5" width="55" height="11" class="OngThang_Nuoc" stroke-width="0"/>
   <rect fill="#01caee" stroke="#000" x="72.5" y="69.5" width="55" height="11" class="OngThang_Nuoc" stroke-width="0"/>
   <rect fill="#01caee" stroke="#000" x="72.5" y="59.5" width="55" height="11" class="OngThang_Nuoc" stroke-width="0"/>
   <rect fill="#01caee" stroke="#000" x="72.5" y="49.5" width="55" height="11" class="OngThang_Nuoc" stroke-width="0"/>
   <rect fill="#01caee" stroke="#000" x="72.5" y="39.5" width="55" height="11" class="OngThang_Nuoc" stroke-width="0"/>
   <rect fill="#01caee" stroke="#000" x="72.5" y="29.5" width="55" height="11" class="OngThang_Nuoc" stroke-width="0"/>
   <rect fill="#01caee" stroke="#000" x="72.5" y="19.5" width="55" height="11" class="OngThang_Nuoc" stroke-width="0"/>
   <rect fill="#01caee" stroke="#000" x="72.5" y="9.5" width="55" height="11" class="OngThang_Nuoc" stroke-width="0"/>
  </g>
 </g>
</svg>
`;

// SVG CHO DÒNG NƯỚC CHỮ THẬP
const svgNuocChuThapToanPhan = `
<svg width="100%" height="100%" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" class="Ong-Nuoc">
 <g>
  <g id="NhanhDuoi">
   <rect fill="#01caee" stroke-width="0" x="72.5" y="179.5" width="55" height="11" class="Nuoc_NhanhDuoi"/>
   <rect fill="#01caee" stroke-width="0" x="72.5" y="169.5" width="55" height="11" class="Nuoc_NhanhDuoi"/>
   <rect fill="#01caee" stroke-width="0" x="72.5" y="159.5" width="55" height="11" class="Nuoc_NhanhDuoi"/>
   <rect fill="#01caee" stroke-width="0" x="72.5" y="149.5" width="55" height="11" class="Nuoc_NhanhDuoi"/>
   <rect fill="#01caee" stroke-width="0" x="72.5" y="139.5" width="55" height="11" class="Nuoc_NhanhDuoi"/>
   <rect fill="#01caee" stroke-width="0" x="72.5" y="129.5" width="55" height="11" class="Nuoc_NhanhDuoi"/>
   <rect fill="#01caee" stroke-width="0" x="72.5" y="119.5" width="55" height="11" class="Nuoc_NhanhDuoi"/>
   <rect fill="#01caee" stroke-width="0" x="72.5" y="109.5" width="55" height="11" class="Nuoc_NhanhDuoi"/>
  </g>

  <g id="NhanhTren">
   <rect fill="#01caee" stroke-width="0" x="72.5" y="9.5" width="55" height="11" class="Nuoc_NhanhTren"/>
   <rect fill="#01caee" stroke-width="0" x="72.5" y="19.5" width="55" height="11" class="Nuoc_NhanhTren"/>
   <rect fill="#01caee" stroke-width="0" x="72.5" y="29.5" width="55" height="11" class="Nuoc_NhanhTren"/>
   <rect fill="#01caee" stroke-width="0" x="72.5" y="39.5" width="55" height="11" class="Nuoc_NhanhTren"/>
   <rect fill="#01caee" stroke-width="0" x="72.5" y="49.5" width="55" height="11" class="Nuoc_NhanhTren"/>
   <rect fill="#01caee" stroke-width="0" x="72.5" y="59.5" width="55" height="11" class="Nuoc_NhanhTren"/>
   <rect fill="#01caee" stroke-width="0" x="72.5" y="69.5" width="55" height="11" class="Nuoc_NhanhTren"/>
   <rect fill="#01caee" stroke-width="0" x="72.5" y="79.5" width="55" height="11" class="Nuoc_NhanhTren"/>
  </g>

  <g id="NhanhTrai">
   <rect x="10" y="72.5" width="11" height="55" fill="#01caee" stroke-width="0" class="Nuoc_NhanhTrai"/>
   <rect x="20" y="72.5" width="11" height="55" fill="#01caee" stroke-width="0" class="Nuoc_NhanhTrai"/>
   <rect x="30" y="72.5" width="11" height="55" fill="#01caee" stroke-width="0" class="Nuoc_NhanhTrai"/>
   <rect x="40" y="72.5" width="11" height="55" fill="#01caee" stroke-width="0" class="Nuoc_NhanhTrai"/>
   <rect x="50" y="72.5" width="11" height="55" fill="#01caee" stroke-width="0" class="Nuoc_NhanhTrai"/>
   <rect x="60" y="72.5" width="11" height="55" fill="#01caee" stroke-width="0" class="Nuoc_NhanhTrai"/>
  </g>

  <g id="NhanhPhai">
   <rect x="180" y="72.5" width="11" height="55" fill="#01caee" stroke-width="0" class="Nuoc_NhanhPhai"/>
   <rect x="170" y="72.5" width="11" height="55" fill="#01caee" stroke-width="0" class="Nuoc_NhanhPhai"/>
   <rect x="160" y="72.5" width="11" height="55" fill="#01caee" stroke-width="0" class="Nuoc_NhanhPhai"/>
   <rect x="150" y="72.5" width="11" height="55" fill="#01caee" stroke-width="0" class="Nuoc_NhanhPhai"/>
   <rect x="140" y="72.5" width="11" height="55" fill="#01caee" stroke-width="0" class="Nuoc_NhanhPhai"/>
   <rect x="130" y="72.5" width="11" height="55" fill="#01caee" stroke-width="0" class="Nuoc_NhanhPhai"/>
  </g>
  
  <rect x="72.5" y="72.5" width="55" height="55" fill="#01caee" stroke-width="0" class="Nuoc_Tam" style="opacity: 0; transition: opacity 0.2s;"/>
 </g>
</svg>
`;

// SVG CHO DÒNG NƯỚC CHỮ L
const svgNuocChuL = `
<svg width="100%" height="100%" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" class="Ong-Nuoc">
 <g>
  <title>Layer 1</title>
  <g id="svg_17">
   <g id="OngChuL_Cum1">
    <rect x="72.5" y="180" width="55" height="11" fill="#01caee" stroke-width="0" class="OngChuL_Nuoc"/>
    <rect x="72.5" y="170" width="55" height="11" fill="#01caee" stroke-width="0" class="OngChuL_Nuoc"/>
    <rect x="72.5" y="160" width="55" height="11" fill="#01caee" stroke-width="0" class="OngChuL_Nuoc"/>
    <rect x="72.5" y="150" width="55" height="11" fill="#01caee" stroke-width="0" class="OngChuL_Nuoc"/>
    <rect x="72.5" y="140" width="55" height="11" fill="#01caee" stroke-width="0" class="OngChuL_Nuoc"/>
    <rect x="72.5" y="130" width="55" height="11" fill="#01caee" stroke-width="0" class="OngChuL_Nuoc"/>
    <rect x="72.5" y="120" width="55" height="11" fill="#01caee" stroke-width="0" class="OngChuL_Nuoc"/>
   </g>
   <g id="OngChuL_Cum2">
    <rect x="72.5" y="120.44993" width="59" height="10" fill="#01caee" stroke-width="0" class="OngChuL_Nuoc" transform="rotate(20 102 125.45)"/>
    <rect x="73.5" y="114.69993" width="59" height="10" fill="#01caee" stroke="null" stroke-width="0" class="OngChuL_Nuoc" transform="rotate(28 103 119.7)"/>
    <rect x="76.19314" y="108.48586" width="61" height="13" fill="#01caee" stroke="null" stroke-width="0" class="OngChuL_Nuoc" transform="rotate(36 106.693 114.986)"/>
    <rect x="79.99999" y="105.44994" width="61" height="10" fill="#01caee" stroke-width="0" class="OngChuL_Nuoc" transform="rotate(46 110.5 110.45)"/>
   </g>
   <g id="OngChuL_Cum3">
    <rect x="120" y="72.5" width="11" height="55" fill="#01caee" stroke="null" stroke-width="0" class="OngChuL_Nuoc"/>
    <rect x="130" y="72.5" width="11" height="55" fill="#01caee" stroke="null" stroke-width="0" class="OngChuL_Nuoc"/>
    <rect x="140" y="72.5" width="11" height="55" fill="#01caee" stroke="null" stroke-width="0" class="OngChuL_Nuoc"/>
    <rect x="150" y="72.5" width="11" height="55" fill="#01caee" stroke="null" stroke-width="0" class="OngChuL_Nuoc"/>
    <rect x="160" y="72.5" width="11" height="55" fill="#01caee" stroke="null" stroke-width="0" class="OngChuL_Nuoc"/>
    <rect x="170" y="72.5" width="11" height="55" fill="#01caee" stroke="null" stroke-width="0" class="OngChuL_Nuoc"/>
    <rect x="180" y="72.5" width="11" height="55" fill="#01caee" stroke="null" stroke-width="0" class="OngChuL_Nuoc"/>
   </g>
   <g id="OngChuL_Cum4">
    <rect x="108" y="76.65" width="13" height="61" fill="#01caee" stroke="null" stroke-width="0" class="OngChuL_Nuoc" transform="rotate(-37 114.5 107.15)"/>
    <rect x="115" y="73.4" width="10" height="60" fill="#01caee" stroke="null" stroke-width="0" class="OngChuL_Nuoc" transform="rotate(-28 120 103.4)"/>
    <rect x="120.25" y="71.9" width="10" height="60" fill="#01caee" stroke="null" stroke-width="0" class="OngChuL_Nuoc" transform="rotate(-20 125.25 101.9)"/>
	</g>
  </g>
 </g>
</svg>
`;

// ============================================================================================================================

    const timers = new Set();
    function stop() { timers.forEach(clearTimeout); timers.clear(); }
    function later(fn, ms) { const id=setTimeout(()=>{timers.delete(id);fn();},ms); timers.add(id); }
    function clear(grid) {
        stop(); grid?.querySelectorAll('.portal-water').forEach(n=>n.remove());
    }
    function play(grid, map, cells, paths) {
        clear(grid);
        const positions = new Map(map.cells.map((c,i)=>[`${c.r}-${c.c}`,i]));
        const direction=(a,b)=>b.r<a.r?0:b.c>a.c?1:b.r>a.r?2:3;
        paths.forEach(path => {
            const color=map.pipes.find(p=>p.pairId===path.pairId)?.color.hex || '#01caee';
            path.cells.forEach((at,step) => {
                const cell=cells[at], tile=grid.children[positions.get(at)];
                if (!tile) return;
                later(()=>{
                    if (cell.role) { tile.style.boxShadow=`inset 0 0 16px ${color}`; return; }
                    const shape=['ChuThap','ChuL','Thang'].find(s=>cell.file.includes(s));
                    const template={Thang:svgNuocThang,ChuL:svgNuocChuL,ChuThap:svgNuocChuThapToanPhan}[shape];
                    if (!template) return;
                    const layer=document.createElement('div');
                    layer.className='portal-water';
                    layer.style.cssText='position:absolute;inset:0;pointer-events:none;z-index:1;';
                    layer.style.transform=`rotate(${cell.initialRot}deg)`;
                    layer.innerHTML=template;
                    // Templates contain duplicate IDs; they are scoped and unused here.
                    layer.querySelectorAll('[id]').forEach(n=>n.removeAttribute('id'));
                    const rects=Array.from(layer.querySelectorAll('rect'));
                    const input=(direction(cell,cells[path.cells[step-1]])-cell.initialRot/90+4)%4;
                    const exits={0:[100,0],1:[200,100],2:[100,200],3:[0,100]};
                    const [x,y]=exits[input];
                    rects.sort((a,b)=>{
                        const distance=n=>Math.abs(Number(n.getAttribute('x'))+Number(n.getAttribute('width'))/2-x)+Math.abs(Number(n.getAttribute('y'))+Number(n.getAttribute('height'))/2-y);
                        return distance(a)-distance(b);
                    });
                    rects.forEach(rect=>{rect.style.opacity=0;rect.style.fill=color;});
                    tile.appendChild(layer);
                    rects.forEach((rect,i)=>later(()=>{rect.style.opacity=1;},i*420/rects.length));
                },step*450);
            });
        });
    }
    window.PortalMapWater={play,clear,stop};
})();
