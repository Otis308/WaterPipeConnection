@props(['difficulty' => 'easy'])
@php
    $diff = strtolower((string)($difficulty ?? 'easy'));
    $bgColor = $diff === 'hard' ? '#FEE2E2' : ($diff === 'medium' ? '#FEF3C7' : '#DCFCE7');
    $textColor = $diff === 'hard' ? '#991B1B' : ($diff === 'medium' ? '#92400E' : '#166534');
    $label = $diff === 'hard' ? 'Hard (Ống Vàng + Đỏ)' : ($diff === 'medium' ? 'Medium (Ống Đỏ)' : 'Easy (Cơ bản)');
@endphp
<span class="badge px-2 py-1 rounded-pill fw-semibold"
      style="background:{{ $bgColor }}; color:{{ $textColor }};">
    {{ $label }}
</span>
