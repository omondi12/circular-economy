@props(['label', 'kg', 'maxKg'])

@php
    $pct = $maxKg > 0 ? max(0, min(100, round($kg / $maxKg * 100, 1))) : 0;
@endphp

<div class="flex items-center gap-3">
    <div class="w-24 sm:w-28 shrink-0 text-sm text-neutral-600">{{ $label }}</div>
    <div class="flex-1 h-2.5 rounded-full bg-neutral-100 overflow-hidden">
        <div class="h-full rounded-full bg-gradient-to-r from-[#1a9650] to-[#0f7a3d]" style="width: {{ $pct }}%"></div>
    </div>
    <div class="w-28 text-right text-sm font-semibold text-neutral-900 tabular-nums shrink-0">{{ number_format($kg, 1) }} kg</div>
</div>
