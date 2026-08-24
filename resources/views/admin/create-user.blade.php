<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New RM Account - AMAC Circular Economy Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-b from-[#eaf7ee] via-white to-white text-neutral-900 min-h-screen">
    <div class="h-1.5 w-full bg-gradient-to-r from-[#0f7a3d] via-[#1a9650] to-[#c98500]"></div>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-page-header
            title="New RM Account"
            subtitle="Create login credentials for a Relationship Manager."
            :back="route('admin.users')"
            back-label="Back to RMs"
        />

        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-6">
            @csrf

            <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm">
                <x-form-field label="Full Name" name="name" required />
                <x-form-field label="Email" name="email" type="email" required />
                <x-form-field label="Password" name="password" type="password" required />
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-6 py-3 rounded-md bg-[#0f7a3d] hover:bg-[#0b5c2e] text-white text-sm font-semibold transition-colors shadow-sm shadow-[#0f7a3d]/30">
                    Create Account
                </button>
            </div>
        </form>
    </div>
</body>
</html>
