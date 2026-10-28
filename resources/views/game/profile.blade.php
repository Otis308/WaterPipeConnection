<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ sơ của tôi · Water Pipe Connection</title>
    <link rel="stylesheet" href="/Bootstrap/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="/CSS/Profile.css">
    <script src="/JS/Profile.js" defer></script>
</head>
<body>
<main class="container profile-shell py-4">
    <?php
        // Khai báo giá trị mặc định để tránh cảnh báo Undefined variable từ Intelephense
        $notice       = $notice ?? null;
        $loadError    = $loadError ?? false;
        $profile      = $profile ?? ['avatar_url' => '', 'name' => '', 'username' => '', 'created_at' => '', 'email' => '', 'bio' => ''];
        $old          = $old ?? [];
        $avatarReady  = $avatarReady ?? false;
        $soloStats    = $soloStats ?? null;
        $pvpStats     = $pvpStats ?? null;
        $source       = $source ?? 'solo';
        $history      = $history ?? ['available' => false, 'items' => [], 'page' => 1, 'pages' => 1];
    ?>
    <nav class="d-flex flex-wrap justify-content-between gap-2 mb-3" aria-label="Điều hướng">
        <a href="/" class="btn btn-light">← Trang chủ</a>
        <a href="/TrangGame.html" class="btn btn-light">Chơi game</a>
    </nav>
    <h1 class="profile-title">Hồ sơ của tôi</h1>
    @if($notice)
        <div class="alert {{ $notice['success'] ? 'alert-success' : 'alert-danger' }}" role="status">{{ $notice['message'] }}</div>
    @endif
    @if($loadError)
        <div class="alert alert-warning" role="alert">Chưa tải được hồ sơ. Vui lòng thử lại sau.</div>
    @else
        <section class="profile-panel mb-4" aria-labelledby="profile-info">
            <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                <img id="profile-avatar-preview" class="profile-avatar" src="{{ $profile['avatar_url'] }}" alt="Avatar của bạn" width="96" height="96">
                <div class="profile-identity">
                    <h2 id="profile-info">{{ $profile['name'] }}</h2>
                    <p class="mb-1">Tên đăng nhập: <strong>{{ $profile['username'] }}</strong></p>
                    <p class="mb-1">Tham gia: {{ $profile['created_at'] ?: 'Chưa có dữ liệu' }}</p>
                    @if(!empty($profile['email']))
                        <p class="mb-1">Email (chỉ bạn xem được): {{ $profile['email'] }}</p>
                    @endif
                </div>
            </div>
            <p class="profile-bio">{{ $profile['bio'] ?: 'Bạn chưa viết giới thiệu.' }}</p>
            <form action="/profile" method="post" enctype="multipart/form-data" id="profile-form">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="profile-name">Tên hiển thị</label>
                    <input id="profile-name" class="form-control" name="name" type="text" required maxlength="100" value="{{ $old['name'] ?? $profile['name'] }}" autocomplete="nickname">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="profile-bio">Giới thiệu ngắn</label>
                    <textarea id="profile-bio" class="form-control" name="bio" maxlength="500" rows="3" aria-describedby="bio-help">{{ $old['bio'] ?? $profile['bio'] }}</textarea>
                    <small id="bio-help">Tối đa 500 ký tự.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="profile-avatar">Đổi avatar</label>
                    <input id="profile-avatar" class="form-control" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" <?php if (empty($avatarReady)) echo 'disabled'; ?> aria-describedby="avatar-help avatar-status">
                    <p id="avatar-help" class="text-secondary mt-2 mb-0">{{ !empty($avatarReady) ? 'JPEG, PNG hoặc WebP, tối đa 2 MB. Tối đa 4096 px mỗi chiều và 8 megapixel. Chọn ảnh để xem trước, sau đó bấm Lưu hồ sơ.' : 'Đổi avatar tạm thời chưa khả dụng. Bạn vẫn có thể sửa tên hiển thị và giới thiệu.' }}</p>
                    <p id="avatar-status" class="mt-2 mb-0" role="status" aria-live="polite"></p>
                </div>
                <button class="btn btn-primary" type="submit" id="profile-save">Lưu hồ sơ</button>
                <span id="profile-save-status" role="status" aria-live="polite" class="ms-2"></span>
            </form>
        </section>
        <section class="profile-panel mb-4" aria-labelledby="profile-stats">
            <h2 id="profile-stats">Thống kê cá nhân</h2>
            <p class="text-secondary">Tính từ lịch sử đã lưu trên server. Điểm cao nhất chỉ tính điểm chất lượng 0–100 có dữ liệu xác định; điểm theo luật cũ không được so chung.</p>
            <?php foreach (['Chơi đơn' => $soloStats, 'PvP' => $pvpStats] as $label => $stats): ?>
                <h3 class="h5 mt-3">{{ $label }}</h3>
                @if($stats === null)
                    <p class="text-secondary">Chưa có nguồn dữ liệu thống kê khả dụng.</p>
                @else
                    <div class="profile-stats">
                        <div><span>Số trận</span><strong>{{ $stats['games'] }}</strong></div>
                        <div><span>Số trận thắng</span><strong>{{ $stats['wins'] }}</strong></div>
                        <div><span>Tỷ lệ thắng</span><strong>{{ $stats['win_rate'] === null ? '—' : $stats['win_rate'] . '%' }}</strong></div>
                        <div><span>Điểm cao nhất</span><strong>{{ $stats['best'] === null ? '—' : $stats['best'] . '/100' }}</strong></div>
                    </div>
                @endif
            <?php endforeach; ?>
            <p class="text-secondary mt-3 mb-0">Chơi đơn chỉ tính chế độ người chơi; lượt AI chỉ xuất hiện trong lịch sử. Dấu “—” nghĩa là chưa đủ dữ liệu. PvP chưa đủ thông tin để xác định điểm cao nhất theo cùng thang điểm. Kết quả chơi đơn được lưu từ client theo cơ chế hiện có.</p>
        </section>
        <section class="profile-panel" aria-labelledby="profile-history">
            <h2 id="profile-history">Lịch sử chơi</h2>
            <nav class="d-flex flex-wrap gap-2 my-3" aria-label="Nguồn lịch sử">
                <a href="/profile?source=solo" class="btn {{ $source === 'solo' ? 'btn-primary' : 'btn-outline-primary' }}" <?php if ($source === 'solo') echo 'aria-current="page"'; ?>>Chơi đơn / AI</a>
                <a href="/profile?source=pvp" class="btn {{ $source === 'pvp' ? 'btn-primary' : 'btn-outline-primary' }}" <?php if ($source === 'pvp') echo 'aria-current="page"'; ?>>PvP</a>
            </nav>
            @if(!$history['available'])
                <p>Chưa tải được nguồn lịch sử này. Vui lòng thử lại sau.</p>
            @elseif(!$history['items'])
                <p>Bạn chưa có lịch sử chơi ở mục này.</p>
            @else
                <div class="table-responsive" tabindex="0" role="region" aria-label="Bảng lịch sử, cuộn ngang trên điện thoại">
                    <table class="table align-middle">
                        <thead><tr><th>Thời điểm</th><th>Chế độ</th><th>Kết quả</th><th>Điểm</th><th>Độ khó / kích thước</th><th>Thời lượng</th></tr></thead>
                        <tbody>
                        <?php
                        $results = ['win' => 'Thắng', 'lose' => 'Thua', 'loss' => 'Thua', 'surrender' => 'Đầu hàng', 'draw' => 'Hòa', 'abandoned' => 'Rời trận'];
                        $difficulties = ['easy' => 'Dễ', 'medium' => 'Vừa', 'hard' => 'Khó'];
                        foreach ($history['items'] as $item):
                        ?>
                            <tr>
                                <td>{{ $item['time'] ?: '—' }}</td>
                                <td>{{ $item['mode'] }}</td>
                                <td>{{ $results[$item['result'] ?? ''] ?? 'Chưa có dữ liệu' }}</td>
                                <td>{{ $item['score'] }}</td>
                                <td>{{ $difficulties[$item['difficulty'] ?? ''] ?? '—' }} / {{ $item['map_size'] ?: '—' }}</td>
                                <td>{{ $item['duration_ms'] === null ? '—' : number_format((int)$item['duration_ms'] / 1000, 1) . ' giây' }}</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <nav class="d-flex flex-wrap align-items-center gap-3" aria-label="Phân trang lịch sử">
                    @if($history['page'] > 1)
                        <a class="btn btn-outline-primary" rel="prev" href="/profile?source={{ $source }}&amp;page={{ $history['page'] - 1 }}">← Trang trước</a>
                    @endif
                    <span>Trang {{ $history['page'] }} / {{ $history['pages'] }}</span>
                    @if($history['page'] < $history['pages'])
                        <a class="btn btn-outline-primary" rel="next" href="/profile?source={{ $source }}&amp;page={{ $history['page'] + 1 }}">Trang sau →</a>
                    @endif
                </nav>
            @endif
        </section>
    @endif
</main>
</body>
</html>
