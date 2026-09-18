<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Requisition;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Daily transport + airtime facilitation requests, per the boss's brief
 * (2026-09-17). RMs and Supervisors request for themselves; only admins
 * approve/decline. Three surfaces share the same underlying data:
 *  - /requisitions        - a requester's own history + new-request form
 *  - /admin/requisitions  - admin-only, every request, approve/decline
 *  - /facilitation        - public, PIN-gated read-only breakdown for the
 *                           boss, since admin accounts are shared among
 *                           several people and he doesn't want to need one
 *                           just to check this.
 */
class RequisitionController extends Controller
{
    private const PIN_SETTING_KEY = 'requisition_pin';

    private const PIN_SESSION_KEY = 'requisition_unlocked';

    /**
     * A requester's own history, newest first, plus the New Request form.
     */
    public function mine(): View
    {
        $requester = Auth::user();

        $requisitions = Requisition::where('requester_id', $requester->id)
            ->with(['transportApprovedBy', 'airtimeApprovedBy'])
            ->orderByDesc('working_day')
            ->orderByDesc('id')
            ->paginate(20);

        return view('requisitions.mine', [
            'requisitions' => $requisitions,
            'defaultTransport' => Requisition::DEFAULT_TRANSPORT_AMOUNT,
            'defaultAirtime' => Requisition::DEFAULT_AIRTIME_AMOUNT,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'institutions' => ['required', 'array', 'min:1'],
            'institutions.*' => ['required', 'string', 'max:255'],
            'working_day' => ['required', 'date'],
            'transport_amount_requested' => ['required', 'numeric', 'min:0'],
            'airtime_amount_requested' => ['required', 'numeric', 'min:0'],
        ]);

        $now = now();

        // Stored as one comma-separated string (no schema/display change
        // needed elsewhere) - an RM/Supervisor can visit several
        // institutions in one day but it's still one day's facilitation
        // request (2026-09-18).
        $institutionVisiting = collect($data['institutions'])
            ->map(fn ($name) => trim($name))
            ->filter()
            ->implode(', ');

        $requisition = Requisition::create([
            'requester_id' => Auth::id(),
            'institution_visiting' => $institutionVisiting,
            'working_day' => $data['working_day'],
            'transport_requested_at' => $now,
            'transport_amount_requested' => $data['transport_amount_requested'],
            'airtime_requested_at' => $now,
            'airtime_amount_requested' => $data['airtime_amount_requested'],
        ]);

        AuditLog::record('requisition.requested', $requisition, [
            'requester' => Auth::user()->name,
            'institution_visiting' => $requisition->institution_visiting,
            'working_day' => $requisition->working_day->toDateString(),
            'transport_amount_requested' => (float) $requisition->transport_amount_requested,
            'airtime_amount_requested' => (float) $requisition->airtime_amount_requested,
        ]);

        return redirect()->route('requisitions.mine')->with('status', 'Request submitted.');
    }

    /**
     * Admin-only: every request, a stat breakdown, and the approve/decline
     * actions. Shares the middleware group with Supervisors (same as the
     * rest of /admin) but "only the admins can approve" means this one
     * page stays admin-only inside it, same pattern as distributeMinistries.
     */
    public function adminIndex(Request $request): View
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $filters = [
            'status' => $request->string('status')->toString() ?: null,
            'requester_id' => $request->string('requester_id')->toString() ?: null,
        ];

