<x-layout>

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
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-10">
        <x-stat-tile
            label="Ministries" icon="landmark" tone="rose"
            :value="number_format($ministryTotal)"
            :hint="number_format($ministryParticipating).' have submitted - includes the Presidency and Council of Governors'"
            :href="route('ministries.index')"
        />
        <x-stat-tile
            label="Clients" icon="building" tone="violet"
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
        <x-stat-tile
            label="Client Reports" icon="calendar" tone="teal"
            :value="number_format($reportCount)"
            hint="Daily engagement reports logged by RMs"
            :href="route('admin.reports.index')"
        />
    </div>

</x-layout>
