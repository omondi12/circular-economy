<?php

namespace App\Services;

use App\Models\ClientReport;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * When each RM last logged a client report (2026-10-27, per the boss: the reports page shows "the last time someone
 * logged a report"). Every active RM is listed, including those who never logged one; quietest first.
 */
class ReportActivity
{
    /** Days without a report after which an RM is flagged */
    public const QUIET_AFTER_DAYS = 7;

    /**
     * @return Collection<int, array{rm: User, last: ?Carbon, days: ?int, week: int, month: int, total: int, quiet: bool}>
     */
    public function lastByRm(): Collection
    {
        $stats = ClientReport::query()
            ->select('rm_id')
            ->selectRaw('max(report_date) as last_date, count(*) as total')
            ->selectRaw('sum(case when report_date >= ? then 1 else 0 end) as week', [today()->subDays(6)->toDateString()])
            ->selectRaw('sum(case when report_date >= ? then 1 else 0 end) as month', [today()->subDays(29)->toDateString()])
            ->groupBy('rm_id')
            ->get()->keyBy('rm_id');

        return User::assignableRms()->orderBy('name')->get()
            ->map(function (User $rm) use ($stats) {
                $row = $stats[$rm->id] ?? null;
                $last = $row?->last_date ? Carbon::parse($row->last_date) : null;
                $days = $last ? (int) $last->startOfDay()->diffInDays(today()) : null;

                return [
                    'rm' => $rm,
                    'last' => $last,
                    'days' => $days,
                    'week' => (int) ($row->week ?? 0),
                    'month' => (int) ($row->month ?? 0),
                    'total' => (int) ($row->total ?? 0),
                    'quiet' => $days === null || $days > self::QUIET_AFTER_DAYS,
                ];
            })
            // Never reported first, then the longest since the last report
            ->sortBy(fn (array $row) => [$row['days'] === null ? 0 : 1, -($row['days'] ?? 0), $row['rm']->name])
            ->values();
    }
}
