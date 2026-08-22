<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Submissions - AMAC Circular Economy Tracker</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-b from-[#eaf7ee] via-white to-white text-neutral-900 min-h-screen">
    <div class="h-1.5 w-full bg-gradient-to-r from-[#0f7a3d] via-[#1a9650] to-[#c98500]"></div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-page-header
            title="Submissions"
            :subtitle="number_format($collections->total()).' collections recorded. Search or filter to find one directly.'"
        />

        {{-- Filters --}}
        <form method="GET" action="{{ route('collections.index') }}" class="mb-6 bg-white border border-neutral-200 rounded-xl p-4 shadow-sm flex flex-wrap gap-3 items-end">
            <div class="flex flex-col gap-1 flex-1 min-w-[180px]">
                <label for="entity" class="text-xs text-neutral-500">Ministry / County / Commission</label>
                <input type="text" id="entity" name="entity" value="{{ $filters['entity'] }}" placeholder="e.g. Ministry of..."
                    class="rounded-md border-neutral-300 text-sm focus:border-[#0f7a3d] focus:ring-[#0f7a3d]">
            </div>

            <div class="flex flex-col gap-1">
                <label for="material" class="text-xs text-neutral-500">Material</label>
                <select id="material" name="material" class="rounded-md border-neutral-300 text-sm focus:border-[#0f7a3d] focus:ring-[#0f7a3d]">
                    <option value="">All materials</option>
                    @foreach ($materials as $key => $label)
                        <option value="{{ $key }}" @selected($filters['material'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label for="from" class="text-xs text-neutral-500">From</label>
                <input type="date" id="from" name="from" value="{{ $filters['from'] }}"
                    class="rounded-md border-neutral-300 text-sm focus:border-[#0f7a3d] focus:ring-[#0f7a3d]">
            </div>

            <div class="flex flex-col gap-1">
                <label for="to" class="text-xs text-neutral-500">To</label>
                <input type="date" id="to" name="to" value="{{ $filters['to'] }}"
                    class="rounded-md border-neutral-300 text-sm focus:border-[#0f7a3d] focus:ring-[#0f7a3d]">
            </div>

            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 rounded-md bg-[#0f7a3d] hover:bg-[#0b5c2e] text-white text-sm font-medium transition-colors shadow-sm shadow-[#0f7a3d]/30">
                    Filter
                </button>
                <a href="{{ route('collections.index') }}" class="px-4 py-2 rounded-md border border-neutral-300 text-sm hover:bg-neutral-50 transition-colors">
                    Reset
                </a>
            </div>
        </form>

        <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#0f7a3d]/5 text-left text-neutral-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Entity</th>
                        <th class="px-4 py-2 font-medium">Contact</th>
                        <th class="px-4 py-2 font-medium text-right">Total Kg</th>
                        <th class="px-4 py-2 font-medium">Date</th>
                        <th class="px-4 py-2 font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($collections as $collection)
                        <tr class="hover:bg-neutral-50 transition-colors">
                            <td class="px-4 py-3 font-medium max-w-xs">
                                <div class="line-clamp-2">{{ $collection->entity_name }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-500">{{ $collection->contact_person_name }}</td>
                            <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap font-medium">{{ number_format($collection->totalKg(), 1) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-500">{{ $collection->collection_date->format('d M Y') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="{{ route('collections.show', $collection) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-[#0f7a3d]/10 text-[#0b5c2e] text-xs font-medium hover:bg-[#0f7a3d]/20 transition-colors">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-neutral-400">No submissions match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $collections->links() }}
        </div>
    </div>
</body>
</html>
