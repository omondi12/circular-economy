<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - AMAC Circular Economy Tracker</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-b from-[#eaf7ee] via-white to-white text-neutral-900 min-h-screen">
    <div class="h-1.5 w-full bg-gradient-to-r from-[#0f7a3d] via-[#1a9650] to-[#c98500]"></div>

    <div class="max-w-md mx-auto px-4 sm:px-6 py-16">
        <div class="text-center mb-8">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-gradient-to-br from-[#0f7a3d] to-[#0a5228] shadow-lg shadow-[#0f7a3d]/30 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8" class="w-7 h-7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5M16.5 3.5a3 3 0 0 1 3 3v1M20.5 9.5v3a3 3 0 0 1-3 3M13.5 20.5h-3a3 3 0 0 1-3-3v-1" />
                </svg>
            </div>
            <h1 class="text-xl font-semibold text-neutral-900 mt-4">AMAC Circular Economy Tracker</h1>
            <p class="text-sm text-neutral-500 mt-1">Relationship Manager &amp; Admin login</p>
        </div>

        <div class="bg-white border border-neutral-200 rounded-xl shadow-sm p-6">
            @if ($errors->any())
                <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-xs font-medium text-neutral-500 mb-1">Email</label>
                    <input
                        type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                        class="w-full rounded-md border border-neutral-300 text-sm focus:border-[#0f7a3d] focus:ring-[#0f7a3d]"
                    >
                </div>

                <div>
                    <label for="password" class="block text-xs font-medium text-neutral-500 mb-1">Password</label>
                    <input
                        type="password" id="password" name="password" required
                        class="w-full rounded-md border border-neutral-300 text-sm focus:border-[#0f7a3d] focus:ring-[#0f7a3d]"
                    >
                </div>

                <label class="flex items-center gap-2 text-sm text-neutral-600">
                    <input type="checkbox" name="remember" class="rounded border-neutral-300 text-[#0f7a3d] focus:ring-[#0f7a3d]">
                    Remember me
                </label>

                <button type="submit" class="w-full px-4 py-2.5 rounded-md bg-[#0f7a3d] hover:bg-[#0b5c2e] text-white text-sm font-semibold transition-colors shadow-sm shadow-[#0f7a3d]/30">
                    Log In
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-neutral-400 mt-6">
            <a href="{{ route('dashboard') }}" class="hover:text-neutral-600 transition-colors">← Back to public dashboard</a>
        </p>
    </div>
</body>
</html>
