@extends('layouts.portal')
@section('title', 'Thực hành Lập trình Hướng đối tượng OOP PHP (Chương 2 HUIT)')

@section('content')
<div class="mb-4 pb-2 border-bottom border-secondary">
    <h2 class="fw-bold text-warning mb-1">🧩 PHÒNG THỰC HÀNH OOP PHP CHUYÊN SÂU (CHƯƠNG 2)</h2>
    <p class="text-secondary mb-0">
        Minh họa trực tiếp từ mã nguồn <code>app/Domain/PipeGame/</code>: Class, Object, <code>$this</code>, Constructor/Destructor,
        Encapsulation (Getter/Setter), Inheritance (<code>extends</code>), Polymorphism (<code>override</code>), Abstract Class, Interface (<code>implements</code>) & PSR-4 Autoloading.
    </p>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <x-card title="1. Đa hình (Polymorphism) trên các lớp kế thừa AbstractPipe" subtitle="StraightPipe · CornerPipe · CrossPipe">
            <p class="small text-secondary">
                Cả 3 lớp <code>StraightPipe</code>, <code>CornerPipe</code>, <code>CrossPipe</code> đều kế thừa từ lớp trừu tượng <code>AbstractPipe</code>
                và triển khai 2 interface <code>ConnectableInterface</code>, <code>ScorableInterface</code>.
            </p>
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-info">
                            <th>Loại Ống (Class)</th>
                            <th>Vị trí</th>
                            <th>Nhóm màu</th>
                            <th>Góc xoay</th>
                            <th>Cổng mở (Ports)</th>
                            <th>Điểm (Easy / Hard)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($simulation['pipes'] as $p)
                            <tr>
                                <td class="fw-bold text-warning">{{ $p['type'] }}</td>
                                <td><code>{{ $p['position'] }}</code></td>
                                <td><span class="badge bg-secondary">{{ $p['color_group'] }}</span></td>
                                <td>{{ $p['rotation'] }}°</td>
                                <td><code>[{{ implode(', ', $p['open_directions']) }}]</code></td>
                                <td class="fw-bold text-success">+{{ $p['score_easy'] }} / +{{ $p['score_hard'] }} ⭐</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3 p-3 rounded border border-secondary" style="background:#0f172a;">
                <div>✅ <strong>Kiểm thử hoán đổi 2 Ống Vàng liền kề (<code>CornerPipe::swapPositionWith</code>):</strong>
                    <span class="badge bg-success">{{ $simulation['swap_yellow_test'] ? 'Hợp lệ — Đã tráo đổi vị trí' : 'Thất bại' }}</span>
                </div>
                <div class="mt-1">✅ <strong>Tổng điểm toàn bàn cờ tính qua Đa hình (<code>calculateTotalBoardScore('hard')</code>):</strong>
                    <strong class="text-warning">{{ $simulation['total_score_hard'] }} ⭐</strong>
                </div>
                <div class="mt-1">🏅 <strong>Xếp hạng học lực/kỹ năng (<code>PipeBoardSimulator::getPlayerRank</code>):</strong>
                    <span class="text-info fw-bold">{{ $playerRank }}</span>
                </div>
            </div>
        </x-card>
    </div>

    <div class="col-lg-5">
        <x-card title="2. Tính Đóng Gói (Encapsulation) & Hợp Đồng Interface" subtitle="Slide 6, 13 & 27 Chương 2">
            <h6 class="text-info fw-bold">💳 Lớp <code>PlayerWalletAccount</code> (<code>private $balance</code>):</h6>
            <p class="small text-secondary mb-2">
                Số dư được bảo vệ bằng <code>private $balance</code>, chỉ thay đổi qua <code>deposit()</code> và <code>withdraw()</code> có kiểm tra điều kiện:
            </p>
            <div class="p-2 mb-3 rounded border border-secondary bg-black">
                <div>Chủ ví: <strong class="text-warning">{{ $wallet->getOwnerUsername() }}</strong></div>
                <div>Số dư hiện tại (<code>getBalance()</code>): <strong class="text-success">{{ number_format($wallet->getBalance(), 0, ',', '.') }} VNĐ</strong></div>
            </div>

            <h6 class="text-info fw-bold">🔌 Đa hình qua <code>PaymentInterface</code> (MoMo vs Ngân hàng):</h6>
            <div class="p-2 mb-2 rounded border border-secondary bg-black small">
                <div class="text-warning fw-bold">{{ $momoReceipt['gateway'] }} ({{ $momoReceipt['tx_code'] }})</div>
                <div>Vật phẩm: {{ $momoReceipt['item'] }} — <strong>{{ $momoReceipt['amount_vnd'] }}</strong> [{{ $momoReceipt['status'] }}]</div>
            </div>
            <div class="p-2 rounded border border-secondary bg-black small">
                <div class="text-info fw-bold">{{ $bankReceipt['gateway'] }} ({{ $bankReceipt['tx_code'] }})</div>
                <div>Vật phẩm: {{ $bankReceipt['item'] }} — <strong>{{ $bankReceipt['amount_vnd'] }}</strong> [{{ $bankReceipt['status'] }}]</div>
            </div>
        </x-card>
    </div>
</div>
@endsection
