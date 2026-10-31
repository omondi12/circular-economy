<?php

namespace App\Services;

use App\Models\Lso;
use App\Models\LsoLot;
use App\Models\LsoPayment;
use App\Models\ServiceOrder;
use App\Models\StateCorporation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Every order clients have given Westport, in one shape: the source for WIC's Orders dashboard and for the orders
 * Agent 360 reads (Agent360Records), so both always agree. Orders are entered only here, in WIC.
 *
 * - Sale orders: Lot 1 LSOs (the worth of the materials Westport takes; collected and earned from confirmed payments).
 * - Disposal orders: Lot 2 service orders (the client's order for disposal work, priced at the contract rates;
 *   confirmed as Finance confirms each collection).
 *
 * Amounts are minor units (cents).
 */
class OrderBook
{
    public const SALE = 'sale';

    public const DISPOSAL = 'disposal';

    public const KINDS = [self::SALE => 'Sale (Lot 1 LSO)', self::DISPOSAL => 'Disposal (Lot 2 service order)'];

    /**
     * @return Collection<int, array{key: string, kind: string, kind_label: string, id: int, reference: string, customer: ?string, client: ?string, client_record: ?StateCorporation, rm_id: ?int, rm: ?User, date: ?Carbon, status: string, value: int, received: int, earned: int, expected: ?int, outstanding: ?int, items: int, quantity: ?string, description: ?string}>
     */
    public function rows(): Collection
    {
        $sales = Lso::query()->with(['user:id,name', 'stateCorporation', 'institution', 'ministry', 'payments'])->get()->map(function (Lso $lso) {
            $confirmed = $lso->payments->where('status', LsoPayment::STATUS_CONFIRMED);

            return [
                'key' => 'lso-'.$lso->id,
                'kind' => self::SALE,
                'kind_label' => self::KINDS[self::SALE],
                'id' => $lso->id,
                'reference' => (string) $lso->reference_number,
                'customer' => $lso->customer_name,
                'client' => $lso->stateCorporation?->name ?? $lso->institution?->name ?? $lso->ministry?->name,
                'client_record' => $lso->stateCorporation,
                'rm_id' => $lso->user_id,
                'rm' => $lso->user,
                'date' => $lso->issue_date,
                'status' => (string) $lso->status,
                'value' => (int) $lso->original_amount_minor,
                'received' => (int) $confirmed->sum(fn ($p) => $p->gross_amount_minor ?? $p->amount_minor),
                'earned' => (int) $confirmed->sum('amount_minor'),
                'expected' => null,
                // Not yet collected on the order's value (nothing is settled until it is collected)
                'outstanding' => max(0, (int) $lso->original_amount_minor - (int) $confirmed->sum(fn ($p) => $p->gross_amount_minor ?? $p->amount_minor)),
                'items' => $confirmed->count(),
                'quantity' => null,
                'description' => $lso->description,
            ];
        });

        $disposals = ServiceOrder::query()->with(['user:id,name', 'collections.lsoLot', 'collections.stateCorporation'])->get()->map(function (ServiceOrder $order) {
            $lots = $order->collections->map->lsoLot->filter();
            $expected = (int) $lots->sum('expected_revenue_minor');
            $confirmed = (int) $lots->where('status', LsoLot::STATUS_CONFIRMED)->sum('confirmed_revenue_minor');
            $first = $order->collections->first();

            return [
                'key' => 'so-'.$order->id,
                'kind' => self::DISPOSAL,
                'kind_label' => self::KINDS[self::DISPOSAL],
                'id' => $order->id,
                'reference' => (string) $order->reference_number,
                'customer' => $order->customer_name,
                'client' => $first?->stateCorporation?->name ?? $first?->entity_name,
                'client_record' => $first?->stateCorporation,
                'rm_id' => $order->user_id,
                'rm' => $order->user,
                'date' => $order->order_date ?? $order->created_at,
                'status' => match (true) {
                    $lots->isNotEmpty() && $lots->every(fn ($l) => $l->status === LsoLot::STATUS_CONFIRMED) => 'confirmed',
                    $lots->contains(fn ($l) => $l->status === LsoLot::STATUS_CONFIRMED) => 'partly_confirmed',
                    $lots->isNotEmpty() && $lots->every(fn ($l) => $l->status === LsoLot::STATUS_REJECTED) => 'rejected',
                    default => 'pending',
                },
                'value' => (int) ($order->amount_minor ?? $expected),
                'received' => $confirmed,
                'earned' => $confirmed,
                'expected' => $expected,
                'outstanding' => max(0, $expected - $confirmed),
                'items' => $order->collections->count(),
                'quantity' => number_format((float) $order->collections->sum('quantity')).' '.($first?->unit ?? ''),
                'description' => $order->notes,
            ];
        });

        return $sales->concat($disposals)->sortByDesc(fn ($r) => $r['date']?->timestamp ?? 0)->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{count: int, value: int, received: int, earned: int, outstanding: int, kinds: Collection, statuses: Collection, rms: Collection, clients: Collection, months: list<array{label: string, count: int, value: int}>}
     */
    public function summary(Collection $rows): array
    {
        $group = fn (callable $by, ?callable $label = null) => $rows->groupBy($by)->map(fn (Collection $g, $key) => [
            'key' => (string) $key, 'label' => $label ? $label($key, $g) : (string) $key, 'count' => $g->count(), 'value' => (int) $g->sum('value'), 'received' => (int) $g->sum('received'),
        ])->sortByDesc('value')->values();

        return [
            'count' => $rows->count(),
            'value' => (int) $rows->sum('value'),
            'received' => (int) $rows->sum('received'),
            'earned' => (int) $rows->sum('earned'),
            'outstanding' => (int) $rows->sum(fn ($r) => (int) $r['outstanding']),
            'kinds' => $group(fn ($r) => $r['kind'], fn ($k) => self::KINDS[$k] ?? $k),
            'statuses' => $group(fn ($r) => $r['status'], fn ($k) => self::statusLabel((string) $k)),
            'rms' => $group(fn ($r) => (string) $r['rm_id'], fn ($k, $g) => $g->first()['rm']?->name ?? 'Not assigned'),
            'clients' => $group(fn ($r) => $r['customer'] ?? $r['client'] ?? '—')->take(8),
            'months' => collect(range(11, 0))->map(function (int $back) use ($rows) {
                $month = now()->startOfMonth()->subMonths($back);
                $in = $rows->filter(fn ($r) => $r['date']?->isSameMonth($month));

                return ['label' => $month->format('M'), 'count' => $in->count(), 'value' => (int) $in->sum('value')];
            })->all(),
        ];
    }

    public static function statusLabel(string $status): string
    {
        return ucfirst(str_replace('_', ' ', $status));
    }
}
