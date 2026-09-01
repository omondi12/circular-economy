<x-layout title="Material Items">
        <x-page-header
            title="Material Items"
            subtitle="The Lot 1 and Lot 2 material catalog - every category, subcategory and the units it's recorded in. Tap a category to expand it."
        />

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
            @foreach ($lots as $lot)
                <section class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
                    <div class="px-5 py-3.5 border-b border-border {{ $lot['lot'] === 1 ? 'bg-brand-50' : 'bg-gold-50' }} flex items-center justify-between">
                        <h2 class="text-sm font-semibold {{ $lot['lot'] === 1 ? 'text-brand-800' : 'text-gold-700' }}">
                            {{ $lot['label'] }}
                        </h2>
                        <span class="text-xs text-ink-faint">{{ $lot['categories']->count() }} categories</span>
                    </div>

                    <div class="divide-y divide-border">
                        @foreach ($lot['categories'] as $category)
                            <details class="group">
                                <summary class="list-none cursor-pointer px-5 py-3 flex items-center justify-between hover:bg-panel-muted transition-colors">
                                    <span class="flex items-center gap-2 text-sm font-medium text-ink">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5 text-ink-faint shrink-0 transition-transform group-open:rotate-90">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6" />
                                        </svg>
                                        {{ $category['label'] }}
                                    </span>
                                    <span class="text-xs text-ink-faint shrink-0">{{ number_format($category['submissions']) }} submissions</span>
                                </summary>

                                <div class="px-5 pb-4 pl-11">
                                    @if ($lot['has_subcategories'] && $category['subcategories']->isNotEmpty())
                                        <table class="w-full text-sm">
                                            <thead class="text-left text-ink-faint text-xs">
                                                <tr>
                                                    <th class="py-1 font-medium">Subcategory</th>
                                                    <th class="py-1 font-medium">Units of Measure</th>
                                                    <th class="py-1 font-medium text-right">Submissions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-border">
                                                @foreach ($category['subcategories'] as $sub)
                                                    <tr>
                                                        <td class="py-1.5 text-ink-muted">{{ $sub['label'] }}</td>
                                                        <td class="py-1.5 text-ink-faint">{{ implode(', ', $sub['units']) }}</td>
                                                        <td class="py-1.5 text-right tabular-nums text-ink-faint">{{ number_format($sub['submissions']) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @else
                                        <p class="text-sm text-ink-faint">
                                            Units of Measure: {{ implode(', ', $category['units']) }}
                                        </p>
                                    @endif
                                </div>
                            </details>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
</x-layout>
