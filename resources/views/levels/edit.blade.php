@extends('layouts.portal')
@section('title', 'Chỉnh sửa Màn Chơi #' . $level['id'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="fw-bold text-warning mb-0">✏️ Chỉnh sửa Màn Chơi #{{ $level['id'] }}: {{ $level['title'] }}</h3>
    <a href="{{ route('levels.index') }}" class="btn btn-outline-light btn-sm">← Quay lại danh sách</a>
</div>

<x-card title="Form Cập Nhật (Sử dụng @csrf và Method Spoofing @method('PUT'))" subtitle="Tác giả tạo: {{ $level['creator_username'] ?? 'admin' }}">
    <form action="/levels/{{ $level['id'] }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-md-6">
                <x-input name="title" label="Tiêu đề màn chơi" :value="$level['title']" />
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label fw-semibold text-info">Chủ đề Ống Nước</label>
                <select name="category_id" class="form-select bg-dark text-light border-secondary">
                    @foreach($categories as $cat)
                        <option value="{{ $cat['id'] }}" @selected($level['category_id'] == $cat['id'])>{{ $cat['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label fw-semibold text-info">Kích thước Bản đồ</label>
                <select name="map_size" class="form-select bg-dark text-light border-secondary">
                    @foreach($allowedSizes as $sz)
                        <option value="{{ $sz }}" @selected($level['map_size'] === $sz)>{{ $sz }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label fw-semibold text-info">Độ khó</label>
                <select name="difficulty" class="form-select bg-dark text-light border-secondary">
                    <option value="easy" @selected($level['difficulty'] === 'easy')>Easy</option>
                    <option value="medium" @selected($level['difficulty'] === 'medium')>Medium</option>
                    <option value="hard" @selected($level['difficulty'] === 'hard')>Hard</option>
                </select>
            </div>
            <div class="col-md-4">
                <x-input name="time_limit_seconds" type="number" label="Thời gian giới hạn (Giây)" :value="$level['time_limit_seconds']" />
            </div>
            <div class="col-md-4">
                <x-input name="reward_stars" type="number" label="Số Sao thưởng" :value="$level['reward_stars']" />
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold text-info">Đồng bộ Tags N-N (<code>sync()</code> trên bảng Pivot <code>game_level_tag</code>)</label>
            <div class="d-flex flex-wrap gap-3 p-2 rounded border border-secondary">
                @foreach($tags as $t)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tag_ids[]" value="{{ $t['id'] }}"
                               id="edit_tag_{{ $t['id'] }}"
                               @if(in_array((int)$t['id'], $level['tag_ids'] ?? [], true)) checked @endif>
                        <label class="form-check-label" for="edit_tag_{{ $t['id'] }}">
                            <span class="badge" style="background:{{ $t['color_hex'] }}">{{ $t['name'] }}</span>
                        </label>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold text-info">Cập nhật Ảnh Thumbnail (Tùy chọn)</label>
            <input type="file" name="thumbnail" class="form-control bg-dark text-light border-secondary" accept=".jpg,.jpeg,.png">
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold text-info">Mô tả chi tiết</label>
            <textarea name="description" rows="3" class="form-control bg-dark text-light border-secondary">{{ old('description', $level['description']) }}</textarea>
            @error('description')
                <div class="mt-1 small fw-bold" style="color:#ff6b6b;">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-warning fw-bold px-4">💾 Cập Nhật Màn Chơi</button>
    </form>
</x-card>
@endsection
