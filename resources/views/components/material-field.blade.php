@props(['label', 'name', 'number'])

<tr class="border-b border-border last:border-b-0 hover:bg-panel-muted transition-colors">
    <td class="px-4 py-2.5 text-sm font-medium text-ink-muted">{{ $number }}. {{ $label }}</td>
    <td class="px-4 py-2">
        <input
            type="number"
            step="0.01"
            min="0"
            id="{{ $name }}"
            name="{{ $name }}"
            value="{{ old($name) }}"
            placeholder="0.00"
            class="w-full sm:w-40 border-border rounded-lg text-sm focus:border-brand-600 focus:ring-4 focus:ring-brand-600/10 shadow-sm transition-shadow"
        >
        @error($name)
            <p class="text-xs text-danger mt-1">{{ $message }}</p>
        @enderror
    </td>
</tr>
