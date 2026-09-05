@props(['title' => null, 'wide' => false])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' — Westport Industrial City' : 'Westport Industrial City' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="bg-canvas text-ink antialiased font-sans min-h-screen flex flex-col">

    <div class="h-1 w-full bg-gradient-to-r from-brand-600 via-brand-400 to-gold-500 shrink-0"></div>

    <header class="sticky top-0 z-40 bg-panel/90 backdrop-blur border-b border-border" x-data="{ mobileOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 shrink-0 group">
                    <span class="relative w-8 h-8 shrink-0">
                        <svg viewBox="0 0 32 32" class="w-8 h-8 loop-spin" style="animation-play-state: paused" onmouseover="this.style.animationPlayState='running'">
                            <path d="M16 4 A12 12 0 0 1 27.8 14" fill="none" stroke="#147041" stroke-width="3.2" stroke-linecap="round"/>
                            <path d="M27.8 14 L24.5 10.5 M27.8 14 L23.5 15.5" fill="none" stroke="#147041" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M16 28 A12 12 0 0 1 4.2 18" fill="none" stroke="#b5810a" stroke-width="3.2" stroke-linecap="round"/>
                            <path d="M4.2 18 L7.5 21.5 M4.2 18 L8.5 16.5" fill="none" stroke="#b5810a" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span class="font-display italic text-lg leading-none text-brand-800 whitespace-nowrap hidden sm:inline">Westport Industrial City</span>
                    <span class="font-display italic text-lg leading-none text-brand-800 whitespace-nowrap sm:hidden">Westport</span>
                </a>

                <nav class="hidden lg:flex items-center gap-1">
                    <a href="{{ route('dashboard') }}" @class(['px-3 py-2 rounded-lg text-sm font-medium transition-colors', 'bg-brand-50 text-brand-800' => request()->routeIs('dashboard'), 'text-ink-muted hover:text-ink hover:bg-panel-muted' => ! request()->routeIs('dashboard')])>{{ __('Overview') }}</a>
                    <a href="{{ route('ministries.index') }}" @class(['px-3 py-2 rounded-lg text-sm font-medium transition-colors', 'bg-brand-50 text-brand-800' => request()->routeIs('ministries.*'), 'text-ink-muted hover:text-ink hover:bg-panel-muted' => ! request()->routeIs('ministries.*')])>{{ __('Ministries') }}</a>
                    <a href="{{ route('state-corporations.index') }}" @class(['px-3 py-2 rounded-lg text-sm font-medium transition-colors', 'bg-brand-50 text-brand-800' => request()->routeIs('state-corporations.*'), 'text-ink-muted hover:text-ink hover:bg-panel-muted' => ! request()->routeIs('state-corporations.*')])>{{ __('Clients') }}</a>
                    <a href="{{ route('material-items.index') }}" @class(['px-3 py-2 rounded-lg text-sm font-medium transition-colors', 'bg-brand-50 text-brand-800' => request()->routeIs('material-items.*'), 'text-ink-muted hover:text-ink hover:bg-panel-muted' => ! request()->routeIs('material-items.*')])>{{ __('Materials') }}</a>
                    <a href="{{ route('feasibility-study.index') }}" @class(['px-3 py-2 rounded-lg text-sm font-medium transition-colors', 'bg-brand-50 text-brand-800' => request()->routeIs('feasibility-study.*'), 'text-ink-muted hover:text-ink hover:bg-panel-muted' => ! request()->routeIs('feasibility-study.*')])>{{ __('Feasibility Study') }}</a>
                    <a href="{{ route('collections.index') }}" @class(['px-3 py-2 rounded-lg text-sm font-medium transition-colors', 'bg-brand-50 text-brand-800' => request()->routeIs('collections.*'), 'text-ink-muted hover:text-ink hover:bg-panel-muted' => ! request()->routeIs('collections.*')])>{{ __('Submissions') }}</a>
                </nav>

                <div class="flex items-center gap-2">
                    @auth
                        <a href="{{ auth()->user()->canAccessAdminArea() ? route('admin.dashboard') : route('rm.dashboard') }}" class="hidden sm:inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-brand-700 text-white text-sm font-semibold hover:bg-brand-800 transition-colors shadow-sm">
                            <x-icon name="layout-dashboard" size="15" />
                            {{ auth()->user()->canAccessAdminArea() ? __('Admin') : __('My Dashboard') }}
                        </a>
                        <span class="hidden md:inline-flex items-center justify-center w-8 h-8 rounded-full bg-gold-100 text-gold-700 text-xs font-bold shrink-0" title="{{ auth()->user()->name }}">
                            {{ collect(explode(' ', auth()->user()->name))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                        </span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="p-2 rounded-lg text-ink-faint hover:text-ink hover:bg-panel-muted transition-colors" title="{{ __('Log out') }}">
                                <x-icon name="logout" size="17" />
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-brand-700 text-white text-sm font-semibold hover:bg-brand-800 transition-colors shadow-sm">
                            {{ __('RM / Admin Login') }}
                        </a>
                    @endauth

                    <button @click="mobileOpen = ! mobileOpen" class="lg:hidden p-2 rounded-lg text-ink-muted hover:bg-panel-muted transition-colors">
                        <x-icon name="menu" size="20" />
                    </button>
                </div>
            </div>
        </div>

        <div x-show="mobileOpen" x-cloak x-transition class="lg:hidden border-t border-border bg-panel px-4 py-3 space-y-1">
            <a href="{{ route('dashboard') }}" class="block px-3 py-2 rounded-lg text-sm font-medium text-ink-muted hover:bg-panel-muted">{{ __('Overview') }}</a>
            <a href="{{ route('ministries.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium text-ink-muted hover:bg-panel-muted">{{ __('Ministries') }}</a>
            <a href="{{ route('state-corporations.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium text-ink-muted hover:bg-panel-muted">{{ __('Clients') }}</a>
            <a href="{{ route('material-items.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium text-ink-muted hover:bg-panel-muted">{{ __('Materials') }}</a>
            <a href="{{ route('feasibility-study.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium text-ink-muted hover:bg-panel-muted">{{ __('Feasibility Study') }}</a>
            <a href="{{ route('collections.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium text-ink-muted hover:bg-panel-muted">{{ __('Submissions') }}</a>
        </div>
    </header>

    @if (session('status'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full pt-6">
            <div class="rounded-lg bg-brand-50 border border-brand-200 text-brand-800 text-sm px-4 py-3 flex items-center gap-2">
                <x-icon name="circle-check" />
                {{ session('status') }}
            </div>
        </div>
    @endif

    <main class="flex-1 w-full {{ $wide ? 'max-w-[100rem]' : 'max-w-7xl' }} mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{ $slot }}
    </main>

    <footer class="border-t border-border bg-panel-muted mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-ink-faint">
            <span>{{ __('Westport Industrial City · Circular Economy Materials Register') }}</span>
            <span>&copy; {{ date('Y') }}</span>
        </div>
    </footer>
</body>
</html>
