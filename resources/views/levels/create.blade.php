@extends('layouts.portal')
@section('title', 'Tạo Màn Chơi Mới (Form Request, Custom Rule & File Upload)')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="fw-bold text-info mb-0">+ Tạo Màn Chơi Nối Ống Nước Mới</h3>
    <a href="{{ route('levels.index') }}" class="btn btn-outline-light btn-sm">← Quay lại danh sách</a>
</div>

<x-alert type="warning" title="Kiểm thử Validation & Custom Rule (Bài 9)">
    Form này áp dụng <code>StoreGameLevelRequest</code> và <code>NoForbiddenWords</code>: Nếu bạn nhập các từ cấm như <code>test</code>, <code>spam</code>, <code>hack</code>, <code>xxx</code> vào Tiêu đề hoặc Mô tả, hệ thống sẽ báo lỗi ngay dưới trường tương ứng và giữ lại dữ liệu cũ bằng <code>old()</code>!
</x-alert>

<x-card title="Thông tin Cấu hình Màn Chơi" subtitle="Hỗ trợ Upload Thumbnail & Gắn Tags N-N">
    <form action="{{ route('levels.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="portal_generated_hash" value="{{ old('portal_generated_hash', '') }}">

        <div class="row">
            <div class="col-md-6">
                <x-input name="title" label="Tiêu đề màn chơi (*)" placeholder="VD: Hệ thống Thủy lợi Mê Kông Cấp 3" />
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label fw-semibold text-info">Chủ đề Ống Nước (1-N)</label>
                <select name="category_id" class="form-select bg-dark text-light border-secondary">
                    @foreach($categories as $cat)
                        <option value="{{ $cat['id'] }}" @selected(old('category_id') == $cat['id'])>
                            {{ $cat['name'] }} (x{{ $cat['score_multiplier'] }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label fw-semibold text-info">Kích thước Lưới Bản đồ</label>
                <select name="map_size" class="form-select bg-dark text-light border-secondary">
                    @foreach($allowedSizes as $sz)
                        <option value="{{ $sz }}" @selected(old('map_size', '5x8') === $sz)>{{ $sz }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label fw-semibold text-info">Độ khó (Difficulty)</label>
                <select name="difficulty" class="form-select bg-dark text-light border-secondary">
                    <option value="easy" @selected(old('difficulty') === 'easy')>Easy (Chỉ ống xanh xoay)</option>
                    <option value="medium" @selected(old('difficulty') === 'medium')>Medium (Thêm ống đỏ & tường)</option>
                    <option value="hard" @selected(old('difficulty', 'hard') === 'hard')>Hard (Thêm cụm ống vàng hoán đổi)</option>
                </select>
            </div>
            <div class="col-md-4">
                <x-input name="time_limit_seconds" type="number" label="Thời gian cột nước dâng (Giây)" value="60" />
            </div>
            <div class="col-md-4">
                <x-input name="reward_stars" type="number" label="Số Sao thưởng khi thắng" value="250" />
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold text-info">Gắn Nhãn Tags (Quan hệ Many-to-Many qua bảng Pivot <code>game_level_tag</code>)</label>
            <div class="d-flex flex-wrap gap-3 p-2 rounded border border-secondary">
                @foreach($tags as $t)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tag_ids[]" value="{{ $t['id'] }}" id="tag_{{ $t['id'] }}">
                        <label class="form-check-label" for="tag_{{ $t['id'] }}">
                            <span class="badge" style="background-color: {{ $t['color_hex'] }}">{{ $t['name'] }}</span>
                        </label>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold text-info">Ảnh minh họa (File Upload Validation: jpg, jpeg, png, max 2MB)</label>
            <input type="file" name="thumbnail" class="form-control bg-dark text-light border-secondary" accept=".jpg,.jpeg,.png">
            @error('thumbnail')
                <div class="mt-1 small fw-bold" style="color:#ff6b6b;">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold text-info">Mô tả chi tiết màn chơi (*)</label>
            <textarea name="description" rows="3" class="form-control bg-dark text-light border-secondary" placeholder="Nhập mô tả chiến thuật vượt màn chơi (tối thiểu 10 ký tự)...">{{ old('description') }}</textarea>
            @error('description')
                <div class="mt-1 small fw-bold" style="color:#ff6b6b;">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-info fw-bold px-4">💾 Lưu Màn Chơi Mới</button>
    </form>
</x-card>
@endsection
