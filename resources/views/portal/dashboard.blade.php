@extends('layouts.admin')
@section('title', 'HUIT Admin Portal — Quản Trị Hệ Thống Game Nối Ống Nước')

@section('content')
{{-- Breadcrumb Navigation --}}
<nav class="admin-breadcrumb mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/portal">Trang chủ</a></li>
        <li class="breadcrumb-item active">Dashboard</li>
    </ol>
</nav>

{{-- Page Header --}}
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-2">⚡ Bảng Điều Khiển Quản Trị</h1>
        <p class="text-muted mb-0">Quản lý người chơi, màn chơi, theo dõi thống kê và kiểm tra thuật toán</p>
    </div>
    <div class="d-flex gap-2 mt-2 mt-md-0">
        <a href="{{ route('game.home') }}" class="admin-btn admin-btn-secondary">
            <span class="btn-icon">🎮</span>
            Chơi thử
        </a>
        <a href="{{ route('levels.create') }}" class="admin-btn admin-btn-primary">
            <span class="btn-icon">+</span>
            Tạo màn chơi
        </a>
    </div>
</div>

{{-- 4 Stat Cards with Icons and Trends --}}
<div class="row g-4 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="admin-stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-value">{{ $adminStats['total_users'] ?? count($allUsers) }}</div>
            <div class="stat-label">Tổng người chơi</div>
            <div class="stat-trend trend-up">
                <span>↑</span>
                <span>{{ $adminStats['active_users'] ?? count($allUsers) }} hoạt động</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="admin-stat-card">
            <div class="stat-icon">⚔️</div>
            <div class="stat-value">{{ $adminStats['total_pvp_matches'] ?? 0 }}</div>
            <div class="stat-label">Trận đấu PvP</div>
            <div class="stat-trend trend-neutral">
                <span>Real-time</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="admin-stat-card">
            <div class="stat-icon">🏆</div>
            <div class="stat-value">{{ $adminStats['top_elo_user']['pvp_rating'] ?? 1000 }}</div>
            <div class="stat-label">Top 1 Elo</div>
            <div class="stat-trend trend-up">
                <span>{{ $adminStats['top_elo_user']['pvp_tier'] ?? 'Bạc' }}</span>
                <span>- {{ $adminStats['top_elo_user']['username'] ?? 'Chưa có' }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="admin-stat-card">
            <div class="stat-icon">🗺️</div>
            <div class="stat-value">{{ $adminStats['total_levels'] ?? count($allLevels) }}</div>
            <div class="stat-label">Màn chơi</div>
            <div class="stat-trend trend-neutral">
                <span>CRUD Active</span>
            </div>
        </div>
    </div>
</div>

{{-- Navigation Tabs --}}
<ul class="nav nav-tabs mb-4" id="adminTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#tab-users" type="button" role="tab">
            Quản lý Người chơi
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="levels-tab" data-bs-toggle="tab" data-bs-target="#tab-levels" type="button" role="tab">
            Quản lý và Thiết kế màn chơi
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#tab-logs" type="button" role="tab">
            Nhật ký PvP
        </button>
    </li>
</ul>

<div class="tab-content">
    {{-- TAB 1: QUẢN LÝ NGƯỜI CHƠI --}}
    <div class="tab-pane fade show active" id="tab-users">
        <div class="admin-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1">Danh sách Người chơi</h3>
                    <p class="text-muted small mb-0">Phân quyền: admin (Quản trị viên) và user (Game thủ)</p>
                </div>
                <span class="admin-badge info">{{ count($allUsers) }} Tài khoản</span>
            </div>

            {{-- Table Controls --}}
            <div class="admin-table-controls">
                <input type="text" class="admin-input admin-table-search" id="usersSearch" data-table="usersTable" placeholder="Tìm kiếm người chơi...">
                <select class="admin-input admin-table-filter" id="usersRoleFilter" data-table="usersTable" data-column="role">
                    <option value="">Tất cả vai trò</option>
                    <option value="admin">Admin</option>
                    <option value="user">User</option>
                </select>
            </div>

            <div class="table-responsive">
                <table class="admin-table" id="usersTable" data-sortable="true">
                    <thead>
                        <tr>
                            <th class="sortable">ID</th>
                            <th class="sortable">Người chơi</th>
                            <th class="sortable">Email</th>
                            <th class="sortable">Điểm Elo</th>
                            <th class="sortable">Bậc Rank</th>
                            <th>PvP (W/L)</th>
                            <th>Tỉ lệ thắng</th>
                            <th>Trạng thái</th>
                            <th>Phân quyền</th>
                            <th class="text-end">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($allUsers as $u)
                            @php
                                $matches = (int)($u['pvp_matches'] ?? ($u['total_games'] ?? 0));
                                $wins = (int)($u['pvp_wins'] ?? ($u['total_wins'] ?? 0));
                                $losses = (int)($u['pvp_losses'] ?? max(0, $matches - $wins));
                                $winRate = $matches > 0 ? round(($wins / $matches) * 100, 1) : 0.0;
                                $isActive = (int)($u['is_active'] ?? 1);
                                $tier = $u['pvp_tier'] ?? 'Bạc';
                                $rating = (int)($u['pvp_rating'] ?? 1000);
                            @endphp
                            <tr data-column="role" data-value="{{ $u['role'] ?? 'user' }}">
                                <td><strong>#{{ $u['id'] }}</strong></td>
                                <td>
                                    <div><strong>{{ $u['username'] }}</strong></div>
                                    <small class="text-muted">{{ $u['name'] ?? $u['username'] }}</small>
                                </td>
                                <td><code>{{ $u['email'] }}</code></td>
                                <td><strong>{{ $rating }}</strong></td>
                                <td>
                                    <span class="admin-badge @if($tier === 'Cao Thủ') error @elseif($tier === 'Kim Cương') info @elseif($tier === 'Bạch Kim') success @elseif($tier === 'Vàng') warning @else secondary @endif">
                                        {{ $tier }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-success">{{ $wins }}W</span> - <span class="text-danger">{{ $losses }}L</span>
                                    <div><small class="text-muted">({{ $matches }} trận)</small></div>
                                </td>
                                <td>
                                    <div class="progress admin-progress">
                                        <div class="progress-bar" data-width="{{ $winRate }}"></div>
                                    </div>
                                    <small>{{ $winRate }}%</small>
                                </td>
                                <td>
                                        @if($isActive === 1)
                                            <span class="admin-badge success">Đang hoạt động</span>
                                        @else
                                            <span class="admin-badge error">Đã khóa</span>
                                        @endif
                                </td>
                                <td>
                                    <form method="POST" action="/admin/users/{{ $u['id'] }}/role" class="d-inline-flex gap-1 align-items-center">
                                        @csrf
                                        <select name="role" class="admin-input" style="width: 100px; padding: 6px 10px;">
                                            <option value="user" {{ ($u['role'] ?? '') === 'user' ? 'selected' : '' }}>User</option>
                                            <option value="admin" {{ ($u['role'] ?? '') === 'admin' ? 'selected' : '' }}>Admin</option>
                                        </select>
                                        <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">Lưu</button>
                                    </form>
                                </td>

                                <td>
                                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                                {{-- Nút khóa / mở khóa --}}
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.users.toggle_active', ['id' => $u['id']]) }}"
                                                    onsubmit="return confirm('{{ $isActive === 1 ? 'Bạn có chắc chắn muốn khóa tài khoản này?' : 'Bạn có chắc chắn muốn mở khóa tài khoản này?' }}');"
                                                >
                                                    @csrf

                                                    @if($isActive === 1)
                                                        <button type="submit" class="admin-btn admin-btn-sm"
                                                                style="background: #dc3545; color: #fff; border: 1px solid #dc3545;">
                                                            Khóa
                                                        </button>
                                                    @else
                                                        <button type="submit" class="admin-btn admin-btn-sm"
                                                                style="background: #198754; color: #fff; border: 1px solid #198754;">
                                                            Mở
                                                        </button>
                                                    @endif
                                                </form>

                                                {{-- Giữ lại chức năng lịch sử PvP --}}
                                                <button
                                                    type="button"
                                                    class="admin-btn admin-btn-secondary admin-btn-sm"
                                                    data-user-id="{{ $u['id'] }}"
                                                    data-username="{{ $u['username'] }}"
                                                    onclick="viewUserPvpHistoryFromMenu(this)"
                                                >
                                                    Lịch sử PvP
                                                </button>
                                            </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile Card View --}}
            <div class="admin-table-card-list d-md-none">
                @foreach($allUsers as $u)
                    @php
                        $matches = (int)($u['pvp_matches'] ?? ($u['total_games'] ?? 0));
                        $wins = (int)($u['pvp_wins'] ?? ($u['total_wins'] ?? 0));
                        $winRate = $matches > 0 ? round(($wins / $matches) * 100, 1) : 0.0;
                        $isActive = (int)($u['is_active'] ?? 1);
                        $tier = $u['pvp_tier'] ?? 'Bạc';
                        $rating = (int)($u['pvp_rating'] ?? 1000);
                    @endphp
                    <div class="admin-table-card">
                        <div class="card-row">
                            <span class="card-label">ID</span>
                            <span class="card-value">#{{ $u['id'] }}</span>
                        </div>
                        <div class="card-row">
                            <span class="card-label">Người chơi</span>
                            <span class="card-value">{{ $u['username'] }}</span>
                        </div>
                        <div class="card-row">
                            <span class="card-label">Email</span>
                            <span class="card-value"><small>{{ $u['email'] }}</small></span>
                        </div>
                        <div class="card-row">
                            <span class="card-label">Điểm Elo</span>
                            <span class="card-value">{{ $rating }}</span>
                        </div>
                        <div class="card-row">
                            <span class="card-label">Bậc Rank</span>
                            <span class="card-value">{{ $tier }}</span>
                        </div>
                        <div class="card-row">
                            <span class="card-label">Trạng thái</span>
                            <span class="card-value">
                                @if($isActive === 1)
                                    <span class="admin-badge success">Hoạt động</span>
                                @else
                                    <span class="admin-badge error">Đã khóa</span>
                                @endif
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            <div class="admin-table-pagination" data-table="usersTable" data-items-per-page="10">
                <div class="page-info">Hiển thị 1-10 của {{ count($allUsers) }}</div>
                <div class="pagination page-numbers"></div>
            </div>
        </div>
    </div>

    {{-- TAB 2: QUẢN LÝ MÀN CHƠI --}}
    <div class="tab-pane fade" id="tab-levels">
        <div class="admin-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1">Quản lý Màn chơi</h3>
                    <p class="text-muted small mb-0">CRUD: Xem, Thêm, Sửa, Xóa màn chơi</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="admin-btn admin-btn-success" data-bs-toggle="modal" data-bs-target="#createLevelModal">
                        <span class="btn-icon">+</span> Thêm mới
                    </button>
                    <a href="{{ route('levels.index') }}" class="admin-btn admin-btn-secondary">
                        Xem tất cả →
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="admin-table" id="levelsTable" data-sortable="true">
                    <thead>
                        <tr>
                            <th class="sortable">ID</th>
                            <th class="sortable">Tiêu đề</th>
                            <th>Danh mục</th>
                            <th>Kích thước</th>
                            <th>Độ khó</th>
                            <th>Thời gian</th>
                            <th>Thưởng</th>
                            <th>Trạng thái</th>
                            <th class="text-end">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($allLevels as $lvl)
                            <tr>
                                <td><strong>#{{ $lvl['id'] }}</strong></td>
                                <td>
                                    <div><strong>{{ $lvl['title'] }}</strong></div>
                                    <small class="text-muted">/levels/{{ $lvl['slug'] ?? $lvl['id'] }}</small>
                                </td>
                                <td><span class="admin-badge secondary">{{ $lvl['category_name'] ?? 'Cơ Bản' }}</span></td>
                                <td>
                                    <span class="admin-badge info">{{ $lvl['map_size'] }}</span>
                                    <small class="text-muted">({{ $lvl['grid_rows'] ?? 3 }}x{{ $lvl['grid_cols'] ?? 8 }})</small>
                                </td>
                                <td>
                                    @if($lvl['difficulty'] === 'hard')
                                        <span class="admin-badge error">Khó</span>
                                    @elseif($lvl['difficulty'] === 'medium')
                                        <span class="admin-badge warning">Vừa</span>
                                    @else
                                        <span class="admin-badge success">Dễ</span>
                                    @endif
                                </td>
                                <td>{{ $lvl['time_limit_seconds'] }}s</td>
                                <td>⭐ {{ $lvl['reward_stars'] }}</td>
                                <td>
                                    @if((int)($lvl['is_published'] ?? 1) === 1)
                                        <span class="admin-badge success">Xuất bản</span>
                                    @else
                                        <span class="admin-badge secondary">Bản nháp</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="admin-action-menu">
                                        <button class="admin-kebab-btn">⋮</button>
                                        <div class="menu-dropdown">
                                            <a href="{{ route('levels.show', $lvl['id']) }}" class="menu-item">
                                                <span></span> Chi tiết
                                            </a>
                                            <a href="{{ route('levels.edit', $lvl['id']) }}" class="menu-item">
                                                <span></span> Sửa
                                            </a>
                                            <div class="menu-divider"></div>
                                            <button type="button" class="menu-item danger" onclick="confirmDeleteLevel({{ $lvl['id'] }}, '{{ $lvl['title'] }}')">
                                                <span></span> Xóa
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">
                                    <div class="admin-empty-state">
                                        <div class="empty-icon"></div>
                                        <div class="empty-title">Chưa có màn chơi nào</div>
                                        <div class="empty-description">Hãy tạo màn chơi đầu tiên để bắt đầu</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                        
                    </tbody>
                </table>
            </div>
        
        </div>
        {{-- TAB 3: TEST SEED (giữ nguyên nội dung cũ) --}}
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="admin-card">
                        <h3 class="mb-3">Cấu hình Test Seed</h3>
                        <form id="seedTesterForm" onsubmit="runSeedGenerator(event)">
                            <div class="admin-form-group">
                                <label class="admin-form-label">Seed Bản Đồ</label>
                                <div class="d-flex gap-2">
                                    <input type="text" id="testSeedInput" class="admin-input" placeholder="SEED_HUIT_2026" required value="SEED_HUIT_2026">
                                    <button type="button" class="admin-btn admin-btn-secondary" onclick="generateRandomSeed()">🎲</button>
                                </div>
                                <small class="text-muted">Cùng Seed = Cùng bản đồ 100%</small>
                            </div>

                            <div class="admin-form-group">
                                <label class="admin-form-label">Kích thước Ma trận</label>
                                <select id="testMapSize" class="admin-input">
                                    <option value="3x8" selected>3x8</option>
                                    <option value="5x8">5x8</option>
                                    <option value="5x12">5x12</option>
                                </select>
                            </div>

                            <div class="admin-form-group">
                                <label class="admin-form-label">Số Cặp Ống</label>
                                <select id="testNumPairs" class="admin-input">
                                    <option value="1">1 Cặp</option>
                                    <option value="2" selected>2 Cặp</option>
                                    <option value="3">3 Cặp</option>
                                    <option value="4">4 Cặp</option>
                                </select>
                            </div>

                            <div class="admin-form-group">
                                <label class="admin-form-label">Độ khó</label>
                                <select id="testDifficulty" class="admin-input">
                                    <option value="easy" selected>Dễ</option>
                                    <option value="medium">Vừa</option>
                                    <option value="hard">Khó</option>
                                </select>
                            </div>

                            <button type="submit" id="btnRunSeed" class="admin-btn admin-btn-primary w-100">
                                Sinh Bản Đồ
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="admin-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="mb-0">Preview Bản Đồ</h3>
                            <span id="configHashBadge" class="admin-badge secondary">Hash: --</span>
                        </div>
                        <div class="d-flex gap-2 flex-wrap mb-3">
                            <button type="button" id="portalReplay" class="admin-btn admin-btn-secondary" disabled>Xem cách giải</button>
                            <button type="button" id="portalReset" class="admin-btn admin-btn-secondary" disabled>Đặt lại</button>
                            <button type="button" id="portalSave" class="admin-btn admin-btn-primary" disabled>Thêm vào quản lý màn chơi</button>
                        </div>
                        <p id="portalReplayStatus" aria-live="polite"></p>
                        <div id="seedPreviewContainer" style="background: var(--bg); min-height: 380px; border-radius: var(--border-radius-sm); padding: 20px;">
                            <div class="d-flex align-items-center justify-content-center h-100 text-muted" style="min-height: 340px;">
                                <div class="text-center">
                                    <div style="font-size: 48px; margin-bottom: 16px;"></div>
                                    <p>Nhấn nút bên trái để sinh bản đồ</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </div>
    {{-- TAB 4: NHẬT KÝ PVP --}}
    <div class="tab-pane fade" id="tab-logs">
        <div class="admin-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1">Nhật ký Thi đấu PvP</h3>
                    <p class="text-muted small mb-0">Giám sát các phiên thi đấu Player vs Player</p>
                </div>
                <span class="admin-badge info">{{ count($recentSessions) }} Phiên</span>
            </div>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Phiên #</th>
                            <th>Người chơi</th>
                            <th>Chế độ</th>
                            <th>Bản đồ</th>
                            <th>Độ khó</th>
                            <th>Kết quả</th>
                            <th>Điểm</th>
                            <th>Thời gian</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentSessions as $s)
                            <tr>
                                <td><strong>#{{ $s['id'] }}</strong></td>
                                <td>{{ $s['player_name'] ?? 'Anonymous' }}</td>
                                <td>
                                    <span class="admin-badge @if(($s['game_mode'] ?? '') === 'ai') info @else primary @endif">
                                        {{ strtoupper($s['game_mode'] ?? 'PVP') }}
                                    </span>
                                </td>
                                <td><code>{{ $s['map_size'] ?? '3x8' }}</code></td>
                                <td><span class="admin-badge secondary">{{ $s['difficulty'] ?? 'easy' }}</span></td>
                                <td>
                                    <span class="admin-badge @if(($s['result'] ?? '') === 'win') success @else error @endif">
                                        {{ strtoupper($s['result'] ?? 'END') }}
                                    </span>
                                </td>
                                <td><strong>{{ $s['score_label'] ?? $s['score_earned'] ?? 0 }}</strong></td>
                                <td>{{ round(($s['duration_ms'] ?? 0) / 1000, 2) }}s</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="admin-empty-state">
                                        <div class="empty-icon"></div>
                                        <div class="empty-title">Chưa có nhật ký</div>
                                        <div class="empty-description">Chưa có trận đấu nào được ghi nhận</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- MODAL: LỊCH SỬ PVP --}}
