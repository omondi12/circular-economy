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
                <x-form-field label="Nawiri Phone Number" name="phone_number" type="tel" :value="$editedUser->phone_number" placeholder="0712345678" required />
                <x-form-field label="New Password" name="password" type="password" placeholder="Leave blank to keep the current password" />

                @if (auth()->user()->isAdmin())
                    <div class="px-4 py-3 border-t border-border">
                        <fieldset>
                        <legend class="block text-sm font-medium text-ink-muted mb-2">Role</legend>
                        <div class="flex flex-wrap gap-x-5 gap-y-1">
                            <label class="inline-flex items-center gap-2 text-sm min-h-10 cursor-pointer">
                                <input type="radio" name="role" value="rm" class="text-brand-700 focus:ring-brand-600" @checked($editedUser->isRm())>
                                Relationship Manager
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm min-h-10 cursor-pointer">
                                <input type="radio" name="role" value="supervisor" class="text-brand-700 focus:ring-brand-600" @checked($editedUser->isSupervisor())>
                                Supervisor
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm min-h-10 cursor-pointer">
                                <input type="radio" name="role" value="office_admin" class="text-brand-700 focus:ring-brand-600" @checked($editedUser->isOfficeAdmin())>
                                Office Admin
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm min-h-10 cursor-pointer">
                                <input type="radio" name="role" value="operations" class="text-brand-700 focus:ring-brand-600" @checked($editedUser->isOperations())>
                                Operations
                            </label>
                        </div>
                        </fieldset>
                        @if ($editedUser->isRm())
                            <p class="text-xs text-ink-faint mt-2">Switching this account away from RM will free up any clients or ministries currently assigned to them.</p>
                        @elseif ($editedUser->isSupervisor())
                            <p class="text-xs text-ink-faint mt-2">Switching this account away from Supervisor will unassign any RMs currently reporting to them.</p>
                        @endif
                    </div>

                    <div class="px-4 py-3 border-t border-border">
                        <label for="supervisor_id" class="block text-sm font-medium text-ink-muted mb-2">Supervisor (if this is an RM)</label>
                        <select
                            id="supervisor_id" name="supervisor_id"
                            class="field-control"
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
                <button type="submit" class="btn btn-primary">
                    Save Changes
                </button>
            </div>
        </form>
</x-layout>
