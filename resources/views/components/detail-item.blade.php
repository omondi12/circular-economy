@props(['label', 'value' => null])

<div>
    <p class="text-xs font-medium text-ink-faint uppercase tracking-wide font-mono">{{ $label }}</p>
    <p class="mt-0.5 text-sm text-ink">{{ $value !== null && $value !== '' ? $value : '—' }}</p>
</div>
