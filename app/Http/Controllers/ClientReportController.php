<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ClientReport;
use App\Models\StateCorporation;
use App\Models\User;
use App\Support\ClientReportOptions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Daily engagement reports - the admin-side data-entry surface for
 * whoever the boss assigns to transcribe the RMs' WhatsApp updates
 * (2026-09-02/03 brief). Scoped to one client at a time (reached via the
 * "Report" action on the Clients tab of Assign RMs), rather than a
 * separate free-standing page - the institution and its usual RM are
 * already known from context, so the form only asks what isn't already
 * known.
 */
class ClientReportController extends Controller
{
    /**
     * Every report across every client, newest first - the boss's own
     * view for reading through what's been logged, distinct from the
     * per-client index() below which is the data-entry surface. Public
     * (2026-09-03, per the boss - he doesn't want to log in just to
     * read reports); only logging a new report stays behind admin login.
     */
    public function all(Request $request): View
    {
        $filters = [
            'q' => $request->string('q')->toString() ?: null,
            'rm_id' => $request->string('rm_id')->toString() ?: null,
            'current_stage' => $request->string('current_stage')->toString() ?: null,
        ];

        $reports = ClientReport::query()
            ->with(['client', 'rm', 'createdBy'])
            ->when($filters['q'], fn ($q, $v) => $q->whereHas('client', fn ($cq) => $cq->where('name', 'like', "%{$v}%")))
            ->when($filters['rm_id'], fn ($q, $v) => $q->where('rm_id', $v))
            ->when($filters['current_stage'], fn ($q, $v) => $q->where('current_stage', $v))
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('reports.index', [
            'reports' => $reports,
            'filters' => $filters,
            'rms' => $this->assignableRms(),
            'stages' => ClientReportOptions::STAGES,
            'totalReports' => ClientReport::count(),
        ]);
    }

    public function index(StateCorporation $client): View
    {
        $reports = $client->reports()
            ->with(['rm', 'createdBy'])
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.clients.reports', [
            'client' => $client,
            'reports' => $reports,
            'rms' => $this->assignableRms(),
            'engagementTypes' => ClientReportOptions::ENGAGEMENT_TYPES,
            'stages' => ClientReportOptions::STAGES,
        ]);
    }

    public function store(Request $request, StateCorporation $client): RedirectResponse
    {
        $data = $request->validate([
            'rm_id' => ['nullable', 'integer', 'exists:users,id'],
            'report_date' => ['required', 'date'],
            'engagement_type' => ['required', Rule::in(ClientReportOptions::ENGAGEMENT_TYPES)],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'outcome' => ['required', 'string', 'max:2000'],
            'current_stage' => ['required', Rule::in(ClientReportOptions::STAGES)],
            'next_action' => ['nullable', 'string', 'max:255'],
            'follow_up_date' => ['nullable', 'date'],
            'comments' => ['nullable', 'string', 'max:2000'],
        ]);

        $report = $client->reports()->create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        AuditLog::record('client.report_logged', $report, [
            'client' => $client->name,
            'report_date' => $data['report_date'],
            'current_stage' => $data['current_stage'],
        ]);

        return redirect()->route('admin.clients.reports.index', $client)
            ->with('status', "Report logged for {$client->name}.");
    }

    /**
     * Same active, real (non-demo) scope used by Assign RMs, so the "RM
     * Name" dropdown here only ever offers real assignees.
     */
    private function assignableRms()
    {
        return User::assignableRms()->orderBy('name')->get();
    }
}
