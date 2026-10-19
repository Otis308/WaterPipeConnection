@extends('layouts.portal')
@section('title', 'Thực hành MySQL & Truy vấn nâng cao PDO (Buổi 4 & Lab 5 HUIT)')

@section('content')
<div class="mb-4 pb-2 border-bottom border-secondary">
    <h2 class="fw-bold text-success mb-1">🗄️ PHÒNG THỰC HÀNH MYSQL & TRUY VẤN NÂNG CAO PDO (BUỔI 4 & BÀI 5)</h2>
    <p class="text-secondary mb-0">
        Toàn bộ truy vấn dưới đây được thực thi trực tiếp qua lớp <code>App\Services\PdoDatabaseService</code> sử dụng
        <code>PDO Prepared Statement</code>, <code>INNER/LEFT JOIN</code>, <code>GROUP BY</code>, <code>HAVING</code>, và <code>Correlated Subquery</code>.
    </p>
</div>

<div class="row g-4">
    {{-- Bài tập 1 Lab 5: LEFT JOIN + COUNT + GROUP BY --}}
    <div class="col-lg-6">
        <x-card title="Bài 1 (Lab 5): Thống kê theo Chủ đề (LEFT JOIN + COUNT + GROUP BY)" subtitle="Slide 16 Lab 5">
            <div class="table-responsive">
                <table class="table table-dark table-striped mb-0">
                    <thead>
                        <tr class="text-info">
                            <th>Chủ đề Ống Nước</th>
                            <th>Hệ số điểm</th>
                            <th>Số Màn Chơi</th>
                            <th>Số Vật Phẩm Shop</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lab5Ex1 as $r)
                            <tr>
                                <td class="fw-bold">{{ $r['category_name'] }}</td>
                                <td>x{{ $r['score_multiplier'] }}</td>
                                <td><span class="badge bg-info text-dark">{{ $r['total_levels'] }} màn</span></td>
                                <td><span class="badge bg-secondary">{{ $r['total_shop_items'] }} món</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    {{-- Bài tập 2 Lab 5: Doanh thu theo ngày (JOIN + SUM + GROUP BY) --}}
    <div class="col-lg-6">
        <x-card title="Bài 2 (Lab 5): Tổng doanh thu vật phẩm theo ngày (SUM + GROUP BY)" subtitle="Slide 17 Lab 5">
            <div class="table-responsive">
                <table class="table table-dark table-striped mb-0">
                    <thead>
                        <tr class="text-warning">
                            <th>Ngày đặt hàng</th>
                            <th>Số đơn</th>
                            <th>Tổng SL bán</th>
                            <th>Tổng doanh thu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lab5Ex2 as $r)
                            <tr>
                                <td><code>{{ $r['order_date'] }}</code></td>
                                <td>{{ $r['total_orders'] }}</td>
                                <td>{{ $r['total_items_sold'] }}</td>
                                <td class="fw-bold text-success">{{ number_format((float)$r['total_revenue'], 0, ',', '.') }} đ</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    {{-- Bài tập 3 & 4 Lab 5: HAVING lọc nhóm sau GROUP BY --}}
    <div class="col-lg-6">
        <x-card title="Bài 3 & 4 (Lab 5): Lọc nhóm với HAVING (Khách chi tiêu > 200.000đ)" subtitle="Slide 18-19 Lab 5">
            <div class="table-responsive">
                <table class="table table-dark table-striped mb-0">
                    <thead>
                        <tr class="text-info">
                            <th>Tài khoản</th>
                            <th>Họ tên</th>
                            <th>SL Vật phẩm mua</th>
                            <th>Tổng chi tiêu (HAVING)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lab5Ex4 as $r)
                            <tr>
                                <td class="text-warning fw-bold">{{ $r['username'] }}</td>
                                <td>{{ $r['name'] }}</td>
                                <td>{{ $r['total_items'] }}</td>
                                <td class="fw-bold text-info">{{ number_format((float)$r['total_spent'], 0, ',', '.') }} đ</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    {{-- Bài tập 5 & 6 Lab 5: Correlated Subquery MAX() & LEFT JOIN ... IS NULL --}}
    <div class="col-lg-6">
        <x-card title="Bài 5 & 6 (Lab 5): Subquery Giá cao nhất & LEFT JOIN IS NULL" subtitle="Slide 20-21 Lab 5">
            <h6 class="text-warning mb-2">1. Vật phẩm đắt nhất mỗi loại (Correlated Subquery <code>WHERE price = (SELECT MAX(...))</code>):</h6>
            <ul class="small mb-3">
                @foreach($lab5Ex5 as $r)
                    <li><strong>{{ $r['category_name'] }}</strong>: {{ $r['item_name'] }} — <span class="text-success fw-bold">{{ number_format((float)$r['price'], 0, ',', '.') }} đ</span></li>
                @endforeach
            </ul>
            <h6 class="text-danger mb-2">2. Vật phẩm chưa từng được đặt mua (<code>LEFT JOIN ... WHERE sod.shop_item_id IS NULL</code>):</h6>
            <ul class="small mb-0">
                @foreach($lab5Ex6 as $r)
                    <li><code>#{{ $r['id'] }}</code> <strong>{{ $r['name'] }}</strong> ({{ $r['category_name'] }}) — {{ number_format((float)$r['price'], 0, ',', '.') }} đ</li>
                @endforeach
            </ul>
        </x-card>
    </div>
</div>

{{-- Bài tập 7 & 8: Thống kê thuật toán AI Leo Đồi vs Player --}}
<x-card title="📊 Thống kê Hiệu năng Thuật toán AI Leo Đồi vs Player (AVG, MAX, SUM, GROUP BY)" subtitle="Tích hợp AI Engineering">
    <div class="table-responsive">
        <table class="table table-dark table-bordered align-middle mb-0">
            <thead>
                <tr class="text-info">
                    <th>Chế độ (Mode)</th>
                    <th>Độ khó</th>
                    <th>Số trận</th>
                    <th>Số trận thắng</th>
                    <th>Điểm TB (AVG)</th>
                    <th>Điểm Cao Nhất (MAX)</th>
                    <th>Thời gian xử lý TB</th>
                    <th>Số bước TB</th>
                    <th>Tổng lần AI Restart</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lab5Ex7 as $r)
                    <tr>
                        <td><span class="badge {{ $r['game_mode'] === 'ai' ? 'bg-info text-dark' : 'bg-primary' }}">{{ strtoupper($r['game_mode']) }}</span></td>
                        <td><x-badge :difficulty="$r['difficulty']" /></td>
                        <td>{{ $r['total_matches'] }}</td>
                        <td class="text-success fw-bold">{{ $r['total_wins'] }}</td>
                        <td>{{ $r['avg_score'] }} ⭐</td>
                        <td class="text-warning fw-bold">{{ $r['max_score'] }} ⭐</td>
                        <td>{{ $r['avg_duration_sec'] }}s</td>
                        <td>{{ $r['avg_steps'] }} bước</td>
                        <td>{{ $r['total_ai_restarts'] }} lần</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-card>
@endsection
