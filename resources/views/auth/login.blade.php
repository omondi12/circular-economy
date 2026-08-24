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

    <div class="min-h-[calc(100vh-6px)] flex items-center justify-center p-4 sm:p-6">
        <div class="w-full max-w-4xl rounded-2xl overflow-hidden shadow-2xl shadow-[#0f7a3d]/20 grid grid-cols-1 md:grid-cols-5 bg-white">

            {{-- Branding panel --}}
            <div class="relative md:col-span-2 bg-gradient-to-br from-[#0f7a3d] via-[#177a44] to-[#c98500] px-8 py-10 sm:py-12 flex flex-col justify-between overflow-hidden">
                <div class="absolute -top-14 -right-14 w-56 h-56 rounded-full bg-white/10 blur-2xl"></div>
                <div class="absolute -bottom-16 -left-10 w-56 h-56 rounded-full bg-white/10 blur-2xl"></div>

                <div class="relative">
                    <div class="w-14 h-14 rounded-2xl bg-white/15 ring-1 ring-white/30 flex items-center justify-center backdrop-blur-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8" class="w-7 h-7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5M16.5 3.5a3 3 0 0 1 3 3v1M20.5 9.5v3a3 3 0 0 1-3 3M13.5 20.5h-3a3 3 0 0 1-3-3v-1" />
                        </svg>
                    </div>
                    <h1 class="text-2xl font-semibold text-white mt-6 leading-tight">AMAC Circular<br>Economy Tracker</h1>
                    <p class="text-sm text-white/80 mt-3 max-w-xs">
                        Sign in to record collections and manage Relationship Manager accounts.
                    </p>
                </div>

                <div class="relative hidden sm:block space-y-3 mt-10">
                    <div class="flex items-center gap-2.5 text-white/85 text-sm">
                        <span class="w-1.5 h-1.5 rounded-full bg-white/70 shrink-0"></span>
                        Lot 1 &amp; Lot 2 disposal tracking
                    </div>
                    <div class="flex items-center gap-2.5 text-white/85 text-sm">
                        <span class="w-1.5 h-1.5 rounded-full bg-white/70 shrink-0"></span>
                        Per-RM submission history
                    </div>
                    <div class="flex items-center gap-2.5 text-white/85 text-sm">
                        <span class="w-1.5 h-1.5 rounded-full bg-white/70 shrink-0"></span>
                        Full audit log for admins
                    </div>
                </div>
            </div>

            {{-- Form panel --}}
            <div class="md:col-span-3 px-8 py-10 sm:px-12 sm:py-12 flex flex-col justify-center">
                <h2 class="text-lg font-semibold text-neutral-900">Log In</h2>
                <p class="text-sm text-neutral-500 mt-1 mb-6">Enter your Relationship Manager or Admin credentials.</p>

                @if ($errors->any())
                    <div class="mb-5 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="block text-xs font-medium text-neutral-500 mb-1.5">Email</label>
                        <input
                            type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                            placeholder="you@example.com"
                            class="w-full rounded-lg border border-neutral-300 text-sm px-3.5 py-2.5 placeholder:text-neutral-300 focus:border-[#0f7a3d] focus:ring-1 focus:ring-[#0f7a3d] outline-none transition-colors"
                        >
                    </div>

                    <div>
                        <label for="password" class="block text-xs font-medium text-neutral-500 mb-1.5">Password</label>
                        <input
                            type="password" id="password" name="password" required
                            placeholder="••••••••"
                            class="w-full rounded-lg border border-neutral-300 text-sm px-3.5 py-2.5 placeholder:text-neutral-300 focus:border-[#0f7a3d] focus:ring-1 focus:ring-[#0f7a3d] outline-none transition-colors"
                        >
                    </div>

                    <label class="flex items-center gap-2 text-sm text-neutral-600">
                        <input type="checkbox" name="remember" class="rounded border-neutral-300 text-[#0f7a3d] focus:ring-[#0f7a3d]">
                        Remember me
                    </label>

                    <button type="submit" class="w-full px-4 py-3 rounded-lg bg-[#0f7a3d] hover:bg-[#0b5c2e] text-white text-sm font-semibold transition-colors shadow-sm shadow-[#0f7a3d]/30">
                        Log In
                    </button>
                </form>

                <p class="text-center text-xs text-neutral-400 mt-8">
                    <a href="{{ route('dashboard') }}" class="hover:text-neutral-600 transition-colors">← Back to public dashboard</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
