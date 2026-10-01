<x-layout title="Lot 2 Revenue">
    <x-page-header
        title="Lot 2 Revenue"
        subtitle="Waste disposal collections - confirm Westport's contract-priced revenue before RM commission can be earned on it."
        :back="route('admin.dashboard')"
        back-label="Back to admin"
    />

    @if (session('status'))
        <div class="mb-6 rounded-lg bg-brand-50 border border-brand-600/30 text-brand-800 text-sm px-4 py-3">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-lg bg-red-50 border border-red-300 text-red-800 text-sm px-4 py-3">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="mb-6 flex gap-2">
        @foreach ([\App\Models\LsoLot::STATUS_PENDING => 'Pending', \App\Models\LsoLot::STATUS_CONFIRMED => 'Confirmed', \App\Models\LsoLot::STATUS_REJECTED => 'Rejected'] as $value => $label)
            <a href="{{ route('admin.lso-lots.index', ['status' => $value]) }}"
                class="px-4 py-2 rounded-md text-sm font-medium transition-colors {{ $status === $value ? 'bg-brand-700 text-white' : 'bg-panel border border-border text-ink-muted hover:bg-panel-muted' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-brand-50 text-left text-ink-faint">
                <tr>
                    <th class="px-4 py-2 font-medium">Date</th>
                    <th class="px-4 py-2 font-medium">RM</th>
                    <th class="px-4 py-2 font-medium">Entity</th>
                    <th class="px-4 py-2 font-medium">Category</th>
                    <th class="px-4 py-2 font-medium text-right">Quantity</th>
                    <th class="px-4 py-2 font-medium text-right">Expected Revenue</th>
                    @if ($status !== \App\Models\LsoLot::STATUS_PENDING)
                        <th class="px-4 py-2 font-medium text-right">{{ $status === \App\Models\LsoLot::STATUS_CONFIRMED ? 'Confirmed Revenue' : 'Reviewed By' }}</th>
                    @endif
                    <th class="px-4 py-2 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($lots as $lot)
                    <tr class="hover:bg-panel-muted transition-colors">
                        <td class="px-4 py-3 whitespace-nowrap text-ink-faint">{{ $lot->collection?->collection_date?->format('d M Y') }}</td>
                        <td class="px-4 py-3">{{ $lot->collection?->user?->name }}</td>
                        <td class="px-4 py-3 max-w-xs"><div class="line-clamp-2">{{ $lot->collection?->entity_name }}</div></td>
                        <td class="px-4 py-3">{{ $lot->collection?->categoryLabel() }}</td>
                        <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap">{{ number_format($lot->collection?->quantity ?? 0, 1) }} {{ $lot->collection?->unitLabel() }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            @if ($lot->expected_revenue_minor !== null)
                                KES {{ number_format($lot->expected_revenue_minor / 100) }}
                            @else
                                <span class="text-ink-faint text-xs italic">Unpriced</span>
                            @endif
                        </td>
                        @if ($status === \App\Models\LsoLot::STATUS_CONFIRMED)
                            <td class="px-4 py-3 text-right tabular-nums font-medium">
                                KES {{ number_format($lot->confirmed_revenue_minor / 100) }}
                                <span class="block text-xs text-ink-faint font-normal">RM commission KES {{ number_format($lot->rmCommissionMinor() / 100) }}</span>
                            </td>
                        @elseif ($status === \App\Models\LsoLot::STATUS_REJECTED)
                            <td class="px-4 py-3 text-right text-ink-faint text-xs">
                                {{ $lot->confirmedBy?->name }}
                                <span class="block">{{ $lot->confirmed_at?->format('d M Y') }}</span>
                            </td>
                        @endif
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if ($lot->isPending() && $canManageFinance)
                                <form method="POST" action="{{ route('admin.lso-lots.confirm', $lot) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-brand-700 hover:text-brand-900 text-sm font-medium" {{ $lot->expected_revenue_minor === null ? 'disabled' : '' }}>Confirm</button>
                                </form>
                                <form method="POST" action="{{ route('admin.lso-lots.reject', $lot) }}" class="inline ml-3">
                                    @csrf
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium">Reject</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-ink-faint">No {{ $status }} Lot 2 collections.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $lots->links() }}
    </div>
</x-layout>
