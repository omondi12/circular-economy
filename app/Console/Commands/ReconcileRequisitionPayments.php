<?php

namespace App\Console\Commands;

use App\Exceptions\NawiriPayrollException;
use App\Models\RequisitionPayment;
use App\Services\RequisitionPaymentService;
use Illuminate\Console\Command;

class ReconcileRequisitionPayments extends Command
{
    protected $signature = 'payroll:reconcile {--limit=100}';

    protected $description = 'Reconcile requisition payouts that are waiting for Nawiri confirmation';

    public function handle(RequisitionPaymentService $payments): int
    {
        $reconcileBefore = now()->subSeconds(
            max(1, (int) config('services.nawiri_payroll.reconcile_after_seconds', 60)),
        );
        $reversalWindowStarts = now()->subHours(
            max(1, (int) config('services.nawiri_payroll.reversal_window_hours', 72)),
        );

        $limit = max(1, (int) $this->option('limit'));
        $completedQuota = max(1, min(25, intdiv($limit, 4)));
        $recentCompleted = RequisitionPayment::query()
            ->where('updated_at', '<=', $reconcileBefore)
            ->where('status', RequisitionPayment::STATUS_COMPLETED)
            ->where('completed_at', '>=', $reversalWindowStarts)
            ->oldest('updated_at')
            ->limit($completedQuota)
            ->get();
        $activeLimit = max(1, $limit - $recentCompleted->count());
        $active = RequisitionPayment::query()
            ->where('updated_at', '<=', $reconcileBefore)
            ->whereIn('status', [
                RequisitionPayment::STATUS_INITIATING,
                RequisitionPayment::STATUS_SUBMITTED,
                RequisitionPayment::STATUS_PENDING_RECONCILIATION,
            ])
            ->oldest('updated_at')
            ->limit($activeLimit)
            ->get();
        $paymentsToCheck = $active->concat($recentCompleted);

        $completed = 0;
        $failed = 0;

        foreach ($paymentsToCheck as $payment) {
            try {
                $updated = $payments->reconcile($payment);
                $completed += $updated->status === RequisitionPayment::STATUS_COMPLETED ? 1 : 0;
                $failed += $updated->status === RequisitionPayment::STATUS_FAILED ? 1 : 0;
            } catch (NawiriPayrollException $exception) {
                $this->warn("Payment {$payment->id}: {$exception->getMessage()}");
            }
        }

        $this->info("Checked {$paymentsToCheck->count()} payment(s), completed {$completed}, failed {$failed}.");

        return self::SUCCESS;
    }
}
