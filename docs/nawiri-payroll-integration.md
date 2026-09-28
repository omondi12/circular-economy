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

An Admin or Office Admin must save a funded Nawiri account from Nawiri Treasury.
Payroll stays disabled until Nawiri verifies the email, password and transaction
PIN through `/api/auth/pin/verify`. A Nawiri customer account can fund payroll
from its own wallet. Nawiri platform admin access is not required. The site
encrypts the saved password and PIN. Only Office Admins can pay requisitions.

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

Only an office admin can press Pay or reconcile a wallet transfer. Regular
admins and supervisors can use the approval screens but cannot move money.

One click creates a payment record with a unique idempotency key and submits it
to Nawiri. The requisition remains unpaid while Nawiri reports `SUBMITTED` or
`PENDING_RECONCILIATION`. It becomes paid only after Nawiri reports
`COMPLETED`.

If the request times out, use Reconcile. Reconciliation checks the original
Nawiri transfer and does not create a second transfer.

Requisition actions update the page in place, preserving filters and scroll
position. If an action's result cannot be confirmed, use Refresh status before
trying again. The browser never retries a payment submission automatically.

## Frontend checks

Run `bun run build` when deploying frontend changes. Generated files in
`public/build` are not committed.

With Bun and `agent-browser` installed, run `bun run test:browser` for the
requisition action and scroll checks. This uses a local fixture with simulated
responses. It does not connect to Nawiri or send money.
