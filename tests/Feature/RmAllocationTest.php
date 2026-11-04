<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ClientReport;
use App\Models\StateCorporation;
use App\Models\SupervisorCounty;
use App\Models\User;
use App\Services\RmAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Allocating by the boss's rules (2026-11-03): no RM above 7 accounts, every supervisor's area 4-5 counties; the
 * operator sets the areas, sees the rules check and balances a team.
 */
class RmAllocationTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private User $milicent;

    private int $phone = 254700000400;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operator = $this->user('Josephine Kimani', User::ROLE_OPERATIONS);
        $this->milicent = $this->user('Milicent Morgan', User::ROLE_SUPERVISOR);
    }

    private function user(string $name, string $role, ?User $supervisor = null): User
    {
        return User::factory()->create(['name' => $name, 'role' => $role, 'phone_number' => (string) $this->phone++, 'is_active' => true, 'supervisor_id' => $supervisor?->id]);
    }

    private function client(string $name, ?User $rm = null, string $classification = 'State Corporation'): StateCorporation
    {
        return StateCorporation::create(['name' => $name, 'classification' => $classification, 'phase' => StateCorporation::PHASE_TWO, 'assigned_rm_id' => $rm?->id]);
    }

    /**
     * @return list<StateCorporation>
     */
    private function counties(int $n): array
    {
        return array_map(fn ($i) => $this->client("County Government of C{$i}", classification: 'County Government'), range(1, $n));
    }

    public function test_areas_take_four_or_five_counties_per_supervisor(): void
    {
        [$a, $b, $c, $d, $e, $f] = $this->counties(6);
        $this->actingAs($this->operator);

        $this->post(route('admin.allocation.areas'), ['area' => [$a->id => $this->milicent->id, $b->id => $this->milicent->id, $c->id => $this->milicent->id]])
            ->assertSessionHasErrors('areas');
        $this->assertSame(0, SupervisorCounty::count());

        $this->post(route('admin.allocation.areas'), ['area' => [$a->id => $this->milicent->id, $b->id => $this->milicent->id, $c->id => $this->milicent->id, $d->id => $this->milicent->id, $e->id => '', $f->id => '']])
            ->assertRedirect(route('admin.allocation.index'));
        $this->assertSame(4, SupervisorCounty::where('supervisor_id', $this->milicent->id)->count());
        $this->assertTrue(AuditLog::where('action', 'supervisor_areas.saved')->exists());

        $this->get(route('admin.allocation.index'))->assertOk()
            ->assertSee('Rules check')->assertSee('Counties without a supervisor')->assertSee('County Government of C5');
    }

    public function test_balancing_a_team_brings_in_its_counties_spreads_them_and_keeps_every_rm_at_seven_or_fewer(): void
    {
        $grace = $this->user('Grace Wambui', User::ROLE_RM, $this->milicent);
        $felix = $this->user('Felix Letia', User::ROLE_RM, $this->milicent);
        $outsider = $this->user('Outside RM', User::ROLE_RM);

        // Grace holds 10 parastatals (has reported on one) and two counties; Felix has nothing
        $parastatals = array_map(fn ($i) => $this->client("Parastatal {$i}", $grace), range(1, 10));
        ClientReport::create(['state_corporation_id' => $parastatals[0]->id, 'rm_id' => $grace->id, 'report_date' => today(), 'engagement_type' => 'Phone call', 'outcome' => 'Spoke', 'current_stage' => 'Awaiting response']);
        $counties = $this->counties(4);
        $counties[0]->update(['assigned_rm_id' => $grace->id]);
        $counties[1]->update(['assigned_rm_id' => $grace->id]);
        $counties[2]->update(['assigned_rm_id' => $outsider->id]);
        foreach ($counties as $county) {
            SupervisorCounty::create(['supervisor_id' => $this->milicent->id, 'state_corporation_id' => $county->id]);
        }

        $this->actingAs($this->operator);
        $this->get(route('admin.allocation.balance', $this->milicent))->assertOk()
            ->assertSee('Nothing has moved yet')->assertSee('County Government of C3')->assertSee('Over 7 accounts');
        $this->assertSame($outsider->id, $counties[2]->fresh()->assigned_rm_id);

        $this->post(route('admin.allocation.balance.apply', $this->milicent))->assertRedirect(route('admin.allocation.index'));

        $holdings = app(RmAllocation::class)->holdings();
        $count = fn (User $rm) => $holdings->filter(fn ($id) => $id === $rm->id)->count();
        $this->assertLessThanOrEqual(7, $count($grace));
        $this->assertLessThanOrEqual(7, $count($felix));
        $this->assertSame(14, $count($grace) + $count($felix));
        // Every area county is in the team, two each; the reported-on client stayed with Grace
        foreach ($counties as $county) {
            $this->assertContains($holdings[$county->id], [$grace->id, $felix->id]);
        }
        $this->assertSame(2, collect($counties)->filter(fn ($c) => $holdings[$c->id] === $felix->id)->count());
        $this->assertSame($grace->id, $holdings[$parastatals[0]->id]);
        $this->assertTrue(AuditLog::where('action', 'client.rm_assigned')->exists());

        $check = app(RmAllocation::class)->check();
        $this->assertTrue($check['over']->isEmpty());
        $this->assertTrue($check['supervisors']->first()['area_ok']);
    }

    public function test_the_operator_can_move_rms_between_supervisors_and_an_rm_cannot_allocate(): void
    {
        $rm = $this->user('New RM', User::ROLE_RM);

        $this->actingAs($this->operator)->get(route('admin.assign-rms', ['view' => 'supervisors']))->assertOk()->assertSee('Areas &amp; rules', false);
        $this->post(route('admin.assign-rms.supervisor.update', $rm), ['supervisor_id' => $this->milicent->id])->assertRedirect();
        $this->assertSame($this->milicent->id, $rm->fresh()->supervisor_id);

        $this->actingAs($this->milicent)->get(route('admin.allocation.index'))->assertForbidden();
    }

    public function test_clients_of_a_blocked_rm_count_as_covered_by_nobody(): void
    {
        $gone = $this->user('Blocked RM', User::ROLE_RM);
        $gone->update(['is_active' => false]);
        $this->client('Kenya Ports Authority', $gone);

        $check = app(RmAllocation::class)->check();

        $this->assertContains('Kenya Ports Authority', $check['unassigned']->all());
    }
}
