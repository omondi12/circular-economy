<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Participating Entities - Westport Industrial City</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-b from-[#eaf7ee] via-white to-white text-neutral-900 min-h-screen">
    <div class="h-1.5 w-full bg-gradient-to-r from-[#0f7a3d] via-[#1a9650] to-[#c98500]"></div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-page-header
            title="Participating Entities"
            :subtitle="number_format($entities->count()).' ministries, counties and commissions have submitted collections. Tap a row to see its submissions.'"
        />

        <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#f7edd6] text-left text-neutral-600">
                    <tr>
                        <th class="px-4 py-2 font-medium">Ministry / County / Commission</th>
                        <th class="px-4 py-2 font-medium text-right">Submissions</th>
                        <th class="px-4 py-2 font-medium text-right">Recorded Quantities</th>
                        <th class="px-4 py-2 font-medium">Last Collection</th>
                        <th class="px-4 py-2 font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($entities as $row)
                        <tr class="hover:bg-neutral-50 transition-colors">
                            <td class="px-4 py-3 font-medium max-w-sm">
                                <div class="line-clamp-2">{{ $row->entity_name }}</div>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-500">{{ number_format($row->submissions) }}</td>
                            <td class="px-4 py-3 text-right font-medium"><x-entity-quantity :row="$row" /></td>
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-500">{{ \Illuminate\Support\Carbon::parse($row->last_collection_date)->format('d M Y') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="{{ route('collections.index', ['entity' => $row->entity_name]) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-[#0f7a3d]/10 text-[#0b5c2e] text-xs font-medium hover:bg-[#0f7a3d]/20 transition-colors">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-neutral-400">No entities recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
