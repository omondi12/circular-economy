<x-layout title="Facilitation">
        <div class="max-w-sm mx-auto mt-12">
            <x-page-header title="Facilitation" subtitle="Enter the PIN to view the transport and airtime facilitation breakdown." />

            @if ($errors->any())
                <div class="mb-4 rounded-lg bg-red-50 border border-danger/30 text-danger text-sm px-4 py-3">
                    {{ $errors->first('pin') }}
                </div>
            @endif

            <form method="POST" action="{{ route('requisitions.public.unlock') }}" class="bg-panel border border-border rounded-xl shadow-sm p-6">
                @csrf
                <label for="pin" class="block text-sm font-semibold text-ink-muted mb-2">PIN</label>
                <input
                    type="text" inputmode="numeric" id="pin" name="pin" autofocus
                    class="w-full text-center tracking-[0.5em] text-2xl rounded-lg border-border py-3 focus:border-brand-600 focus:ring-brand-600"
                    maxlength="6"
                >
                <button type="submit" class="w-full mt-4 px-4 py-3 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold transition-colors shadow-sm">
                    Unlock
                </button>
            </form>
        </div>
</x-layout>