        $requisitions = Requisition::query()
            ->with(['requester', 'transportApprovedBy', 'airtimeApprovedBy'])
            ->when($filters['status'] === 'pending', fn ($q) => $q->where(fn ($q2) => $q2->where('transport_status', Requisition::STATUS_PENDING)->orWhere('airtime_status', Requisition::STATUS_PENDING)))
            ->when($filters['requester_id'], fn ($q, $v) => $q->where('requester_id', $v))
            ->orderByDesc('working_day')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.requisitions.index', [
            'requisitions' => $requisitions,
            'stats' => $this->stats(),
            'requesterTotals' => $this->requesterTotals(),
            'requesters' => User::whereIn('role', [User::ROLE_RM, User::ROLE_SUPERVISOR])->orderBy('name')->get(),
            'filters' => $filters,
            'pin' => Setting::get(self::PIN_SETTING_KEY),
        ]);
    }

    /**
     * Admin-only: every requisition with at least one approved track
     * (transport and/or airtime), as a CSV for the boss/finance - the
     * "send this to finance" button on the admin requisitions page
     * (2026-09-18). Still-pending or declined-only requests are excluded
     * since there's nothing approved on them to pay.
     */
    public function exportApproved(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $requesterId = $request->string('requester_id')->toString() ?: null;

        $requisitions = Requisition::query()
            ->with(['requester', 'transportApprovedBy', 'airtimeApprovedBy'])
            ->where(fn ($q) => $q->where('transport_status', Requisition::STATUS_APPROVED)->orWhere('airtime_status', Requisition::STATUS_APPROVED))
            ->when($requesterId, fn ($q, $v) => $q->where('requester_id', $v))
            ->orderBy('working_day')
            ->orderBy('requester_id')
            ->get();

        $requesterTotals = $this->requesterTotals();
        $filename = 'approved-facilitation-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($requisitions, $requesterTotals) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Name', 'Institution', 'Working Day',
                'Transport Requested', 'Transport Approved By', 'Transport Paid', 'Transport Balance',
                'Airtime Requested', 'Airtime Approved By', 'Airtime Paid', 'Airtime Balance',
                'Total for Day', 'Cumulative Facilitation', 'Days Facilitated',
            ]);

            foreach ($requisitions as $req) {
                $totals = $requesterTotals[$req->requester_id] ?? ['days' => 0, 'cumulative' => 0];

                fputcsv($handle, [
                    $req->requester?->name,
                    $req->institution_visiting,
                    $req->working_day->format('d M Y'),
                    (float) $req->transport_amount_requested,
                    $req->transport_status === Requisition::STATUS_APPROVED ? $req->transportApprovedBy?->name : $req->transport_status,
                    (float) $req->transport_paid_amount,
                    $req->transportBalance(),
                    (float) $req->airtime_amount_requested,
                    $req->airtime_status === Requisition::STATUS_APPROVED ? $req->airtimeApprovedBy?->name : $req->airtime_status,
                    (float) $req->airtime_paid_amount,
                    $req->airtimeBalance(),
                    $req->totalRequestedForDay(),
                    $totals['cumulative'],
                    $totals['days'],
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function approveTransport(Request $request, Requisition $requisition): RedirectResponse
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $data = $request->validate(['paid_amount' => ['required', 'numeric', 'min:0']]);

        $requisition->update([
            'transport_status' => Requisition::STATUS_APPROVED,
            'transport_approved_by_id' => Auth::id(),
            'transport_approved_at' => now(),
            'transport_paid_amount' => $data['paid_amount'],
        ]);

        AuditLog::record('requisition.transport_approved', $requisition, [
            'requester' => $requisition->requester?->name,
            'paid_amount' => (float) $data['paid_amount'],
        ]);

        return back()->with('status', 'Transport approved.');
    }

    public function declineTransport(Requisition $requisition): RedirectResponse
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $requisition->update([
            'transport_status' => Requisition::STATUS_DECLINED,
            'transport_approved_by_id' => Auth::id(),
            'transport_approved_at' => now(),
            'transport_paid_amount' => 0,
        ]);

        AuditLog::record('requisition.transport_declined', $requisition, ['requester' => $requisition->requester?->name]);

        return back()->with('status', 'Transport declined.');
    }

    public function approveAirtime(Request $request, Requisition $requisition): RedirectResponse
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $data = $request->validate(['paid_amount' => ['required', 'numeric', 'min:0']]);

        $requisition->update([
            'airtime_status' => Requisition::STATUS_APPROVED,
            'airtime_approved_by_id' => Auth::id(),
            'airtime_approved_at' => now(),
            'airtime_paid_amount' => $data['paid_amount'],
        ]);

        AuditLog::record('requisition.airtime_approved', $requisition, [
            'requester' => $requisition->requester?->name,
            'paid_amount' => (float) $data['paid_amount'],
        ]);

        return back()->with('status', 'Airtime approved.');
    }

    public function declineAirtime(Requisition $requisition): RedirectResponse
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $requisition->update([
            'airtime_status' => Requisition::STATUS_DECLINED,
            'airtime_approved_by_id' => Auth::id(),
            'airtime_approved_at' => now(),
            'airtime_paid_amount' => 0,
        ]);

        AuditLog::record('requisition.airtime_declined', $requisition, ['requester' => $requisition->requester?->name]);

        return back()->with('status', 'Airtime declined.');
    }

    public function regeneratePin(): RedirectResponse
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Setting::set(self::PIN_SETTING_KEY, $pin);

        AuditLog::record('requisition.pin_regenerated');

        return back()->with('status', "New PIN generated: {$pin}. Share it with whoever needs to view the public page.");
    }

    /**
     * Public, PIN-gated. The PIN unlocks this browser's session, not the
     * account - there's no login here on purpose, per the boss.
     */
    public function publicIndex(Request $request): View
    {
        if (! $request->session()->get(self::PIN_SESSION_KEY)) {
            return view('requisitions.locked');
        }

        $requisitions = Requisition::query()
            ->with(['requester', 'transportApprovedBy', 'airtimeApprovedBy'])
            ->orderByDesc('working_day')
            ->orderByDesc('id')
            ->paginate(30);

        return view('requisitions.public', [
            'requisitions' => $requisitions,
            'stats' => $this->stats(),
            'requesterTotals' => $this->requesterTotals(),
        ]);
    }

    public function unlockPublic(Request $request): RedirectResponse
    {
        $data = $request->validate(['pin' => ['required', 'string']]);

        $pin = Setting::get(self::PIN_SETTING_KEY);

        if ($pin === null || $data['pin'] !== $pin) {
            return back()->withErrors(['pin' => 'Incorrect PIN.']);
        }

        $request->session()->put(self::PIN_SESSION_KEY, true);

        return redirect()->route('requisitions.public');
    }

    private function stats(): array
    {
        $totals = Requisition::query()->selectRaw('
            COUNT(*) as total_count,
            SUM(CASE WHEN transport_status = ? OR airtime_status = ? THEN 1 ELSE 0 END) as pending_count,
            SUM(transport_amount_requested) as transport_requested,
            SUM(transport_paid_amount) as transport_paid,
            SUM(airtime_amount_requested) as airtime_requested,
            SUM(airtime_paid_amount) as airtime_paid
        ', [Requisition::STATUS_PENDING, Requisition::STATUS_PENDING])->first();

        $transportRequested = (float) ($totals->transport_requested ?? 0);
        $transportPaid = (float) ($totals->transport_paid ?? 0);
        $airtimeRequested = (float) ($totals->airtime_requested ?? 0);
        $airtimePaid = (float) ($totals->airtime_paid ?? 0);

        return [
            'totalCount' => (int) ($totals->total_count ?? 0),
            'pendingCount' => (int) ($totals->pending_count ?? 0),
            'transportRequested' => $transportRequested,
            'transportPaid' => $transportPaid,
            'airtimeRequested' => $airtimeRequested,
            'airtimePaid' => $airtimePaid,
            'totalRequested' => $transportRequested + $airtimeRequested,
            'totalPaid' => $transportPaid + $airtimePaid,
            'totalBalance' => ($transportRequested + $airtimeRequested) - ($transportPaid + $airtimePaid),
        ];
    }

    /**
     * Per-requester all-time totals ("Total Cumulative Facilitation" and
     * "Number of Days Facilitated" in the boss's column list) - a lookup
     * shown on every row for that requester, not a running/incremental
     * value, since the table isn't grouped by requester.
     */
    private function requesterTotals(): array
    {
        return Requisition::query()
            ->selectRaw('requester_id, COUNT(*) as days, SUM(transport_amount_requested + airtime_amount_requested) as cumulative')
            ->groupBy('requester_id')
            ->get()
            ->keyBy('requester_id')
            ->map(fn ($row) => ['days' => (int) $row->days, 'cumulative' => (float) $row->cumulative])
            ->all();
    }
}
