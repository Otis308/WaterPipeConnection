@extends('layouts.portal')
@section('title', 'Quản lý Màn Chơi (Resource Controller 7 Actions & AJAX Search)')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-info mb-1">🗺️ QUẢN LÝ MÀN CHƠI GAME NỐI ỐNG NƯỚC (CRUD 7 ACTIONS RESTFUL)</h3>
        <p class="text-secondary mb-0">
            Áp dụng: <code>Route::resource('levels', GameLevelController::class)</code> · Eager Loading · Policy Ownership · AJAX Live Search
        </p>
    </div>
    <a href="{{ route('levels.create') }}" class="btn btn-info fw-bold">+ Tạo Màn Chơi Mới (Admin / Staff)</a>
</div>

{{-- Bộ lọc Tìm kiếm kết hợp cả Form GET (PDO Pagination) lẫn AJAX Fetch API (Không reload trang) --}}
<x-card title="🔍 Tìm kiếm & Lọc Màn Chơi (Hỗ trợ cả PDO Prepared Statement & AJAX Fetch API)">
    <form method="GET" action="{{ route('levels.index') }}" id="levelFilterForm" class="row g-3 align-items-end">
        <div class="col-md-5">
            <label class="form-label text-info">Từ khóa (Tên màn chơi, kích thước 3x8..5x12, chủ đề)</label>
            <input type="text" name="keyword" id="ajaxKeywordInput" value="{{ $keyword }}"
                   class="form-control bg-dark text-light border-secondary"
                   placeholder="Gõ để tìm kiếm tức thì bằng AJAX hoặc nhấn Lọc...">
        </div>
        <div class="col-md-4">
            <label class="form-label text-info">Độ khó (Sử dụng chỉ thị @selected của Blade)</label>
            <select name="difficulty" id="ajaxDifficultySelect" class="form-select bg-dark text-light border-secondary">
                <option value="" @selected(empty($difficulty))>-- Tất cả độ khó --</option>
                <option value="easy" @selected($difficulty === 'easy')>Easy (Cơ bản)</option>
                <option value="medium" @selected($difficulty === 'medium')>Medium (Ống Đỏ cố định)</option>
                <option value="hard" @selected($difficulty === 'hard')>Hard (Ống Vàng hoán đổi)</option>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-info fw-bold flex-grow-1">Lọc (PDO)</button>
            <a href="{{ route('levels.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</x-card>

<x-card title="📋 Danh sách Màn chơi (Tổng số: {{ $pagination['total_records'] }} bản ghi)" subtitle="Trang {{ $pagination['current_page'] }} / {{ $pagination['total_pages'] }}">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr class="text-info">
                    <th>ID</th>
                    <th>Ảnh</th>
                    <th>Tên màn chơi & Chủ đề (1-N)</th>
                    <th>Kích thước</th>
                    <th>Độ khó</th>
                    <th>Thưởng</th>
                    <th>Tags (Quan hệ N-N)</th>
                    <th>Tác giả</th>
                    <th class="text-end">Thao tác (Policy)</th>
                </tr>
            </thead>
            <tbody id="levelsTableBody">
                @forelse($pagination['items'] as $lv)
                    <tr>
                        <td>#{{ $lv['id'] }}</td>
                        <td>
                            <img src="/{{ $lv['thumbnail'] }}" alt="thumb" class="rounded border border-secondary" style="width:56px;height:40px;object-fit:cover;">
                        </td>
                        <td>
                            <a href="/levels/{{ $lv['id'] }}" class="fw-bold text-warning text-decoration-none">{{ $lv['title'] }}</a>
                            <div class="small text-secondary">📂 {{ $lv['category_name'] }} (x{{ $lv['score_multiplier'] }})</div>
                        </td>
                        <td><code class="fs-6">{{ $lv['map_size'] }}</code></td>
                        <td><x-badge :difficulty="$lv['difficulty']" /></td>
                        <td class="text-warning fw-bold">{{ $lv['reward_stars'] }} ⭐</td>
                        <td>
                            @foreach($lv['tags'] as $t)
                                <span class="badge me-1" style="background:{{ $t['color_hex'] }};">{{ $t['name'] }}</span>
                            @endforeach
                        </td>
                        <td>
                            <span class="badge bg-secondary">{{ $lv['creator_username'] ?? 'admin' }}</span>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a href="/levels/{{ $lv['id'] }}" class="btn btn-sm btn-outline-info">Xem</a>
                                <a href="/levels/{{ $lv['id'] }}/edit" class="btn btn-sm btn-outline-warning">Sửa</a>
                                <form action="/levels/{{ $lv['id'] }}" method="POST" onsubmit="return confirm('Xác nhận xóa màn chơi này?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-secondary py-4">Không tìm thấy màn chơi nào khớp với từ khóa.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Phân trang giữ nguyên từ khóa tìm kiếm (Buổi 4 Tiết 5) --}}
    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top border-secondary">
        <div class="small text-secondary">
            Đang hiển thị trang <strong>{{ $pagination['current_page'] }}</strong> / <strong>{{ $pagination['total_pages'] }}</strong>
            (Mỗi trang {{ $pagination['limit'] }} màn chơi)
        </div>
        <div class="btn-group">
            @php
                $totalPages = (int)$pagination['total_pages'];
                $currPage = (int)$pagination['current_page'];
            @endphp
            @if($currPage > 1)
                <a class="btn btn-sm btn-outline-info"
                   href="/levels?keyword={{ urlencode($keyword) }}&difficulty={{ urlencode($difficulty) }}&page={{ $currPage - 1 }}">
                   « Trang trước
                </a>
            @endif
            @if($currPage < $totalPages)
                <a class="btn btn-sm btn-outline-info"
                   href="/levels?keyword={{ urlencode($keyword) }}&difficulty={{ urlencode($difficulty) }}&page={{ $currPage + 1 }}">
                   Trang sau »
                </a>
            @endif
        </div>
    </div>
