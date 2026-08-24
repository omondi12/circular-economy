<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Audit Log - AMAC Circular Economy Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-b from-[#eaf7ee] via-white to-white text-neutral-900 min-h-screen">
    <div class="h-1.5 w-full bg-gradient-to-r from-[#0f7a3d] via-[#1a9650] to-[#c98500]"></div>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-page-header
            title="Audit Log"
            :subtitle="number_format($entries->total()).' recorded action(s).'"
            :back="route('admin.dashboard')"
            back-label="Back to admin"
        />

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.audit-log') }}" class="mb-6 bg-white border border-neutral-200 rounded-xl p-4 shadow-sm flex flex-wrap gap-3 items-end">
            <div class="flex flex-col gap-1">
                <label for="action" class="text-xs text-neutral-500">Action</label>
                <select id="action" name="action" class="rounded-md border-neutral-300 text-sm focus:border-[#0f7a3d] focus:ring-[#0f7a3d]">
                    <option value="">All actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected($filters['action'] === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label for="user_id" class="text-xs text-neutral-500">Who</label>
                <select id="user_id" name="user_id" class="rounded-md border-neutral-300 text-sm focus:border-[#0f7a3d] focus:ring-[#0f7a3d]">
                    <option value="">Everyone</option>
                    @foreach ($rms as $rm)
                        <option value="{{ $rm->id }}" @selected((string) $filters['user_id'] === (string) $rm->id)>{{ $rm->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 rounded-md bg-[#0f7a3d] hover:bg-[#0b5c2e] text-white text-sm font-medium transition-colors shadow-sm shadow-[#0f7a3d]/30">
                    Filter
                </button>
                <a href="{{ route('admin.audit-log') }}" class="px-4 py-2 rounded-md border border-neutral-300 text-sm hover:bg-neutral-50 transition-colors">
                    Reset
                </a>
            </div>
        </form>

        <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#0f7a3d]/5 text-left text-neutral-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Who</th>
                        <th class="px-4 py-2 font-medium">Action</th>
                        <th class="px-4 py-2 font-medium">Detail</th>
                        <th class="px-4 py-2 font-medium">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($entries as $entry)
                        <tr>
                            <td class="px-4 py-3 font-medium whitespace-nowrap">{{ $entry->user?->name ?? 'System' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-[#0f7a3d]/10 text-[#0b5c2e] text-xs font-medium">{{ $entry->action }}</span>
                            </td>
                            <td class="px-4 py-3 text-neutral-500 max-w-md">
                                @if ($entry->meta)
                                    <span class="text-xs">{{ collect($entry->meta)->map(fn ($v, $k) => "{$k}: {$v}")->implode(' · ') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-500">{{ $entry->created_at->format('d M Y, H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-neutral-400">No activity matches these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $entries->links() }}
        </div>
    </div>
</body>
</html>
