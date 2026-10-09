@props(['label', 'name', 'type' => 'text', 'required' => false, 'value' => null, 'step' => null, 'placeholder' => null, 'hint' => null])

@php
    $invalid = $errors->has($name);
    $describedBy = trim(($invalid ? $name.'-error ' : '').($hint ? $name.'-hint' : ''));
    // The borderless input hands its focus indicator to the whole row: a
    // visible inset ring (green, or red while invalid) that never depends
    // on the background tint alone.
    $inputClasses = 'w-full rounded-md border-0 text-sm py-2 px-0 text-ink placeholder:text-ink-faint bg-transparent focus:outline-none';
@endphp

<div @class([
    'grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border last:border-b-0 transition-colors focus-within:ring-2 focus-within:ring-inset',
    'bg-danger-bg/40 focus-within:ring-danger' => $invalid,
    'focus-within:bg-brand-50/40 focus-within:ring-brand-600' => ! $invalid,
])>
    <label for="{{ $name }}" class="bg-gold-50 px-4 pt-3 pb-1 sm:py-3 text-sm font-semibold text-ink-muted flex items-center">
        {{ $label }}
        @if ($required)
            <span class="text-danger ml-1" aria-hidden="true">*</span>
            <span class="sr-only">(required)</span>
        @endif
    </label>
    <div class="px-4 py-2 flex flex-col justify-center" @if ($type === 'password') x-data="{ visible: false }" @endif>
        @if ($type === 'password')
            <div class="relative">
                <input
                    type="password"
                    :type="visible ? 'text' : 'password'"
                    id="{{ $name }}"
                    name="{{ $name }}"
                    value="{{ old($name, $value) }}"
                    @if ($required) required @endif
                    @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                    @if ($invalid) aria-invalid="true" @endif
                    @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                    autocomplete="new-password"
                    class="{{ $inputClasses }} pr-10"
                >
                <button
                    type="button" @click="visible = ! visible"
                    class="absolute inset-y-0 right-0 flex items-center justify-center w-9 text-ink-faint hover:text-ink"
                    :aria-label="visible ? 'Hide password' : 'Show password'"
                    aria-label="Show password"
                    :aria-pressed="visible.toString()"
                >
                    <span x-show="! visible"><x-icon name="eye" size="17" /></span>
                    <span x-show="visible" x-cloak><x-icon name="eye-off" size="17" /></span>
                </button>
            </div>
        @else
            <input
                type="{{ $type }}"
                id="{{ $name }}"
                name="{{ $name }}"
                value="{{ old($name, $value) }}"
                @if ($required) required @endif
                @if ($step) step="{{ $step }}" @endif
                @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                @if ($invalid) aria-invalid="true" @endif
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                class="{{ $inputClasses }}"
            >
        @endif
        @if ($hint)
            <p id="{{ $name }}-hint" class="field-hint">{{ $hint }}</p>
        @endif
        @error($name)
            <p id="{{ $name }}-error" class="field-error flex items-center gap-1"><x-icon name="alert-circle" size="13" /> {{ $message }}</p>
        @enderror
    </div>
</div>
