@props(['label', 'count', 'hint' => null, 'href' => null])

@php $tag = $href ? 'a' : 'div'; @endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    @class([
        'group block bg-panel border border-border rounded-xl p-4 shadow-sm transition-all duration-200',
        'hover:shadow-md hover:-translate-y-0.5 hover:border-brand-300' => $href,
        'opacity-60' => $count === 0,
    ])
>
    <p class="text-sm font-medium text-ink-muted line-clamp-2 mb-2 min-h-[2.5rem]">{{ $label }}</p>
    <p class="text-2xl font-display {{ $count > 0 ? 'text-brand-700' : 'text-ink-faint' }} tabular-nums">{{ number_format($count) }}</p>
    <p class="text-xs text-ink-faint mt-0.5">{{ $hint ?? ($count === 1 ? 'submission' : 'submissions') }}</p>
</{{ $tag }}>
