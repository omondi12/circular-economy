<x-layout title="Nawiri Treasury">
    <x-page-header
        title="Nawiri Treasury"
        subtitle="Choose the funded Nawiri account used to send every requisition payment."
        :back="route('admin.requisitions.index')"
        back-label="Back to requisitions"
    />

    @if (session('status'))
        <div class="mb-6 rounded-lg border border-brand-600/30 bg-brand-50 px-4 py-3 text-sm text-brand-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-6 rounded-xl border border-border bg-panel p-5 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Current source</p>
                @if ($credential)
                    <p class="mt-1 text-base font-semibold text-ink">Saved treasury account</p>
                    <p class="mt-1 text-sm text-ink-muted">{{ $credential->email }}</p>
                @else
                    <p class="mt-1 text-base font-semibold text-danger">Treasury account required</p>
                    <p class="mt-1 text-sm text-ink-muted">Add and verify the funded Nawiri account before making payroll payments.</p>
                @endif
            </div>

            @if ($credential?->verified_at)
                <span class="inline-flex self-start shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border border-brand-600/20 bg-brand-50 px-3 py-2 text-xs font-semibold leading-none text-brand-800">
                    <x-icon name="circle-check" size="14" />
                    Credentials verified {{ $credential->verified_at->diffForHumans() }}
                </span>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('admin.nawiri-treasury.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="overflow-hidden rounded-xl border border-border bg-panel shadow-sm">
            <div class="border-b border-border px-5 py-4">
                <h2 class="text-sm font-semibold text-ink">Treasury account credentials</h2>
                <p class="mt-1 text-sm text-ink-muted">
                    The password and PIN are encrypted before they are stored. They are never shown again.
                </p>
            </div>

            <div class="grid grid-cols-1 border-b border-border sm:grid-cols-[220px_1fr]">
                <label for="email" class="flex items-center bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted">
                    Nawiri email <span class="ml-1 text-danger">*</span>
                </label>
                <div class="flex flex-col justify-center px-4 py-2">
                    <input
                        id="email" name="email" type="email" required autocomplete="username"
                        value="{{ old('email', $credential?->email) }}"
                        class="w-full border-0 bg-transparent px-0 py-1.5 text-sm text-ink placeholder:text-ink-faint focus:ring-0"
                        placeholder="treasury@example.com"
                    >
                    @error('email')
                        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 border-b border-border sm:grid-cols-[220px_1fr]">
                <label for="password" class="flex items-center bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted">
                    Nawiri password @unless ($credential)<span class="ml-1 text-danger">*</span>@endunless
                </label>
                <div class="flex flex-col justify-center px-4 py-2">
                    <input
                        id="password" name="password" type="password" autocomplete="new-password"
                        @required(! $credential)
                        class="w-full border-0 bg-transparent px-0 py-1.5 text-sm text-ink placeholder:text-ink-faint focus:ring-0"
                        placeholder="{{ $credential ? 'Leave blank to keep the saved password' : 'Enter the Nawiri account password' }}"
                    >
                    @error('password')
                        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr]">
                <label for="pin" class="flex items-center bg-gold-50 px-4 py-3 text-sm font-semibold text-ink-muted">
                    Nawiri transaction PIN @unless ($credential)<span class="ml-1 text-danger">*</span>@endunless
                </label>
                <div class="flex flex-col justify-center px-4 py-2">
                    <input
                        id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" autocomplete="off"
                        @required(! $credential)
                        class="w-full border-0 bg-transparent px-0 py-1.5 text-sm text-ink placeholder:text-ink-faint focus:ring-0"
                        placeholder="{{ $credential ? 'Leave blank to keep the saved PIN' : 'Enter the Nawiri transaction PIN' }}"
                    >
                    @error('pin')
                        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Saving checks the email, password, admin access and PIN with Nawiri. This check does not move any money. Changing the treasury account affects future payments only.
        </div>

        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-brand-700 px-6 py-3 text-sm font-semibold text-white shadow-sm shadow-brand-900/20 transition-colors hover:bg-brand-800">
                Verify and save treasury account
            </button>
        </div>
    </form>
</x-layout>
