@props(['row'])

{{--
    kg, litres, tonnes, m3, pieces, units, cartons and sets are all
    different quantities and must never be summed into one number - shows
    each non-zero unit total on its own line instead of a fabricated
    blended figure.
--}}
@php
    $parts = [];
    foreach (\App\Support\WasteCategories::UNIT_LABELS as $key => $label) {
        $value = $row->{"total_{$key}"} ?? null;
        if ($value > 0) {
            $parts[] = number_format($value, 1).' '.$label;
        }
    }
@endphp

@if (empty($parts))
    <span class="text-ink-faint">—</span>
@else
    <div class="space-y-0.5">
        @foreach ($parts as $part)
            <div class="tabular-nums">{{ $part }}</div>
        @endforeach
    </div>
@endif
