<x-layout title="New Account">
        <x-page-header
            title="New Account"
            :subtitle="auth()->user()->isAdmin() ? 'Create login credentials for a Relationship Manager or Supervisor.' : 'Create login credentials for a new RM on your team.'"
            :back="route('admin.users')"
            back-label="Back to accounts"
        />

        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-6">
            @csrf

            <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
                <x-form-field label="Full Name" name="name" required />
                <x-form-field label="Email" name="email" type="email" required />
                <x-form-field label="Password" name="password" type="password" required />

                @if (auth()->user()->isAdmin())
                    <div class="px-4 py-3 border-t border-border">
                        <label class="block text-sm font-medium text-ink-muted mb-2">Role</label>
                        <div class="flex gap-4">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="radio" name="role" value="rm" checked class="text-brand-700 focus:ring-brand-600">
                                Relationship Manager
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="radio" name="role" value="supervisor" class="text-brand-700 focus:ring-brand-600">
                                Supervisor
                            </label>
                        </div>
                    </div>

                    <div class="px-4 py-3 border-t border-border">
                        <label for="supervisor_id" class="block text-sm font-medium text-ink-muted mb-2">Supervisor (if creating an RM)</label>
                        <select
                            id="supervisor_id" name="supervisor_id"
                            class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand-600/30 focus:border-brand-600"
                        >
                            <option value="">— Unassigned —</option>
                            @foreach ($supervisors as $supervisor)
                                <option value="{{ $supervisor->id }}">{{ $supervisor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-6 py-3 rounded-md bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold transition-colors shadow-sm shadow-brand-900/20">
                    Create Account
                </button>
            </div>
        </form>
</x-layout>
