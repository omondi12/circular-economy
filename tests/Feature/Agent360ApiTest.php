<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ClientReport;
use App\Models\GovernmentEntity;
use App\Models\StateCorporation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Agent 360 (the RM register shared with Adam AHB Souq) reads Westport's RMs and their assigned clients, and
 * creates / blocks their logins through /api/agent360/v1 behind a server-to-server key.
 */
class Agent360ApiTest extends TestCase
{
    use RefreshDatabase;

    private array $headers = ['Authorization' => 'Bearer test-key-123'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.agent360.key' => 'test-key-123']);
    }

    private function makeRm(array $attributes = []): User
    {
        return User::factory()->create(['role' => User::ROLE_RM, 'phone_number' => '254700000301', 'is_active' => true, ...$attributes]);
    }

    public function test_the_api_is_off_without_a_key_and_refuses_a_wrong_key(): void
    {
        $this->getJson('/api/agent360/v1/agents', ['Authorization' => 'Bearer wrong'])->assertUnauthorized();

        config(['services.agent360.key' => '']);
        $this->getJson('/api/agent360/v1/agents', $this->headers)->assertNotFound();
    }

    public function test_it_lists_rms_and_supervisors_but_not_admins_or_demo_accounts(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR, 'phone_number' => '254700000201', 'name' => 'Ann Supervisor']);
        $rm = $this->makeRm(['name' => 'Grace Wambui', 'supervisor_id' => $supervisor->id]);
        User::factory()->create(['role' => User::ROLE_ADMIN, 'phone_number' => '254700000401']);
        User::factory()->create(['role' => User::ROLE_RM, 'phone_number' => '254700000501', 'email' => 'rm@demo.amac-circular.local']);

        $this->getJson('/api/agent360/v1/agents', $this->headers)->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.id', (string) $rm->id)
            ->assertJsonPath('data.1.phone', '254700000301')
            ->assertJsonPath('data.1.role', User::ROLE_RM)
            ->assertJsonPath('data.1.supervisor_id', (string) $supervisor->id);
    }

    public function test_clients_are_the_state_corporations_and_government_entities_assigned_to_the_rm(): void
    {
        $rm = $this->makeRm();
        $other = $this->makeRm(['phone_number' => '254700000302']);

        $ministry = GovernmentEntity::create(['name' => 'Ministry of Testing', 'level' => GovernmentEntity::LEVEL_MINISTRY, 'type' => 'ministry', 'assigned_rm_id' => $rm->id]);
        GovernmentEntity::create(['name' => 'State Dept A', 'level' => GovernmentEntity::LEVEL_STATE_DEPARTMENT, 'type' => 'state_department', 'parent_id' => $ministry->id, 'assigned_rm_id' => $rm->id]);
        GovernmentEntity::create(['name' => 'State Dept B', 'level' => GovernmentEntity::LEVEL_STATE_DEPARTMENT, 'type' => 'state_department', 'parent_id' => $ministry->id, 'assigned_rm_id' => $other->id]);
        foreach (['Kenya Power', 'KenGen', 'KPLC'] as $name) {
            StateCorporation::create(['name' => $name, 'classification' => 'State Corporation', 'phase' => StateCorporation::PHASE_TWO, 'assigned_rm_id' => $rm->id]);
        }
        $client = StateCorporation::first();
        ClientReport::create(['state_corporation_id' => $client->id, 'rm_id' => $rm->id, 'report_date' => today(), 'engagement_type' => 'visit', 'outcome' => 'Met the CEO', 'current_stage' => 'engaged']);

        $figures = $this->getJson('/api/agent360/v1/agents/metrics', $this->headers)->assertOk()->json('data.'.$rm->id);

        $this->assertSame(5, $figures['clients']);
        $this->assertSame(['state_corporations' => 3, 'ministries' => 1, 'state_departments' => 1, 'institutions' => 0], collect($figures['breakdown'])->pluck('value', 'key')->all());
        $this->assertSame(1, collect($figures['activity'])->firstWhere('key', 'reports')['value']);
        $this->assertSame('0.00', collect($figures['activity'])->firstWhere('key', 'rm_commission')['value']);
    }

    public function test_it_creates_an_rm_login_and_returns_the_existing_one_instead_of_a_duplicate(): void
    {
        $response = $this->postJson('/api/agent360/v1/agents', ['name' => 'Felix Letia', 'phone' => '0722 000 111', 'email' => 'Felix@Example.com'], $this->headers)
            ->assertCreated()->assertJsonPath('created', true)->assertJsonPath('data.phone', '254722000111');

        $user = User::where('email', 'felix@example.com')->sole();
        $this->assertSame(User::ROLE_RM, $user->role);
        $this->assertTrue(Hash::check($response->json('temporary_password'), $user->password));
        $this->assertTrue(AuditLog::where('action', 'agent360.rm_created')->exists());

        $this->postJson('/api/agent360/v1/agents', ['name' => 'Felix', 'phone' => '0722000111', 'email' => 'other@example.com'], $this->headers)
            ->assertOk()->assertJsonPath('created', false)->assertJsonPath('data.id', (string) $user->id)->assertJsonPath('temporary_password', null);
    }

    public function test_it_blocks_and_unblocks_an_rm_and_cannot_touch_admins(): void
    {
        $rm = $this->makeRm();

        $this->postJson("/api/agent360/v1/agents/{$rm->id}/deactivate", [], $this->headers)->assertOk()->assertJsonPath('data.active', false);
        $this->assertFalse($rm->fresh()->is_active);

        $this->postJson("/api/agent360/v1/agents/{$rm->id}/activate", [], $this->headers)->assertOk()->assertJsonPath('data.active', true);
        $this->assertTrue($rm->fresh()->is_active);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'phone_number' => '254700000401']);
        $this->postJson("/api/agent360/v1/agents/{$admin->id}/deactivate", [], $this->headers)->assertNotFound();
        $this->assertTrue((bool) $admin->fresh()->is_active);
    }

    public function test_it_lists_the_records_behind_the_figures_with_the_best_known_county(): void
    {
        $rm = $this->makeRm();
        $nakuru = StateCorporation::create(['name' => 'County Government of Nakuru', 'classification' => 'County Government', 'phase' => StateCorporation::PHASE_TWO, 'assigned_rm_id' => $rm->id]);
        $kenGen = StateCorporation::create(['name' => 'KenGen', 'classification' => 'State Corporation', 'phase' => StateCorporation::PHASE_TWO, 'assigned_rm_id' => $rm->id]);
        ClientReport::create(['state_corporation_id' => $nakuru->id, 'rm_id' => $rm->id, 'report_date' => today(), 'engagement_type' => 'visit', 'outcome' => 'Met the CEO', 'current_stage' => 'engaged', 'contact_person' => 'Jane Doe']);
        ClientReport::create(['state_corporation_id' => $kenGen->id, 'rm_id' => $rm->id, 'report_date' => today(), 'engagement_type' => 'call', 'outcome' => 'Follow up', 'current_stage' => 'engaged']);

        $reports = $this->getJson('/api/agent360/v1/agents/records?type=reports', $this->headers)->assertOk()->json('data');

        $this->assertCount(2, $reports);
        $this->assertSame(['County Government of Nakuru', 'Nakuru', (string) $rm->id], [$reports[0]['title'], $reports[0]['county'], $reports[0]['agent_id']]);
        $this->assertSame('Jane Doe', collect($reports[0]['fields'])->firstWhere('key', 'contact')['value']);
        $this->assertNull($reports[1]['county']);

        $clients = $this->getJson('/api/agent360/v1/agents/records?type=clients', $this->headers)->assertOk()->json('data');
        $this->assertSame(['County Government of Nakuru', 'KenGen'], array_column($clients, 'title'));

        foreach (['lsos', 'payments', 'collections'] as $type) {
            $this->getJson('/api/agent360/v1/agents/records?type='.$type, $this->headers)->assertOk()->assertJsonCount(0, 'data');
        }
        $this->getJson('/api/agent360/v1/agents/records?type=other', $this->headers)->assertStatus(422);
    }
}
