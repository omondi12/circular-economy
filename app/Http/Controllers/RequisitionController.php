<?php

namespace App\Http\Controllers;

use App\Exceptions\NawiriPayrollException;
use App\Models\AuditLog;
use App\Models\Requisition;
use App\Models\RequisitionPayment;
use App\Models\Setting;
use App\Models\User;
use App\Services\RequisitionPaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Daily transport + airtime facilitation requests, per the boss's brief
 * (2026-09-17, extended 2026-09-19). RMs, Supervisors and Office Admins
 * request for themselves; admins, supervisors and office admins can all
 * approve or decline, while only office admins can pay (see
 * authorizeApproval() for the per-request rules -
 * nobody approves their own, and an Office Admin's request needs a
 * Supervisor or Admin, never another Office Admin). Three surfaces share
 * the same underlying data:
 *  - /requisitions        - a requester's own history + new-request form
 *  - /admin/requisitions  - every request, stat breakdown, approval and payment
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
        $request->merge([
            'recipient_phone_numbers' => collect($request->input('recipient_phone_numbers', []))
                ->map(fn ($phone) => $this->normalizeKenyanPhone($phone))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ]);

        $data = $request->validate([
            'institutions' => ['required', 'array', 'min:1'],
            'institutions.*' => ['required', 'string', 'max:255'],
            'working_day' => ['required', 'date'],
            'recipient_phone_numbers' => ['required', 'array', 'min:1', 'max:20'],
            'recipient_phone_numbers.*' => ['required', 'regex:/^254(?:7|1)\d{8}$/', 'distinct'],
            'transport_amount_requested' => ['required', 'numeric', 'integer', 'min:0'],
            'airtime_amount_requested' => ['required', 'numeric', 'integer', 'min:0'],
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
            'recipient_phone_numbers' => $data['recipient_phone_numbers'],
            'transport_requested_at' => $now,
            'transport_amount_requested' => $data['transport_amount_requested'],
            'airtime_requested_at' => $now,
            'airtime_amount_requested' => $data['airtime_amount_requested'],
        ]);

        AuditLog::record('requisition.requested', $requisition, [
            'requester' => Auth::user()->name,
            'institution_visiting' => $requisition->institution_visiting,
            'working_day' => $requisition->working_day->toDateString(),
            'recipient_phone_numbers' => $requisition->recipientPhoneNumbers(),
            'transport_amount_requested' => (float) $requisition->transport_amount_requested,
            'airtime_amount_requested' => (float) $requisition->airtime_amount_requested,
        ]);

        return redirect()->route('requisitions.mine')->with('status', 'Request submitted.');
    }

    /**
     * Admin/Office Admin correction of a requisition's own submitted
     * details (2026-09-24, per the boss - repeated date/amount mistakes
     * were otherwise needing a direct database fix every time).
     * Deliberately does not touch approval/payment state - see
     * canEditRequisitions() on the User model for why.
     */
    public function edit(Requisition $requisition): View
    {
        abort_unless(Auth::user()->canEditRequisitions(), 403);

        return view('admin.requisitions.edit', [
            'requisition' => $requisition,
            'institutions' => explode(', ', $requisition->institution_visiting),
        ]);
    }

    public function update(Request $request, Requisition $requisition): RedirectResponse
    {
        abort_unless(Auth::user()->canEditRequisitions(), 403);

        $request->merge([
            'recipient_phone_numbers' => collect($request->input('recipient_phone_numbers', []))
                ->map(fn ($phone) => $this->normalizeKenyanPhone($phone))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ]);

        $data = $request->validate([
            'institutions' => ['required', 'array', 'min:1'],
            'institutions.*' => ['required', 'string', 'max:255'],
            'working_day' => ['required', 'date'],
            'requested_date' => ['required', 'date'],
            'recipient_phone_numbers' => ['required', 'array', 'min:1', 'max:20'],
            'recipient_phone_numbers.*' => ['required', 'regex:/^254(?:7|1)\d{8}$/', 'distinct'],
            'transport_amount_requested' => ['required', 'numeric', 'integer', 'min:0'],
            'airtime_amount_requested' => ['required', 'numeric', 'integer', 'min:0'],
        ]);

        $institutionVisiting = collect($data['institutions'])
            ->map(fn ($name) => trim($name))
            ->filter()
            ->implode(', ');

        // Keeps each field's own time-of-day, just moves it onto the
        // corrected calendar date - the mistake being fixed is almost
        // always "wrong day", not "wrong time".
        $requestedDate = \Illuminate\Support\Carbon::parse($data['requested_date']);
        $newTransportRequestedAt = $requisition->transport_requested_at->copy()->setDate($requestedDate->year, $requestedDate->month, $requestedDate->day);
        $newAirtimeRequestedAt = $requisition->airtime_requested_at->copy()->setDate($requestedDate->year, $requestedDate->month, $requestedDate->day);

        $before = [
            'institution_visiting' => $requisition->institution_visiting,
            'working_day' => $requisition->working_day->toDateString(),
            'transport_requested_at' => $requisition->transport_requested_at->toDateTimeString(),
            'airtime_requested_at' => $requisition->airtime_requested_at->toDateTimeString(),
            'recipient_phone_numbers' => implode(', ', $requisition->recipientPhoneNumbers()),
            'transport_amount_requested' => (float) $requisition->transport_amount_requested,
            'airtime_amount_requested' => (float) $requisition->airtime_amount_requested,
        ];

        $requisition->update([
            'institution_visiting' => $institutionVisiting,
            'working_day' => $data['working_day'],
            'transport_requested_at' => $newTransportRequestedAt,
            'airtime_requested_at' => $newAirtimeRequestedAt,
            'recipient_phone_numbers' => $data['recipient_phone_numbers'],
            'transport_amount_requested' => $data['transport_amount_requested'],
            'airtime_amount_requested' => $data['airtime_amount_requested'],
        ]);

        AuditLog::record('requisition.edited', $requisition, [
            'requester' => $requisition->requester?->name,
            'before' => $before,
            'after' => [
                'institution_visiting' => $requisition->institution_visiting,
                'working_day' => $requisition->working_day->toDateString(),
                'transport_requested_at' => $requisition->transport_requested_at->toDateTimeString(),
                'airtime_requested_at' => $requisition->airtime_requested_at->toDateTimeString(),
                'recipient_phone_numbers' => implode(', ', $requisition->recipientPhoneNumbers()),
                'transport_amount_requested' => (float) $requisition->transport_amount_requested,
                'airtime_amount_requested' => (float) $requisition->airtime_amount_requested,
            ],
        ]);

        return redirect()->route('admin.requisitions.index')->with('status', "Requisition for {$requisition->requester?->name} updated.");
    }

    /**
     * Every request, a stat breakdown, and the approval/payment actions -
     * reachable by anyone who can approve requisitions (admin, supervisor,
     * office admin). Individual actions still run their own per-request
     * authorization (see authorizeApproval()).
     */
    public function adminIndex(Request $request): View
    {
        abort_unless(Auth::user()->canApproveRequisitions(), 403);

        $filters = [
            'status' => $request->string('status')->toString() ?: null,
            'requester_id' => $request->string('requester_id')->toString() ?: null,
        ];

        $requisitions = Requisition::query()
            ->with(['requester', 'transportApprovedBy', 'airtimeApprovedBy', 'payments'])
            ->when($filters['status'] === 'pending', fn ($q) => $q->where(fn ($q2) => $q2->where('transport_status', Requisition::STATUS_PENDING)->orWhere('airtime_status', Requisition::STATUS_PENDING)))
            ->when($filters['requester_id'], fn ($q, $v) => $q->where('requester_id', $v))
            ->orderByDesc('working_day')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.requisitions.index', [
            'requisitions' => $requisitions,
            'stats' => $this->stats(),
            'todayStats' => $this->stats(now()->toDateString()),
            'requesterTotals' => $this->requesterTotals(),
            'requesters' => User::whereIn('role', [User::ROLE_RM, User::ROLE_SUPERVISOR, User::ROLE_OFFICE_ADMIN])->orderBy('name')->get(),
            'filters' => $filters,
            'pin' => Setting::get(self::PIN_SETTING_KEY),
        ]);
    }

    /**
     * Every requisition with at least one approved track (transport and/or
     * airtime), as a CSV for the boss/finance - the "send this to finance"
     * button on the admin requisitions page (2026-09-18). Still-pending or
     * declined-only requests are excluded since there's nothing approved
     * on them to pay.
     */
    public function exportApproved(Request $request): StreamedResponse
    {
        abort_unless(Auth::user()->canApproveRequisitions(), 403);

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
                'Name', 'Institution', 'Working Day', 'Nawiri Recipients',
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
                    implode(', ', $req->recipientPhoneNumbers()),
                    $req->categoryTotalRequested(RequisitionPayment::CATEGORY_TRANSPORT),
                    $req->transport_status === Requisition::STATUS_APPROVED ? $req->transportApprovedBy?->name : $req->transport_status,
                    (float) $req->transport_paid_amount,
                    $req->transportBalance(),
                    $req->categoryTotalRequested(RequisitionPayment::CATEGORY_AIRTIME),
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

    /**
     * Approving no longer records a payment (2026-09-19) - it just
     * authorizes the amount. Whether it's actually been paid, in full or
     * partially, is a separate step via payTransport/payAirtime, since
     * approval and disbursement don't happen at the same moment in
     * practice.
     */
    public function approveTransport(Requisition $requisition): RedirectResponse
    {
        $this->authorizeApproval($requisition);

        $requisition->update([
            'transport_status' => Requisition::STATUS_APPROVED,
            'transport_approved_by_id' => Auth::id(),
            'transport_approved_at' => now(),
        ]);

        AuditLog::record('requisition.transport_approved', $requisition, ['requester' => $requisition->requester?->name]);

        return back()->with('status', 'Transport approved.');
    }

    public function payTransport(Requisition $requisition, RequisitionPaymentService $payments): RedirectResponse
    {
        abort_unless(Auth::user()->canPayRequisition($requisition), 403);
        abort_unless($requisition->transport_status === Requisition::STATUS_APPROVED, 422);

        return $this->payRecipients($requisition, RequisitionPayment::CATEGORY_TRANSPORT, $payments);
    }

    public function declineTransport(Requisition $requisition): RedirectResponse
    {
        $this->authorizeApproval($requisition);

        $requisition->update([
            'transport_status' => Requisition::STATUS_DECLINED,
            'transport_approved_by_id' => Auth::id(),
            'transport_approved_at' => now(),
            'transport_paid_amount' => 0,
        ]);

        AuditLog::record('requisition.transport_declined', $requisition, ['requester' => $requisition->requester?->name]);

        return back()->with('status', 'Transport declined.');
    }

    public function approveAirtime(Requisition $requisition): RedirectResponse
    {
        $this->authorizeApproval($requisition);

        $requisition->update([
            'airtime_status' => Requisition::STATUS_APPROVED,
            'airtime_approved_by_id' => Auth::id(),
            'airtime_approved_at' => now(),
        ]);

        AuditLog::record('requisition.airtime_approved', $requisition, ['requester' => $requisition->requester?->name]);

        return back()->with('status', 'Airtime approved.');
    }

    public function payAirtime(Requisition $requisition, RequisitionPaymentService $payments): RedirectResponse
    {
        abort_unless(Auth::user()->canPayRequisition($requisition), 403);
        abort_unless($requisition->airtime_status === Requisition::STATUS_APPROVED, 422);

        return $this->payRecipients($requisition, RequisitionPayment::CATEGORY_AIRTIME, $payments);
    }

    public function reconcilePayment(
        RequisitionPayment $payment,
        RequisitionPaymentService $payments,
    ): RedirectResponse|JsonResponse {
        abort_unless(Auth::user()->canPayRequisitions(), 403);

        $previousState = $payment->pollingState();

        try {
            $payment = $payments->reconcile($payment);
        } catch (NawiriPayrollException $exception) {
            if (request()->expectsJson()) {
                return response()->json(['error' => $exception->getMessage()], 503);
            }

            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        if (! request()->expectsJson() || $previousState !== $payment->pollingState()) {
            AuditLog::record('requisition.payment_reconciled', $payment->requisition, [
                'payment_id' => $payment->id,
                'payment_status' => $payment->status,
                'nawiri_payment_id' => $payment->nawiri_payment_id,
            ]);
        }

        if (request()->expectsJson()) {
            return response()->json($payment->pollingState());
        }

        return $this->paymentRedirect($payment);
    }

    public function authorizePayment(Request $request, RequisitionPayment $payment, RequisitionPaymentService $payments): RedirectResponse
    {
        abort_unless(Auth::user()->canPayRequisitions(), 403);
        $data = $request->validate(['reference' => ['required', 'string', 'max:100'], 'otp' => ['required', 'string', 'regex:/^[0-9]{6}$/']]);
        try {
            $payment = $payments->authorize($payment, $data['reference'], $data['otp']);
        } catch (NawiriPayrollException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }
        AuditLog::record('requisition.payment_authorized', $payment->requisition, ['payment_id' => $payment->id, 'payment_status' => $payment->status]);

        return $this->paymentRedirect($payment);
    }

    public function resendPaymentOtp(Request $request, RequisitionPayment $payment, RequisitionPaymentService $payments): RedirectResponse
    {
        abort_unless(Auth::user()->canPayRequisitions(), 403);
        $data = $request->validate(['reference' => ['required', 'string', 'max:100']]);
        try {
            $payment = $payments->authorize($payment, $data['reference'], null);
        } catch (NawiriPayrollException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        if ($payment->requiresOtp()) {
            AuditLog::record('requisition.payment_otp_resent', $payment->requisition, ['payment_id' => $payment->id, 'payment_status' => $payment->status]);
        }

        return $payment->requiresOtp()
            ? back()->with('status', 'A new OTP was requested from JamboPay. Use it for payment '.$payment->provider_reference.'.')
            : $this->paymentRedirect($payment);
    }

    public function declineAirtime(Requisition $requisition): RedirectResponse
    {
        $this->authorizeApproval($requisition);

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
            'todayStats' => $this->stats(now()->toDateString()),
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

    /**
     * See User::canApproveRequisition() for the actual rules - kept here
     * as a thin wrapper so every action method has one consistent call.
     */
    private function authorizeApproval(Requisition $requisition): void
    {
        abort_unless(Auth::user()->canApproveRequisition($requisition), 403);
    }

    private function payRecipients(
        Requisition $requisition,
        string $category,
        RequisitionPaymentService $payments,
    ): RedirectResponse {
        $requisition->loadMissing(['requester', 'payments']);
        $recipientBalances = $requisition->payableRecipientBalances($category);

        if ($recipientBalances === []) {
            if ($requisition->activePayment($category)) {
                return back()->with('warning', 'Every unpaid recipient already has a transfer being checked. Do not pay again.');
            }

            return back()->withErrors(['payment' => 'There are no unpaid recipients for this payment.']);
        }

        $submitted = [];
        $failures = [];

        foreach ($recipientBalances as $phoneNumber => $amount) {
            try {
                $payment = $payments->initiate(
                    $requisition,
                    $category,
                    $amount,
                    Auth::user(),
                    $phoneNumber,
                );
                $submitted[] = $payment;
                $this->auditPaymentAttempt($requisition, $payment);
            } catch (NawiriPayrollException $exception) {
                $payment = RequisitionPayment::query()
                    ->where('requisition_id', $requisition->id)
                    ->where('category', $category)
                    ->where('phone_number', $phoneNumber)
                    ->latest('id')
                    ->first();

                if ($payment) {
                    $this->auditPaymentAttempt($requisition, $payment);
                }

                if ($exception->outcomeUnknown && $payment) {
                    $submitted[] = $payment;
                } else {
                    $failures[$phoneNumber] = $exception->getMessage();
                }
            }
        }

        $completedCount = collect($submitted)
            ->where('status', RequisitionPayment::STATUS_COMPLETED)
            ->count();
        $processingCount = count($submitted) - $completedCount;
        $parts = [];

        if ($completedCount > 0) {
            $parts[] = $completedCount.' recipient'.($completedCount === 1 ? '' : 's').' paid';
        }
        if ($processingCount > 0) {
            $parts[] = $processingCount.' transfer'.($processingCount === 1 ? '' : 's').' awaiting authorization or processing. Enter the JamboPay OTP when shown below';
        }
        if ($failures !== []) {
            $parts[] = count($failures).' failed';
        }

        $message = ucfirst(implode(', ', $parts)).'.';

        if ($failures !== []) {
            $message .= ' Retry Pay to send only to the unpaid recipient'.(count($failures) === 1 ? '' : 's').'.';

            if ($submitted === []) {
                $message .= ' '.implode(' ', array_values($failures));

                return back()->withErrors(['payment' => $message]);
            }

            return back()->with('warning', $message);
        }

        if ($processingCount > 0) {
            return back()->with('warning', $message);
        }

        return back()->with('status', $message);
    }

    private function auditPaymentAttempt(Requisition $requisition, RequisitionPayment $payment): void
    {
        AuditLog::record('requisition.'.$payment->category.'_payment_submitted', $requisition, [
            'requester' => $requisition->requester?->name,
            'paid_amount' => $payment->amount_minor / 100,
            'payment_id' => $payment->id,
            'payment_status' => $payment->status,
            'nawiri_payment_id' => $payment->nawiri_payment_id,
            'recipient_phone' => $payment->phone_number,
        ]);
    }

    private function paymentStatusMessage(RequisitionPayment $payment): string
    {
        if ($payment->requiresOtp()) {
            return 'Enter the JamboPay OTP below to authorize this payment.';
        }

        return match ($payment->status) {
            RequisitionPayment::STATUS_COMPLETED => 'Nawiri confirmed the payment.',
            RequisitionPayment::STATUS_FAILED => 'Nawiri reported that the payment failed.',
            RequisitionPayment::STATUS_PENDING_RECONCILIATION => 'Nawiri is checking the payment status. Do not pay again.',
            default => 'JamboPay is processing this payment. This page will show any required OTP step.',
        };
    }

    private function paymentRedirect(RequisitionPayment $payment): RedirectResponse
    {
        if ($payment->status === RequisitionPayment::STATUS_COMPLETED) {
            return back()->with('status', $this->paymentStatusMessage($payment));
        }

        if ($payment->status === RequisitionPayment::STATUS_FAILED) {
            return back()->withErrors([
                'payment' => $payment->failure_reason ?: $this->paymentStatusMessage($payment),
            ]);
        }

        return back()->with('warning', $this->paymentStatusMessage($payment));
    }

    /**
     * $workingDay narrows this to one day's requests ("Today", on the
     * admin/public breakdown pages, 2026-09-19) - same shape either way,
     * just a where() added to the same query, so the two stat rows can
     * never drift apart in what they count.
     */
    private function stats(?string $workingDay = null): array
    {
        $requisitions = Requisition::query()
            ->when($workingDay, fn ($q, $v) => $q->where('working_day', $v))
            ->with('requester:id,phone_number')
            ->get();

        // "Requested" excludes declined tracks - a declined request was
        // never real spend, so it shouldn't inflate what's requested
        // (2026-09-19). "Approved" is its own figure, separate from
        // "Paid" - approving only authorizes an amount, it doesn't mean
        // the money has actually gone out (see payTransport/payAirtime).
        $transportRequested = $requisitions->sum(fn (Requisition $requisition) => $requisition->transport_status !== Requisition::STATUS_DECLINED
            ? $requisition->categoryTotalRequested(RequisitionPayment::CATEGORY_TRANSPORT)
            : 0);
        $transportApproved = $requisitions->sum(fn (Requisition $requisition) => $requisition->transport_status === Requisition::STATUS_APPROVED
            ? $requisition->categoryTotalRequested(RequisitionPayment::CATEGORY_TRANSPORT)
            : 0);
        $transportPaid = $requisitions->sum(fn (Requisition $requisition) => (float) $requisition->transport_paid_amount);
        $airtimeRequested = $requisitions->sum(fn (Requisition $requisition) => $requisition->airtime_status !== Requisition::STATUS_DECLINED
            ? $requisition->categoryTotalRequested(RequisitionPayment::CATEGORY_AIRTIME)
            : 0);
        $airtimeApproved = $requisitions->sum(fn (Requisition $requisition) => $requisition->airtime_status === Requisition::STATUS_APPROVED
            ? $requisition->categoryTotalRequested(RequisitionPayment::CATEGORY_AIRTIME)
            : 0);
        $airtimePaid = $requisitions->sum(fn (Requisition $requisition) => (float) $requisition->airtime_paid_amount);

        $totalApproved = $transportApproved + $airtimeApproved;
        $totalPaid = $transportPaid + $airtimePaid;

        return [
            'totalCount' => $requisitions->count(),
            'pendingCount' => $requisitions->filter(fn (Requisition $requisition) => $requisition->transport_status === Requisition::STATUS_PENDING
                || $requisition->airtime_status === Requisition::STATUS_PENDING)->count(),
            'declinedCount' => $requisitions->filter(fn (Requisition $requisition) => $requisition->transport_status === Requisition::STATUS_DECLINED
                || $requisition->airtime_status === Requisition::STATUS_DECLINED)->count(),
            'transportRequested' => $transportRequested,
            'transportApproved' => $transportApproved,
            'transportPaid' => $transportPaid,
            'airtimeRequested' => $airtimeRequested,
            'airtimeApproved' => $airtimeApproved,
            'airtimePaid' => $airtimePaid,
            'totalRequested' => $transportRequested + $airtimeRequested,
            'totalApproved' => $totalApproved,
            'totalPaid' => $totalPaid,
            // Approved-but-unpaid, not requested-but-unpaid - a still-
            // pending request isn't "outstanding" money, and a declined
            // one is never owed at all.
            'totalBalance' => $totalApproved - $totalPaid,
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
            ->with('requester:id,phone_number')
            ->get()
            ->groupBy('requester_id')
            ->map(fn ($requisitions) => [
                'days' => $requisitions->count(),
                'cumulative' => $requisitions->sum(fn (Requisition $requisition) => $requisition->totalRequestedForDay()),
            ])
            ->all();
    }

    private function normalizeKenyanPhone(mixed $value): string
    {
        $phone = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (str_starts_with($phone, '0')) {
            return '254'.substr($phone, 1);
        }
        if (strlen($phone) === 9 && in_array($phone[0] ?? '', ['7', '1'], true)) {
            return '254'.$phone;
        }

        return $phone;
    }
}
