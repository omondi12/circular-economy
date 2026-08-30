<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin - AMAC Circular Economy</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-b from-[#eaf7ee] via-white to-white text-neutral-900 min-h-screen">
    <div class="h-1.5 w-full bg-gradient-to-r from-[#0f7a3d] via-[#1a9650] to-[#c98500]"></div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-lg bg-[#0f7a3d]/10 border border-[#0f7a3d]/30 text-[#0b5c2e] text-sm px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        <header class="relative mb-8 rounded-2xl bg-gradient-to-br from-[#0f7a3d] via-[#177a44] to-[#c98500] shadow-xl shadow-[#0f7a3d]/20 px-6 py-7 sm:px-8 sm:py-8 overflow-hidden">
            <div class="absolute -top-16 -right-10 w-64 h-64 rounded-full bg-white/10 blur-2xl"></div>

            <div class="relative flex flex-col sm:flex-row items-center gap-6">
                <div class="flex-1 text-center sm:text-left">
                    <p class="text-white/70 text-xs uppercase tracking-wide">Admin</p>
                    <h1 class="text-xl sm:text-2xl font-semibold text-white">{{ auth()->user()->name }}</h1>
                    <p class="text-sm text-white/80 mt-1">Manage RM accounts and review activity.</p>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="px-4 py-3 rounded-lg bg-white/15 ring-1 ring-white/25 text-white text-sm font-medium hover:bg-white/25 transition-colors">
                        Log Out
                    </button>
                </form>
            </div>
        </header>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
            <x-stat-tile label="Relationship Managers" :value="number_format($userCount)" hint="Tap to manage accounts" icon="building" tone="green" :href="route('admin.users')" />
            <x-stat-tile label="Total Submissions" :value="number_format($submissionCount)" hint="Across all RMs and legacy data" icon="document" tone="gold" :href="route('dashboard')" />
            <x-stat-tile label="RM Performance" value="View" hint="Ministries and activity per RM" icon="landmark" tone="violet" :href="route('admin.rm-performance')" />
            <x-stat-tile label="Audit Log" value="View" hint="Every account and submission action" icon="scale" tone="teal" :href="route('admin.audit-log')" />
        </div>

        <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-neutral-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-600">Recent Activity</h2>
                <a href="{{ route('admin.audit-log') }}" class="text-sm text-[#0f7a3d] hover:text-[#0b5c2e] font-medium">View full log →</a>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-[#0f7a3d]/5 text-left text-neutral-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Who</th>
                        <th class="px-4 py-2 font-medium">Action</th>
                        <th class="px-4 py-2 font-medium">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($recentAuditLog as $entry)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $entry->user?->name ?? 'System' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $entry->action }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-500">{{ $entry->created_at->format('d M Y, H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-neutral-400">No activity recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