<div class="modal fade" id="pvpHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Lịch sử PvP — <span id="modalUsername"></span></h5>
                <button type="button" class="close-btn" data-bs-dismiss="modal">×</button>
            </div>
            <div class="modal-body">
                <div id="modalLoading" class="text-center py-4">
                    <div class="admin-spinner"></div>
                    <p class="text-muted mt-2">Đang tải...</p>
                </div>
                <div id="modalHistoryContent" style="display:none;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Mã Phòng</th>
                                <th>Ma trận</th>
                                <th>Hạng</th>
                                <th>Kết quả</th>
                                <th>Elo Trước</th>
                                <th>Elo Sau</th>
                                <th>Biến động</th>
                            </tr>
                        </thead>
                        <tbody id="pvpHistoryTbody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="admin-btn admin-btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

{{-- MODAL: TẠO MÀN CHƠI --}}
<div class="modal fade" id="createLevelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">+ Tạo Màn Chơi Mới</h5>
                <button type="button" class="close-btn" data-bs-dismiss="modal">×</button>
            </div>
            <form method="POST" id="portalCreateLevelForm" action="{{ route('levels.store') }}">
                <input type="hidden" name="portal_generated_hash" value="">
                <p id="portalGeneratedNotice" class="px-3" aria-live="polite"></p>
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <div class="admin-form-group">
                                <label class="admin-form-label">Tiêu đề (*)</label>
                                <input type="text" name="title" class="admin-input" placeholder="Tên màn chơi" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="admin-form-group">
                                <label class="admin-form-label">Danh mục (*)</label>
                                <select name="category_id" class="admin-input" required>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat['id'] }}">{{ $cat['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="admin-form-group">
                                <label class="admin-form-label">Kích thước (*)</label>
                                <select name="map_size" class="admin-input" required>
                                    <option value="3x8">3x8</option>
                                    <option value="5x8">5x8</option>
                                    <option value="5x12">5x12</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="admin-form-group">
                                <label class="admin-form-label">Độ khó (*)</label>
                                <select name="difficulty" class="admin-input" required>
                                    <option value="easy">Dễ</option>
                                    <option value="medium">Vừa</option>
                                    <option value="hard">Khó</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="admin-form-group">
                                <label class="admin-form-label">Thời gian (s)</label>
                                <input type="number" name="time_limit_seconds" class="admin-input" value="120" min="30" max="600" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="admin-form-group">
                                <label class="admin-form-label">Thưởng Sao</label>
                                <input type="number" name="reward_stars" class="admin-input" value="3" min="1" max="5" required>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="admin-form-group">
                                <label class="admin-form-label">Mô tả</label>
                                <textarea name="description" rows="2" class="admin-input" placeholder="Mô tả màn chơi..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="admin-btn admin-btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="admin-btn admin-btn-success">Lưu</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script src="/JS/TrangGame/PortalMapWater.js"></script>
