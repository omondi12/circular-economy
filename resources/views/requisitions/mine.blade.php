<x-layout title="My Requisitions">
        @if (session('status'))
            <div class="mb-6 rounded-lg bg-brand-50 border border-brand-600/30 text-brand-800 text-sm px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex items-center justify-between mb-6">
            <x-page-header
                title="My Requisitions"
                subtitle="Daily transport and airtime requests - only admins can approve or decline."
            />
            <button
                type="button" x-data @click="$dispatch('open-new-requisition')"
                class="shrink-0 inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold transition-colors shadow-sm shadow-brand-900/20 -mt-10"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                New Request
            </button>
        </div>

        <div
            x-data="{ open: false }"
            x-on:open-new-requisition.window="open = true"
            x-show="open"
            x-cloak
            class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm mb-8"
        >
            <div class="bg-gradient-to-r from-brand-700 to-brand-500 px-4 py-2.5 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-white uppercase tracking-wide">New Request</h2>
                <button type="button" @click="open = false" class="text-white/80 hover:text-white">
                    <x-icon name="x" size="16" />
                </button>
            </div>

            <form method="POST" action="{{ route('requisitions.store') }}">
                @csrf

                <div
                    class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border"
                    x-data="{ institutions: {{ old('institutions') ? json_encode(old('institutions')) : '[""]' }} }"
                >
                    <label class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-start sm:items-center">
                        Institution(s) Visiting <span class="text-danger ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center gap-2">
                        <template x-for="(institution, i) in institutions" :key="i">
                            <div class="flex items-center gap-2">
                                <input
                                    type="text" :name="'institutions[' + i + ']'" required
                                    x-model="institutions[i]"
                                    placeholder="e.g. Kenya Revenue Authority"
                                    class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink placeholder:text-ink-faint"
                                >
                                <button
                                    type="button" x-show="institutions.length > 1" x-cloak
                                    @click="institutions.splice(i, 1)"
                                    class="shrink-0 text-ink-faint hover:text-danger"
                                >
                                    <x-icon name="x" size="14" />
                                </button>
                            </div>
                        </template>
                        <button
                            type="button" @click="institutions.push('')"
                            class="self-start text-xs font-medium text-brand-700 hover:text-brand-800"
                        >
                            + Add another institution
                        </button>
                        @error('institutions')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                        @error('institutions.*')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                    <label for="working_day" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                        Working Day <span class="text-danger ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <input
                            type="date" id="working_day" name="working_day" required
                            value="{{ old('working_day', now()->addDay()->toDateString()) }}"
                            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink"
                        >
                        @error('working_day')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

				<div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
					<label for="recipient_phone_number" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-start sm:items-center">
						Nawiri Phone Number <span class="text-danger ml-1">*</span>
					</label>
					<div class="px-4 py-2 flex flex-col justify-center gap-2">
						<input
							type="tel" id="recipient_phone_number" name="recipient_phone_numbers[]" required
							value="{{ old('recipient_phone_numbers.0', auth()->user()->phone_number) }}"
							placeholder="e.g. 0712345678"
							class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink placeholder:text-ink-faint"
						>
						@if (auth()->user()->phone_number)
							<p class="text-xs text-ink-faint">Auto-filled from your profile - you're requesting for yourself.</p>
						@else
							<p class="text-xs text-ink-faint">
								Set your Nawiri number once in <a href="{{ route('account.profile.edit') }}" class="text-brand-700 underline hover:text-brand-800">My Profile</a> and it'll auto-fill here next time.
							</p>
						@endif
						@error('recipient_phone_numbers')
							<p class="text-xs text-danger">{{ $message }}</p>
						@enderror
						@error('recipient_phone_numbers.*')
							<p class="text-xs text-danger">{{ $message }}</p>
						@enderror
					</div>
				</div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] border-b border-border">
                    <label for="transport_amount_requested" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                        Transport per recipient <span class="text-danger ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <input
                            type="number" step="0.01" min="0" id="transport_amount_requested" name="transport_amount_requested" required
                            value="{{ old('transport_amount_requested', $defaultTransport) }}"
                            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink"
                        >
                        <p class="text-xs text-ink-faint">Each Nawiri number receives this amount.</p>
                        @error('transport_amount_requested')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr]">
                    <label for="airtime_amount_requested" class="bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted flex items-center">
                        Airtime per recipient <span class="text-danger ml-1">*</span>
                    </label>
                    <div class="px-4 py-2 flex flex-col justify-center">
                        <input
                            type="number" step="0.01" min="0" id="airtime_amount_requested" name="airtime_amount_requested" required
                            value="{{ old('airtime_amount_requested', $defaultAirtime) }}"
                            class="w-full border-0 focus:ring-0 text-sm py-1.5 px-0 text-ink"
                        >
                        <p class="text-xs text-ink-faint">Each Nawiri number receives this amount.</p>
                        @error('airtime_amount_requested')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="px-4 py-4 flex justify-end border-t border-border">
                    <button type="submit" class="px-6 py-2.5 rounded-md bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold transition-colors shadow-sm shadow-brand-900/20">
                        Submit Request
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-brand-50 text-left text-ink-faint">
                    <tr>
                        <th class="px-4 py-2 font-medium">Working Day</th>
                        <th class="px-4 py-2 font-medium">Institution</th>
                        <th class="px-4 py-2 font-medium">Transport</th>
                        <th class="px-4 py-2 font-medium">Airtime</th>
                        <th class="px-4 py-2 font-medium">Total for Day</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($requisitions as $req)
                        <tr class="hover:bg-panel-muted transition-colors align-top">
                            <td class="px-4 py-3 whitespace-nowrap text-ink-muted">{{ $req->working_day->format('D, d M Y') }}</td>
                            <td class="px-4 py-3 font-medium max-w-xs">
                                <div class="line-clamp-2">{{ $req->institution_visiting }}</div>
                                <div class="mt-1 text-xs font-normal text-ink-faint">Nawiri: {{ implode(', ', $req->recipientPhoneNumbers()) }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <x-requisition-status-badge :status="$req->transport_status" />
                                <div class="text-xs text-ink-faint mt-1 tabular-nums">
                                    KES {{ number_format($req->transport_amount_requested, 0) }}{{ $req->recipientCount() > 1 ? ' each' : '' }}
                                    @if ($req->recipientCount() > 1)
                                        · {{ number_format($req->categoryTotalRequested('transport'), 0) }} total
                                    @endif
                                    @if ($req->transport_status === 'approved')
                                        · paid {{ number_format($req->transport_paid_amount, 0) }}
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <x-requisition-status-badge :status="$req->airtime_status" />
                                <div class="text-xs text-ink-faint mt-1 tabular-nums">
                                    KES {{ number_format($req->airtime_amount_requested, 0) }}{{ $req->recipientCount() > 1 ? ' each' : '' }}
                                    @if ($req->recipientCount() > 1)
                                        · {{ number_format($req->categoryTotalRequested('airtime'), 0) }} total
                                    @endif
                                    @if ($req->airtime_status === 'approved')
                                        · paid {{ number_format($req->airtime_paid_amount, 0) }}
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap font-medium tabular-nums">KES {{ number_format($req->totalRequestedForDay(), 0) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-ink-faint">No requests yet - use "New Request" above.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $requisitions->links() }}
        </div>
</x-layout>
