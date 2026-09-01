@props(['categories', 'total'])

@php
    $cursor = 0;
    $stops = $categories->map(function (array $c) use (&$cursor, $total) {
        $pct = $total > 0 ? $c['kg'] / $total * 100 : 0;
        $start = $cursor;
        $cursor += $pct;

        return "{$c['color']} {$start}% {$cursor}%";
    })->implode(', ');
@endphp

<div class="rounded-2xl border border-border bg-panel p-5 shadow-sm">
    <div class="flex items-baseline justify-between mb-4">
        <h2 class="text-sm font-semibold text-ink flex items-center gap-2">
            <span class="w-1.5 h-1.5 rounded-full bg-brand-700"></span>
            {{ __('Collected by Weight') }}
        </h2>
        <span class="text-xs text-ink-faint font-mono">{{ number_format($total, 1) }} kg</span>
    </div>

    @if ($categories->isEmpty())
        <p class="text-sm text-ink-faint">{{ __('No kg-denominated collections recorded yet.') }}</p>
    @else
        <div class="flex items-center gap-6">
            <div class="relative w-28 h-28 shrink-0 rounded-full" style="background: conic-gradient({{ $stops }})">
                <div class="absolute inset-[15%] rounded-full bg-panel flex flex-col items-center justify-center shadow-inner">
                    <span class="text-base font-display text-ink tabular-nums">{{ number_format($total, 0) }}</span>
                    <span class="text-[10px] text-ink-faint">{{ __('kg total') }}</span>
                </div>
            </div>

            <div class="flex-1 min-w-0 space-y-2">
                @foreach ($categories as $c)
                    @php $pct = $total > 0 ? $c['kg'] / $total * 100 : 0; @endphp
                    <div class="group flex items-center gap-2" title="{{ $c['label'] }}: {{ number_format($c['kg'], 1) }} kg ({{ number_format($pct, 1) }}%)">
                        <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $c['color'] }}"></span>
                        <span class="text-xs text-ink-muted truncate">{{ $c['label'] }}</span>
                        <span class="text-xs font-medium text-ink tabular-nums ml-auto shrink-0">{{ number_format($c['kg'], 1) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
