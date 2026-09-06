<x-layout title="Edit Account">
        <x-page-header
            title="Edit Account"
            :subtitle="'Update '.$editedUser->name.'\'s details.'"
            :back="route('admin.users')"
            back-label="Back to accounts"
        />

        <form method="POST" action="{{ route('admin.users.update', $editedUser) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
                <x-form-field label="Full Name" name="name" :value="$editedUser->name" required />
                <x-form-field label="Email" name="email" type="email" :value="$editedUser->email" required />
                <x-form-field label="New Password" name="password" type="password" placeholder="Leave blank to keep the current password" />

                @if (auth()->user()->isAdmin())
                    <div class="px-4 py-3 border-t border-border">
                        <label class="block text-sm font-medium text-ink-muted mb-2">Role</label>
                        <div class="flex gap-4">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="radio" name="role" value="rm" class="text-brand-700 focus:ring-brand-600" @checked($editedUser->isRm())>
                                Relationship Manager
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="radio" name="role" value="supervisor" class="text-brand-700 focus:ring-brand-600" @checked($editedUser->isSupervisor())>
                                Supervisor
                            </label>
                        </div>
                        @if ($editedUser->isRm())
                            <p class="text-xs text-ink-faint mt-2">Switching this account to Supervisor will free up any clients or ministries currently assigned to them as an RM.</p>
                        @else
                            <p class="text-xs text-ink-faint mt-2">Switching this account to RM will unassign any RMs currently reporting to them.</p>
                        @endif
                    </div>

                    <div class="px-4 py-3 border-t border-border">
                        <label for="supervisor_id" class="block text-sm font-medium text-ink-muted mb-2">Supervisor (if this is an RM)</label>
                        <select
                            id="supervisor_id" name="supervisor_id"
                            class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand-600/30 focus:border-brand-600"
                        >
                            <option value="">— Unassigned —</option>
                            @foreach ($supervisors as $supervisor)
                                <option value="{{ $supervisor->id }}" @selected($editedUser->supervisor_id === $supervisor->id)>{{ $supervisor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-6 py-3 rounded-md bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold transition-colors shadow-sm shadow-brand-900/20">
                    Save Changes
                </button>
            </div>
        </form>
</x-layout>
