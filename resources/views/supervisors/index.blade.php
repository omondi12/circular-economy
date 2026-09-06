<x-layout title="Supervisors">
        <x-page-header
            title="Supervisors"
            :subtitle="$supervisors->count().' active Supervisor account(s), each covering their own team of RMs.'"
            :back="route('dashboard')"
            back-label="Back to dashboard"
        />

        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-brand-50 text-left text-ink-faint">
                    <tr>
                        <th class="px-4 py-2 font-medium">Name</th>
                        <th class="px-4 py-2 font-medium">RMs on Team</th>
                        <th class="px-4 py-2 font-medium">Clients Under Them</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($supervisors as $row)
                        <tr class="hover:bg-panel-muted transition-colors">
                            <td class="px-4 py-3 font-medium">{{ $row['supervisor']->name }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ number_format($row['rmCount']) }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ number_format($row['clientCount']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-ink-faint">No Supervisor accounts yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
</x-layout>
