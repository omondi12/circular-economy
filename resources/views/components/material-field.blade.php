@props(['label', 'name', 'number'])

<tr class="border-b border-neutral-200 last:border-b-0">
    <td class="px-4 py-2.5 text-sm font-medium text-neutral-700">{{ $number }}. {{ $label }}</td>
    <td class="px-4 py-2">
        <input
            type="number"
            step="0.01"
            min="0"
            id="{{ $name }}"
            name="{{ $name }}"
            value="{{ old($name) }}"
            placeholder="0.00"
            class="w-full sm:w-40 border-neutral-300 rounded-md text-sm focus:border-[#0f7a3d] focus:ring-[#0f7a3d]"
        >
        @error($name)
            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </td>
</tr>
