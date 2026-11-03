<x-layout title="Balance Team">
    @php $max = \App\Services\RmAllocation::MAX_ACCOUNTS; @endphp

    <x-page-header
        :title="'Balance '.$supervisor->name.'\'s team'"
        :subtitle="'Nothing has moved yet. These are the moves that bring this supervisor\'s area counties into the team, spread them over its RMs and keep every RM at '.$max.' accounts or fewer. Apply them only if they look right.'"
        :back="route('admin.allocation.index')"
        back-label="Back to areas & rules"
    />

    @if ($plan['team']->isEmpty())
        <x-empty-state title="This supervisor has no RMs yet." message="Put RMs in the team on the Supervisors tab first." />
    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
            <section class="lg:col-span-2 bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
                <h2 class="px-5 pt-5 font-medium text-ink">{{ count($plan['moves']) }} {{ count($plan['moves']) === 1 ? 'move' : 'moves' }}</h2>
                @if ($plan['moves'] === [])
                    <p class="px-5 pb-5 pt-2 text-sm text-ink-faint">The team is already balanced: nothing to move.</p>
                @else
                    <div class="mt-3 border-t border-border overflow-x-auto">
                        <table data-stack class="w-full text-sm">
                            <thead class="bg-brand-50 text-left text-ink-faint">
                                <tr>
                                    <th class="px-4 py-2 font-medium">Client</th>
                                    <th class="px-4 py-2 font-medium">From</th>
                                    <th class="px-4 py-2 font-medium">To</th>
                                    <th class="px-4 py-2 font-medium">Why</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach ($plan['moves'] as $m)
                                    <tr>
                                        <td class="px-4 py-2 font-medium" data-label="Client">{{ $m['client']->name }}</td>
                                        <td class="px-4 py-2 text-ink-muted" data-label="From">{{ $m['from']?->name ?? 'Nobody' }}</td>
                                        <td class="px-4 py-2" data-label="To">{{ $m['to']->name }}</td>
                                        <td class="px-4 py-2 text-xs text-ink-faint" data-label="Why">{{ $m['reason'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="bg-panel border border-border rounded-xl p-5 shadow-sm">
                <h2 class="font-medium text-ink">The team after</h2>
                <ul class="mt-3 divide-y divide-border text-sm">
                    @foreach ($plan['after'] as $r)
                        <li class="flex justify-between gap-3 py-2">
                            <span>{{ $r['rm']->name }}@if ($r['counties'])<span class="block text-xs text-ink-faint">{{ $r['counties'] }} {{ $r['counties'] === 1 ? 'county' : 'counties' }}</span>@endif</span>
                            <span class="whitespace-nowrap tabular-nums {{ $r['after'] > $max ? 'text-danger' : '' }}">{{ $r['before'] }} → {{ $r['after'] }}</span>
                        </li>
                    @endforeach
                </ul>
                @if ($plan['still_over']->isNotEmpty())
                    <p class="mt-3 rounded-lg bg-danger-bg px-3 py-2 text-xs text-danger">
                        The team has no room left: {{ $plan['still_over']->count() }} {{ $plan['still_over']->count() === 1 ? 'RM stays' : 'RMs stay' }} over {{ $max }}.
                        Add RMs to this team (Supervisors tab), then balance again.
                    </p>
                @endif
            </section>
        </div>

        @if ($plan['moves'] !== [])
            <form method="POST" action="{{ route('admin.allocation.balance.apply', $supervisor) }}" class="flex justify-end gap-3"
                onsubmit="return confirm('Move {{ count($plan['moves']) }} client(s) as listed? Each move is recorded in the audit log.')">
                @csrf
                <a href="{{ route('admin.allocation.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Apply these moves</button>
            </form>
        @endif
    @endif
</x-layout>
