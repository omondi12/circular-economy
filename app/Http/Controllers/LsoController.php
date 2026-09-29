<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\GovernmentEntity;
use App\Models\Lso;
use App\Models\LsoPayment;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Local Service Orders - a tender/service opportunity awarded to a
 * person/company, recorded by the RM who covers it, with money collected
 * against it tracked as a separate payment ledger (see the Lso and
 * LsoPayment models). Combines the RM's own view and the Finance/Admin
 * view in one controller, same shape as RequisitionController (mine() +
 * adminIndex() + confirm/reject actions all together).
 */
class LsoController extends Controller
{
    /**
     * An RM's own LSOs, newest first.
     */
    public function mine(): View
    {
        $user = Auth::user();

        $lsos = Lso::where('user_id', $user->id)
            ->withCount('payments')
            ->with('payments')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(15);

        return view('rm.lsos.index', [
            'lsos' => $lsos,
            'totalLsos' => Lso::where('user_id', $user->id)->count(),
            'totalOriginalMinor' => (int) Lso::where('user_id', $user->id)->sum('original_amount_minor'),
            'totalConfirmedMinor' => (int) LsoPayment::where('status', LsoPayment::STATUS_CONFIRMED)
                ->whereHas('lso', fn ($q) => $q->where('user_id', $user->id))
                ->sum('amount_minor'),
        ]);
    }

