# Lot 1 / Lot 2 pricing

Source: Tender No. TNT/KEPDA/011/2026-2027, award letter from the State
Department for Public Investments and Assets Management / Kenya Procurement
and Disposal Agency to M/S Westport Industrial Limited, 17 Aug 2026. Rates
below are authoritative contract facts unless explicitly marked as an
implementation assumption.

## 1. Lot 1 commission (contract fact)

Lot 1 ("Sale by Public Auction of the identified and recommended Equipment,
motor vehicles, assets, stores, scrap, tires, etc.") is paid as a tiered
commission on the realized sale value, not a per-unit rate:

- 10% of the first KES 100,000
- 7% of any amount above KES 100,000

`App\Services\LotPricingService::calculateLot1CommissionMinor()` implements
this exactly, in minor units (KES x 100), with a single `round()` at each
tier boundary rather than floating-point accumulation. Worked examples the
implementation is pinned against: KES 50,000 -> 5,000; KES 100,000 -> 10,000;
KES 150,000 -> 13,500; KES 1,000,000 -> 73,000.

**An LSO's payments must never each re-claim the 10% tier independently.**
`calculateLot1IncrementalCommissionMinor(priorCumulativeGrossMinor,
thisPaymentGrossMinor)` prices one payment's marginal contribution against
whatever's already been confirmed under the same LSO - e.g. two KES 80,000
payments total the correct KES 14,200 (10% of the first 100,000 of the true
160,000, 7% of the rest), never KES 16,000 (10% applied to each
independently). `LsoController::confirmPayment()` recomputes this
authoritatively at confirmation time, using cumulative *confirmed* gross
value excluding the payment being confirmed - confirmation order is the
only point at which "what's already been counted" is deterministic (record
order isn't: multiple payments can be recorded before any of them are
confirmed). `storePayment()` computes the same formula against whatever's
confirmed *so far* purely to show a provisional figure before confirmation;
it is not trusted as final.

**Assumption (not contract text):** the KES 100,000 threshold is evaluated
per LSO's own cumulative confirmed sale value, not per individual lot/item
and not cumulative across the wider framework period. The contract doesn't
state a scope; this is the interpretation requiring no state beyond what a
single LSO's own payments already carry. If the business confirms a
different scope (e.g. per-item, or cumulative across a quarter), the
threshold's meaning in `LotPricingService` needs to change accordingly -
it currently applies independently to each Lot 1 payment's own gross sale
value at the point it's recorded.

## 2. Lot 2 rates (contract fact)

| Category | Unit | Rate |
|---|---|---|
| Medical and Industrial waste | Kg | KES 200 |
| Liquid waste | Ltr | KES 20 |
| E-waste | Kg | KES 120 |
| Solid Waste | Ton | KES 6,500 |
| Any Other Waste | Kg | KES 250 |

Corroborated independently by Westport's own internal panel/partners notes.
Charge = quantity x rate, computed at Collection-recording time and stored
on `LsoLot` (`rate_minor`, `expected_revenue_minor`) since it's fully known
the moment category/unit/quantity are known - unlike Lot 1, nothing here
depends on a later payment event.

## 3. Category mapping (assumption, not contract text)

`WasteCategories`'s Lot 2 taxonomy has 7 categories; the contract defines 5
pricing buckets. `LotPricingService::CATEGORY_MAP`:

| App category | Contract bucket |
|---|---|
| `medical_waste` | Medical and Industrial waste |
| `industrial_hazardous` | Medical and Industrial waste |
| `liquid_waste` | Liquid waste |
| `ewaste` | E-waste |
| `solid_waste` | Solid Waste |
| `other_waste` | Any Other Waste |
| `construction_waste` | **unmapped** |

`medical_waste` and `industrial_hazardous` sharing one bucket is directly
supported by the contract's own wording ("Medical and Industrial waste" as
one line item). `other_waste` -> "Any Other Waste" is the direct semantic
match. `construction_waste` has no contractual rate at all and is
deliberately left unmapped - its own units (tonnes/m3) don't even match
"Any Other Waste"'s Kg basis, so folding it in would silently misprice it.
A `construction_waste` Collection still records normally; its `LsoLot` just
stays unpriced (`rate_minor`/`expected_revenue_minor` both null).

## 4. Unit validation, not conversion (assumption)

Each contract bucket requires an exact unit match - `medical_waste`/
`industrial_hazardous`/`ewaste`/`other_waste` require `kg`, `liquid_waste`
requires `litres`, `solid_waste` requires `tonnes`. `WasteCategories` allows
some of these categories to be recorded in other units too (e.g. `ewaste` in
tonnes, `liquid_waste` in m3) - when that happens, `calculateLot2ChargeMinor()`
returns `null` rather than guessing a conversion factor (density, etc. are
business inputs this service has no authority to invent). The `LsoLot` still
gets created; it's simply left unpriced.

