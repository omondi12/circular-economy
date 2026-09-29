<x-layout :title="'LSO '.$lso->reference_number">
    <x-page-header
        :title="'LSO '.$lso->reference_number"
        :subtitle="$lso->customer_name"
        :back="auth()->user()->isRm() ? route('rm.lsos.index') : route('admin.lsos.index')"
        :back-label="auth()->user()->isRm() ? 'Back to my LSOs' : 'Back to LSOs'"
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

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
        <x-stat-tile label="Original Value" :value="'KES '.number_format($lso->original_amount_minor / 100)" icon="scale" tone="gold" />
        <x-stat-tile label="Confirmed Collected" :value="'KES '.number_format($lso->confirmedCollectedMinor() / 100)" icon="stamp" tone="teal" />
        <x-stat-tile label="Outstanding" :value="'KES '.number_format($lso->outstandingMinor() / 100)" icon="building" tone="violet" />
        <x-stat-tile label="Status" :value="ucfirst(str_replace('_', ' ', $lso->status))" icon="document" tone="green" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
                <div class="px-5 py-4 border-b border-border">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-ink-muted">Payment History</h2>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-brand-50 text-left text-ink-faint">
                        <tr>
                            <th class="px-4 py-2 font-medium">Date</th>
                            <th class="px-4 py-2 font-medium text-right">Amount</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="px-4 py-2 font-medium">Recorded By</th>
                            @if ($canManageFinance)
                                <th class="px-4 py-2 font-medium"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($lso->payments as $payment)
                            <tr class="hover:bg-panel-muted transition-colors">
                                <td class="px-4 py-3 whitespace-nowrap">{{ $payment->collected_at->format('d M Y') }}</td>
                                <td class="px-4 py-3 text-right tabular-nums font-medium">{{ number_format($payment->amount_minor / 100) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @php
                                        $tone = match ($payment->status) {
                                            \App\Models\LsoPayment::STATUS_CONFIRMED => 'bg-brand-50 text-brand-800',
                                            \App\Models\LsoPayment::STATUS_REJECTED, \App\Models\LsoPayment::STATUS_CANCELLED => 'bg-red-50 text-red-700',
                                            default => 'bg-amber-50 text-amber-800',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full {{ $tone }} text-xs font-medium capitalize">{{ $payment->status }}</span>
                                </td>
                                <td class="px-4 py-3 text-ink-faint">{{ $payment->recordedBy?->name }}</td>
                                @if ($canManageFinance)
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        @if ($payment->isPending())
                                            <form method="POST" action="{{ route('admin.lso-payments.confirm', $payment) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="text-brand-700 hover:text-brand-900 text-sm font-medium">Confirm</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.lso-payments.reject', $payment) }}" class="inline ml-3">
                                                @csrf
                                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium">Reject</button>
                                            </form>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManageFinance ? 5 : 4 }}" class="px-4 py-8 text-center text-ink-faint">No payments recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($canRecordPayment && $lso->status !== \App\Models\Lso::STATUS_CANCELLED)
                <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
                    <div class="bg-gradient-to-r from-brand-700 to-brand-500 px-4 py-2.5">
                        <h2 class="text-sm font-semibold text-white uppercase tracking-wide">Record a Payment</h2>
                    </div>
                    <form method="POST" action="{{ route('rm.lsos.payments.store', $lso) }}" class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                        @csrf
                        <div>
                            <label for="amount" class="block text-xs font-semibold text-ink-muted mb-1">Amount Collected (KES)</label>
                            <input type="number" min="1" step="1" id="amount" name="amount" required class="w-full rounded-lg border border-border text-sm px-3 py-2">
                        </div>
                        <div>
                            <label for="collected_at" class="block text-xs font-semibold text-ink-muted mb-1">Date Collected</label>
                            <input type="date" id="collected_at" name="collected_at" value="{{ now()->toDateString() }}" required class="w-full rounded-lg border border-border text-sm px-3 py-2">
                        </div>
                        <div>
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-brand-700 text-white text-sm font-semibold hover:bg-brand-800 transition-colors">
                                Record Payment
                            </button>
                        </div>
                        <div class="sm:col-span-3">
                            <label for="notes" class="block text-xs font-semibold text-ink-muted mb-1">Notes</label>
                            <input type="text" id="notes" name="notes" class="w-full rounded-lg border border-border text-sm px-3 py-2" placeholder="Optional">
                        </div>
                    </form>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
                <div class="px-5 py-4 border-b border-border">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-ink-muted">LSO Details</h2>
                </div>
                <dl class="p-5 space-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-ink-faint uppercase tracking-wide">RM</dt>
                        <dd class="text-ink font-medium">{{ $lso->user?->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-ink-faint uppercase tracking-wide">Customer</dt>
                        <dd class="text-ink">{{ $lso->customer_name }}</dd>
                    </div>
                    @if ($lso->customer_contact)
                        <div>
                            <dt class="text-xs text-ink-faint uppercase tracking-wide">Contact</dt>
                            <dd class="text-ink">{{ $lso->customer_contact }}</dd>
                        </div>
                    @endif
                    @if ($lso->ministry)
                        <div>
                            <dt class="text-xs text-ink-faint uppercase tracking-wide">Ministry</dt>
                            <dd class="text-ink">{{ $lso->ministry->name }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs text-ink-faint uppercase tracking-wide">Issue Date</dt>
                        <dd class="text-ink">{{ $lso->issue_date->format('d M Y') }}</dd>
                    </div>
                    @if ($lso->description)
                        <div>
                            <dt class="text-xs text-ink-faint uppercase tracking-wide">Description</dt>
                            <dd class="text-ink">{{ $lso->description }}</dd>
                        </div>
                    @endif
                    @if ($lso->notes)
                        <div>
                            <dt class="text-xs text-ink-faint uppercase tracking-wide">Notes</dt>
                            <dd class="text-ink">{{ $lso->notes }}</dd>
                        </div>
                    @endif
                    @if ($lso->document_path)
                        <div>
                            <dt class="text-xs text-ink-faint uppercase tracking-wide">Evidence</dt>
                            <dd>
                                <a href="{{ route('lso.document.show', $lso) }}" target="_blank" class="inline-flex items-center gap-1.5 text-brand-700 hover:text-brand-900 font-medium">
                                    <x-icon name="file-text" size="15" />
                                    View LSO document
                                </a>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</x-layout>
