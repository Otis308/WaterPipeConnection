@extends('layouts.portal')
@section('title', 'Chi tiết Màn Chơi: ' . $level['title'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="fw-bold text-info mb-0">🔎 Chi tiết Màn Chơi #{{ $level['id'] }}: {{ $level['title'] }}</h3>
    <div class="d-flex gap-2">
        <a href="/levels/{{ $level['id'] }}/edit" class="btn btn-warning btn-sm fw-bold">✏️ Chỉnh sửa</a>
        <a href="{{ route('levels.index') }}" class="btn btn-outline-light btn-sm">← Danh sách</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-5">
        <x-card title="Hình ảnh & Khởi chạy Nhanh">
            <img src="/{{ $level['thumbnail'] }}" class="img-fluid rounded border border-secondary mb-3 w-100" style="max-height:240px;object-fit:cover;">
            <p class="text-secondary">{{ $level['description'] }}</p>
            <button type="button" class="btn btn-warning w-100 fw-bold py-2" onclick="napCauHinhVaVaoGame('{{ $level['map_size'] }}', '{{ $level['difficulty'] }}')">
                🎮 Chơi Ngay Bản Đồ Này ({{ $level['map_size'] }} - {{ strtoupper($level['difficulty']) }})
            </button>
        </x-card>
    </div>
    <div class="col-md-7">
        <x-card title="Thông số Kỹ thuật & Cấu hình NoSQL JSON">
            <table class="table table-dark table-bordered mb-0">
                <tr>
                    <th class="text-info" style="width:35%;">Chủ đề (Quan hệ 1-N)</th>
                    <td>{{ $level['category_name'] }}</td>
                </tr>
                <tr>
                    <th class="text-info">Slug chuẩn SEO (Regex)</th>
                    <td><code>{{ $level['slug'] }}</code></td>
                </tr>
                <tr>
                    <th class="text-info">Kích thước Lưới</th>
                    <td><code>{{ $level['map_size'] }}</code> ({{ $level['grid_rows'] }} hàng × {{ $level['grid_cols'] }} cột)</td>
                </tr>
                <tr>
                    <th class="text-info">Độ khó</th>
                    <td><x-badge :difficulty="$level['difficulty']" /></td>
                </tr>
                <tr>
                    <th class="text-info">Thời gian & Phần thưởng</th>
                    <td>⏱  {{ $level['time_limit_seconds'] }} giây — 🏆 <strong class="text-warning">{{ $level['reward_stars'] }} Sao</strong></td>
                </tr>
                <tr>
                    <th class="text-info">Tác giả thiết kế</th>
                    <td>{{ $level['creator_username'] ?? 'admin' }}</td>
                </tr>
                <tr>
                    <th class="text-info">Cấu hình NoSQL JSON</th>
                    <td><code>{{ $level['map_config_json'] }}</code></td>
                </tr>
            </table>
        </x-card>
    </div>
</div>
@endsection

@push('scripts')
<script>
const levelData = {
    mapSize: "{{ $level['map_size'] }}",
    difficulty: "{{ $level['difficulty'] }}"
};

function napCauHinhVaVaoGame() {
    sessionStorage.setItem('gameMode', 'player');
    sessionStorage.setItem('mapOption', 'custom');
    sessionStorage.setItem('mapSize', levelData.mapSize);
    sessionStorage.setItem('difficulty', levelData.difficulty);
    localStorage.setItem('tongDiem', '0');
    window.location.href = '/TrangGame.html';
}
</script>
@endpush
