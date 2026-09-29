<x-layout title="My LSOs">
    @if (session('status'))
        <div class="mb-6 rounded-lg bg-brand-50 border border-brand-600/30 text-brand-800 text-sm px-4 py-3">
            {{ session('status') }}
        </div>
    @endif

    <header class="relative mb-8 rounded-2xl bg-gradient-to-br from-brand-700 via-brand-800 to-brand-900 shadow-xl shadow-brand-900/10 px-6 py-7 sm:px-8 sm:py-8 overflow-hidden">
        <div class="absolute -top-16 -right-10 w-64 h-64 rounded-full bg-white/10 blur-2xl"></div>

        <div class="relative flex flex-col sm:flex-row items-center gap-6">
            <div class="flex-1 text-center sm:text-left">
                <p class="text-white/70 text-xs uppercase tracking-wide">My Local Service Orders</p>
                <h1 class="text-xl sm:text-2xl font-semibold text-white">LSOs &amp; Collections</h1>
                <p class="text-sm text-white/80 mt-1">Record a tender/LSO and track money collected against it.</p>
            </div>

            <div class="shrink-0 flex items-center gap-2">
                <a href="{{ route('rm.lsos.create') }}" class="inline-flex items-center gap-2 px-5 py-3 rounded-lg bg-white text-brand-800 text-sm font-semibold hover:bg-white/90 transition-colors shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Record an LSO
                </a>
                <a href="{{ route('rm.dashboard') }}" class="inline-flex items-center gap-2 px-5 py-3 rounded-lg bg-white/15 ring-1 ring-white/25 text-white text-sm font-semibold hover:bg-white/25 transition-colors">
                    <x-icon name="arrow-left" size="16" />
                    Dashboard
                </a>
            </div>
        </div>
    </header>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <x-stat-tile label="My LSOs" :value="number_format($totalLsos)" icon="document" tone="green" />
        <x-stat-tile label="Total LSO Value" :value="'KES '.number_format($totalOriginalMinor / 100)" hint="Value awarded, not money collected" icon="scale" tone="gold" />
        <x-stat-tile label="Confirmed Collected" :value="'KES '.number_format($totalConfirmedMinor / 100)" hint="Only Finance-confirmed payments" icon="stamp" tone="teal" />
    </div>

    <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
        <div class="px-5 py-4 border-b border-border">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-ink-muted">My LSOs</h2>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-brand-50 text-left text-ink-faint">
                <tr>
                    <th class="px-4 py-2 font-medium">Reference</th>
                    <th class="px-4 py-2 font-medium">Customer</th>
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
                        <td class="px-4 py-3 font-medium">{{ $lso->reference_number }}</td>
                        <td class="px-4 py-3">{{ $lso->customer_name }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($lso->original_amount_minor / 100) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($lso->confirmedCollectedMinor() / 100) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($lso->outstandingMinor() / 100) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-brand-50 text-brand-800 text-xs font-medium capitalize">{{ str_replace('_', ' ', $lso->status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('rm.lsos.show', $lso) }}" class="text-brand-700 hover:text-brand-900 text-sm font-medium">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-ink-faint">You haven't recorded any LSOs yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $lsos->links() }}
    </div>
</x-layout>
