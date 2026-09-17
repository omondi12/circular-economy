@props(['status'])

@php
    $styles = [
        'pending' => 'bg-gold-50 text-gold-800',
        'approved' => 'bg-brand-50 text-brand-800',
        'declined' => 'bg-red-100 text-danger',
    ];
    $labels = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'declined' => 'Declined',
    ];
@endphp

<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap {{ $styles[$status] ?? 'bg-panel-high text-ink-faint' }}">
    {{ $labels[$status] ?? ucfirst($status) }}
</span>
