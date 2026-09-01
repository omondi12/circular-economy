<x-layout title="Audit Log">
        <x-page-header
            title="Audit Log"
            :subtitle="number_format($entries->total()).' recorded action(s).'"
            :back="route('admin.dashboard')"
            back-label="Back to admin"
        />

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.audit-log') }}" class="mb-6 bg-panel border border-border rounded-xl p-4 shadow-sm flex flex-wrap gap-3 items-end">
            <div class="flex flex-col gap-1">
                <label for="action" class="text-xs text-ink-faint">Action</label>
                <select id="action" name="action" class="rounded-md border-border text-sm focus:border-brand-600 focus:ring-brand-600">
                    <option value="">All actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected($filters['action'] === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label for="user_id" class="text-xs text-ink-faint">Who</label>
                <select id="user_id" name="user_id" class="rounded-md border-border text-sm focus:border-brand-600 focus:ring-brand-600">
                    <option value="">Everyone</option>
                    @foreach ($rms as $rm)
                        <option value="{{ $rm->id }}" @selected((string) $filters['user_id'] === (string) $rm->id)>{{ $rm->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 rounded-md bg-brand-700 hover:bg-brand-800 text-white text-sm font-medium transition-colors shadow-sm shadow-brand-900/20">
                    Filter
                </button>
                <a href="{{ route('admin.audit-log') }}" class="px-4 py-2 rounded-md border border-border text-sm hover:bg-panel-muted transition-colors">
                    Reset
                </a>
            </div>
        </form>

        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-brand-50 text-left text-ink-faint">
                    <tr>
                        <th class="px-4 py-2 font-medium">Who</th>
                        <th class="px-4 py-2 font-medium">Action</th>
                        <th class="px-4 py-2 font-medium">Detail</th>
                        <th class="px-4 py-2 font-medium">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($entries as $entry)
                        <tr>
                            <td class="px-4 py-3 font-medium whitespace-nowrap">{{ $entry->user?->name ?? 'System' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-brand-50 text-brand-800 text-xs font-medium">{{ $entry->action }}</span>
                            </td>
                            <td class="px-4 py-3 text-ink-faint max-w-md">
                                @if ($entry->meta)
                                    <span class="text-xs">{{ collect($entry->meta)->map(fn ($v, $k) => "{$k}: {$v}")->implode(' · ') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-ink-faint">{{ $entry->created_at->format('d M Y, H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-ink-faint">No activity matches these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $entries->links() }}
        </div>
</x-layout>