</x-card>
@endsection

@push('scripts')
<script>
// Minh họa Bài tập thực hành Chương 2 (Slide 27-28): Tìm kiếm động không reload trang bằng Fetch API
const ajaxKeywordInput = document.getElementById('ajaxKeywordInput');
const ajaxDifficultySelect = document.getElementById('ajaxDifficultySelect');
const levelsTableBody = document.getElementById('levelsTableBody');

let searchTimer = null;
function triggerAjaxLevelSearch() {
    const kw = encodeURIComponent(ajaxKeywordInput.value.trim());
    const diff = encodeURIComponent(ajaxDifficultySelect.value);
    fetch(`/api/levels/search?keyword=${kw}&difficulty=${diff}`)
        .then(res => res.json())
        .then(json => {
            if (!json.success || !json.data || !Array.isArray(json.data.items)) return;
            levelsTableBody.innerHTML = '';
            if (json.data.items.length === 0) {
                levelsTableBody.innerHTML = '<tr><td colspan="9" class="text-center text-secondary py-4">Không có màn chơi phù hợp (AJAX Filtered).</td></tr>';
                return;
            }
            json.data.items.forEach(lv => {
                const tr = document.createElement('tr');
                const tagsHtml = (lv.tags || []).map(t => `<span class="badge me-1" style="background:${t.color_hex}">${t.name}</span>`).join('');
                tr.innerHTML = `
                    <td>#${lv.id}</td>
                    <td><img src="/${lv.thumbnail}" class="rounded border border-secondary" style="width:56px;height:40px;object-fit:cover;"></td>
                    <td>
                        <a href="/levels/${lv.id}" class="fw-bold text-warning text-decoration-none">${lv.title}</a>
                        <div class="small text-secondary">📂 ${lv.category_name || ''}</div>
                    </td>
                    <td><code class="fs-6">${lv.map_size}</code></td>
                    <td><span class="badge bg-info text-dark">${lv.difficulty.toUpperCase()}</span></td>
                    <td class="text-warning fw-bold">${lv.reward_stars} ⭐</td>
                    <td>${tagsHtml}</td>
                    <td><span class="badge bg-secondary">${lv.creator_username || 'admin'}</span></td>
                    <td class="text-end">
                        <a href="/levels/${lv.id}" class="btn btn-sm btn-outline-info">Xem</a>
                        <a href="/levels/${lv.id}/edit" class="btn btn-sm btn-outline-warning">Sửa</a>
                    </td>
                `;
                levelsTableBody.appendChild(tr);
            });
        })
        .catch(err => console.error('AJAX search error:', err));
}

ajaxKeywordInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(triggerAjaxLevelSearch, 300);
});
ajaxDifficultySelect.addEventListener('change', triggerAjaxLevelSearch);
</script>
@endpush
