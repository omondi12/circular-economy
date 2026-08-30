<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RM Performance - AMAC Circular Economy</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-b from-[#eaf7ee] via-white to-white text-neutral-900 min-h-screen">
    <div class="h-1.5 w-full bg-gradient-to-r from-[#0f7a3d] via-[#1a9650] to-[#c98500]"></div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6">
            <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1 text-sm text-[#0f7a3d] hover:text-[#0b5c2e] font-medium mb-3">
                &larr; Back to admin
            </a>
            <h1 class="text-xl sm:text-2xl font-semibold text-neutral-900">RM Performance</h1>
            <p class="text-sm text-neutral-500 mt-1">Each RM's assigned ministry portfolio and submission activity.</p>
        </div>

        <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#0f7a3d]/5 text-left text-neutral-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">RM</th>
                        <th class="px-4 py-2 font-medium">Ministries Assigned</th>
                        <th class="px-4 py-2 font-medium text-right">Total Submissions</th>
                        <th class="px-4 py-2 font-medium text-right">This Month</th>
                        <th class="px-4 py-2 font-medium">Last Submission</th>
                        <th class="px-4 py-2 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @foreach ($rms as $row)
                        <tr class="hover:bg-neutral-50 transition-colors align-top">
                            <td class="px-4 py-3">
                                <div class="font-medium text-neutral-900">{{ $row['rm']->name }}</div>
                                <div class="text-xs text-neutral-400">{{ $row['rm']->email }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @if ($row['ministries']->isEmpty())
                                    <span class="text-neutral-400 text-xs">None assigned</span>
                                @else
                                    <div class="flex flex-wrap gap-1 max-w-md">
                                        @foreach ($row['ministries'] as $ministry)
                                            <span class="inline-block px-2 py-0.5 rounded-full bg-[#0f7a3d]/10 text-[#0b5c2e] text-xs">
                                                {{ $ministry }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium">{{ number_format($row['totalSubmissions']) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-600">{{ number_format($row['submissionsThisMonth']) }}</td>
                            <td class="px-4 py-3 text-neutral-600 whitespace-nowrap">
                                {{ $row['lastSubmissionAt'] ? \Illuminate\Support\Carbon::parse($row['lastSubmissionAt'])->format('d M Y') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('collections.index', ['rm' => $row['rm']->id]) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-[#0f7a3d]/10 text-[#0b5c2e] text-xs font-medium hover:bg-[#0f7a3d]/20 transition-colors whitespace-nowrap">
                                    View submissions
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
