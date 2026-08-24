<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AMAC Circular Economy Tracker</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-b from-[#eaf7ee] via-white to-white text-neutral-900 min-h-screen">
    <div class="h-1.5 w-full bg-gradient-to-r from-[#0f7a3d] via-[#1a9650] to-[#c98500]"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        @if (session('status'))
            <div class="mb-6 rounded-lg bg-[#0f7a3d]/10 border border-[#0f7a3d]/30 text-[#0b5c2e] text-sm px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        {{-- Hero banner --}}
        <header class="relative mb-8 rounded-2xl bg-gradient-to-br from-[#0f7a3d] via-[#177a44] to-[#c98500] shadow-xl shadow-[#0f7a3d]/20 px-6 py-7 sm:px-8 sm:py-8 overflow-hidden">
            <div class="absolute -top-16 -right-10 w-64 h-64 rounded-full bg-white/10 blur-2xl"></div>
            <div class="absolute -bottom-20 left-1/3 w-72 h-72 rounded-full bg-[#c98500]/20 blur-3xl"></div>

            <div class="relative flex flex-col sm:flex-row items-center gap-6">
                <div class="w-14 h-14 shrink-0 rounded-2xl bg-white/15 ring-1 ring-white/30 flex items-center justify-center backdrop-blur-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8" class="w-7 h-7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5M16.5 3.5a3 3 0 0 1 3 3v1M20.5 9.5v3a3 3 0 0 1-3 3M13.5 20.5h-3a3 3 0 0 1-3-3v-1" />
                    </svg>
                </div>

                <div class="flex-1 text-center sm:text-left">
                    <h1 class="text-xl sm:text-2xl font-semibold text-white">AMAC Circular Economy Tracker</h1>
                    <p class="text-sm text-white/85 mt-1 max-w-2xl">
                        Recyclable materials collected from ministries, counties and commissions.
                    </p>
                </div>

                @auth
                    <div class="shrink-0 flex items-center gap-2">
                        <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('rm.dashboard') }}" class="inline-flex items-center gap-2 px-5 py-3 rounded-lg bg-white text-[#0b5c2e] text-sm font-semibold hover:bg-white/90 transition-colors shadow-sm">
                            {{ auth()->user()->isAdmin() ? 'Admin Dashboard' : 'My Dashboard' }}
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="px-4 py-3 rounded-lg bg-white/15 ring-1 ring-white/25 text-white text-sm font-medium hover:bg-white/25 transition-colors">
                                Log Out
                            </button>
                        </form>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="shrink-0 inline-flex items-center gap-2 px-5 py-3 rounded-lg bg-white/15 ring-1 ring-white/25 text-white text-sm font-semibold hover:bg-white/25 transition-colors backdrop-blur-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25" />
                        </svg>
                        RM / Admin Login
                    </a>
                @endauth
            </div>
        </header>

        {{-- Stat cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <x-stat-tile
                label="Total Submissions" icon="document" tone="green"
                :value="number_format($totalSubmissions)"
                hint="Tap to search or filter"
                :href="route('collections.index')"
            />
            <x-stat-tile
                label="Total Weight Collected" icon="scale" tone="gold"
                :value="number_format($totalKg, 1).' kg'"
                hint="Tap for the material breakdown"
                :href="route('materials.index')"
            />
            <x-stat-tile
                label="This Month" icon="calendar" tone="teal"
                :value="number_format($thisMonthKg, 1).' kg'"
                hint="Tap to see this month's submissions"
                :href="$monthRangeHref"
            />
            <x-stat-tile
                label="Participating Entities" icon="building" tone="violet"
                :value="number_format($entityCount)"
                hint="Tap to see every entity"
                :href="route('entities.index')"
            />
        </div>

        {{-- Breakdown by material --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">
            <x-material-breakdown-chart :materials="$byMaterial" :total="$byMaterial->sum('kg')" />

            <section class="bg-white border border-neutral-200 rounded-xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wide border-l-4 border-[#0f7a3d] pl-3 text-neutral-600">
                        Ranked by Weight
                    </h2>
                    <a href="{{ route('materials.index') }}" class="text-xs font-medium text-[#0f7a3d] hover:text-[#0b5c2e] transition-colors">
                        View full breakdown &rarr;
                    </a>
                </div>
                @if ($byMaterial->isEmpty() || $byMaterial->sum('kg') == 0)
                    <p class="text-sm text-neutral-400">No collections recorded yet.</p>
                @else
                    <div class="space-y-3">
                        @php $maxKg = $byMaterial->max('kg'); @endphp
                        @foreach ($byMaterial as $row)
                            <x-material-bar :label="$row['label']" :kg="$row['kg']" :max-kg="$maxKg" />
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        {{-- Breakdown by lot / category --}}
        <section class="mb-8 bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm">
            <div class="mt-5 mb-4 mx-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide border-l-4 border-[#0f7a3d] pl-3 text-neutral-600">
                    By Lot &amp; Category
                </h2>
            </div>

            @if ($byLot->sum(fn ($lot) => $lot['submissions']) == 0)
                <p class="text-sm text-neutral-400 px-5 pb-5">No Lot submissions recorded yet - RMs enter these from their own dashboard.</p>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-neutral-100">
                    @foreach ($byLot as $lot)
                        <div class="p-5">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-semibold {{ $lot['lot'] === 1 ? 'text-[#0b5c2e]' : 'text-[#8a5c00]' }}">{{ $lot['label'] }}</h3>
                                <span class="text-xs text-neutral-400">{{ number_format($lot['submissions']) }} submissions</span>
                            </div>
                            <table class="w-full text-sm">
                                <tbody class="divide-y divide-neutral-100">
                                    @foreach ($lot['categories'] as $category)
                                        <tr>
                                            <td class="py-1.5 text-neutral-600">{{ $category['label'] }}</td>
                                            <td class="py-1.5 text-right tabular-nums font-medium whitespace-nowrap">{{ number_format($category['quantity'], 1) }} {{ $category['unit'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Breakdown by entity --}}
        <section class="mb-8 bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm">
            <div class="flex items-center justify-between mt-5 mb-4 mx-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide border-l-4 border-[#c98500] pl-3 text-neutral-600">
                    Top Contributing Entities
                </h2>
                <a href="{{ route('entities.index') }}" class="text-xs font-medium text-[#0f7a3d] hover:text-[#0b5c2e] transition-colors">
                    View all entities &rarr;
                </a>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-[#f7edd6] text-left text-neutral-600">
                    <tr>
                        <th class="px-5 py-2 font-medium">Ministry / County / Commission</th>
                        <th class="px-5 py-2 font-medium text-right">Submissions</th>
                        <th class="px-5 py-2 font-medium text-right">Total Kg</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($byEntity as $row)
                        <tr class="hover:bg-neutral-50 transition-colors">
                            <td class="px-5 py-2.5 font-medium">{{ $row->entity_name }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums text-neutral-500">{{ number_format($row->submissions) }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums font-medium">{{ number_format($row->total_kg, 1) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-5 py-8 text-center text-neutral-400">No collections recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        {{-- Recent submissions --}}
        <section>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide border-l-4 border-[#0f7a3d] pl-3 text-neutral-600">
                    Recent Submissions ({{ number_format($recent->total()) }})
                </h2>
                <a href="{{ route('collections.index') }}" class="text-xs font-medium text-[#0f7a3d] hover:text-[#0b5c2e] transition-colors">
                    Search &amp; filter all &rarr;
                </a>
            </div>
            <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-[#0f7a3d]/5 text-left text-neutral-500">
                        <tr>
                            <th class="px-4 py-2 font-medium">Entity</th>
                            <th class="px-4 py-2 font-medium">Lot / Category</th>
                            <th class="px-4 py-2 font-medium">Contact</th>
                            <th class="px-4 py-2 font-medium text-right">Quantity</th>
                            <th class="px-4 py-2 font-medium">Date</th>
                            <th class="px-4 py-2 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($recent as $collection)
                            <tr class="hover:bg-neutral-50 transition-colors">
                                <td class="px-4 py-3 font-medium max-w-xs">
                                    <div class="line-clamp-2">{{ $collection->entity_name }}</div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($collection->isLegacyMaterialEntry())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-neutral-100 text-neutral-500 text-xs">Legacy</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full {{ $collection->lot === 1 ? 'bg-[#0f7a3d]/10 text-[#0b5c2e]' : 'bg-[#c98500]/10 text-[#8a5c00]' }} text-xs font-medium">
                                            {{ \App\Support\WasteCategories::shortLotLabel($collection->lot) }}
                                        </span>
                                        <span class="block text-xs text-neutral-500 mt-0.5">{{ $collection->categoryLabel() }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-neutral-500">{{ $collection->contact_person_name }}</td>
                                <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap font-medium">
                                    @if ($collection->isLegacyMaterialEntry())
                                        {{ number_format($collection->totalKg(), 1) }} kg
                                    @else
                                        {{ number_format($collection->quantity, 1) }} {{ $collection->unit }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-neutral-500">{{ $collection->collection_date->format('d M Y') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <a href="{{ route('collections.show', $collection) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-[#0f7a3d]/10 text-[#0b5c2e] text-xs font-medium hover:bg-[#0f7a3d]/20 transition-colors">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-neutral-400">No collections recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $recent->links() }}
            </div>
        </section>
    </div>
</body>
</html>