## 5. Lot 1 payment model (implementation decision)

`LsoPayment.amount_minor` keeps its existing meaning everywhere unchanged -
the money that counts toward outstanding balances, RM targets, and
dashboard totals. A new nullable `gross_amount_minor` column preserves the
underlying realized sale value for a Lot 1 payment recorded via the
commission calculator, purely for audit/transparency - it is never summed
into any existing total. `LsoPayment::netAmountMinor()` = gross - commission,
computed on read, not stored (consistent with how `Lso::outstandingMinor()`
is already computed rather than persisted).

Recording a payment (`LsoController::storePayment()`) accepts either:
- `amount` (KES) - the exact pre-existing behaviour, stored directly as
  `amount_minor`, `gross_amount_minor` stays null; or
- `gross_amount` (KES) - the realized sale value; the server computes the
  contractual commission and stores that as `amount_minor`, with
  `gross_amount_minor` set to the entered value.

This is additive and backward compatible: every payment recorded before
this feature, and every payment that doesn't use `gross_amount`, behaves
exactly as before.

## 6. Lot 1 + Lot 2 workflow (implementation decision, building on existing behavior)

Every `Lso` record is a Lot 1 (auction) order by construction - Lot 2
Collections have never created one (`Collection.lso_id` stays null for
Lot 2, unchanged by this work; see `test_lot_2_collection_never_creates_an_lso`).
Lot 2 now gets its own contract-priced `LsoLot` at recording time (standalone,
reached via `Collection::lsoLot()`, never via `Lso::lots()`), without
requiring an `Lso` parent - Lot 2 has no reference number/document/appraised-
value concept in the current business workflow, so nothing about that
workflow changes. Lot 1 and Lot 2 pricing mechanisms therefore never mix:
Lot 1's tiered commission runs on `Lso`/`LsoPayment`; Lot 2's per-unit rate
runs on standalone `LsoLot` rows. `Lso::expectedRevenueMinor()` (summing all
lots' `expected_revenue_minor`) is correct for a hypothetical Lot-2-only LSO,
but will always return `null` for every LSO that exists today, since a Lot 1
LSO's lots never carry a stored per-lot revenue figure - its commission is
realized per confirmed payment instead, shown as "Confirmed Collected."

## 7. What's still deferred / not built

- No pricing/rate table with effective dates - `LotPricingService`'s
  constants are the single source of truth for these specific contract
  rates. If the rates change or a new tender supersedes this one, the
  constants change; nothing here versions historical calculations against
  a superseded rate.
- Lot 2 `LsoLot`s still aren't visible on the admin LSO list itself (that
  list is scoped to `Lso` records, which are Lot 1 only) - but their
  revenue is now aggregated per-RM on the existing RM Performance page
  (`AdminController::rmPerformance()`, "Lot 2 Revenue" column), which
  seemed like the right existing report to extend rather than building a
  new one. Only priced lots are summed; unpriced ones are shown as a
  separate count, never folded into the total as KES 0.
- No UI enforcement preventing an admin from manually setting an `Lso`'s
  status in a way that contradicts its payment ledger - unchanged from
  before this feature.
- **Fixed:** the overpayment ceiling guard in `confirmPayment()` now
  compares against the realized *sale value* for a gross-based payment,
  not the commission. `original_amount_minor` is "the value stated on the
  physical LSO document" (see `Lso`'s docblock) - the appraised worth of
  the goods being auctioned - which is only ever commensurate with the
  sale value a buyer actually paid, never with Westport's 7-10% commission
  slice of it. Comparing cumulative commission against the full appraised
  value (the original check) would almost never trip, since commission is
  always a small fraction of the value it's derived from - e.g. a gross
  sale of KES 60,000 against a KES 50,000 appraised LSO produces only KES
  6,000 commission, which sails under a KES 50,000 ceiling even though the
  underlying sale has already exceeded it. The guard now sums
  `COALESCE(gross_amount_minor, amount_minor)` across confirmed payments -
  falling back to `amount_minor` for a plain payment (where it already
  *is* the value collected, unchanged from before) and using
  `gross_amount_minor` whenever present. See
  `test_confirming_a_gross_based_payment_is_blocked_when_the_sale_value_exceeds_the_original_amount`.
  This is scoped to the ceiling check only - it does not change what
  counts toward `confirmedCollectedMinor()`/outstanding/dashboard totals
  (still commission for a gross-based payment, per section 5), which is a
  separate, already-decided design choice.
- No idempotency guard on `LsoPayment` creation (unlike
  `RequisitionPayment`, which has one) - a double form-submission could
  create two payment rows for what the RM intended as one payment. This
  predates this feature (the plain-`amount` path has always had this gap)
  and applies equally to both payment-input modes; not introduced or
  worsened by gross-value support.
