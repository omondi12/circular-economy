<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — Westport Industrial City</title>
    {{-- Deliberately standalone (no x-layout): an error page must still
         render when the database, session or auth is what failed. --}}
    @vite(['resources/css/app.css'])
</head>
<body class="bg-canvas text-ink antialiased font-sans min-h-screen flex flex-col">
    <div class="h-1 w-full bg-gradient-to-r from-brand-600 via-brand-400 to-gold-500"></div>
    <main class="flex-1 flex items-center justify-center px-4 py-16">
        <div class="card w-full max-w-md p-8 text-center">
            <p class="font-mono text-xs font-semibold tracking-widest text-ink-faint">ERROR @yield('code')</p>
            <h1 class="mt-2 font-display italic text-3xl text-ink">@yield('heading')</h1>
            <p class="mt-3 text-sm text-ink-muted">@yield('message')</p>
            <div class="mt-6 flex flex-wrap justify-center gap-2">
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="btn btn-secondary">Go back</a>
                <a href="{{ url('/') }}" class="btn btn-primary">Go to home</a>
            </div>
        </div>
    </main>
    <footer class="py-6 text-center text-xs text-ink-faint">Westport Industrial City · Circular Economy Materials Register</footer>
</body>
</html>
