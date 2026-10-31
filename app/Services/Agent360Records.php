<?php

namespace App\Services;

use App\Models\ClientReport;
use App\Models\Collection as WasteCollection;
use App\Models\GovernmentEntity;
use App\Models\Lso;
use App\Models\LsoLot;
use App\Models\LsoPayment;
use App\Models\ServiceOrder;
use App\Models\StateCorporation;
use InvalidArgumentException;

/**
 * The individual records behind Agent 360's figures (GET /api/agent360/v1/agents/records, docs/agent360-api.md):
 * client reports, LSOs, LSO payments, collections and assigned clients, each with the RM it belongs to.
 *
 * Westport has no regions. A record's county is the best that is known: a collection's own county, or the county of
 * a County Government client ("County Government of Nakuru" -> Nakuru); otherwise null (a national body).
 * Agent 360 maps counties to regions.
 *
 * Record: {id, agent_id, date, title, status, amount, county, region (always null), fields: [{key, label, value, format}]}
 */
class Agent360Records
{
    public const TYPES = ['reports', 'lsos', 'payments', 'collections', 'clients', 'orders'];

    /**
     * @return list<array<string, mixed>>
     *
     * @throws InvalidArgumentException for an unknown type
     */
    public function records(string $type): array
    {
        return match ($type) {
            'reports' => $this->reports(),
            'lsos' => $this->lsos(),
            'payments' => $this->payments(),
            'collections' => $this->collections(),
            'clients' => $this->clients(),
            'orders' => $this->orders(),
            default => throw new InvalidArgumentException("Unknown record type [{$type}]."),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reports(): array
    {
        return ClientReport::query()->with('client')->orderBy('id')->get()->map(fn (ClientReport $report) => $this->record(
            id: $report->id,
            agentId: $report->rm_id,
            date: $report->report_date?->toDateString(),
            title: $report->client?->name ?? 'Client #'.$report->state_corporation_id,
            status: $report->current_stage,
            amount: null,
            county: self::countyOf($report->client),
            fields: [
                $this->field('client', 'Client', $report->client?->name),
                $this->field('classification', 'Type of client', $report->client?->classification),
                $this->field('engagement', 'Engagement', $report->engagement_type),
                $this->field('contact', 'Contact person', $report->contact_person),
                $this->field('outcome', 'Outcome', $report->outcome),
                $this->field('next', 'Next action', $report->next_action),
                $this->field('follow_up', 'Follow-up date', $report->follow_up_date?->toDateString()),
                $this->field('comments', 'Comments', $report->comments),
            ],
        ))->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lsos(): array
    {
        return Lso::query()->with(['stateCorporation', 'institution', 'ministry', 'payments'])->orderBy('id')->get()->map(function (Lso $lso) {
            $client = $lso->stateCorporation;
            $confirmed = $lso->payments->where('status', LsoPayment::STATUS_CONFIRMED);

            return $this->record(
                id: $lso->id,
                agentId: $lso->user_id,
                date: $lso->issue_date?->toDateString(),
                title: $lso->reference_number,
                status: $lso->status,
                amount: self::kes($lso->original_amount_minor),
                county: self::countyOf($client),
                fields: [
                    $this->field('customer', 'Customer', $lso->customer_name),
                    $this->field('client', 'Client', $client?->name ?? $lso->institution?->name ?? $lso->ministry?->name),
                    $this->field('description', 'Description', $lso->description),
                    $this->field('value', 'LSO value', self::kes($lso->original_amount_minor), 'money'),
                    $this->field('payments', 'Confirmed payments', $confirmed->count()),
                    $this->field('earned', 'Westport earned', self::kes((int) $confirmed->sum('amount_minor')), 'money'),
                ],
            );
        })->all();
    }

    /**
     * Every order clients have given Westport, for Agent 360's orders dashboard (read only there): sale orders (Lot 1
     * LSOs: the worth of materials Westport takes, and what has been collected and earned on them) and disposal
     * orders (Lot 2 service orders: the client's order for disposal work, priced at the contract rates, and how
     * much of it Finance has confirmed). Ids are "lso-…" and "so-…".
     *
     * @return list<array<string, mixed>>
     */
    private function orders(): array
    {
        $sales = Lso::query()->with(['stateCorporation', 'institution', 'ministry', 'payments'])->orderBy('id')->get()->map(function (Lso $lso) {
            $client = $lso->stateCorporation;
            $confirmed = $lso->payments->where('status', LsoPayment::STATUS_CONFIRMED);

            return $this->record(
                id: 'lso-'.$lso->id,
                agentId: $lso->user_id,
                date: $lso->issue_date?->toDateString(),
                title: $lso->reference_number,
                status: $lso->status,
                amount: self::kes($lso->original_amount_minor),
                county: self::countyOf($client),
                fields: [
                    $this->field('kind', 'Order type', 'Sale (Lot 1 LSO)'),
                    $this->field('customer', 'Customer', $lso->customer_name),
                    $this->field('client', 'Client', $client?->name ?? $lso->institution?->name ?? $lso->ministry?->name),
                    $this->field('value', 'Order value', self::kes($lso->original_amount_minor), 'money'),
                    $this->field('received', 'Collected (confirmed)', self::kes((int) $confirmed->sum(fn ($p) => $p->gross_amount_minor ?? $p->amount_minor)), 'money'),
                    $this->field('earned', 'Westport earned', self::kes((int) $confirmed->sum('amount_minor')), 'money'),
                    $this->field('items', 'Payments confirmed', $confirmed->count(), 'count'),
                    $this->field('description', 'Description', $lso->description),
                ],
            );
        });

        $disposals = ServiceOrder::query()->with(['collections.lsoLot', 'collections.stateCorporation'])->orderBy('id')->get()->map(function (ServiceOrder $order) {
            $lots = $order->collections->map->lsoLot->filter();
            $expected = (int) $lots->sum('expected_revenue_minor');
            $confirmed = (int) $lots->where('status', LsoLot::STATUS_CONFIRMED)->sum('confirmed_revenue_minor');
            $status = match (true) {
                $lots->isNotEmpty() && $lots->every(fn ($l) => $l->status === LsoLot::STATUS_CONFIRMED) => 'confirmed',
                $lots->contains(fn ($l) => $l->status === LsoLot::STATUS_CONFIRMED) => 'partly_confirmed',
                $lots->isNotEmpty() && $lots->every(fn ($l) => $l->status === LsoLot::STATUS_REJECTED) => 'rejected',
                default => 'pending',
            };
            $first = $order->collections->first();

            return $this->record(
                id: 'so-'.$order->id,
                agentId: $order->user_id,
                date: $order->order_date?->toDateString() ?? $order->created_at?->toDateString(),
                title: $order->reference_number,
                status: $status,
                amount: self::kes($order->amount_minor ?? $expected),
                county: self::countyOf($first?->stateCorporation),
                fields: [
                    $this->field('kind', 'Order type', 'Disposal (Lot 2 service order)'),
                    $this->field('customer', 'Customer', $order->customer_name),
                    $this->field('client', 'Client', $first?->stateCorporation?->name ?? $first?->entity_name),
                    $this->field('value', 'Order value', self::kes($order->amount_minor ?? $expected), 'money'),
                    $this->field('expected', 'Contract-rate revenue', self::kes($expected), 'money'),
                    $this->field('received', 'Confirmed revenue', self::kes($confirmed), 'money'),
                    $this->field('earned', 'Westport earned', self::kes($confirmed), 'money'),
                    $this->field('outstanding', 'Not yet confirmed', self::kes(max(0, $expected - $confirmed)), 'money'),
                    $this->field('items', 'Collections', $order->collections->count(), 'count'),
                    $this->field('quantity', 'Quantity', number_format((float) $order->collections->sum('quantity')).' '.($first?->unit ?? '')),
                ],
            );
        });

        return $sales->concat($disposals)->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function payments(): array
    {
        return LsoPayment::query()->with('lso.stateCorporation')->orderBy('id')->get()->map(fn (LsoPayment $payment) => $this->record(
            id: $payment->id,
            agentId: $payment->lso?->user_id,
            date: ($payment->collected_at ?? $payment->created_at)?->toDateString(),
            title: $payment->lso?->reference_number ?? 'LSO payment #'.$payment->id,
            status: $payment->status,
            amount: self::kes($payment->amount_minor),
            county: self::countyOf($payment->lso?->stateCorporation),
            fields: [
                $this->field('customer', 'Customer', $payment->lso?->customer_name),
                $this->field('gross', 'Sale value', $payment->gross_amount_minor !== null ? self::kes($payment->gross_amount_minor) : null, 'money'),
                $this->field('earned', 'Westport earned', self::kes($payment->amount_minor), 'money'),
                $this->field('rm_commission', 'RM commission (10%)', ($c = $payment->rmCommissionMinor()) !== null ? self::kes($c) : null, 'money'),
                $this->field('notes', 'Notes', $payment->notes),
            ],
        ))->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collections(): array
    {
        return WasteCollection::query()->with('stateCorporation')->orderBy('id')->get()->map(fn (WasteCollection $collection) => $this->record(
            id: $collection->id,
            agentId: $collection->user_id,
            date: $collection->collection_date?->toDateString(),
            title: $collection->entity_name ?? $collection->stateCorporation?->name ?? 'Collection #'.$collection->id,
            status: $collection->lso_id ? 'On an LSO' : 'Recorded',
            amount: $collection->commission !== null ? number_format((float) $collection->commission, 2, '.', '') : null,
            county: self::cleanCounty($collection->county) ?? self::countyOf($collection->stateCorporation),
            fields: [
                $this->field('lot', 'Lot', $collection->lot ? 'Lot '.$collection->lot : null),
                $this->field('category', 'Category', $collection->category),
                $this->field('subcategory', 'Sub-category', $collection->subcategory),
                $this->field('quantity', 'Quantity', $collection->quantity !== null ? rtrim(rtrim(number_format($collection->quantity, 2, '.', ''), '0'), '.').' '.$collection->unit : null),
                $this->field('office', 'Office / location', $collection->location_office),
                $this->field('contact', 'Contact person', $collection->contact_person_name),
                $this->field('commission', 'Commission', $collection->commission !== null ? number_format((float) $collection->commission, 2, '.', '') : null, 'money'),
            ],
        ))->all();
    }

    /**
     * The clients assigned to RMs: state corporations and government entities.
     *
     * @return list<array<string, mixed>>
     */
    private function clients(): array
    {
        $corporations = StateCorporation::query()->whereNotNull('assigned_rm_id')->with('ministry')->orderBy('id')->get()
            ->map(fn (StateCorporation $client) => $this->record(
                id: 'sc-'.$client->id,
                agentId: $client->assigned_rm_id,
                date: null,
                title: $client->name,
                status: $client->classification ?? 'State corporation',
                amount: null,
                county: self::countyOf($client),
                fields: [
                    $this->field('type', 'Type of client', $client->classification ?? 'State corporation'),
                    $this->field('ministry', 'Ministry / department', $client->ministry?->name),
                    $this->field('ceo', 'CEO', $client->ceo_name),
                    $this->field('phase', 'Phase', $client->phase ? 'Phase '.$client->phase : null),
                ],
            ));

        $levels = [GovernmentEntity::LEVEL_MINISTRY => 'Ministry', GovernmentEntity::LEVEL_STATE_DEPARTMENT => 'State department', GovernmentEntity::LEVEL_INSTITUTION => 'Institution'];
        $entities = GovernmentEntity::query()->whereNotNull('assigned_rm_id')->with('parent')->orderBy('id')->get()
            ->map(fn (GovernmentEntity $entity) => $this->record(
                id: 'ge-'.$entity->id,
                agentId: $entity->assigned_rm_id,
                date: null,
                title: $entity->name,
                status: $levels[$entity->level] ?? 'Government body',
                amount: null,
                county: null,
                fields: [
                    $this->field('type', 'Type of client', $levels[$entity->level] ?? 'Government body'),
                    $this->field('parent', 'Under', $entity->parent?->name),
                    $this->field('contact', 'Contact person', $entity->contact_person_name),
                ],
            ));

        return $corporations->concat($entities)->values()->all();
    }

    /**
     * "County Government of Nakuru" -> "Nakuru"; any other client is national (null).
     */
    public static function countyOf(?StateCorporation $client): ?string
    {
        if ($client === null || $client->classification !== 'County Government') {
            return null;
        }

        return self::cleanCounty(preg_replace('/^(the\s+)?county\s+government\s+of\s+/i', '', $client->name));
    }

    private static function cleanCounty(?string $county): ?string
    {
        $county = trim((string) preg_replace('/\s+county$/i', '', trim((string) $county)));

        return $county === '' ? null : $county;
    }

    private static function kes(?int $minor): ?string
    {
        return $minor === null ? null : number_format($minor / 100, 2, '.', '');
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private function record(int|string $id, ?int $agentId, ?string $date, string $title, ?string $status, ?string $amount, ?string $county, array $fields): array
    {
        return [
            'id' => (string) $id,
            'agent_id' => $agentId !== null ? (string) $agentId : null,
            'date' => $date,
            'title' => $title,
            'status' => $status !== null ? ucfirst(str_replace('_', ' ', $status)) : null,
            'amount' => $amount,
            'county' => $county,
            'region' => null,
            'fields' => array_values(array_filter($fields, fn (array $f) => $f['value'] !== null && $f['value'] !== '')),
        ];
    }

    /**
     * @return array{key: string, label: string, value: mixed, format: string}
     */
    private function field(string $key, string $label, mixed $value, string $format = 'text'): array
    {
        return ['key' => $key, 'label' => $label, 'value' => $value, 'format' => $format];
    }
}
