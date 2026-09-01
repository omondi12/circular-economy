<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — Westport Industrial City</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="bg-canvas text-ink antialiased font-sans min-h-screen">
    <div class="h-1 w-full bg-gradient-to-r from-brand-600 via-brand-400 to-gold-500"></div>

    <div class="min-h-[calc(100vh-4px)] flex items-center justify-center p-4 sm:p-6">
        <div class="w-full max-w-4xl rounded-2xl overflow-hidden shadow-2xl shadow-brand-900/10 grid grid-cols-1 md:grid-cols-5 bg-panel fade-rise">

            {{-- Branding panel --}}
            <div class="relative md:col-span-2 bg-gradient-to-br from-brand-700 via-brand-800 to-brand-900 px-8 py-10 sm:py-12 flex flex-col justify-between overflow-hidden">
                <svg class="absolute -top-14 -right-14 w-56 h-56 opacity-[0.12] loop-spin" viewBox="0 0 32 32" style="animation-duration: 30s">
                    <path d="M16 4 A12 12 0 0 1 27.8 14" fill="none" stroke="white" stroke-width="1.6" stroke-linecap="round"/>
                    <path d="M16 28 A12 12 0 0 1 4.2 18" fill="none" stroke="white" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                <div class="absolute -bottom-16 -left-10 w-56 h-56 rounded-full bg-gold-500/10 blur-2xl"></div>

                <div class="relative">
                    <div class="w-14 h-14 rounded-2xl bg-white/15 ring-1 ring-white/30 flex items-center justify-center backdrop-blur-sm">
                        <x-icon name="recycle" size="26" class="text-white" />
                    </div>
                    <h1 class="font-display italic text-3xl text-white mt-6 leading-tight">Westport<br>Industrial City</h1>
                    <p class="text-sm text-white/75 mt-3 max-w-xs">
                        {{ __('Sign in to record collections and manage Relationship Manager accounts.') }}
                    </p>
                </div>

                <div class="relative hidden sm:block space-y-3 mt-10">
                    <div class="flex items-center gap-2.5 text-white/85 text-sm">
                        <x-icon name="circle-check" size="16" class="text-gold-300" />
                        {{ __('Lot 1 & Lot 2 disposal tracking') }}
                    </div>
                    <div class="flex items-center gap-2.5 text-white/85 text-sm">
                        <x-icon name="circle-check" size="16" class="text-gold-300" />
                        {{ __('Per-RM submission history') }}
                    </div>
                    <div class="flex items-center gap-2.5 text-white/85 text-sm">
                        <x-icon name="circle-check" size="16" class="text-gold-300" />
                        {{ __('Full audit log for admins') }}
                    </div>
                </div>
            </div>

            {{-- Form panel --}}
            <div class="md:col-span-3 px-8 py-10 sm:px-12 sm:py-12 flex flex-col justify-center">
                <h2 class="font-display italic text-2xl text-ink">{{ __('Log in') }}</h2>
                <p class="text-sm text-ink-muted mt-1 mb-6">{{ __('Enter your Relationship Manager or Admin credentials.') }}</p>

                @if ($errors->any())
                    <div class="mb-5 rounded-lg bg-danger-bg border border-danger/20 text-danger text-sm px-4 py-3 flex items-center gap-2">
                        <x-icon name="alert-triangle" />
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="block text-xs font-medium text-ink-faint mb-1.5 uppercase tracking-wide font-mono">{{ __('Email') }}</label>
                        <input
                            type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                            placeholder="you@example.com"
                            class="w-full rounded-lg border border-border text-sm px-3.5 py-2.5 placeholder:text-ink-faint focus:border-brand-600 focus:ring-4 focus:ring-brand-600/10 outline-none shadow-sm transition-shadow"
                        >
                    </div>

                    <div>
                        <label for="password" class="block text-xs font-medium text-ink-faint mb-1.5 uppercase tracking-wide font-mono">{{ __('Password') }}</label>
                        <input
                            type="password" id="password" name="password" required
                            placeholder="••••••••"
                            class="w-full rounded-lg border border-border text-sm px-3.5 py-2.5 placeholder:text-ink-faint focus:border-brand-600 focus:ring-4 focus:ring-brand-600/10 outline-none shadow-sm transition-shadow"
                        >
                    </div>

                    <label class="flex items-center gap-2 text-sm text-ink-muted">
                        <input type="checkbox" name="remember" class="rounded border-border text-brand-700 focus:ring-brand-600">
                        {{ __('Remember me') }}
                    </label>

                    <button type="submit" class="w-full px-4 py-3 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold transition-colors shadow-sm shadow-brand-900/20 flex items-center justify-center gap-2 group">
                        {{ __('Log in') }}
                        <x-icon name="arrow-right" class="text-sm transition-transform group-hover:translate-x-0.5" />
                    </button>
                </form>

                <p class="text-center text-xs text-ink-faint mt-8">
                    <a href="{{ route('dashboard') }}" class="hover:text-ink-muted transition-colors inline-flex items-center gap-1">
                        <x-icon name="arrow-left" size="13" />
                        {{ __('Back to public dashboard') }}
                    </a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
