<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('requisitions')->orderBy('id')->chunkById(100, function ($requisitions): void {
            foreach ($requisitions as $requisition) {
                [$transportRequested, $transportPaid] = $this->normalizeCategory(
                    $requisition,
                    'transport',
                );
                [$airtimeRequested, $airtimePaid] = $this->normalizeCategory(
                    $requisition,
                    'airtime',
                );

                DB::table('requisitions')->where('id', $requisition->id)->update([
                    'transport_amount_requested' => $transportRequested,
                    'transport_paid_amount' => $transportPaid,
                    'airtime_amount_requested' => $airtimeRequested,
                    'airtime_paid_amount' => $airtimePaid,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Normalized financial amounts cannot be reconstructed safely.
    }

    private function wholeKes(string|float|int $amount): int
    {
        $amount = (float) $amount;

        return $amount > 0 ? max(1, (int) round($amount)) : 0;
    }

    private function normalizeCategory(object $requisition, string $category): array
    {
        $completedPayments = DB::table('requisition_payments')
            ->where('requisition_id', $requisition->id)
            ->where('category', $category)
            ->where('status', 'completed')
            ->get(['id', 'amount_minor']);

        foreach ($completedPayments as $payment) {
            $normalizedMinor = max(100, (int) round($payment->amount_minor / 100) * 100);
            if ($normalizedMinor !== (int) $payment->amount_minor) {
                DB::table('requisition_payments')->where('id', $payment->id)->update([
                    'amount_minor' => $normalizedMinor,
                ]);
                $payment->amount_minor = $normalizedMinor;
            }
        }

        $ledgerPaid = $completedPayments->sum(fn ($payment) => (int) $payment->amount_minor) / 100;
        $legacyPaid = $this->wholeKes($requisition->{$category.'_paid_amount'});
        $paid = $completedPayments->isNotEmpty() ? (int) $ledgerPaid : $legacyPaid;
        $requested = max(
            $this->wholeKes($requisition->{$category.'_amount_requested'}),
            $paid,
        );

        return [$requested, $paid];
    }
};
