@props(['materials', 'total'])

@php
    $colors = [
        'paper_kg' => '#0f7a3d',
        'metal_kg' => '#c98500',
        'plastic_kg' => '#b23a78',
        'furniture_kg' => '#0093b3',
        'ewaste_kg' => '#b2491f',
        'other_kg' => '#7a4fa0',
    ];

    $ordered = collect($colors)->map(fn ($color, $key) => (
        $materials->firstWhere('key', $key) ?? ['key' => $key, 'label' => $key, 'kg' => 0]
    ) + ['color' => $color])->values();

    $cursor = 0;
    $stops = $ordered->map(function (array $m) use (&$cursor, $total) {
        $pct = $total > 0 ? $m['kg'] / $total * 100 : 0;
        $start = $cursor;
        $cursor += $pct;

        return "{$m['color']} {$start}% {$cursor}%";
    })->implode(', ');
@endphp

<div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
    <div class="flex items-baseline justify-between mb-4">
        <h2 class="text-sm font-semibold text-neutral-900">Collected by Material</h2>
        <span class="text-xs text-neutral-400">{{ number_format($total, 1) }} kg</span>
    </div>

    <div class="flex items-center gap-6">
        <div class="relative w-28 h-28 shrink-0 rounded-full" style="background: conic-gradient({{ $stops }})">
            <div class="absolute inset-[15%] rounded-full bg-white flex flex-col items-center justify-center">
                <span class="text-base font-bold text-neutral-900 tabular-nums">{{ number_format($total, 0) }}</span>
                <span class="text-[10px] text-neutral-400">kg total</span>
            </div>
        </div>

        <div class="flex-1 min-w-0 space-y-2">
            @foreach ($ordered as $m)
                @php $pct = $total > 0 ? $m['kg'] / $total * 100 : 0; @endphp
                <a
                    href="{{ route('collections.index', ['material' => $m['key']]) }}"
                    class="group flex items-center gap-2"
                    title="{{ $m['label'] }}: {{ number_format($m['kg'], 1) }} kg ({{ number_format($pct, 1) }}%)"
                >
                    <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $m['color'] }}"></span>
                    <span class="text-xs text-neutral-600 truncate group-hover:text-neutral-900 transition-colors">{{ $m['label'] }}</span>
                    <span class="text-xs font-medium text-neutral-900 tabular-nums ml-auto shrink-0">{{ number_format($m['kg'], 1) }}</span>
                </a>
            @endforeach
        </div>
    </div>
</div>
