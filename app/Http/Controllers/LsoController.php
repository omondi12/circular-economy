<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Lso;
use App\Models\LsoLot;
use App\Models\LsoPayment;
use App\Models\User;
use App\Services\LotPricingService;
use App\Support\WasteCategories;
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
     * Shared by both rm.lsos.show and admin.lsos.show - visibility is
     * decided by Lso::scopeVisibleTo(), not by which route matched.
     */
    public function show(Lso $lso): View
    {
        abort_unless(Lso::whereKey($lso->id)->visibleTo(Auth::user())->exists(), 403);

        $lso->load(['user', 'payments' => fn ($q) => $q->orderByDesc('collected_at')->orderByDesc('id'), 'payments.recordedBy', 'payments.confirmedBy', 'lots.collection']);

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
     *
     * Every existing Lso is, by construction, a Lot 1 (auction) order -
     * Lot 2 collections never create one (see RmDashboardController::
     * store()). `gross_amount` is an optional alternative to `amount`:
     * when given, it's the realized auction sale value, and the actual
     * amount that counts (`amount_minor`) is Westport's contractual
     * tiered commission on it (LotPricingService), not the sale value
     * itself - the sale value is preserved separately as
     * `gross_amount_minor`, purely for audit/transparency, and is never
     * what counts toward outstanding/target/reporting totals. Omitting
     * `gross_amount` (the default, backward-compatible path) records
     * `amount` directly exactly as before this feature existed.
     */
    public function storePayment(Request $request, Lso $lso, LotPricingService $pricing): RedirectResponse
    {
        abort_unless(Auth::id() === $lso->user_id || Auth::user()->isAdmin(), 403);
        abort_if($lso->status === Lso::STATUS_CANCELLED, 422);

        $data = $request->validate([
            'amount' => ['required_without:gross_amount', 'nullable', 'integer', 'min:1', 'prohibits:gross_amount'],
            'gross_amount' => ['required_without:amount', 'nullable', 'integer', 'min:1'],
            'collected_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if (isset($data['gross_amount'])) {
            $grossMinor = $data['gross_amount'] * 100;
            // Provisional only - the KES 100,000 tier is a property of
            // this LSO's whole cumulative CONFIRMED sale value, which
            // isn't settled until confirmation order is known, so this
            // is recomputed authoritatively in confirmPayment(). Using
            // confirmed-so-far here is just the best current estimate to
            // show while pending.
            $priorConfirmedGrossMinor = (int) LsoPayment::where('lso_id', $lso->id)
                ->where('status', LsoPayment::STATUS_CONFIRMED)
                ->sum('gross_amount_minor');
            $amountMinor = $pricing->calculateLot1IncrementalCommissionMinor($priorConfirmedGrossMinor, $grossMinor);
        } else {
            $grossMinor = null;
            $amountMinor = $data['amount'] * 100;
        }

        $payment = LsoPayment::create([
            'lso_id' => $lso->id,
            'amount_minor' => $amountMinor,
            'gross_amount_minor' => $grossMinor,
            'collected_at' => $data['collected_at'],
            'status' => LsoPayment::STATUS_RECORDED,
            'recorded_by' => Auth::id(),
            'notes' => $data['notes'] ?? null,
        ]);

        AuditLog::record('lso.payment.recorded', $lso, [
            'payment_id' => $payment->id,
            'amount' => $payment->amount_minor / 100,
            'gross_amount' => $payment->gross_amount_minor !== null ? $payment->gross_amount_minor / 100 : null,
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
            'reference' => $request->string('reference')->toString() ?: null,
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
        ];

        $lsos = Lso::query()
            ->visibleTo($viewer)
            ->with(['user', 'payments'])
            ->withCount('lots')
            ->when($filters['user_id'], fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['reference'], fn ($q, $v) => $q->where('reference_number', 'like', "%{$v}%"))
            ->when($filters['from'], fn ($q, $v) => $q->whereDate('issue_date', '>=', $v))
            ->when($filters['to'], fn ($q, $v) => $q->whereDate('issue_date', '<=', $v))
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

    public function confirmPayment(LsoPayment $payment, LotPricingService $pricing): RedirectResponse
    {
        abort_unless(Auth::user()->canConfirmLsoPayment($payment), 403);
        abort_unless($payment->status === LsoPayment::STATUS_RECORDED, 422);

        DB::transaction(function () use ($payment, $pricing) {
            $lso = Lso::query()->lockForUpdate()->findOrFail($payment->lso_id);
            $lockedPayment = LsoPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($lockedPayment->status !== LsoPayment::STATUS_RECORDED) {
                return;
            }

            // A gross-sale-value payment's commission is only finalized
            // here, not at record time - the KES 100,000 tier applies to
            // this LSO's cumulative CONFIRMED sale value, which is only
            // deterministic in confirmation order (see
            // LotPricingService::calculateLot1IncrementalCommissionMinor()).
            // Whatever storePayment() estimated was provisional; this
            // recomputation is authoritative and overwrites it.
            if ($lockedPayment->gross_amount_minor !== null) {
                $priorConfirmedGrossMinor = (int) LsoPayment::where('lso_id', $lso->id)
                    ->where('status', LsoPayment::STATUS_CONFIRMED)
                    ->whereKeyNot($lockedPayment->id)
                    ->sum('gross_amount_minor');

                $lockedPayment->amount_minor = $pricing->calculateLot1IncrementalCommissionMinor(
                    $priorConfirmedGrossMinor,
                    $lockedPayment->gross_amount_minor,
                );
            }

            // Ceiling guard: original_amount_minor is the appraised value
            // stated on the LSO document (see Lso's docblock), not
            // Westport's commission revenue. For a plain payment,
            // amount_minor already IS the value collected, so comparing it
            // directly is correct (the pre-existing behavior). For a
            // gross-based Lot 1 payment, amount_minor holds only the 7-10%
            // commission - comparing that against the full appraised value
            // would almost never trip, since commission is always a small
            // fraction of it. The quantity actually comparable to the
            // document's stated value is the realized sale value
            // (gross_amount_minor), so that's what's summed here whenever
            // it's present.
            $alreadyConfirmedValueMinor = (int) LsoPayment::where('lso_id', $lso->id)
                ->where('status', LsoPayment::STATUS_CONFIRMED)
                ->sum(DB::raw('COALESCE(gross_amount_minor, amount_minor)'));

            $thisValueMinor = $lockedPayment->gross_amount_minor ?? $lockedPayment->amount_minor;

            if ($alreadyConfirmedValueMinor + $thisValueMinor > $lso->original_amount_minor) {
                throw ValidationException::withMessages([
                    'payment' => 'Confirming this payment would exceed the LSO\'s original value.',
                ]);
            }

            $alreadyConfirmedMinor = (int) LsoPayment::where('lso_id', $lso->id)
                ->where('status', LsoPayment::STATUS_CONFIRMED)
                ->sum('amount_minor');

            $lockedPayment->status = LsoPayment::STATUS_CONFIRMED;
            $lockedPayment->confirmed_by = Auth::id();
            $lockedPayment->save();

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

    /**
     * Finance/Admin's confirmation queue for Lot 2 (Disposal) revenue -
     * the Lot 2 equivalent of adminIndex()/confirmPayment() above, kept
     * separate since a Lot 2 LsoLot has no parent Lso at all (see LsoLot's
     * docblock). Scoped to the same visible-RM set as rmPerformance() so a
     * supervisor only ever sees their own team's pending revenue.
     */
    public function lsoLotsIndex(Request $request): View
    {
        $viewer = Auth::user();
        $visibleRmIds = User::visibleRmsFor($viewer)->pluck('id');

        $status = $request->string('status')->toString();
        $status = in_array($status, [LsoLot::STATUS_PENDING, LsoLot::STATUS_CONFIRMED, LsoLot::STATUS_REJECTED], true)
            ? $status
            : LsoLot::STATUS_PENDING;

        $lots = LsoLot::query()
            ->whereHas('collection', fn ($q) => $q->whereIn('user_id', $visibleRmIds)->where('lot', WasteCategories::LOT_DISPOSAL))
            ->where('status', $status)
            ->with(['collection.user', 'confirmedBy'])
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.lso-lots.index', [
            'lots' => $lots,
            'status' => $status,
            'canManageFinance' => $viewer->canManageLsoFinance(),
        ]);
    }

    /**
     * Locks in Lot 2's authoritative Westport-earned amount - recomputed
     * server-side from the collection's own category/unit/quantity via
     * LotPricingService, exactly like confirmPayment() recomputes Lot 1's
     * commission rather than trusting a provisional stored figure. Finance
     * cannot submit or adjust the confirmed amount (no client input is
     * accepted at all) - confirming either locks in the contractual
     * calculation or, if it's genuinely unpriced, is refused outright.
     */
    public function confirmLsoLot(LsoLot $lsoLot, LotPricingService $pricing): RedirectResponse
    {
        abort_unless(Auth::user()->canConfirmLsoLot($lsoLot), 403);
        abort_unless($lsoLot->status === LsoLot::STATUS_PENDING, 422);

        $confirmedMinor = null;

        // The transaction reports whether THIS request actually confirmed
        // the lot, not just whether it's confirmed by the time we get here -
        // a concurrent request may have already resolved it in the window
        // between the unlocked check above and this lock being granted
        // (same race confirmPayment() above already guards against). Only a
        // genuine change gets an audit entry and a success message; a lost
        // race is reported honestly as a no-op, never as this request's own
        // confirmation.
        $changed = DB::transaction(function () use ($lsoLot, $pricing, &$confirmedMinor) {
            $locked = LsoLot::query()->lockForUpdate()->findOrFail($lsoLot->id);

            if ($locked->status !== LsoLot::STATUS_PENDING) {
                return false;
            }

            $collection = $locked->collection;

            $confirmedMinor = $pricing->calculateLot2ChargeMinor(
                $collection->category,
                $collection->unit,
                (float) $collection->quantity,
            );

            if ($confirmedMinor === null) {
                throw ValidationException::withMessages([
                    'lot' => 'This collection has no contractual rate (unmapped category or incompatible unit) and cannot be confirmed.',
                ]);
            }

            $locked->update([
                'status' => LsoLot::STATUS_CONFIRMED,
                'confirmed_revenue_minor' => $confirmedMinor,
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ]);

            return true;
        });

        if (! $changed) {
            return back()->with('status', 'This Lot 2 collection was already resolved by someone else - no changes made.');
        }

        AuditLog::record('lso_lot.confirmed', $lsoLot, [
            'collection_id' => $lsoLot->collection_id,
            'confirmed_revenue' => $confirmedMinor / 100,
        ]);

        return back()->with('status', 'Lot 2 revenue confirmed.');
    }

    public function rejectLsoLot(LsoLot $lsoLot): RedirectResponse
    {
        abort_unless(Auth::user()->canConfirmLsoLot($lsoLot), 403);
        abort_unless($lsoLot->status === LsoLot::STATUS_PENDING, 422);

        $changed = DB::transaction(function () use ($lsoLot) {
            $locked = LsoLot::query()->lockForUpdate()->findOrFail($lsoLot->id);

            if ($locked->status !== LsoLot::STATUS_PENDING) {
                return false;
            }

            $locked->update(['status' => LsoLot::STATUS_REJECTED, 'confirmed_by' => Auth::id(), 'confirmed_at' => now()]);

            return true;
        });

        if (! $changed) {
            return back()->with('status', 'This Lot 2 collection was already resolved by someone else - no changes made.');
        }

        AuditLog::record('lso_lot.rejected', $lsoLot, [
            'collection_id' => $lsoLot->collection_id,
        ]);

        return back()->with('status', 'Lot 2 revenue rejected.');
    }
}
