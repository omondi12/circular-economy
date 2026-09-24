<?php

namespace App\Services;

use App\Exceptions\NawiriPayrollException;
use App\Models\NawiriTreasuryCredential;
use App\Models\RequisitionPayment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class NawiriPayrollClient
{
    private bool $storedCredentialLoaded = false;

    private ?NawiriTreasuryCredential $storedCredential = null;

    public function submit(RequisitionPayment $payment): array
    {
        $response = $this->withAuthenticationRetry(fn (PendingRequest $request) => $request
            ->withHeader('Idempotency-Key', $payment->idempotency_key)
            ->post('/api/jambopay/business/payroll/wallet', [
                'pin' => $this->config('pin'),
                'reference' => $this->reference($payment),
                'memo' => $this->memo($payment),
                'amount' => intdiv($payment->amount_minor, 100),
                'phoneNumber' => $payment->phone_number,
            ]));

        return $this->decodePayoutResponse($response);
    }

    public function reconcile(RequisitionPayment $payment): array
    {
        if (! $payment->nawiri_payment_id) {
            return $this->submit($payment);
        }

        $response = $this->withAuthenticationRetry(fn (PendingRequest $request) => $request
            ->post("/api/jambopay/business/payroll/wallet/{$payment->nawiri_payment_id}/reconcile"));

        return $this->decodePayoutResponse($response);
    }

    public function authorize(RequisitionPayment $payment, string $reference, string $otp): array
    {
        $response = $this->withAuthenticationRetry(fn (PendingRequest $request) => $request
            ->post("/api/jambopay/business/payroll/wallet/{$payment->nawiri_payment_id}/authorize", [
                'reference' => $reference,
                'otp' => $otp,
            ]));

        return $this->decodePayoutResponse($response);
    }

    public function resendOtp(RequisitionPayment $payment, string $reference): array
    {
        $response = $this->withAuthenticationRetry(fn (PendingRequest $request) => $request
            ->post("/api/jambopay/business/payroll/wallet/{$payment->nawiri_payment_id}/otp", ['reference' => $reference]));

        return $this->decodePayoutResponse($response);
    }

    public function verifyCredentials(string $email, string $password, string $pin): array
    {
        try {
            $response = $this->request()->post('/api/auth/login', [
                'email' => $email,
                'password' => $password,
            ]);
        } catch (ConnectionException $exception) {
            throw new NawiriPayrollException(
                'Nawiri could not be reached. The treasury account was not changed.',
                false,
                previous: $exception,
                field: 'email',
            );
        }

        if (! $response->successful()) {
            $message = in_array($response->status(), [401, 403, 422], true)
                ? 'Nawiri rejected that email or password. The treasury account was not changed.'
                : 'Nawiri could not verify the account right now. The treasury account was not changed.';

            throw new NawiriPayrollException(
                $message,
                false,
                $response->status(),
                field: 'email',
            );
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new NawiriPayrollException(
                'Nawiri returned no access token. The treasury account was not changed.',
                field: 'email',
            );
        }

        try {
            $pinResponse = $this->request()->withToken($token)->post('/api/admin/pin/verify', [
                'pin' => $pin,
            ]);
        } catch (ConnectionException $exception) {
            throw new NawiriPayrollException(
                'Nawiri could not verify the PIN. The treasury account was not changed.',
                false,
                previous: $exception,
                field: 'pin',
            );
        }

        if (! $pinResponse->successful() || $pinResponse->json('valid') !== true) {
            $code = $pinResponse->json('code');
            [$message, $field] = match ($code) {
                'INVALID_PIN' => ['Nawiri rejected that PIN. The treasury account was not changed.', 'pin'],
                'PIN_NOT_SET' => ['This Nawiri account does not have a transaction PIN.', 'pin'],
                'PIN_LOCKED' => ['Nawiri has locked PIN checks after too many failed attempts. Try again later.', 'pin'],
                default => $pinResponse->status() === 404
                    ? ['That Nawiri account does not have admin access.', 'email']
                    : ['Nawiri could not verify that PIN. The treasury account was not changed.', 'pin'],
            };

            throw new NawiriPayrollException($message, false, $pinResponse->status(), field: $field);
        }

        $expiresIn = (int) $response->json('expires_in', 300);
        Cache::put($this->tokenCacheKey($email), $token, max(30, $expiresIn - 30));

        return is_array($response->json()) ? $response->json() : [];
    }

    private function withAuthenticationRetry(callable $operation): Response
    {
        $response = $this->send($operation, $this->accessToken());

        if ($response->status() === 401) {
            Cache::forget($this->tokenCacheKey());
            $response = $this->send($operation, $this->accessToken());
        }

        if (! $response->successful()) {
            $message = $this->errorMessage($response);
            $unknown = $response->status() >= 500 || in_array($response->status(), [408, 429], true);

            throw new NawiriPayrollException($message, $unknown, $response->status());
        }

        return $response;
    }

    private function send(callable $operation, string $token): Response
    {
        try {
            return $operation($this->request()->withToken($token));
        } catch (ConnectionException $exception) {
            throw new NawiriPayrollException(
                'Nawiri did not confirm whether the wallet payment was accepted. Reconcile it before trying again.',
                true,
                previous: $exception,
            );
        }
    }

    private function accessToken(): string
    {
        if ($token = Cache::get($this->tokenCacheKey())) {
            return $token;
        }

        try {
            $response = $this->request()->post('/api/auth/login', [
                'email' => $this->config('email'),
                'password' => $this->config('password'),
            ]);
        } catch (ConnectionException $exception) {
            throw new NawiriPayrollException('Nawiri authentication is unavailable.', false, previous: $exception);
        }

        if (! $response->successful()) {
            throw new NawiriPayrollException('Nawiri authentication failed. Check the payroll account credentials.', false, $response->status());
        }

        $token = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 300);
        if (! is_string($token) || $token === '') {
            throw new NawiriPayrollException('Nawiri authentication returned no access token.');
        }

        Cache::put($this->tokenCacheKey(), $token, max(30, $expiresIn - 30));

        return $token;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->config('base_url'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) $this->config('timeout'));
    }

    private function decodePayoutResponse(Response $response): array
    {
        $payload = $response->json();
        if (! is_array($payload) || ! is_array($payload['payment'] ?? null)) {
            throw new NawiriPayrollException(
                'Nawiri accepted the request but returned an unreadable wallet-payment response. Reconcile this payment.',
                true,
                $response->status(),
            );
        }

        return $payload;
    }

    private function errorMessage(Response $response): string
    {
        $error = $response->json('error');
        $details = $response->json('details');
        $message = is_string($error) && $error !== '' ? $error : 'Nawiri rejected the wallet payment.';

        if (is_string($details) && $details !== '') {
            $message .= ' '.$details;
        }

        return $message;
    }

    private function config(string $key): mixed
    {
        $value = in_array($key, ['email', 'password', 'pin'], true)
            ? $this->storedCredential()?->{$key}
            : config("services.nawiri_payroll.{$key}");
        if ($value === null || $value === '') {
            $message = in_array($key, ['email', 'password', 'pin'], true)
                ? 'A verified Nawiri treasury account is required before payroll can run.'
                : 'NAWIRI_PAYROLL_'.strtoupper($key).' is not configured.';

            throw new NawiriPayrollException($message);
        }

        return $value;
    }

    private function tokenCacheKey(?string $email = null): string
    {
        return 'nawiri-payroll-token:'.sha1((string) config('services.nawiri_payroll.base_url').'|'.($email ?? (string) $this->config('email')));
    }

    private function storedCredential(): ?NawiriTreasuryCredential
    {
        if (! $this->storedCredentialLoaded) {
            $this->storedCredential = NawiriTreasuryCredential::current();
            $this->storedCredentialLoaded = true;
        }

        return $this->storedCredential;
    }

    private function reference(RequisitionPayment $payment): string
    {
        return "REQ-{$payment->requisition_id}-".strtoupper($payment->category)."-{$payment->id}";
    }

    private function memo(RequisitionPayment $payment): string
    {
        return ucfirst($payment->category)." requisition {$payment->requisition_id}";
    }
}
