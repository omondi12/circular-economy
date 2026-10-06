@props(['status'])

@php
    // Icon + text, so status never depends on colour alone.
    $styles = [
        'pending' => ['badge-warning', 'clock', 'Pending'],
        'approved' => ['badge-success', 'check', 'Approved'],
        'declined' => ['badge-danger', 'x', 'Declined'],
    ];
    [$class, $icon, $label] = $styles[$status] ?? ['badge-neutral', null, ucfirst((string) $status)];
@endphp

<span class="badge {{ $class }}">
    @if ($icon)
        <x-icon :name="$icon" size="11" />
    @endif
    {{ $label }}
</span>