<script src="/JS/TrangGame/PortalMapPreview.js"></script>
<script>
function viewUserPvpHistory(userId, username) {
    document.getElementById('modalUsername').textContent = username;
    document.getElementById('modalLoading').style.display = 'block';
    document.getElementById('modalHistoryContent').style.display = 'none';
    
    const modal = new bootstrap.Modal(document.getElementById('pvpHistoryModal'));
    modal.show();

    fetch('/api/admin/users/' + userId + '/pvp-history')
        .then(res => res.json())
        .then(res => {
            document.getElementById('modalLoading').style.display = 'none';
            document.getElementById('modalHistoryContent').style.display = 'block';
            const tbody = document.getElementById('pvpHistoryTbody');
            tbody.innerHTML = '';

            if (!res.success || !res.data || res.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Chưa có lịch sử thi đấu</td></tr>';
                return;
            }

            res.data.forEach(item => {
                const delta = parseInt(item.rating_delta || 0);
                const deltaClass = delta > 0 ? 'text-success' : (delta < 0 ? 'text-danger' : 'text-muted');
                const deltaSign = delta > 0 ? '+' : '';
                const winStatus = item.win_status || 'loss';
                const statusBadge = winStatus === 'win' 
                    ? '<span class="admin-badge success">Thắng</span>'
                    : (winStatus === 'draw' ? '<span class="admin-badge secondary">Hòa</span>' : '<span class="admin-badge error">Thua</span>');

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><code>${item.room_code || ('#' + item.room_id)}</code></td>
                    <td><small class="text-muted">${item.map_size || '3x8'} · ${item.num_pairs || 2} Cặp</small></td>
                    <td><strong>#${item.final_rank}</strong></td>
                    <td>${statusBadge}</td>
                    <td>${item.rating_before}</td>
                    <td>${item.rating_after}</td>
                    <td class="${deltaClass}">${deltaSign}${delta}</td>
                `;
                tbody.appendChild(tr);
            });
        })
        .catch(err => {
            document.getElementById('modalLoading').style.display = 'none';
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Lỗi tải dữ liệu</td></tr>';
        });
}

function viewUserPvpHistoryFromMenu(element) {
    const userId = element.getAttribute('data-user-id');
    const username = element.getAttribute('data-username');
    viewUserPvpHistory(userId, username);
}

function generateRandomSeed() {
    const seed = 'SEED_' + Math.random().toString(36).substring(2, 10).toUpperCase();
    document.getElementById('testSeedInput').value = seed;
}

async function runSeedGenerator(e) {
    e.preventDefault();

    const button = document.getElementById('btnRunSeed');
    const preview = document.getElementById('seedPreviewContainer');
    const badge = document.getElementById('configHashBadge');

    if (button.disabled) return;

    const oldLabel = button.textContent;
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 15000);

    button.disabled = true;
    window.PortalMapPreview.clear();
    button.textContent = 'Đang sinh và kiểm tra...';
    preview.textContent = 'Đang sinh bản đồ và kiểm tra lời giải...';
    badge.textContent = 'Hash: --';

    try {
        const response = await fetch('/api/admin/portal-map', {
            method: 'POST',
            credentials: 'same-origin',
            signal: controller.signal,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN':
                    document.querySelector('meta[name="csrf-token"]')
                        ?.content || ''
            },
            body: JSON.stringify({
                seed: document.getElementById('testSeedInput').value.trim(),
                map_size: document.getElementById('testMapSize').value,
                num_pairs: Number(
                    document.getElementById('testNumPairs').value
                ),
                difficulty:
                    document.getElementById('testDifficulty').value
            })
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Sinh bản đồ thất bại.');
        }

        const { map, verification } = result.data;
        const [rows, cols] = map.mapSizeKey.split('x').map(Number);
        const minR = Math.min(...map.cells.map(cell => cell.r));
        const minC = Math.min(...map.cells.map(cell => cell.c));

        preview.replaceChildren();
        preview.style.overflowX = 'auto';

        const summary = document.createElement('p');
        summary.textContent = verification.solvable
            ? '✓ Generator xác nhận map có lời giải. Bấm “Xem cách giải” để bộ tìm kiếm tìm độc lập.'
            : 'Chưa xác nhận lời giải.';
        preview.appendChild(summary);

        const optimality = document.createElement('p');
        optimality.textContent =
            verification.optimality.status === 'proven'
                ? 'Đã có chuẩn độ dài đường nước tối ưu; không phải kết quả tìm thao tác của nút xem lời giải.'
                : 'Chưa chứng minh tối ưu trong giới hạn tìm kiếm.';
        preview.appendChild(optimality);

        const counts = verification.counts;
        const legend = document.createElement('p');
        legend.textContent =
            `Xanh: ${counts.green} · Vàng: ${counts.yellow}` +
            ` · Đỏ: ${counts.red} · Tường: ${counts.wall}` +
            ' · S: bắt đầu · E: kết thúc';
        preview.appendChild(legend);

        const grid = document.createElement('div');
        grid.style.cssText = `
            display: grid;
            grid-template-columns: repeat(${cols}, 52px);
            grid-template-rows: repeat(${rows}, 52px);
            gap: 3px;
            width: max-content;
            padding: 8px;
            background: #111827;
            border-radius: 8px;
        `;

        for (const cell of map.cells) {
            const tile = document.createElement('div');
            tile.style.cssText = `
                position: relative;
                width: 52px;
                height: 52px;
                background: #243247;
                border: 1px solid #475569;
                box-sizing: border-box;
            `;
            tile.style.gridRow = String(cell.r - minR + 1);
            tile.style.gridColumn = String(cell.c - minC + 1);

            const image = document.createElement('img');
            image.src = '/Images/svg/Ong/' + cell.file;
            image.alt = cell.role || cell.shape || 'Tường';
            image.draggable = false;
            image.style.cssText = `
                display: block;
                width: 100%;
                height: 100%;
                object-fit: contain;
            `;
            image.style.transform =
                `rotate(${cell.initialRot || 0}deg)`;

            tile.appendChild(image);

            if (cell.role === 'start' || cell.role === 'end') {
                const pair = map.pipes.find(
                    item => item.pairId === cell.pairId
                );

                tile.style.borderColor = pair?.color?.hex || '#ffffff';

                const label = document.createElement('span');
                label.textContent =
                    (cell.role === 'start' ? 'S' : 'E') + cell.pairId;
                label.style.cssText = `
                    position: absolute;
                    top: 1px;
                    left: 1px;
                    padding: 1px 3px;
                    border-radius: 3px;
                    background: #ffffff;
                    color: #111827;
                    font-size: 11px;
                    font-weight: bold;
                `;
                tile.appendChild(label);
            }

            grid.appendChild(tile);
        }

        preview.appendChild(grid);

        badge.textContent = 'Hash: ' + map.configHash.slice(0, 12);
        badge.title = map.configHash;
        window.PortalMapPreview.load(result.data);

        showToast('Đã sinh bản đồ có lời giải!', 'success');
    } catch (error) {
        const message = error.name === 'AbortError'
            ? 'Yêu cầu quá thời gian. Hãy thử lại.'
            : error.message;

        preview.textContent = message;
        showToast(message, 'error');
    } finally {
        clearTimeout(timeout);
        button.disabled = false;
        button.textContent = oldLabel;
    }
}
</script>
@endpush
@endsection
