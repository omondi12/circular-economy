@props(['row'])

{{--
    kg, liters and tons are different quantities and must never be summed
    into one number - shows each non-zero unit total on its own line
    instead of a fabricated blended figure.
--}}
@php
    $parts = [];
    if ($row->total_kg > 0) $parts[] = number_format($row->total_kg, 1).' kg';
    if ($row->total_ltr > 0) $parts[] = number_format($row->total_ltr, 1).' ltr';
    if ($row->total_ton > 0) $parts[] = number_format($row->total_ton, 1).' ton';
@endphp

@if (empty($parts))
    <span class="text-neutral-300">—</span>
@else
    <div class="space-y-0.5">
        @foreach ($parts as $part)
            <div class="tabular-nums">{{ $part }}</div>
        @endforeach
    </div>
@endif
