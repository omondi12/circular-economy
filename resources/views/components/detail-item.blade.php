@props(['label', 'value' => null])

<div>
    <p class="text-xs font-medium text-neutral-400 uppercase tracking-wide">{{ $label }}</p>
    <p class="mt-0.5 text-sm text-neutral-900">{{ $value !== null && $value !== '' ? $value : '—' }}</p>
</div>
