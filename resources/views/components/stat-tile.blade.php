@props(['label', 'value', 'hint' => null, 'icon' => 'scale', 'tone' => 'green', 'href' => null])

@php
    $tones = [
        'green' => ['grad' => 'from-[#0f7a3d] to-[#0a5228]', 'glow' => 'rgba(15,122,61,.35)'],
        'gold' => ['grad' => 'from-[#c98500] to-[#7a5100]', 'glow' => 'rgba(201,133,0,.35)'],
        'teal' => ['grad' => 'from-[#0093b3] to-[#00566b]', 'glow' => 'rgba(0,147,179,.35)'],
        'violet' => ['grad' => 'from-[#7a4fa0] to-[#4f3268]', 'glow' => 'rgba(122,79,160,.35)'],
    ];
    $style = $tones[$tone] ?? $tones['green'];

    $icons = [
        'document' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />',
        'scale' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.589-1.202L18.75 4.97Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.589-1.202L5.25 4.97Z" />',
        'calendar' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />',
        'building' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V6a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v15M14 21V10a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v11M8 8h.01M8 11h.01M8 14h.01M8 17h.01" />',
    ][$icon] ?? '';
@endphp

@php
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    @class([
        'group relative block overflow-hidden rounded-2xl bg-gradient-to-br p-5 shadow-lg transition-all',
        $style['grad'],
        'hover:-translate-y-1' => $href,
    ])
    style="box-shadow: 0 16px 32px -14px {{ $style['glow'] }}"
>
    <div class="absolute -top-8 -right-8 w-28 h-28 rounded-full bg-white/10 blur-2xl"></div>
    <div class="absolute -bottom-10 -left-6 w-24 h-24 rounded-full bg-white/5 blur-2xl"></div>

    <div class="relative flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 shrink-0 rounded-lg bg-white/15 ring-1 ring-white/25 backdrop-blur-sm flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8" class="w-5 h-5">
                    {!! $icons !!}
                </svg>
            </div>
            <p class="text-sm text-white/85">{{ $label }}</p>
        </div>
        @if ($href)
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 text-white/50 group-hover:text-white/80 transition-colors shrink-0">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        @endif
    </div>
    <p class="relative mt-3 text-2xl font-semibold text-white tabular-nums">{{ $value }}</p>
    @if ($hint)
        <p class="relative mt-1 text-xs text-white/60">{{ $hint }}</p>
    @endif
</{{ $tag }}>
