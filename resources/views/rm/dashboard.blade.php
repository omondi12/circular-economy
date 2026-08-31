<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Dashboard - Westport Industrial City</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-b from-[#eaf7ee] via-white to-white text-neutral-900 min-h-screen">
    <div class="h-1.5 w-full bg-gradient-to-r from-[#0f7a3d] via-[#1a9650] to-[#c98500]"></div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-lg bg-[#0f7a3d]/10 border border-[#0f7a3d]/30 text-[#0b5c2e] text-sm px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        <header class="relative mb-8 rounded-2xl bg-gradient-to-br from-[#0f7a3d] via-[#177a44] to-[#c98500] shadow-xl shadow-[#0f7a3d]/20 px-6 py-7 sm:px-8 sm:py-8 overflow-hidden">
            <div class="absolute -top-16 -right-10 w-64 h-64 rounded-full bg-white/10 blur-2xl"></div>

            <div class="relative flex flex-col sm:flex-row items-center gap-6">
                <div class="flex-1 text-center sm:text-left">
                    <p class="text-white/70 text-xs uppercase tracking-wide">Relationship Manager</p>
                    <h1 class="text-xl sm:text-2xl font-semibold text-white">{{ auth()->user()->name }}</h1>
                    <p class="text-sm text-white/80 mt-1">Your submissions, at a glance.</p>
                </div>

                <div class="shrink-0 flex items-center gap-2">
                    <a href="{{ route('rm.collections.create') }}" class="inline-flex items-center gap-2 px-5 py-3 rounded-lg bg-white text-[#0b5c2e] text-sm font-semibold hover:bg-white/90 transition-colors shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Record a Collection
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="px-4 py-3 rounded-lg bg-white/15 ring-1 ring-white/25 text-white text-sm font-medium hover:bg-white/25 transition-colors">
                            Log Out
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <x-stat-tile label="My Submissions" :value="number_format($totalSubmissions)" icon="document" tone="green" />
            <x-stat-tile label="Total Quantity Recorded" :value="number_format($totalQuantity, 1)" hint="Mixed units - see submissions below" icon="scale" tone="gold" />
            @foreach ($byLot as $lot)
                <x-stat-tile :label="$lot['label']" :value="number_format($lot['count'])" hint="submissions" icon="building" tone="teal" />
            @endforeach
        </div>

        <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-neutral-100">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-600">My Submissions</h2>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-[#0f7a3d]/5 text-left text-neutral-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Entity</th>
                        <th class="px-4 py-2 font-medium">Lot / Category</th>
                        <th class="px-4 py-2 font-medium text-right">Quantity</th>
                        <th class="px-4 py-2 font-medium">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($submissions as $submission)
                        <tr class="hover:bg-neutral-50 transition-colors">
                            <td class="px-4 py-3 font-medium max-w-xs">
                                <div class="line-clamp-2">{{ $submission->entity_name }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full {{ $submission->lot === 1 ? 'bg-[#0f7a3d]/10 text-[#0b5c2e]' : 'bg-[#c98500]/10 text-[#8a5c00]' }} text-xs font-medium">
                                    {{ \App\Support\WasteCategories::shortLotLabel($submission->lot) }}
                                </span>
                                <span class="block text-xs text-neutral-500 mt-0.5">{{ $submission->categoryLabel() }}</span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap font-medium">{{ number_format($submission->quantity, 1) }} {{ $submission->unit }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-500">{{ $submission->collection_date->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-neutral-400">You haven't recorded any collections yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $submissions->links() }}
        </div>
    </div>
</body>
</html>
