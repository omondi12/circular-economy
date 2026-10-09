@props(['title', 'message' => null, 'icon' => 'inbox'])

{{-- Shared "nothing here" block for lists and tables. Put an optional
     action (e.g. "Clear filters", "Create LSO") in the default slot. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center text-center px-4 py-10']) }}>
    <span class="inline-flex items-center justify-center w-11 h-11 rounded-full bg-panel-muted text-ink-faint mb-3">
        <x-icon :name="$icon" size="20" />
    </span>
    <p class="text-sm font-semibold text-ink">{{ $title }}</p>
    @if ($message)
        <p class="mt-1 text-sm text-ink-muted max-w-sm">{{ $message }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-4 flex flex-wrap justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
