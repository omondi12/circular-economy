# Agent 360 API

Agent 360 is the register of relationship managers (RMs) who work on Westport Industrial City and also sell houses on
Adam AHB Souq. It reads Westport's RMs and their figures, and creates / blocks their Westport logins. Adam AHB Souq
exposes the same API.

- Base: `https://circular.westportindustrialcity.co.ke/api/agent360/v1`
- Auth: `Authorization: Bearer <AGENT360_API_KEY>` (`config('services.agent360.key')`). No key = every route answers 404.
- Throttle: 60 requests a minute. JSON only.
- Changes are in the audit log: `agent360.rm_created`, `agent360.rm_deactivated`, `agent360.rm_activated`.

An **agent** is a user with role `rm` or `supervisor`, demo accounts excluded (as `User::assignableRms()`).
Phones are returned as `2547XXXXXXXX` (null when the stored number is not a Kenyan mobile number).

## GET /agents

```json
{"data": [{"id": "6", "name": "GRACE WAMBUI", "phone": "254712345678", "email": "grace@example.com",
           "role": "rm", "supervisor_id": "3", "active": true}]}
```

## GET /agents/metrics

Figures per RM, keyed by `id`. **Clients** = the institutions, organisations and offices assigned to the RM: state
corporations plus government entities (ministries, state departments, institutions) with `assigned_rm_id` = the RM.
RM commission is the same figure as the RM dashboard: 10% of what Westport earned on their confirmed Lot 1 payments
and Lot 2 lots (`RmCommissionService`).

```json
{"data": {"6": {
    "clients": 37,
    "breakdown": [
        {"key": "state_corporations", "label": "State corporations", "value": 33},
        {"key": "ministries", "label": "Ministries", "value": 1},
        {"key": "state_departments", "label": "State departments", "value": 3},
        {"key": "institutions", "label": "Institutions", "value": 0}],
    "activity": [
        {"key": "reports", "label": "Client reports", "value": 11, "format": "count"},
        {"key": "lsos", "label": "LSOs", "value": 2, "format": "count"},
        {"key": "lso_value", "label": "LSO value", "value": "450000.00", "format": "money"},
        {"key": "collections", "label": "Collections", "value": 4, "format": "count"},
        {"key": "westport_earned", "label": "Westport earned (confirmed)", "value": "31500.00", "format": "money"},
        {"key": "rm_commission", "label": "RM commission (10%)", "value": "3150.00", "format": "money"}]}},
 "generated_at": "2026-10-24T18:00:00+03:00"}
```

## POST /agents

Body: `name`, `phone`, `email` (required), `supervisor_id` (optional, must be a supervisor). Creates an `rm` login
with a temporary password: **201** with `created: true` and `temporary_password` (returned once, never stored by
Agent 360). If a user already has that email or phone: **200** with `created: false`, the existing user and
`temporary_password: null`.

## POST /agents/{id}/deactivate, POST /agents/{id}/activate

Blocks / unblocks the login (`is_active`). Blocking also signs them out (remember token rotated, database sessions
removed). Only RMs and supervisors: any other user id answers 404. Returns the agent as in `GET /agents`.

## GET /agents/records?type=reports|lsos|payments|collections|clients

Every record behind the figures, for Agent 360's records pages and regions (`App\Services\Agent360Records`):
client reports, LSOs, LSO payments, collections, and the clients assigned to each RM. Each record:
`{id, agent_id, date, title, status, amount, county, region: null, fields: [{key, label, value, format}]}`.

Westport has no regions, so `county` is the best that is known: a collection's own county, or the county of a
County Government client ("County Government of Nakuru" → Nakuru); null for national bodies. Agent 360 maps
counties to regions. Contact persons' names are included; their phone numbers are not.

## RM single sign-in: POST /sso/agent360

RMs sign in only at Agent 360 (phone, password and an authenticator code); its launchpad posts a signed one-time pass
(field `pass`) here and the RM lands on their RM dashboard. On when `AGENT360_SSO_PUBLIC_KEY` is set (Agent 360's
ed25519 public key); empty = off. The pass is checked for signature, `iss` agent360, `aud` WESTPORT, expiry, one-time
`jti` and an active user with role `rm` (`App\Services\Agent360SsoService`); any failure answers 403 with a link back.
With the key set, an RM signing in with email and password here is sent to Agent 360; staff sign in as before.
No CSRF token on the route (the signature is the check); throttled.
