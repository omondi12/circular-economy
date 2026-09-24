# Nawiri payment authorization

Only Office Admins can pay requisitions, authorize them with an OTP, resend
codes or reconcile payments. Admins still configure the treasury account.

Pay prepares a direct transfer from the treasury user's JamboPay wallet to
the recipient's wallet through Nawiri. New payments do not use the payout
wallet or auto-debit. The OTP request supplies the treasury user's phone.
The page shows an OTP form with the recipient, amount and reference instead
of a browser confirmation alert.

Existing payout-routed payments keep their route and references. They are
not resubmitted through the direct route. The `transferRoute` response field
identifies `DIRECT_WALLET` or `PAYOUT_WALLET`.

The code goes to Nawiri's payroll authorization endpoint, which submits it
to JamboPay for the saved transfer reference. Westport never stores the OTP
in its payment records, audit entries or old form input. Resend OTP requests
a new code for the same transfer, not another payment.

An accepted code does not mark the requisition paid. Nawiri must report
`COMPLETED`. Automatic status checks update the page when payment state
changes, but do not refresh while an OTP field has focus or contains text.
The existing scheduled reconciliation command remains available when the
page is closed. Invalid codes and unknown outcomes keep the payment active,
preventing another Pay attempt for the same recipient.

## Verification

Run `php artisan test` and `bun run build` from this checkout. The optional
cross-project contract test runs from Nawiri with `WESTPORT_TEST_REPO`,
`WESTPORT_TEST_PHP` and `NAWIRI_TEST_DATABASE_URL` configured. See Nawiri's
`internal/nawiridocs/wallet-balance-source.md` for the command.

Deploy Nawiri's authorization and OTP resend endpoints before deploying
this UI. A live settlement check requires the real JamboPay code for an
authorized payment. Automated tests use a simulated provider and move no
real funds.
