<x-layout title="My Clients">
        <x-page-header
            title="My Clients"
            subtitle="Pick a client to log an engagement report against."
            :back="route('rm.dashboard')"
            back-label="Back to my dashboard"
        />

        <form method="GET" class="mb-4">
            <div class="relative max-w-md">
                <input
                    type="text" name="q" value="{{ $search }}"
                    placeholder="Search my clients by name…"
                    class="w-full rounded-lg border border-border bg-white pl-4 pr-24 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus:ring-2 focus:ring-brand-600/30 focus:border-brand-600"
                >
                <button type="submit" class="absolute right-1.5 top-1/2 -translate-y-1/2 px-3 py-1.5 rounded-md bg-brand-700 text-white text-xs font-medium hover:bg-brand-800 transition-colors">
                    Search
                </button>
            </div>
            @if ($search)
                <a href="{{ route('rm.clients.index') }}" class="inline-block mt-2 text-xs text-ink-faint hover:text-ink-muted">
                    Clear search ({{ $clients->count() }} match{{ $clients->count() === 1 ? '' : 'es' }})
                </a>
            @endif
        </form>

        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-brand-50 text-left text-ink-faint">
                    <tr>
                        <th class="px-4 py-2 font-medium">Client</th>
                        <th class="px-4 py-2 font-medium">Ministry</th>
                        <th class="px-4 py-2 font-medium">Reports Logged</th>
                        <th class="px-4 py-2 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($clients as $client)
                        <tr class="hover:bg-panel-muted transition-colors">
                            <td class="px-4 py-3 font-medium">{{ $client->name }}</td>
                            <td class="px-4 py-3 text-ink-muted">{{ $client->ministryDisplay() }}</td>
                            <td class="px-4 py-3 tabular-nums text-ink-muted">{{ number_format($client->reports_count) }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('rm.clients.reports.index', $client) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-gold-50 text-gold-700 text-xs font-medium hover:bg-gold-100 transition-colors">
                                    Log Report
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-ink-faint">No clients assigned to you yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
</x-layout>
