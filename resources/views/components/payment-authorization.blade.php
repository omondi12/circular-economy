@props(['payment'])

@if ($payment->isActive() && auth()->user()->canPayRequisition($payment->requisition))
    <div class="mt-2 space-y-2 text-xs" data-payment-poll
         data-url="{{ route('admin.requisition-payments.reconcile', $payment) }}"
         data-state="{{ json_encode($payment->pollingState()) }}">
        @if ($payment->requiresOtp())
            <p class="font-medium text-ink">Authorize KES {{ number_format($payment->amount(), 0) }} to {{ $payment->phone_number }}</p>
            @if (data_get($payment->provider_response, 'payment.transferRoute') === 'DIRECT_WALLET')
                <p class="text-ink-muted">Enter the JamboPay OTP sent to the treasury account holder's phone for reference {{ $payment->otpReference() }}.</p>
            @else
                <p class="text-ink-muted">Enter the OTP from JamboPay for reference {{ $payment->otpReference() }}.</p>
            @endif
            <form method="POST" action="{{ route('admin.requisition-payments.authorize', $payment) }}" class="space-y-2">
                @csrf
                <input type="hidden" name="reference" value="{{ $payment->otpReference() }}">
                <label class="block text-ink" for="payment-otp-{{ $payment->id }}">JamboPay OTP</label>
                @error('otp')<p class="text-red-700">{{ $message }}</p>@enderror
                <input id="payment-otp-{{ $payment->id }}" type="text" name="otp" inputmode="numeric" autocomplete="one-time-code"
                       pattern="[0-9]{6}" minlength="6" maxlength="6" required
                       class="w-full rounded-md border border-border bg-white px-3 py-2 text-ink" placeholder="Six-digit code">
                <button type="submit" class="rounded-md bg-brand-700 px-3 py-2 font-medium text-white hover:bg-brand-800">Authorize payment</button>
            </form>
            <form method="POST" action="{{ route('admin.requisition-payments.otp', $payment) }}">
                @csrf
                <input type="hidden" name="reference" value="{{ $payment->otpReference() }}">
                <button type="submit" class="text-brand-700 underline">Resend OTP</button>
            </form>
        @endif
        <form method="POST" action="{{ route('admin.requisition-payments.reconcile', $payment) }}">
            @csrf
            <button type="submit" class="text-brand-700 underline">Check payment status</button>
        </form>
    </div>
@endif
