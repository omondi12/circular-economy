@props(['title', 'subtitle' => null, 'back' => null, 'backLabel' => 'Back to dashboard'])

{{-- Page title block. Optional `actions` slot puts the page's buttons on
     the right on wide screens and below the title on phones, so they can
     never push the page sideways. --}}
<div class="mb-6 fade-rise flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
    <div class="min-w-0">
        {{-- Default "back" is the viewer's own home (RM dashboard, admin, ...),
             not the public overview a signed-in user never came from. --}}
        <a href="{{ $back ?? route(auth()->check() ? auth()->user()->homeRouteName() : 'dashboard') }}" class="inline-flex items-center gap-1.5 text-sm text-brand-700 hover:text-brand-900 font-medium mb-3 transition-colors">
            <x-icon name="arrow-left" size="15" />
            {{ $backLabel }}
        </a>
        <h1 class="font-display italic text-2xl sm:text-3xl text-ink break-words">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-sm text-ink-muted mt-1.5 max-w-2xl">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap gap-2 md:justify-end md:shrink-0">
            {{ $actions }}
        </div>
    @endisset
</div>
