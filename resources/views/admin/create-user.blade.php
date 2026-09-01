<x-layout title="New RM Account">
        <x-page-header
            title="New RM Account"
            subtitle="Create login credentials for a Relationship Manager."
            :back="route('admin.users')"
            back-label="Back to RMs"
        />

        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-6">
            @csrf

            <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm">
                <x-form-field label="Full Name" name="name" required />
                <x-form-field label="Email" name="email" type="email" required />
                <x-form-field label="Password" name="password" type="password" required />
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-6 py-3 rounded-md bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold transition-colors shadow-sm shadow-brand-900/20">
                    Create Account
                </button>
            </div>
        </form>
</x-layout>
