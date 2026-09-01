@props(['title', 'subtitle' => null, 'back' => null, 'backLabel' => 'Back to dashboard'])

<div class="mb-6 fade-rise">
    <a href="{{ $back ?? route('dashboard') }}" class="inline-flex items-center gap-1.5 text-sm text-brand-700 hover:text-brand-900 font-medium mb-3 transition-colors">
        <x-icon name="arrow-left" size="15" />
        {{ $backLabel }}
    </a>
    <h1 class="font-display italic text-2xl sm:text-3xl text-ink">{{ $title }}</h1>
    @if ($subtitle)
        <p class="text-sm text-ink-muted mt-1.5 max-w-2xl">{{ $subtitle }}</p>
    @endif
</div>
