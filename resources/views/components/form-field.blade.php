@props(['label', 'name', 'type' => 'text', 'required' => false, 'value' => null, 'step' => null])

<div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-neutral-200 last:border-b-0">
    <label for="{{ $name }}" class="bg-[#f7edd6] px-4 py-3 text-sm font-semibold text-neutral-700 flex items-center">
        {{ $label }}
        @if ($required)
            <span class="text-red-500 ml-1">*</span>
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
            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-neutral-900 placeholder:text-neutral-300"
        >
        @error($name)
            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>
</div>
