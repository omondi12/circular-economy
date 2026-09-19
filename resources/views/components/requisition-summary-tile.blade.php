@props(['label', 'amount', 'pendingCount', 'declinedCount', 'tone' => 'gold', 'icon' => 'scale'])

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
    class="group relative block overflow-hidden rounded-2xl p-5 bg-gradient-to-br shadow-lg {{ $style['grad'] }}"
    style="box-shadow: 0 16px 32px -14px {{ $style['glow'] }}"
>
    <svg class="absolute -bottom-6 -right-6 w-32 h-32 opacity-[0.12] rotate-[-12deg]" viewBox="0 0 100 100">
        <circle cx="50" cy="50" r="46" fill="none" stroke="white" stroke-width="1.5" stroke-dasharray="3 3"/>
        <circle cx="50" cy="50" r="38" fill="none" stroke="white" stroke-width="1"/>
    </svg>
    <div class="absolute -top-8 -right-8 w-28 h-28 rounded-full bg-white/10 blur-2xl"></div>

    <div class="relative flex items-center gap-3">
        <div class="shrink-0 w-10 h-10 rounded-full bg-white/15 ring-1 ring-white/30 backdrop-blur-sm flex items-center justify-center">
            <x-icon :name="$iconName" size="17" class="text-white" />
        </div>
        <p class="text-white/85 text-sm truncate">{{ $label }}</p>
    </div>

    <p class="relative mt-3 font-display text-3xl text-white tabular-nums">KES {{ number_format($amount, 0) }}</p>

    <div class="relative mt-3 pt-3 border-t border-white/20 flex flex-wrap items-center gap-2">
        <div class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-2.5 py-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-300"></span>
            <span class="text-[10px] font-medium text-white/75 uppercase tracking-wide">Pending</span>
            <span class="text-sm font-display text-white tabular-nums">{{ number_format($pendingCount) }}</span>
        </div>
        <div class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-2.5 py-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-rose-300"></span>
            <span class="text-[10px] font-medium text-white/75 uppercase tracking-wide">Declined</span>
            <span class="text-sm font-display text-white tabular-nums">{{ number_format($declinedCount) }}</span>
        </div>
    </div>
</div>
