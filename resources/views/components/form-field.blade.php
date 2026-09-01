@props(['label', 'name', 'type' => 'text', 'required' => false, 'value' => null, 'step' => null])

<div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border last:border-b-0">
    <label for="{{ $name }}" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
        {{ $label }}
        @if ($required)
            <span class="text-danger ml-1">*</span>
        @endif
    </label>
    <div class="px-4 py-2 flex flex-col justify-center">
        <input
            type="{{ $type }}"
            id="{{ $name }}"
            name="{{ $name }}"
            value="{{ old($name, $value) }}"
            @if ($required) required @endif
            @if ($step) step="{{ $step }}" @endif
            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink placeholder:text-ink-faint bg-transparent"
        >
        @error($name)
            <p class="text-xs text-danger mt-1">{{ $message }}</p>
        @enderror
    </div>
</div>
