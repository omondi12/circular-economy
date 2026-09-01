<x-layout title="RM Performance">
        <div class="mb-6">
            <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1 text-sm text-brand-700 hover:text-brand-800 font-medium mb-3">
                &larr; Back to admin
            </a>
            <h1 class="text-xl sm:text-2xl font-semibold text-ink">RM Performance</h1>
            <p class="text-sm text-ink-faint mt-1">Each RM's assigned ministry portfolio and submission activity.</p>
        </div>

        <div class="bg-panel border border-border rounded-xl overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-brand-50 text-left text-ink-faint">
                    <tr>
                        <th class="px-4 py-2 font-medium">RM</th>
                        <th class="px-4 py-2 font-medium">Ministries Assigned</th>
                        <th class="px-4 py-2 font-medium text-right">Total Submissions</th>
                        <th class="px-4 py-2 font-medium text-right">This Month</th>
                        <th class="px-4 py-2 font-medium">Last Submission</th>
                        <th class="px-4 py-2 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($rms as $row)
                        <tr class="hover:bg-panel-muted transition-colors align-top">
                            <td class="px-4 py-3">
                                <div class="font-medium text-ink">{{ $row['rm']->name }}</div>
                                <div class="text-xs text-ink-faint">{{ $row['rm']->email }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @if ($row['ministries']->isEmpty())
                                    <span class="text-ink-faint text-xs">None assigned</span>
                                @else
                                    <div class="flex flex-wrap gap-1 max-w-md">
                                        @foreach ($row['ministries'] as $ministry)
                                            <span class="inline-block px-2 py-0.5 rounded-full bg-brand-50 text-brand-800 text-xs">
                                                {{ $ministry }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium">{{ number_format($row['totalSubmissions']) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-ink-muted">{{ number_format($row['submissionsThisMonth']) }}</td>
                            <td class="px-4 py-3 text-ink-muted whitespace-nowrap">
                                {{ $row['lastSubmissionAt'] ? \Illuminate\Support\Carbon::parse($row['lastSubmissionAt'])->format('d M Y') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('collections.index', ['rm' => $row['rm']->id]) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-brand-50 text-brand-800 text-xs font-medium hover:bg-brand-100 transition-colors whitespace-nowrap">
                                    View submissions
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
</x-layout>
