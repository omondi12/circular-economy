# Nawiri requisition payments

The admin requisitions page can send approved transport and airtime amounts to
the requester's Nawiri wallet.

## Deploy

Deploy the matching `nawiri-server` payroll endpoint before deploying this
site. Then run the Laravel migrations:

```bash
php artisan migrate --force
```

Set these variables on the site:

```dotenv
NAWIRI_PAYROLL_BASE_URL=https://nawiri-server-production.up.railway.app
NAWIRI_PAYROLL_TIMEOUT=35
NAWIRI_PAYROLL_RECONCILE_AFTER=60
NAWIRI_PAYROLL_REVERSAL_WINDOW_HOURS=72
```

An admin must save a funded Nawiri admin account from Admin, Nawiri Treasury.
Payroll stays disabled until Nawiri verifies the email, password, admin access
and transaction PIN. The site encrypts the saved password and PIN.

Run Laravel's scheduler every minute so submitted payments reconcile without
waiting for an administrator to open the page:

```bash
php artisan schedule:run
```

Use the platform's cron or scheduler to run that command every minute.

## Staff setup

Each staff account needs the same phone number used by its Nawiri account. The
admin account forms normalize `0712345678`, `712345678`, and `254712345678` to
the stored `254712345678` form.

Existing staff can be updated from Admin, Team Accounts. A requisition cannot
be paid until its requester has a Nawiri phone number.

## Payment behavior

Only an admin can press Pay. Supervisors and office admins can keep using the
existing approval actions, but they cannot move money.

One click creates a payment record with a unique idempotency key and submits it
to Nawiri. The requisition remains unpaid while Nawiri reports `SUBMITTED` or
`PENDING_RECONCILIATION`. It becomes paid only after Nawiri reports
`COMPLETED`.

If the request times out, use Reconcile. Reconciliation checks the original
Nawiri transfer and does not create a second transfer.
