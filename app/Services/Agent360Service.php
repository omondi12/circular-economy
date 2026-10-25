<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ClientReport;
use App\Models\Collection as WasteCollection;
use App\Models\GovernmentEntity;
use App\Models\Lso;
use App\Models\LsoLot;
use App\Models\LsoPayment;
use App\Models\StateCorporation;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * What Agent 360 (the RM register shared with Adam AHB Souq) sees of Westport, through /api/agent360/v1:
 * the RMs and supervisors, the clients assigned to each, their activity, and their logins.
 *
 * Clients = the institutions, organisations and offices assigned to the RM: state corporations plus government
 * entities (ministries, state departments, institutions) whose assigned_rm_id is them. RM commission is the same
 * figure as their own dashboard (RmDashboardController): 10% of what Westport earned on their confirmed
 * Lot 1 payments and Lot 2 lots (RmCommissionService).
 */
class Agent360Service
{
    /**
     * @return list<array{id: string, name: string, phone: ?string, email: string, role: string, supervisor_id: ?string, active: bool}>
     */
    public function agents(): array
    {
        return $this->agentQuery()->orderBy('name')->get()->map(fn (User $user) => $this->present($user))->all();
    }

    /**
     * Figures per RM, keyed by user id (strings).
     *
     * @return array<string, array{clients: int, breakdown: list<array<string, mixed>>, activity: list<array<string, mixed>>}>
     */
    public function metrics(): array
    {
        $count = fn (Builder $query, string $column) => $query->whereNotNull($column)
            ->select($column, DB::raw('count(*) as total'))->groupBy($column)->pluck('total', $column);

        $corporations = $count(StateCorporation::query(), 'assigned_rm_id');
        $entities = GovernmentEntity::query()->whereNotNull('assigned_rm_id')
            ->select('assigned_rm_id', 'level', DB::raw('count(*) as total'))->groupBy('assigned_rm_id', 'level')->get()
            ->groupBy('assigned_rm_id');
        $reports = $count(ClientReport::query(), 'rm_id');
        $collections = $count(WasteCollection::query(), 'user_id');
        $lsos = Lso::query()->select('user_id', DB::raw('count(*) as total'), DB::raw('coalesce(sum(original_amount_minor), 0) as value_minor'))
            ->groupBy('user_id')->get()->keyBy('user_id');

        // RM commission, exactly as their dashboard: per confirmed payment / lot
        $commission = [];
        LsoPayment::query()->where('status', LsoPayment::STATUS_CONFIRMED)->with('lso:id,user_id')->each(function (LsoPayment $payment) use (&$commission) {
            $rm = $payment->lso?->user_id;
            if ($rm !== null) {
                $commission[$rm]['earned'] = ($commission[$rm]['earned'] ?? 0) + (int) $payment->amount_minor;
                $commission[$rm]['rm'] = ($commission[$rm]['rm'] ?? 0) + (int) $payment->rmCommissionMinor();
            }
        });
        LsoLot::query()->where('status', LsoLot::STATUS_CONFIRMED)->with('collection:id,user_id')->each(function (LsoLot $lot) use (&$commission) {
            $rm = $lot->collection?->user_id;
            if ($rm !== null && $lot->confirmed_revenue_minor !== null) {
                $commission[$rm]['earned'] = ($commission[$rm]['earned'] ?? 0) + (int) $lot->confirmed_revenue_minor;
                $commission[$rm]['rm'] = ($commission[$rm]['rm'] ?? 0) + (int) $lot->rmCommissionMinor();
            }
        });

        $kes = fn (int $minor) => number_format($minor / 100, 2, '.', '');

        return $this->agentQuery()->pluck('id')->mapWithKeys(function (int $id) use ($corporations, $entities, $reports, $collections, $lsos, $commission, $kes) {
            $levels = ($entities[$id] ?? collect())->pluck('total', 'level');
            $breakdown = [
                ['key' => 'state_corporations', 'label' => 'State corporations', 'value' => (int) ($corporations[$id] ?? 0)],
                ['key' => 'ministries', 'label' => 'Ministries', 'value' => (int) ($levels[GovernmentEntity::LEVEL_MINISTRY] ?? 0)],
                ['key' => 'state_departments', 'label' => 'State departments', 'value' => (int) ($levels[GovernmentEntity::LEVEL_STATE_DEPARTMENT] ?? 0)],
                ['key' => 'institutions', 'label' => 'Institutions', 'value' => (int) ($levels[GovernmentEntity::LEVEL_INSTITUTION] ?? 0)],
            ];

            return [(string) $id => [
                'clients' => array_sum(array_column($breakdown, 'value')),
                'breakdown' => $breakdown,
                'activity' => [
                    ['key' => 'reports', 'label' => 'Client reports', 'value' => (int) ($reports[$id] ?? 0), 'format' => 'count'],
                    ['key' => 'lsos', 'label' => 'LSOs', 'value' => (int) ($lsos[$id]->total ?? 0), 'format' => 'count'],
                    ['key' => 'lso_value', 'label' => 'LSO value', 'value' => $kes((int) ($lsos[$id]->value_minor ?? 0)), 'format' => 'money'],
                    ['key' => 'collections', 'label' => 'Collections', 'value' => (int) ($collections[$id] ?? 0), 'format' => 'count'],
                    ['key' => 'westport_earned', 'label' => 'Westport earned (confirmed)', 'value' => $kes($commission[$id]['earned'] ?? 0), 'format' => 'money'],
                    ['key' => 'rm_commission', 'label' => 'RM commission ('.RmCommissionService::RATE_PERCENT.'%)', 'value' => $kes($commission[$id]['rm'] ?? 0), 'format' => 'money'],
                ],
            ]];
        })->all();
    }

