<x-layout title="LSOs">
    <x-page-header
        title="LSO Financial Overview"
        :subtitle="number_format($lsos->total()).' LSO(s) recorded.'"
        :back="route('admin.dashboard')"
        back-label="Back to admin"
    />

    @if (session('status'))
        <div class="mb-6 rounded-lg bg-brand-50 border border-brand-600/30 text-brand-800 text-sm px-4 py-3">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
        <x-stat-tile label="Total LSO Value" :value="'KES '.number_format($stats['totalOriginalMinor'] / 100)" hint="Value awarded, not collected" icon="scale" tone="gold" />
        <x-stat-tile label="Confirmed Collected" :value="'KES '.number_format($stats['totalConfirmedMinor'] / 100)" icon="stamp" tone="teal" />
        <x-stat-tile label="Outstanding" :value="'KES '.number_format($stats['totalOutstandingMinor'] / 100)" icon="building" tone="violet" />
        <x-stat-tile label="Fully Paid LSOs" :value="number_format($stats['fullyPaidCount'])" icon="circle-check" tone="green" />
    </div>

    <form method="GET" action="{{ route('admin.lsos.index') }}" class="mb-6 bg-panel border border-border rounded-xl p-4 shadow-sm flex flex-wrap gap-3 items-end">
        <div class="flex flex-col gap-1">
            <label for="reference" class="text-xs text-ink-faint">LSO Reference</label>
            <input type="text" id="reference" name="reference" value="{{ $filters['reference'] }}" placeholder="Search reference…"
                class="rounded-md border-border text-sm focus:border-brand-600 focus:ring-brand-600">
        </div>

        <div class="flex flex-col gap-1">
            <label for="from" class="text-xs text-ink-faint">Issued From</label>
            <input type="date" id="from" name="from" value="{{ $filters['from'] }}" class="rounded-md border-border text-sm focus:border-brand-600 focus:ring-brand-600">
        </div>

        <div class="flex flex-col gap-1">
            <label for="to" class="text-xs text-ink-faint">Issued To</label>
            <input type="date" id="to" name="to" value="{{ $filters['to'] }}" class="rounded-md border-border text-sm focus:border-brand-600 focus:ring-brand-600">
        </div>

        <div class="flex flex-col gap-1">
            <label for="user_id" class="text-xs text-ink-faint">RM</label>
            <select id="user_id" name="user_id" class="rounded-md border-border text-sm focus:border-brand-600 focus:ring-brand-600">
                <option value="">All RMs</option>
                @foreach ($rms as $rm)
                    <option value="{{ $rm->id }}" @selected((string) $filters['user_id'] === (string) $rm->id)>{{ $rm->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label for="status" class="text-xs text-ink-faint">Status</label>
            <select id="status" name="status" class="rounded-md border-border text-sm focus:border-brand-600 focus:ring-brand-600">
                <option value="">All statuses</option>
                @foreach ([\App\Models\Lso::STATUS_RECORDED, \App\Models\Lso::STATUS_ACTIVE, \App\Models\Lso::STATUS_FULLY_PAID, \App\Models\Lso::STATUS_CANCELLED] as $status)
                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 rounded-md bg-brand-700 hover:bg-brand-800 text-white text-sm font-medium transition-colors shadow-sm shadow-brand-900/20">
                Filter
            </button>
            <a href="{{ route('admin.lsos.index') }}" class="px-4 py-2 rounded-md border border-border text-sm hover:bg-panel-muted transition-colors">
                Reset
            </a>
        </div>
    </form>

    <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-brand-50 text-left text-ink-faint">
                <tr>
                    <th class="px-4 py-2 font-medium">Reference</th>
                    <th class="px-4 py-2 font-medium">RM</th>
                    <th class="px-4 py-2 font-medium">Customer</th>
                    <th class="px-4 py-2 font-medium text-right">Lots</th>
                    <th class="px-4 py-2 font-medium text-right">Original Value</th>
                    <th class="px-4 py-2 font-medium text-right">Collected</th>
                    <th class="px-4 py-2 font-medium text-right">Outstanding</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($lsos as $lso)
                    <tr class="hover:bg-panel-muted transition-colors">
                        <td class="px-4 py-3 font-medium whitespace-nowrap">{{ $lso->reference_number }}</td>
                        <td class="px-4 py-3">{{ $lso->user?->name }}</td>
                        <td class="px-4 py-3">{{ $lso->customer_name }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($lso->lots_count) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($lso->original_amount_minor / 100) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($lso->confirmedCollectedMinor() / 100) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($lso->outstandingMinor() / 100) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-brand-50 text-brand-800 text-xs font-medium capitalize">{{ str_replace('_', ' ', $lso->status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.lsos.show', $lso) }}" class="text-brand-700 hover:text-brand-900 text-sm font-medium">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-ink-faint">No LSOs recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $lsos->links() }}
    </div>
</x-layout>
