@props(['name', 'size' => 18])

@php
    $paths = [
        'alert-triangle' => '<path d="M12 9v4M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>',
        'arrow-left' => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'arrow-right' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'building' => '<path d="M3 21h18M6 21V6a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v15M18 21V11a1 1 0 0 0-1-1h-3"/><path d="M9 7h1M9 11h1M9 15h1"/>',
        'building-bank' => '<path d="M3 21h18M4 10h16M4 10l8-6 8 6M6 10v9M10 10v9M14 10v9M18 10v9"/>',
        'building-community' => '<path d="M4 21V8l6-4 6 4v13M4 21h16M9 21v-4h4v4M9 12h.01M9 15h.01M13 12h.01M13 15h.01M16 10v11"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
        'circle' => '<circle cx="12" cy="12" r="9"/>',
        'circle-check' => '<circle cx="12" cy="12" r="9"/><path d="m9 12 2 2 4-4"/>',
        'file-text' => '<path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2Z"/><path d="M9 13h6M9 17h6"/>',
        'inbox' => '<path d="M4 12h4l2 3h4l2-3h4"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11Z"/>',
        'layout-dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'recycle' => '<path d="M7 19H4.815a1.83 1.83 0 0 1-1.57-.881 1.785 1.785 0 0 1-.004-1.784L7.196 9.5M11 19h8.203a1.83 1.83 0 0 0 1.556-.89 1.784 1.784 0 0 0 0-1.775l-1.226-2.12M14.288 4.052 16.5 3.5l1.62 2.803M18.5 8.5l-3.5 6M4.5 12.5l-1.5-2.6L4.5 7.4M8 4.5l-1.5 2.6L4 7.4"/>',
        'scale' => '<path d="M12 3v18M7 21h10M5 7l3-2 3 2M17 7l3-2 3 2"/><path d="m5 7-3 8a3 3 0 0 0 6 0Z"/><path d="m19 7-3 8a3 3 0 0 0 6 0Z"/>',
        'sort-ascending' => '<path d="M4 6h9M4 12h6M4 18h4M17 4v16M17 20l3-3M17 20l-3-3"/>',
        'stamp' => '<path d="M9 4a2 2 0 0 1 4 0v3.17c1.75.42 3 1.98 3 3.83H6c0-1.85 1.25-3.41 3-3.83Z"/><path d="M4 17h16v3H4zM7 14h10v3H7z"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
    ];

    $svg = $paths[$name] ?? $paths['circle'];
@endphp

<svg {{ $attributes->merge(['class' => '', 'width' => $size, 'height' => $size]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    {!! $svg !!}
</svg>