    public function create(): View
    {
        return view('rm.lsos.create', [
            'ministries' => GovernmentEntity::ministries()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->canRecordLso(), 403);

        $data = $request->validate([
            'reference_number' => ['required', 'string', 'max:100', 'unique:lsos,reference_number'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_contact' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'ministry_id' => ['nullable', 'integer', 'exists:government_entities,id'],
            'original_amount' => ['required', 'integer', 'min:1'],
            'issue_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'document' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $document = $request->file('document');
        $documentPath = $document->store('lso-documents', 'local');

        $lso = Lso::create([
            'reference_number' => $data['reference_number'],
            'user_id' => Auth::id(),
            'customer_name' => $data['customer_name'],
            'customer_contact' => $data['customer_contact'] ?? null,
            'description' => $data['description'] ?? null,
            'ministry_id' => $data['ministry_id'] ?? null,
            'original_amount_minor' => $data['original_amount'] * 100,
            'issue_date' => $data['issue_date'],
            'document_path' => $documentPath,
            'document_original_filename' => $document->getClientOriginalName(),
            'document_mime' => $document->getClientMimeType(),
            'notes' => $data['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);

        AuditLog::record('lso.created', $lso, [
            'reference_number' => $lso->reference_number,
            'customer_name' => $lso->customer_name,
            'original_amount' => $lso->original_amount_minor / 100,
        ]);

        return redirect()->route('rm.lsos.index')->with('status', 'LSO recorded.');
    }

    /**
     * Shared by both rm.lsos.show and admin.lsos.show - visibility is
     * decided by Lso::scopeVisibleTo(), not by which route matched.
     */
    public function show(Lso $lso): View
    {
        abort_unless(Lso::whereKey($lso->id)->visibleTo(Auth::user())->exists(), 403);

        $lso->load(['user', 'payments' => fn ($q) => $q->orderByDesc('collected_at')->orderByDesc('id'), 'payments.recordedBy', 'payments.confirmedBy']);

        return view('lsos.show', [
            'lso' => $lso,
            'canRecordPayment' => Auth::id() === $lso->user_id,
            'canManageFinance' => Auth::user()->canManageLsoFinance(),
        ]);
    }

    /**
     * Streams the private LSO document - never through the public
     * /photos/{path} route, since this is financial evidence. Access is
     * gated by the same visibility rule as the LSO record itself.
     */
    public function document(Lso $lso): StreamedResponse
    {
        abort_unless(Lso::whereKey($lso->id)->visibleTo(Auth::user())->exists(), 403);
        abort_unless($lso->document_path && Storage::disk('local')->exists($lso->document_path), 404);

        return Storage::disk('local')->response(
            $lso->document_path,
            $lso->document_original_filename,
            ['Content-Type' => $lso->document_mime ?? 'application/octet-stream'],
        );
    }

    /**
     * An RM records a collection against their own LSO - a self-report,
     * not yet counted toward anything until Finance/Admin confirms it
     * (see confirmPayment()).
     */
    public function storePayment(Request $request, Lso $lso): RedirectResponse
    {
        abort_unless(Auth::id() === $lso->user_id || Auth::user()->isAdmin(), 403);
        abort_if($lso->status === Lso::STATUS_CANCELLED, 422);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'collected_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $payment = LsoPayment::create([
            'lso_id' => $lso->id,
            'amount_minor' => $data['amount'] * 100,
            'collected_at' => $data['collected_at'],
            'status' => LsoPayment::STATUS_RECORDED,
            'recorded_by' => Auth::id(),
            'notes' => $data['notes'] ?? null,
        ]);

        AuditLog::record('lso.payment.recorded', $lso, [
            'payment_id' => $payment->id,
            'amount' => $payment->amount_minor / 100,
            'collected_at' => $payment->collected_at->toDateString(),
        ]);

        return back()->with('status', 'Payment recorded. It will count once Finance confirms it.');
    }

    /**
     * Every LSO, Finance/Admin's consolidated view - reachable by admin,
     * supervisor, office admin and operations (read-only for the latter,
     * enforced by confirmPayment/rejectPayment living in a narrower route
     * group, not by this method).
     */
    public function adminIndex(Request $request): View
    {
        $viewer = Auth::user();

        $filters = [
            'user_id' => $request->string('user_id')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
        ];

        $lsos = Lso::query()
            ->visibleTo($viewer)
            ->with(['user', 'payments'])
            ->when($filters['user_id'], fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $allLsos = Lso::query()->visibleTo($viewer)->with('payments')->get();

        return view('admin.lsos.index', [
            'lsos' => $lsos,
            'filters' => $filters,
            'rms' => User::assignableRms()->orderBy('name')->get(),
            'stats' => [
                'totalOriginalMinor' => (int) $allLsos->sum('original_amount_minor'),
                'totalConfirmedMinor' => (int) $allLsos->sum(fn (Lso $lso) => $lso->confirmedCollectedMinor()),
                'totalOutstandingMinor' => (int) $allLsos->sum(fn (Lso $lso) => $lso->outstandingMinor()),
                'fullyPaidCount' => $allLsos->filter(fn (Lso $lso) => $lso->status === Lso::STATUS_FULLY_PAID)->count(),
            ],
        ]);
    }

    public function confirmPayment(LsoPayment $payment): RedirectResponse
    {
        abort_unless(Auth::user()->canConfirmLsoPayment($payment), 403);
        abort_unless($payment->status === LsoPayment::STATUS_RECORDED, 422);

        DB::transaction(function () use ($payment) {
            $lso = Lso::query()->lockForUpdate()->findOrFail($payment->lso_id);
            $lockedPayment = LsoPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($lockedPayment->status !== LsoPayment::STATUS_RECORDED) {
                return;
            }

            $alreadyConfirmedMinor = (int) LsoPayment::where('lso_id', $lso->id)
                ->where('status', LsoPayment::STATUS_CONFIRMED)
                ->sum('amount_minor');

            if ($alreadyConfirmedMinor + $lockedPayment->amount_minor > $lso->original_amount_minor) {
                throw ValidationException::withMessages([
                    'payment' => 'Confirming this payment would exceed the LSO\'s original value.',
                ]);
            }

            $lockedPayment->update(['status' => LsoPayment::STATUS_CONFIRMED, 'confirmed_by' => Auth::id()]);

            $newTotal = $alreadyConfirmedMinor + $lockedPayment->amount_minor;
            $lso->update([
                'status' => $newTotal >= $lso->original_amount_minor ? Lso::STATUS_FULLY_PAID : Lso::STATUS_ACTIVE,
            ]);
        });

        AuditLog::record('lso.payment.confirmed', $payment->lso, [
            'payment_id' => $payment->id,
            'amount' => $payment->amount_minor / 100,
        ]);

        return back()->with('status', 'Payment confirmed.');
    }

    public function rejectPayment(LsoPayment $payment): RedirectResponse
    {
        abort_unless(Auth::user()->canConfirmLsoPayment($payment), 403);
        abort_unless($payment->status === LsoPayment::STATUS_RECORDED, 422);

        $payment->update(['status' => LsoPayment::STATUS_REJECTED, 'confirmed_by' => Auth::id()]);

        AuditLog::record('lso.payment.rejected', $payment->lso, [
            'payment_id' => $payment->id,
            'amount' => $payment->amount_minor / 100,
        ]);

        return back()->with('status', 'Payment rejected.');
    }
}
