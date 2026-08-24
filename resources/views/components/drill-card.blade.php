@props(['label', 'count', 'hint' => null, 'href' => null])

@php $tag = $href ? 'a' : 'div'; @endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    @class([
        'group block bg-white border border-neutral-200 rounded-xl p-4 shadow-sm transition-all',
        'hover:shadow-md hover:-translate-y-0.5 hover:border-[#0f7a3d]/30' => $href,
        'opacity-60' => $count === 0,
    ])
>
    <p class="text-sm font-medium text-neutral-700 line-clamp-2 mb-2 min-h-[2.5rem]">{{ $label }}</p>
    <p class="text-2xl font-bold {{ $count > 0 ? 'text-[#0f7a3d]' : 'text-neutral-300' }} tabular-nums">{{ number_format($count) }}</p>
    <p class="text-xs text-neutral-400 mt-0.5">{{ $hint ?? ($count === 1 ? 'submission' : 'submissions') }}</p>
</{{ $tag }}>
