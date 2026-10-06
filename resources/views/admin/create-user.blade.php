<x-layout title="New Account">
        <x-page-header
            title="New Account"
            :subtitle="auth()->user()->isAdmin() ? 'Create login credentials for a Relationship Manager, Supervisor or Office Admin.' : 'Create login credentials for a new RM on your team.'"
            :back="route('admin.users')"
            back-label="Back to accounts"
        />

        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-6">
            @csrf

            <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
                <x-form-field label="Full Name" name="name" required />
                <x-form-field label="Email" name="email" type="email" required />
                <x-form-field label="Nawiri Phone Number" name="phone_number" type="tel" placeholder="0712345678" required />
                <x-form-field label="Password" name="password" type="password" required />

                @if (auth()->user()->isAdmin())
                    <div class="px-4 py-3 border-t border-border">
                        <fieldset>
                        <legend class="block text-sm font-medium text-ink-muted mb-2">Role</legend>
                        <div class="flex flex-wrap gap-x-5 gap-y-1">
                            <label class="inline-flex items-center gap-2 text-sm min-h-10 cursor-pointer">
                                <input type="radio" name="role" value="rm" checked class="text-brand-700 focus:ring-brand-600">
                                Relationship Manager
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm min-h-10 cursor-pointer">
                                <input type="radio" name="role" value="supervisor" class="text-brand-700 focus:ring-brand-600">
                                Supervisor
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm min-h-10 cursor-pointer">
                                <input type="radio" name="role" value="office_admin" class="text-brand-700 focus:ring-brand-600">
                                Office Admin
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm min-h-10 cursor-pointer">
                                <input type="radio" name="role" value="operations" class="text-brand-700 focus:ring-brand-600">
                                Operations
                            </label>
                        </div>
                        </fieldset>
                    </div>

                    <div class="px-4 py-3 border-t border-border">
                        <label for="supervisor_id" class="block text-sm font-medium text-ink-muted mb-2">Supervisor (if creating an RM)</label>
                        <select
                            id="supervisor_id" name="supervisor_id"
                            class="field-control"
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
                <button type="submit" class="btn btn-primary">
                    Create Account
                </button>
            </div>
        </form>
</x-layout>
