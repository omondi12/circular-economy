@props(['title' => null, 'wide' => false, 'showFlash' => true])

@php
    // One list drives both the desktop nav row and the mobile menu, so the
    // two can never disagree about which links a role sees.
    $navLinks = auth()->check()
        ? auth()->user()->navLinks()
        : [
            ['label' => 'Overview', 'route' => 'dashboard', 'pattern' => 'dashboard'],
            ['label' => 'Ministries', 'route' => 'ministries.index', 'pattern' => 'ministries.*'],
            ['label' => 'Clients', 'route' => 'state-corporations.index', 'pattern' => 'state-corporations.*'],
            ['label' => 'Materials', 'route' => 'material-items.index', 'pattern' => 'material-items.*'],
            ['label' => 'Feasibility Study', 'route' => 'feasibility-study.index', 'pattern' => 'feasibility-study.*'],
            ['label' => 'Submissions', 'route' => 'collections.index', 'pattern' => 'collections.*'],
        ];

    $homeRoute = auth()->check() ? auth()->user()->homeRouteName() : 'dashboard';

    // Temporary, system-level feedback becomes toasts (rendered by
    // resources/js/ui.js). Field-level validation stays inline next to
    // each field; the toast only says that something needs attention.
    $toasts = [];
    if ($showFlash) {
        foreach (['status' => 'success', 'success' => 'success', 'info' => 'info', 'warning' => 'warning', 'error' => 'error'] as $key => $type) {
            if (is_string(session($key)) && session($key) !== '') {
                $toasts[] = [$type, session($key)];
            }
        }
        if ($errors->any()) {
            $toasts[] = ['error', $errors->count() === 1
                ? $errors->first()
                : 'Please fix the '.$errors->count().' highlighted fields and try again.'];
        }
    }
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f5c37">
    <title>{{ $title ? $title.' — Westport Industrial City' : 'Westport Industrial City' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="bg-canvas text-ink antialiased font-sans min-h-screen flex flex-col">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[70] focus:px-4 focus:py-2 focus:rounded-lg focus:bg-brand-800 focus:text-white focus:text-sm focus:font-semibold">Skip to content</a>

    <div class="h-1 w-full bg-gradient-to-r from-brand-600 via-brand-400 to-gold-500 shrink-0"></div>

    <header
        class="sticky top-0 z-40 bg-panel/95 backdrop-blur border-b border-border"
        x-data="{ mobileOpen: false }"
        @keydown.escape.window="mobileOpen = false"
    >
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between gap-3 h-16">
                <a href="{{ route($homeRoute) }}" class="flex items-center gap-2.5 shrink-0 min-w-0 group" aria-label="Westport Industrial City - home">
                    <span class="relative w-8 h-8 shrink-0">
                        <svg viewBox="0 0 32 32" class="w-8 h-8 loop-spin" style="animation-play-state: paused" onmouseover="this.style.animationPlayState='running'" aria-hidden="true">
                            <path d="M16 4 A12 12 0 0 1 27.8 14" fill="none" stroke="#147041" stroke-width="3.2" stroke-linecap="round"/>
                            <path d="M27.8 14 L24.5 10.5 M27.8 14 L23.5 15.5" fill="none" stroke="#147041" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M16 28 A12 12 0 0 1 4.2 18" fill="none" stroke="#b5810a" stroke-width="3.2" stroke-linecap="round"/>
                            <path d="M4.2 18 L7.5 21.5 M4.2 18 L8.5 16.5" fill="none" stroke="#b5810a" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span class="font-display italic text-lg leading-none text-brand-800 whitespace-nowrap hidden sm:inline">Westport Industrial City</span>
                    <span class="font-display italic text-lg leading-none text-brand-800 whitespace-nowrap sm:hidden">Westport</span>
                </a>

                <div class="flex items-center gap-1.5 sm:gap-2">
                    @auth
                        <a href="{{ route($homeRoute) }}" @class([
                            'hidden sm:inline-flex btn btn-sm sm:min-h-9 sm:px-3.5 sm:text-sm',
                            'btn-primary' => ! request()->routeIs($homeRoute),
                            'btn-primary ring-2 ring-brand-300 ring-offset-1' => request()->routeIs($homeRoute),
                        ]) @if (request()->routeIs($homeRoute)) aria-current="page" @endif>
                            <x-icon name="layout-dashboard" size="15" />
                            {{ __(auth()->user()->homeLabel()) }}
                        </a>
                        <a href="{{ route('account.profile.edit') }}" class="hidden md:inline-flex shrink-0 rounded-full" title="{{ auth()->user()->name }} — My Profile" aria-label="My profile ({{ auth()->user()->name }})">
                            @if (auth()->user()->profilePhotoUrl())
                                <img src="{{ auth()->user()->profilePhotoUrl() }}" alt="" class="w-9 h-9 rounded-full object-cover ring-1 ring-border">
                            @else
                                <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-gold-100 text-gold-700 text-xs font-bold" aria-hidden="true">
                                    {{ auth()->user()->initials() }}
                                </span>
                            @endif
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-ink-faint hover:text-ink hover:bg-panel-muted transition-colors" title="{{ __('Log out') }}" aria-label="{{ __('Log out') }}">
                                <x-icon name="logout" size="17" />
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-primary btn-sm sm:min-h-9 sm:text-sm">
                            {{ __('RM / Admin Login') }}
                        </a>
                    @endauth

                    <button
                        type="button"
                        @click="mobileOpen = ! mobileOpen"
                        class="lg:hidden inline-flex items-center justify-center w-10 h-10 rounded-lg text-ink-muted hover:bg-panel-muted transition-colors"
                        :aria-expanded="mobileOpen.toString()"
                        aria-controls="mobile-nav"
                        :aria-label="mobileOpen ? 'Close menu' : 'Open menu'"
                    >
                        <span x-show="! mobileOpen"><x-icon name="menu" size="20" /></span>
                        <span x-show="mobileOpen" x-cloak><x-icon name="x" size="20" /></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Desktop nav: its own row, so any number of links fits without
             pushing the page sideways (admin roles have nine). --}}
        @if ($navLinks !== [])
            <nav class="hidden lg:block border-t border-border/70" aria-label="Main">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <ul class="flex items-center gap-1 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden -mb-px">
                        @foreach ($navLinks as $link)
                            @php $active = request()->routeIs($link['pattern']); @endphp
                            <li class="shrink-0">
                                <a href="{{ route($link['route']) }}" @if ($active) aria-current="page" @endif @class([
                                    'inline-flex items-center h-11 px-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap',
                                    'border-brand-600 text-brand-800' => $active,
                                    'border-transparent text-ink-muted hover:text-ink hover:border-border-strong' => ! $active,
                                ])>{{ __($link['label']) }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </nav>
        @endif

        {{-- Mobile / tablet menu --}}
        <div
            id="mobile-nav"
            x-show="mobileOpen" x-cloak
            x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            @click.outside="mobileOpen = false"
            class="lg:hidden border-t border-border bg-panel shadow-lg max-h-[calc(100dvh-4.25rem)] overflow-y-auto"
        >
            <nav class="px-4 py-3" aria-label="Main">
                @auth
                    <div class="flex items-center gap-3 px-3 py-2 mb-2 rounded-lg bg-panel-muted">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-gold-100 text-gold-700 text-xs font-bold shrink-0" aria-hidden="true">{{ auth()->user()->initials() }}</span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-ink truncate">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-ink-faint truncate">{{ auth()->user()->email }}</p>
                        </div>
                    </div>
                @endauth
                <ul class="space-y-0.5">
                    @auth
                        <li>
                            <a href="{{ route($homeRoute) }}" @if (request()->routeIs($homeRoute)) aria-current="page" @endif @class([
                                'flex items-center gap-2 px-3 min-h-11 rounded-lg text-sm font-semibold',
                                'bg-brand-50 text-brand-800' => request()->routeIs($homeRoute),
                                'text-ink hover:bg-panel-muted' => ! request()->routeIs($homeRoute),
                            ])>
                                <x-icon name="layout-dashboard" size="16" />
                                {{ __(auth()->user()->homeLabel()) }}
                            </a>
                        </li>
                    @endauth
                    @foreach ($navLinks as $link)
                        @php $active = request()->routeIs($link['pattern']); @endphp
                        <li>
                            <a href="{{ route($link['route']) }}" @if ($active) aria-current="page" @endif @class([
                                'flex items-center px-3 min-h-11 rounded-lg text-sm font-medium',
                                'bg-brand-50 text-brand-800 font-semibold' => $active,
                                'text-ink-muted hover:bg-panel-muted hover:text-ink' => ! $active,
                            ])>{{ __($link['label']) }}</a>
                        </li>
                    @endforeach
                </ul>
                @auth
                    <div class="mt-3 pt-3 border-t border-border grid grid-cols-2 gap-2">
                        <a href="{{ route('account.profile.edit') }}" class="btn btn-secondary">
                            <x-icon name="user" size="15" /> {{ __('My Profile') }}
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-secondary w-full">
                                <x-icon name="logout" size="15" /> {{ __('Log out') }}
                            </button>
                        </form>
                    </div>
                @endauth
            </nav>
        </div>
    </header>

    @if ($toasts !== [])
        <script type="application/json" id="flash-toasts">@json($toasts)</script>
    @endif
    <noscript>
        @foreach ($toasts as [$type, $message])
            <div class="max-w-7xl mx-auto px-4 pt-4"><p class="card px-4 py-3 text-sm">{{ $message }}</p></div>
        @endforeach
    </noscript>

    <main id="main" tabindex="-1" class="flex-1 w-full {{ $wide ? 'max-w-[100rem]' : 'max-w-7xl' }} mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 focus:outline-none">
        {{ $slot }}
    </main>

    <footer class="border-t border-border bg-panel-muted mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-ink-faint text-center">
            <span>{{ __('Westport Industrial City · Circular Economy Materials Register') }}</span>
            <span>&copy; {{ date('Y') }}</span>
        </div>
    </footer>
</body>
</html>
