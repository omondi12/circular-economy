<x-layout>

    {{-- Hero --}}
    <section class="relative rounded-2xl overflow-hidden bg-gradient-to-br from-brand-800 via-brand-700 to-brand-900 shadow-xl mb-10 px-6 py-12 sm:px-12 sm:py-16">
        <svg class="absolute -right-10 -top-10 w-72 h-72 opacity-[0.10]" viewBox="0 0 100 100">
            <circle cx="50" cy="50" r="47" fill="none" stroke="white" stroke-width="0.8" stroke-dasharray="2 3"/>
            <circle cx="50" cy="50" r="40" fill="none" stroke="white" stroke-width="0.6"/>
            <circle cx="50" cy="50" r="33" fill="none" stroke="white" stroke-width="0.6"/>
        </svg>
        <div class="absolute -bottom-24 left-1/4 w-72 h-72 rounded-full bg-gold-500/10 blur-3xl"></div>

        <div class="relative max-w-2xl fade-rise">
            <h1 class="font-display italic text-4xl sm:text-6xl text-white leading-[1.08]">
                {{ __('Circular Economy Materials Register') }}
            </h1>
            <p class="mt-5 text-white/75 leading-relaxed max-w-lg">
                {{ __('Every recyclable and disposal item collected across ministries, counties, commissions and state corporations — tracked from submission to recovery, in one place.') }}
            </p>
        </div>
    </section>

    {{-- Live ticker --}}
    @if ($recent->isNotEmpty())
        <div class="border-y border-border bg-panel-muted -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 py-3 mb-10 overflow-hidden marquee-mask">
            <div class="flex gap-10 ticker-track w-max">
                @for ($rep = 0; $rep < 2; $rep++)
                    @foreach ($recent->take(8) as $item)
                        <span class="flex items-center gap-2 text-xs text-ink-muted whitespace-nowrap">
                            <span class="w-1.5 h-1.5 rounded-full bg-brand-500 shrink-0"></span>
                            <span class="font-medium text-ink">{{ \Illuminate\Support\Str::limit($item->entity_name, 32) }}</span>
                            {{ __('logged a submission') }}
                            <span class="text-ink-faint">· {{ $item->collection_date->diffForHumans() }}</span>
                        </span>
                    @endforeach
                @endfor
            </div>
        </div>
    @endif

    {{-- Stat cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
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

    {{-- Breakdown by weight (kg) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-10">
        <x-category-weight-chart :categories="$byWeight" :total="$byWeight->sum('kg')" />

        <section class="bg-panel border border-border rounded-xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide font-mono border-l-4 border-brand-600 pl-3 text-ink-muted">
                    {{ __('Ranked by Weight') }}
                </h2>
                <a href="{{ route('materials.index') }}" class="text-xs font-medium text-brand-700 hover:text-brand-900 transition-colors">
                    {{ __('View full breakdown') }} &rarr;
                </a>
            </div>
            @if ($byWeight->isEmpty() || $byWeight->sum('kg') == 0)
                <p class="text-sm text-ink-faint">{{ __('No kg-denominated collections recorded yet.') }}</p>
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
    <section class="mb-10 bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
        <div class="mt-5 mb-4 mx-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide font-mono border-l-4 border-brand-600 pl-3 text-ink-muted">
                {{ __('By Lot & Category') }}
            </h2>
        </div>

        @if ($byLot->sum(fn ($lot) => $lot['submissions']) == 0)
            <p class="text-sm text-ink-faint px-5 pb-5">{{ __('No Lot submissions recorded yet - RMs enter these from their own dashboard.') }}</p>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-border">
                @foreach ($byLot as $lot)
                    <div class="p-5">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold {{ $lot['lot'] === 1 ? 'text-brand-800' : 'text-gold-700' }}">{{ $lot['label'] }}</h3>
                            <span class="text-xs text-ink-faint">{{ number_format($lot['submissions']) }} {{ __('submissions') }}</span>
                        </div>
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-border">
                                @php $maxCatSubmissions = collect($lot['categories'])->max('submissions') ?: 1; @endphp
                                @foreach ($lot['categories'] as $category)
                                    <tr class="hover:bg-panel-muted transition-colors">
                                        <td class="py-2 text-ink-muted align-top">
                                            <div class="flex items-center gap-2">
                                                <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $category['submissions'] > 0 ? ($lot['lot'] === 1 ? 'bg-brand-500' : 'bg-gold-500') : 'bg-panel-high' }}"></span>
                                                {{ $category['label'] }}
                                            </div>
                                            @if ($category['submissions'] > 0)
                                                <div class="ml-3.5 mt-1 w-20 h-1 rounded-full bg-panel-high overflow-hidden">
                                                    <div class="h-full rounded-full {{ $lot['lot'] === 1 ? 'bg-brand-400' : 'bg-gold-400' }}" style="width: {{ round($category['submissions'] / $maxCatSubmissions * 100) }}%"></div>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="py-2 text-right tabular-nums font-medium whitespace-nowrap align-top">
                                            @forelse ($category['units'] as $u)
                                                <div>{{ number_format($u['quantity'], 1) }} {{ $u['label'] }}</div>
                                            @empty
                                                <span class="text-ink-faint font-normal">—</span>
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
    <section class="mb-10 bg-panel border border-border rounded-xl overflow-hidden shadow-sm" x-data="{ q: '' }">
        <div class="flex flex-wrap items-center justify-between gap-3 mt-5 mb-4 mx-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide font-mono border-l-4 border-gold-500 pl-3 text-ink-muted">
                {{ __('Top Contributing Entities') }}
            </h2>
            <div class="flex items-center gap-3">
                <div class="relative">
                    <x-icon name="user" size="14" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-ink-faint" />
                    <input x-model="q" type="text" placeholder="{{ __('Filter…') }}" class="text-xs border border-border rounded-md pl-7 pr-2 py-1.5 bg-panel-muted focus:bg-panel focus:ring-2 focus:ring-brand-600/10 focus:border-brand-600 transition-colors w-36 sm:w-48">
                </div>
                <a href="{{ route('entities.index') }}" class="text-xs font-medium text-brand-700 hover:text-brand-900 transition-colors whitespace-nowrap">
                    {{ __('View all') }} &rarr;
                </a>
            </div>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gold-50 text-left text-ink-muted text-[11px] font-mono uppercase tracking-wider">
                <tr>
                    <th class="px-5 py-2.5 font-medium w-10"></th>
                    <th class="px-5 py-2.5 font-medium">{{ __('Ministry / County / Commission') }}</th>
                    <th class="px-5 py-2.5 font-medium text-right">{{ __('Submissions') }}</th>
                    <th class="px-5 py-2.5 font-medium text-right">{{ __('Recorded Quantities') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @php $maxSubmissions = $byEntity->max('submissions') ?: 1; @endphp
                @forelse ($byEntity as $i => $row)
                    @php
                        $rankClass = match (true) {
                            $i === 0 => 'bg-gold-500 text-white',
                            $i === 1 => 'bg-ink-faint/40 text-ink',
                            $i === 2 => 'bg-gold-300/60 text-gold-700',
                            default => 'bg-panel-high text-ink-faint',
                        };
                        $barPct = round($row->submissions / $maxSubmissions * 100);
                    @endphp
                    <tr
                        class="hover:bg-panel-muted transition-colors"
                        x-show="q === '' || {{ Illuminate\Support\Js::from(mb_strtolower($row->entity_name)) }}.includes(q.toLowerCase())"
                    >
                        <td class="px-5 py-2.5">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-[11px] font-bold {{ $rankClass }}">{{ $i + 1 }}</span>
                        </td>
                        <td class="px-5 py-2.5 font-medium text-ink">{{ $row->entity_name }}</td>
                        <td class="px-5 py-2.5 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <div class="w-16 h-1.5 rounded-full bg-panel-high overflow-hidden hidden sm:block">
                                    <div class="h-full rounded-full bg-brand-500" style="width: {{ $barPct }}%"></div>
                                </div>
                                <span class="tabular-nums text-ink-faint w-6 text-right">{{ number_format($row->submissions) }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-2.5 text-right font-medium"><x-entity-quantity :row="$row" /></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-10 text-center text-ink-faint">{{ __('No collections recorded yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>


</x-layout>
