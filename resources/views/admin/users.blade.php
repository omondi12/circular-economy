<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relationship Managers - AMAC Circular Economy Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-b from-[#eaf7ee] via-white to-white text-neutral-900 min-h-screen">
    <div class="h-1.5 w-full bg-gradient-to-r from-[#0f7a3d] via-[#1a9650] to-[#c98500]"></div>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-lg bg-[#0f7a3d]/10 border border-[#0f7a3d]/30 text-[#0b5c2e] text-sm px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex items-center justify-between mb-6">
            <x-page-header
                title="Relationship Managers"
                :subtitle="$users->count().' RM account(s).'"
                :back="route('admin.dashboard')"
                back-label="Back to admin"
            />
            <a href="{{ route('admin.users.create') }}" class="shrink-0 inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-[#0f7a3d] hover:bg-[#0b5c2e] text-white text-sm font-semibold transition-colors shadow-sm shadow-[#0f7a3d]/30 -mt-10">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                New RM Account
            </a>
        </div>

        <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-[#0f7a3d]/5 text-left text-neutral-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Name</th>
                        <th class="px-4 py-2 font-medium">Email</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                        <th class="px-4 py-2 font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($users as $user)
                        <tr class="hover:bg-neutral-50 transition-colors">
                            <td class="px-4 py-3 font-medium">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-neutral-500">{{ $user->email }}</td>
                            <td class="px-4 py-3">
                                @if ($user->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-[#0f7a3d]/10 text-[#0b5c2e] text-xs font-medium">Active</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-xs font-medium">Deactivated</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.users.toggle', $user) }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-medium {{ $user->is_active ? 'text-red-600 hover:text-red-700' : 'text-[#0f7a3d] hover:text-[#0b5c2e]' }}">
                                        {{ $user->is_active ? 'Deactivate' : 'Reactivate' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-neutral-400">No RM accounts yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
