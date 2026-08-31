<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Material Items - AMAC Circular Economy Tracker</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-b from-[#eaf7ee] via-white to-white text-neutral-900 min-h-screen">
    <div class="h-1.5 w-full bg-gradient-to-r from-[#0f7a3d] via-[#1a9650] to-[#c98500]"></div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-page-header
            title="Material Items"
            subtitle="The Lot 1 and Lot 2 material catalog - every category, subcategory and the units it's recorded in. Tap a category to expand it."
        />

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
            @foreach ($lots as $lot)
                <section class="bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm">
                    <div class="px-5 py-3.5 border-b border-neutral-100 {{ $lot['lot'] === 1 ? 'bg-[#0f7a3d]/5' : 'bg-[#c98500]/5' }} flex items-center justify-between">
                        <h2 class="text-sm font-semibold {{ $lot['lot'] === 1 ? 'text-[#0b5c2e]' : 'text-[#8a5c00]' }}">
                            {{ $lot['label'] }}
                        </h2>
                        <span class="text-xs text-neutral-400">{{ $lot['categories']->count() }} categories</span>
                    </div>

                    <div class="divide-y divide-neutral-100">
                        @foreach ($lot['categories'] as $category)
                            <details class="group">
                                <summary class="list-none cursor-pointer px-5 py-3 flex items-center justify-between hover:bg-neutral-50 transition-colors">
                                    <span class="flex items-center gap-2 text-sm font-medium text-neutral-900">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5 text-neutral-400 shrink-0 transition-transform group-open:rotate-90">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6" />
                                        </svg>
                                        {{ $category['label'] }}
                                    </span>
                                    <span class="text-xs text-neutral-400 shrink-0">{{ number_format($category['submissions']) }} submissions</span>
                                </summary>

                                <div class="px-5 pb-4 pl-11">
                                    @if ($lot['has_subcategories'] && $category['subcategories']->isNotEmpty())
                                        <table class="w-full text-sm">
                                            <thead class="text-left text-neutral-400 text-xs">
                                                <tr>
                                                    <th class="py-1 font-medium">Subcategory</th>
                                                    <th class="py-1 font-medium">Units of Measure</th>
                                                    <th class="py-1 font-medium text-right">Submissions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-neutral-50">
                                                @foreach ($category['subcategories'] as $sub)
                                                    <tr>
                                                        <td class="py-1.5 text-neutral-700">{{ $sub['label'] }}</td>
                                                        <td class="py-1.5 text-neutral-500">{{ implode(', ', $sub['units']) }}</td>
                                                        <td class="py-1.5 text-right tabular-nums text-neutral-500">{{ number_format($sub['submissions']) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @else
                                        <p class="text-sm text-neutral-500">
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
    </div>
</body>
</html>
