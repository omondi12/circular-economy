<?php

namespace App\Services;

use App\Models\LsoPayment;
use App\Models\Requisition;
use App\Models\RequisitionPayment;

/**
 * What Agent 360 pays RMs for (docs/agent360-api.md, payments): approved requisition amounts still owed to each
 * recipient, and the RM's commission on each confirmed LSO payment. Amounts are whole cents (minor units).
 *
 * Westport keeps the requisition balances: Agent 360 records each completed payment back
 * (RequisitionPaymentService::recordAgent360Payment). Commissions are tracked as paid in Agent 360.
 */
class Agent360Payables
{
    /**
     * @return list<array{key: string, requisition_id: int, category: string, phone: string, amount_minor: int, requester_id: int, requester: ?string, working_day: ?string, institution: ?string, approved_at: ?string, approved_by: ?string, busy: bool}>
     */
    public function requisitions(): array
    {
        $lines = [];

        $requisitions = Requisition::query()
            ->with(['requester:id,name,phone_number', 'payments', 'transportApprovedBy:id,name', 'airtimeApprovedBy:id,name'])
            ->where(fn ($q) => $q->where('transport_status', Requisition::STATUS_APPROVED)->orWhere('airtime_status', Requisition::STATUS_APPROVED))
            ->orderBy('working_day')->orderBy('id')
            ->get();

        foreach ($requisitions as $requisition) {
            foreach ([RequisitionPayment::CATEGORY_TRANSPORT, RequisitionPayment::CATEGORY_AIRTIME] as $category) {
                if ($requisition->{$category.'_status'} !== Requisition::STATUS_APPROVED) {
                    continue;
                }

                foreach ($requisition->outstandingRecipientBalances($category) as $phone => $balance) {
                    $amountMinor = (int) round($balance * 100);
                    if ($amountMinor <= 0) {
                        continue;
                    }

                    $lines[] = [
                        'key' => "req:{$requisition->id}:{$category}:{$phone}",
                        'requisition_id' => $requisition->id,
                        'category' => $category,
                        'phone' => (string) $phone,
                        'amount_minor' => $amountMinor,
                        'requester_id' => $requisition->requester_id,
                        'requester' => $requisition->requester?->name,
                        'working_day' => $requisition->working_day?->toDateString(),
                        'institution' => $requisition->institution_visiting,
                        'approved_at' => $requisition->{$category.'_approved_at'}?->toIso8601String(),
                        'approved_by' => $requisition->{$category === 'transport' ? 'transportApprovedBy' : 'airtimeApprovedBy'}?->name,
                        // A Westport wallet payment to this recipient is still being confirmed: do not pay it again
                        'busy' => $requisition->activePayment($category, (string) $phone) !== null,
                    ];
                }
            }
        }

        return $lines;
    }

    /**
     * @return list<array{key: string, lso_payment_id: int, agent_id: ?int, amount_minor: int, westport_earned_minor: int, lso: ?string, customer: ?string, collected_on: ?string}>
     */
    public function commissions(): array
    {
        return LsoPayment::query()->with('lso:id,user_id,reference_number,customer_name')
            ->where('status', LsoPayment::STATUS_CONFIRMED)->orderBy('id')->get()
            ->map(fn (LsoPayment $payment) => [
                'key' => "lso_payment:{$payment->id}",
                'lso_payment_id' => $payment->id,
                'agent_id' => $payment->lso?->user_id,
                'amount_minor' => (int) $payment->rmCommissionMinor(),
                'westport_earned_minor' => (int) $payment->amount_minor,
                'lso' => $payment->lso?->reference_number,
                'customer' => $payment->lso?->customer_name,
                'collected_on' => ($payment->collected_at ?? $payment->created_at)?->toDateString(),
            ])
            ->filter(fn (array $line) => $line['amount_minor'] > 0)
            ->values()->all();
    }
}
