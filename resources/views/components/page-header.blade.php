@props(['title', 'subtitle' => null, 'back' => null, 'backLabel' => 'Back to dashboard'])

<div class="mb-6">
    <a href="{{ $back ?? route('dashboard') }}" class="inline-flex items-center gap-1.5 text-sm text-[#0f7a3d] hover:text-[#0b5c2e] font-medium mb-3 transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        {{ $backLabel }}
    </a>
    <h1 class="text-xl sm:text-2xl font-semibold text-neutral-900">{{ $title }}</h1>
    @if ($subtitle)
        <p class="text-sm text-neutral-500 mt-1 max-w-2xl">{{ $subtitle }}</p>
    @endif
</div>
