@props(['label', 'amount', 'pendingCount', 'declinedCount', 'tone' => 'gold', 'icon' => 'scale', 'compact' => false])

@php
    $tones = [
        'green' => ['grad' => 'from-brand-600 to-brand-800', 'glow' => 'rgba(20,112,65,.35)'],
        'gold' => ['grad' => 'from-gold-500 to-gold-700', 'glow' => 'rgba(181,129,10,.35)'],
        'teal' => ['grad' => 'from-[#0093b3] to-[#00566b]', 'glow' => 'rgba(0,147,179,.35)'],
        'violet' => ['grad' => 'from-[#7a4fa0] to-[#4f3268]', 'glow' => 'rgba(122,79,160,.35)'],
        'rose' => ['grad' => 'from-[#b2334f] to-[#6b1e30]', 'glow' => 'rgba(178,51,79,.35)'],
    ];
    $style = $tones[$tone] ?? $tones['gold'];

    $iconName = [
        'document' => 'file-text',
        'scale' => 'scale',
        'calendar' => 'calendar',
        'building' => 'building',
        'landmark' => 'building-bank',
        'user' => 'user',
        'building-community' => 'building-community',
    ][$icon] ?? 'scale';
@endphp

<div
    @class(['group relative block overflow-hidden rounded-2xl bg-gradient-to-br shadow-lg', $style['grad'], $compact ? 'p-3.5' : 'p-5'])
    style="box-shadow: 0 {{ $compact ? '10px 20px -12px' : '16px 32px -14px' }} {{ $style['glow'] }}"
>
    <svg class="absolute -bottom-6 -right-6 {{ $compact ? 'w-20 h-20' : 'w-32 h-32' }} opacity-[0.12] rotate-[-12deg]" viewBox="0 0 100 100">
        <circle cx="50" cy="50" r="46" fill="none" stroke="white" stroke-width="1.5" stroke-dasharray="3 3"/>
        <circle cx="50" cy="50" r="38" fill="none" stroke="white" stroke-width="1"/>
    </svg>
    <div @class(['absolute -top-8 -right-8 rounded-full bg-white/10 blur-2xl', $compact ? 'w-20 h-20' : 'w-28 h-28'])></div>

    <div @class(['relative flex items-center', $compact ? 'gap-2' : 'gap-3'])>
        <div @class(['shrink-0 rounded-full bg-white/15 ring-1 ring-white/30 backdrop-blur-sm flex items-center justify-center', $compact ? 'w-7 h-7' : 'w-10 h-10'])>
            <x-icon :name="$iconName" :size="$compact ? 13 : 17" class="text-white" />
        </div>
        <p @class(['text-white/85 truncate', $compact ? 'text-xs' : 'text-sm'])>{{ $label }}</p>
    </div>

    <p @class(['relative font-display text-white tabular-nums', $compact ? 'mt-2 text-lg' : 'mt-3 text-3xl'])>KES {{ number_format($amount, 0) }}</p>

    <div @class(['relative border-t border-white/20 flex items-center', $compact ? 'mt-2 pt-2 gap-4' : 'mt-3 pt-3 gap-6'])>
        <div>
            <p class="text-[10px] text-white/60 uppercase tracking-wide">Pending</p>
            <p @class(['font-display text-white tabular-nums', $compact ? 'text-sm' : 'text-lg'])>{{ number_format($pendingCount) }}</p>
        </div>
        <div>
            <p class="text-[10px] text-white/60 uppercase tracking-wide">Declined</p>
            <p @class(['font-display text-white tabular-nums', $compact ? 'text-sm' : 'text-lg'])>{{ number_format($declinedCount) }}</p>
        </div>
    </div>
</div>
