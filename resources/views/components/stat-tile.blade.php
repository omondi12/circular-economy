@props(['label', 'value', 'hint' => null, 'icon' => 'scale', 'tone' => 'green', 'href' => null, 'compact' => false])

@php
    $tones = [
        'green' => ['grad' => 'from-brand-600 to-brand-800', 'glow' => 'rgba(20,112,65,.35)'],
        'gold' => ['grad' => 'from-gold-500 to-gold-700', 'glow' => 'rgba(181,129,10,.35)'],
        'teal' => ['grad' => 'from-[#0093b3] to-[#00566b]', 'glow' => 'rgba(0,147,179,.35)'],
        'violet' => ['grad' => 'from-[#7a4fa0] to-[#4f3268]', 'glow' => 'rgba(122,79,160,.35)'],
        'rose' => ['grad' => 'from-[#b2334f] to-[#6b1e30]', 'glow' => 'rgba(178,51,79,.35)'],
    ];
    $style = $tones[$tone] ?? $tones['green'];

    $iconName = [
        'document' => 'file-text',
        'scale' => 'scale',
        'calendar' => 'calendar',
        'building' => 'building',
        'landmark' => 'building-bank',
        'user' => 'user',
        'building-community' => 'building-community',
    ][$icon] ?? 'circle';

    $numericValue = str_replace(',', '', (string) $value);
    $isAnimatable = $numericValue !== '' && ctype_digit($numericValue);
@endphp

@php $tag = $href ? 'a' : 'div'; @endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    @class([
        'group relative block overflow-hidden rounded-2xl bg-gradient-to-br shadow-lg transition-all duration-200',
        $compact ? 'p-3.5' : 'p-5',
        $style['grad'],
        'hover:-translate-y-1 hover:shadow-xl' => $href,
    ])
    style="box-shadow: 0 {{ $compact ? '10px 20px -12px' : '16px 32px -14px' }} {{ $style['glow'] }}"
    @if ($isAnimatable) x-data="{ shown: 0, target: {{ (int) $numericValue }} }" x-init="let start=null; const dur=900; function step(ts){ if(!start) start=ts; const p=Math.min((ts-start)/dur,1); shown=Math.round((1-Math.pow(1-p,3))*target); if(p<1) requestAnimationFrame(step);} requestAnimationFrame(step);" @endif
>
    {{-- Stamp-ring watermark, the "official register" motif --}}
    <svg class="absolute -bottom-6 -right-6 {{ $compact ? 'w-20 h-20' : 'w-32 h-32' }} opacity-[0.12] rotate-[-12deg]" viewBox="0 0 100 100">
        <circle cx="50" cy="50" r="46" fill="none" stroke="white" stroke-width="1.5" stroke-dasharray="3 3"/>
        <circle cx="50" cy="50" r="38" fill="none" stroke="white" stroke-width="1"/>
    </svg>
    <div @class(['absolute -top-8 -right-8 rounded-full bg-white/10 blur-2xl', $compact ? 'w-20 h-20' : 'w-28 h-28'])></div>

    <div class="relative flex items-center justify-between">
        <div @class(['flex items-center min-w-0', $compact ? 'gap-2' : 'gap-3'])>
            <div @class(['shrink-0 rounded-full bg-white/15 ring-1 ring-white/30 backdrop-blur-sm flex items-center justify-center', $compact ? 'w-7 h-7' : 'w-10 h-10'])>
                <x-icon :name="$iconName" :size="$compact ? 13 : 17" class="text-white" />
            </div>
            <p @class(['text-white/85 truncate', $compact ? 'text-xs' : 'text-sm'])>{{ $label }}</p>
        </div>
        @if ($href)
            <x-icon name="arrow-right" :size="$compact ? 14 : 18" class="text-white/50 group-hover:text-white/90 group-hover:translate-x-0.5 transition-all shrink-0" />
        @endif
    </div>
    @if ($isAnimatable)
        <p @class(['relative font-display text-white tabular-nums', $compact ? 'mt-2 text-lg' : 'mt-3 text-3xl']) x-text="shown.toLocaleString()"></p>
    @else
        <p @class(['relative font-display text-white tabular-nums', $compact ? 'mt-2 text-lg' : 'mt-3 text-3xl'])>{{ $value }}</p>
    @endif
    @if ($hint)
        <p @class(['relative text-white/60', $compact ? 'mt-0.5 text-[10px]' : 'mt-1 text-xs'])>{{ $hint }}</p>
    @endif
</{{ $tag }}>
