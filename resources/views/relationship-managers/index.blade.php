<x-layout title="Relationship Managers">
        <x-page-header
            title="Relationship Managers"
            :subtitle="$rms->count().' active RM account(s), and who they report to.'"
            :back="route('dashboard')"
            back-label="Back to dashboard"
        />

        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-brand-50 text-left text-ink-faint">
                    <tr>
                        <th class="px-4 py-2 font-medium">Name</th>
                        <th class="px-4 py-2 font-medium">Supervisor</th>
                        <th class="px-4 py-2 font-medium">Assigned Clients</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($rms as $rm)
                        <tr class="hover:bg-panel-muted transition-colors">
                            <td class="px-4 py-3 font-medium">{{ $rm->name }}</td>
                            <td class="px-4 py-3 text-ink-faint">{{ $rm->supervisor->name ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ number_format($rm->client_count) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-ink-faint">No RM accounts yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
</x-layout>
