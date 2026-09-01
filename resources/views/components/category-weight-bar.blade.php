@props(['label', 'kg', 'maxKg', 'color' => '#147041'])

@php
    $pct = $maxKg > 0 ? max(0, min(100, round($kg / $maxKg * 100, 1))) : 0;
@endphp

<div class="flex items-center gap-3" x-data="{ ready: false }" x-init="requestAnimationFrame(() => requestAnimationFrame(() => ready = true))">
    <div class="w-40 sm:w-48 shrink-0 text-sm text-ink-muted truncate" title="{{ $label }}">{{ $label }}</div>
    <div class="flex-1 h-2.5 rounded-full bg-panel-high overflow-hidden">
        <div class="h-full rounded-full" :style="ready ? 'width: {{ $pct }}%' : 'width: 0%'" style="background-color: {{ $color }}; transition: width 0.8s cubic-bezier(0.16,1,0.3,1) 0.1s"></div>
    </div>
    <div class="w-28 text-right text-sm font-semibold text-ink tabular-nums shrink-0">{{ number_format($kg, 1) }} kg</div>
</div>
