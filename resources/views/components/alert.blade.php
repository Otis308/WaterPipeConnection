@props(['type' => 'success', 'title' => 'Thông báo'])
<div class="alert alert-{{ $type === 'error' ? 'danger' : $type }} border-0 shadow-sm mb-3"
     style="background: {{ $type === 'success' ? '#ECFDF5' : ($type === 'warning' ? '#FEF3C7' : '#FEE2E2') }};
            color: {{ $type === 'success' ? '#065F46' : ($type === 'warning' ? '#92400E' : '#991B1B') }};">
    <strong>{{ $title }}:</strong> {!! $slot !!}
</div>
