@props(['title' => '', 'subtitle' => ''])
<div class="card bg-dark text-light border-secondary shadow-sm mb-4">
    @if(!empty($title))
        <div class="card-header border-secondary d-flex justify-content-between align-items-center" style="background:#111827;">
            <h5 class="mb-0 text-info fw-bold">{{ $title }}</h5>
            @if(!empty($subtitle))
                <span class="badge bg-secondary">{{ $subtitle }}</span>
            @endif
        </div>
    @endif
    <div class="card-body">
        {!! $slot !!}
    </div>
</div>
