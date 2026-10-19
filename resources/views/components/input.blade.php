@props(['name', 'label' => null, 'type' => 'text', 'value' => '', 'placeholder' => ''])
<div class="mb-3">
    <label class="form-label fw-semibold text-info">{{ $label ?? ucfirst($name) }}</label>
    <input type="{{ $type }}"
           name="{{ $name }}"
           class="form-control bg-dark text-light border-secondary"
           placeholder="{{ $placeholder }}"
           value="{{ old($name, $value) }}">
    @error($name)
        <div class="mt-1 small fw-bold" style="color:#ff6b6b;">{{ $message }}</div>
    @enderror
</div>
