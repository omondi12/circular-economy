<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Westport Industrial City</title>
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
                    <h1 class="text-xl sm:text-2xl font-semibold text-white">Westport Industrial City</h1>
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
                label="Ministries" icon="landmark" tone="rose"
                :value="number_format($ministryTotal)"
                :hint="number_format($ministryParticipating).' have submitted - includes the Presidency and Council of Governors'"
                :href="route('ministries.index')"
            />
            <x-stat-tile
                label="State Corporations" icon="building" tone="violet"
                :value="number_format($stateCorpTotal)"
                :hint="'Phase 1: '.number_format($stateCorpPhase1).' - Phase 2: '.number_format($stateCorpPhase2)"
                :href="route('state-corporations.index')"
            />
            <x-stat-tile
                label="Material Items" icon="scale" tone="gold"
                :value="number_format($materialItemCount)"
                hint="Across Lot 1 (Sale) and Lot 2 (Disposal)"
                :href="route('material-items.index')"
            />
            <x-stat-tile
                label="Feasibility Study" icon="document" tone="green"
                :value="number_format($totalSubmissions)"
                hint="RM submissions - by agent, materials, ministry or state corporation"
                :href="route('feasibility-study.index')"
            />
        </div>

        {{-- Breakdown by weight (kg) - legacy submissions + every kg-denominated Lot category --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">
            <x-category-weight-chart :categories="$byWeight" :total="$byWeight->sum('kg')" />

            <section class="bg-white border border-neutral-200 rounded-xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wide border-l-4 border-[#0f7a3d] pl-3 text-neutral-600">
                        Ranked by Weight
                    </h2>
                    <a href="{{ route('materials.index') }}" class="text-xs font-medium text-[#0f7a3d] hover:text-[#0b5c2e] transition-colors">
                        View full breakdown &rarr;
                    </a>
                </div>
                @if ($byWeight->isEmpty() || $byWeight->sum('kg') == 0)
                    <p class="text-sm text-neutral-400">No kg-denominated collections recorded yet.</p>
                @else
                    <div class="space-y-3">
                        @php $maxKg = $byWeight->max('kg'); @endphp
                        @foreach ($byWeight as $row)
                            <x-category-weight-bar :label="$row['label']" :kg="$row['kg']" :max-kg="$maxKg" :color="$row['color']" />
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
                                            <td class="py-1.5 text-neutral-600 align-top">{{ $category['label'] }}</td>
                                            <td class="py-1.5 text-right tabular-nums font-medium whitespace-nowrap">
                                                @forelse ($category['units'] as $u)
                                                    <div>{{ number_format($u['quantity'], 1) }} {{ $u['label'] }}</div>
                                                @empty
                                                    <span class="text-neutral-300 font-normal">—</span>
                                                @endforelse
                                            </td>
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
                        <th class="px-5 py-2 font-medium text-right">Recorded Quantities</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($byEntity as $row)
                        <tr class="hover:bg-neutral-50 transition-colors">
                            <td class="px-5 py-2.5 font-medium">{{ $row->entity_name }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums text-neutral-500">{{ number_format($row->submissions) }}</td>
                            <td class="px-5 py-2.5 text-right font-medium"><x-entity-quantity :row="$row" /></td>
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
            <x-submissions-table :collections="$recent" />
        </section>
    </div>
</body>
</html>