    /**
     * Creates the RM's login with a temporary password. An RM who already has a login (same email or phone) gets
     * that one back instead of a duplicate.
     *
     * @param  array{name: string, phone: string, email: string, supervisor_id?: ?int}  $data
     * @return array{agent: array<string, mixed>, created: bool, temporary_password: ?string}
     */
    public function create(array $data): array
    {
        $email = Str::lower(trim($data['email']));
        $phone = PhoneNumber::normalizeKenyan($data['phone']);

        $existing = User::query()->where('email', $email)->orWhere('phone_number', $phone)->first();

        if ($existing) {
            return ['agent' => $this->present($existing), 'created' => false, 'temporary_password' => null];
        }

        $password = Str::password(12, symbols: false);
        $supervisor = isset($data['supervisor_id']) ? User::where('role', User::ROLE_SUPERVISOR)->find($data['supervisor_id']) : null;

        $user = User::create([
            'name' => trim($data['name']),
            'email' => $email,
            'phone_number' => $phone,
            'password' => $password,
            'role' => User::ROLE_RM,
            'is_active' => true,
            'supervisor_id' => $supervisor?->id,
        ]);

        AuditLog::record('agent360.rm_created', $user, ['name' => $user->name]);

        return ['agent' => $this->present($user), 'created' => true, 'temporary_password' => $password];
    }

    /**
     * Blocks or unblocks an RM's login. Blocking also signs them out (sessions removed, remember token rotated).
     */
    public function setActive(User $user, bool $active): array
    {
        $user->forceFill(['is_active' => $active] + ($active ? [] : ['remember_token' => Str::random(60)]))->save();

        if (! $active && config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        AuditLog::record($active ? 'agent360.rm_activated' : 'agent360.rm_deactivated', $user, ['name' => $user->name]);

        return $this->present($user);
    }

    /**
     * One RM or supervisor by id; any other user (admin, operations …) is not reachable through the API.
     */
    public function find(string $id): ?User
    {
        return ctype_digit($id) ? $this->agentQuery()->whereKey((int) $id)->first() : null;
    }

    /**
     * @return Builder<User>
     */
    private function agentQuery(): Builder
    {
        // Demo accounts excluded the same way as assignableRms()
        return User::query()->whereIn('role', [User::ROLE_RM, User::ROLE_SUPERVISOR])
            ->where('email', 'not like', '%@demo.amac-circular.local');
    }

    /**
     * @return array{id: string, name: string, phone: ?string, email: string, role: string, supervisor_id: ?string, active: bool}
     */
    private function present(User $user): array
    {
        $phone = PhoneNumber::normalizeKenyan($user->phone_number);

        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'phone' => preg_match('/^254[17]\d{8}$/', $phone) ? $phone : null,
            'email' => $user->email,
            'role' => $user->role,
            'supervisor_id' => $user->supervisor_id ? (string) $user->supervisor_id : null,
            'active' => (bool) $user->is_active,
        ];
    }
}
