<?php

namespace App\Services;

use App\Exceptions\NawiriPayrollException;
use App\Models\Requisition;
use App\Models\RequisitionPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RequisitionPaymentService
{
    public function __construct(private readonly NawiriPayrollClient $client) {}

    public function initiate(Requisition $requisition, string $category, float $amount, User $actor, string $phoneNumber): RequisitionPayment
    {
        $this->assertCategory($category);
        $amountMinor = (int) round($amount * 100);
        if ($amountMinor <= 0 || $amountMinor % 100 !== 0) {
            throw ValidationException::withMessages([
                'paid_amount' => 'Nawiri wallet payments must be a positive whole-KES amount.',
            ]);
        }

        $payment = DB::transaction(function () use ($requisition, $category, $amountMinor, $actor, $phoneNumber) {
            $locked = Requisition::query()->with('requester')->lockForUpdate()->findOrFail($requisition->id);
            $this->assertPayable($locked, $category, $amountMinor, $phoneNumber);
            if (! in_array($phoneNumber, $locked->recipientPhoneNumbers(), true)) {
                throw ValidationException::withMessages([
                    'recipient_phone' => 'Choose a recipient number supplied with this requisition.',
                ]);
            }

            $active = RequisitionPayment::query()
                ->where('requisition_id', $locked->id)
                ->where('category', $category)
                ->where('phone_number', $phoneNumber)
                ->whereIn('status', [
                    RequisitionPayment::STATUS_INITIATING,
                    RequisitionPayment::STATUS_SUBMITTED,
                    RequisitionPayment::STATUS_PENDING_RECONCILIATION,
                ])
                ->lockForUpdate()
                ->first();

            if ($active) {
                throw ValidationException::withMessages([
                    'payment' => 'This recipient already has a wallet payment awaiting confirmation.',
                ]);
            }

            return RequisitionPayment::create([
                'requisition_id' => $locked->id,
                'initiated_by_id' => $actor->id,
                'category' => $category,
                'amount_minor' => $amountMinor,
                'phone_number' => $phoneNumber,
                'provider' => 'NAWIRI_WALLET',
                'status' => RequisitionPayment::STATUS_INITIATING,
                'idempotency_key' => 'circular-req-'.$locked->id.'-'.$category.'-'.Str::uuid(),
            ]);
        });

        return $this->submitExisting($payment);
    }

    public function reconcile(RequisitionPayment $payment): RequisitionPayment
    {
        if (! $payment->isActive() && $payment->status !== RequisitionPayment::STATUS_COMPLETED) {
            return $payment;
        }

        try {
            $response = $this->client->reconcile($payment);
        } catch (NawiriPayrollException $exception) {
            if ($exception->outcomeUnknown && $payment->status !== RequisitionPayment::STATUS_COMPLETED) {
                $payment->update([
                    'status' => RequisitionPayment::STATUS_PENDING_RECONCILIATION,
                    'failure_reason' => $exception->getMessage(),
                ]);
            }

            throw $exception;
        }

        return $this->applyResponse($payment, $response);
    }

    public function authorize(RequisitionPayment $payment, string $reference, ?string $otp): RequisitionPayment
    {
        $payment->refresh();
        if ($payment->status === RequisitionPayment::STATUS_COMPLETED) {
            return $payment;
        }
        if (! $payment->requiresOtp() || $payment->otpReference() !== $reference) {
            throw ValidationException::withMessages(['payment' => 'This payment is not awaiting that OTP. Check its status.']);
        }

        // Rejected or uncertain OTP requests must not release the payment for another Pay attempt.
        $response = $otp === null
            ? $this->client->resendOtp($payment, $reference)
            : $this->client->authorize($payment, $reference, $otp);

        return $this->applyResponse($payment, $response);
    }

    private function submitExisting(RequisitionPayment $payment): RequisitionPayment
    {
        try {
            $response = $this->client->submit($payment);
        } catch (NawiriPayrollException $exception) {
            $payment->update([
                'status' => $exception->outcomeUnknown
                    ? RequisitionPayment::STATUS_PENDING_RECONCILIATION
                    : RequisitionPayment::STATUS_FAILED,
                'failure_reason' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return $this->applyResponse($payment, $response);
    }

    private function applyResponse(RequisitionPayment $payment, array $response): RequisitionPayment
    {
        $providerPayment = $response['payment'];
        $providerStatus = strtoupper(trim((string) ($providerPayment['status'] ?? '')));
        $status = $this->localStatus($providerStatus);

        return DB::transaction(function () use ($payment, $response, $providerPayment, $providerStatus, $status) {
            $requisition = Requisition::query()->lockForUpdate()->findOrFail($payment->requisition_id);
            $locked = RequisitionPayment::query()->lockForUpdate()->findOrFail($payment->id);
            $wasCompleted = $locked->status === RequisitionPayment::STATUS_COMPLETED;

            if ($wasCompleted && $providerStatus === 'REVERSED') {
                $locked->update([
                    'status' => RequisitionPayment::STATUS_FAILED,
                    'failure_reason' => $providerPayment['failureReason'] ?? 'Nawiri reversed the payment.',
                    'provider_response' => $response,
                    'completed_at' => null,
                ]);
                $this->reverseCompletedPayment($locked, $requisition);

                return $locked->fresh();
            }

            if ($wasCompleted && $status !== RequisitionPayment::STATUS_COMPLETED) {
                return $locked->fresh();
            }

            $locked->update([
                'status' => $status,
                'nawiri_payment_id' => $providerPayment['id'] ?? $locked->nawiri_payment_id,
                'provider_reference' => $providerPayment['jpRef'] ?? $locked->provider_reference,
                'provider_order_id' => $providerPayment['jpOrderId'] ?? $locked->provider_order_id,
                'failure_reason' => $status === RequisitionPayment::STATUS_FAILED
                    ? ($providerPayment['failureReason'] ?? 'Nawiri reported that the payment failed.')
                    : null,
                'provider_response' => $response,
                'completed_at' => $status === RequisitionPayment::STATUS_COMPLETED
                    ? ($locked->completed_at ?? now())
                    : null,
            ]);

            if ($status === RequisitionPayment::STATUS_COMPLETED && ! $wasCompleted) {
                $this->creditCompletedPayment($locked, $requisition);
            }

            return $locked->fresh();
        });
    }

    private function creditCompletedPayment(RequisitionPayment $payment, Requisition $requisition): void
    {
        $column = $payment->category.'_paid_amount';
        $newPaidMinor = min(
            (int) round($requisition->categoryTotalRequested($payment->category) * 100),
            (int) round((float) $requisition->{$column} * 100) + $payment->amount_minor,
        );

        $requisition->update([$column => $newPaidMinor / 100]);
    }

    private function reverseCompletedPayment(RequisitionPayment $payment, Requisition $requisition): void
    {
        $column = $payment->category.'_paid_amount';
        $newPaidMinor = max(
            0,
            (int) round((float) $requisition->{$column} * 100) - $payment->amount_minor,
        );

        $requisition->update([$column => $newPaidMinor / 100]);
    }

    private function assertPayable(Requisition $requisition, string $category, int $amountMinor, string $phoneNumber): void
    {
        if ($requisition->{$category.'_status'} !== Requisition::STATUS_APPROVED) {
            throw ValidationException::withMessages(['payment' => ucfirst($category).' must be approved before payment.']);
        }
        if ($requisition->recipientPhoneNumbers() === []) {
            throw ValidationException::withMessages(['payment' => 'This requisition has no Nawiri recipient number.']);
        }
        $recipientBalanceMinor = (int) round(($requisition->outstandingRecipientBalances($category)[$phoneNumber] ?? 0) * 100);
        if ($amountMinor > $recipientBalanceMinor) {
            throw ValidationException::withMessages(['paid_amount' => 'The payment exceeds this recipient\'s unpaid balance.']);
        }

        $totalBalanceMinor = (int) round(($category === RequisitionPayment::CATEGORY_TRANSPORT
            ? $requisition->transportBalance()
            : $requisition->airtimeBalance()) * 100);
        if ($amountMinor > $totalBalanceMinor) {
            throw ValidationException::withMessages(['paid_amount' => 'The payment exceeds the requisition\'s unpaid balance.']);
        }
    }

    private function assertCategory(string $category): void
    {
        if (! in_array($category, [RequisitionPayment::CATEGORY_TRANSPORT, RequisitionPayment::CATEGORY_AIRTIME], true)) {
            throw ValidationException::withMessages(['payment' => 'Unknown requisition payment category.']);
        }
    }

    private function localStatus(string $providerStatus): string
    {
        return match (strtoupper(trim($providerStatus))) {
            'COMPLETED', 'SUCCESS' => RequisitionPayment::STATUS_COMPLETED,
            'FAILED', 'PARTIAL_FAILED', 'REVERSED' => RequisitionPayment::STATUS_FAILED,
            'PENDING_RECONCILIATION' => RequisitionPayment::STATUS_PENDING_RECONCILIATION,
            default => RequisitionPayment::STATUS_SUBMITTED,
        };
    }
}
