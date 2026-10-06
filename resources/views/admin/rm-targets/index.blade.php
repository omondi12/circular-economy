<x-layout title="RM Targets">
    <x-page-header
        title="RM Targets"
        subtitle="Monetary or count targets per RM, per explicit period."
        :back="route('admin.dashboard')"
        back-label="Back to admin"
    />


    @if ($errors->any())
        <div class="mb-6 rounded-lg bg-red-50 border border-red-300 text-red-800 text-sm px-4 py-3">
            {{ $errors->first() }}
        </div>
    @endif

    @if ($canManage)
        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm mb-6">
            <div class="bg-gradient-to-r from-brand-700 to-brand-500 px-4 py-2.5">
                <h2 class="text-sm font-semibold text-white uppercase tracking-wide">Set a New Target</h2>
            </div>
            <form method="POST" action="{{ route('admin.rm-targets.store') }}" class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-4 items-end" x-data="{ type: 'monetary' }">
                @csrf
                <div>
                    <label for="user_id" class="block text-xs font-semibold text-ink-muted mb-1">RM</label>
                    <select id="user_id" name="user_id" required class="field-control">
                        @foreach ($rms as $rm)
                            <option value="{{ $rm->id }}">{{ $rm->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="type" class="block text-xs font-semibold text-ink-muted mb-1">Target Type</label>
                    <select id="type" name="type" x-model="type" class="field-control">
                        <option value="monetary">Monetary (KES)</option>
                        <option value="count">Count (fully paid LSOs)</option>
                    </select>
                </div>
                <div x-show="type === 'monetary'">
                    <label for="target_amount" class="block text-xs font-semibold text-ink-muted mb-1">Target Amount (KES)</label>
                    <input type="number" min="1" step="1" id="target_amount" name="target_amount" class="field-control">
                </div>
                <div x-show="type === 'count'" x-cloak>
                    <label for="target_count" class="block text-xs font-semibold text-ink-muted mb-1">Target Count</label>
                    <input type="number" min="1" step="1" id="target_count" name="target_count" class="field-control">
                </div>
                <div>
                    <label for="period_start" class="block text-xs font-semibold text-ink-muted mb-1">Period Start</label>
                    <input type="date" id="period_start" name="period_start" required class="field-control">
                </div>
                <div>
                    <label for="period_end" class="block text-xs font-semibold text-ink-muted mb-1">Period End</label>
                    <input type="date" id="period_end" name="period_end" required class="field-control">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary w-full">
                        Set Target
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
        <table data-stack class="w-full text-sm">
            <thead class="bg-brand-50 text-left text-ink-faint">
                <tr>
                    <th class="px-4 py-2 font-medium">RM</th>
                    <th class="px-4 py-2 font-medium">Type</th>
                    <th class="px-4 py-2 font-medium">Period</th>
                    <th class="px-4 py-2 font-medium text-right">Target</th>
                    <th class="px-4 py-2 font-medium text-right">Actual</th>
                    <th class="px-4 py-2 font-medium text-right">Achievement</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($targets as $target)
                    <tr class="hover:bg-panel-muted transition-colors">
                        <td class="px-4 py-3 font-medium">{{ $target->user?->name }}</td>
                        <td class="px-4 py-3 capitalize">{{ $target->type }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-ink-faint">{{ $target->period_start->format('d M Y') }} – {{ $target->period_end->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ $target->type === \App\Models\RmTarget::TYPE_MONETARY ? 'KES '.number_format($target->targetValue() / 100) : number_format($target->targetValue()) }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ $target->type === \App\Models\RmTarget::TYPE_MONETARY ? 'KES '.number_format($target->actual() / 100) : number_format($target->actual()) }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold">{{ number_format($target->achievementPercent(), 1) }}%</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6"><x-empty-state title="No targets set yet." /></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $targets->links() }}
    </div>
</x-layout>
