<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\GovernmentEntity;
use App\Models\StateCorporation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(string $phone = '254700000501'): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'phone_number' => $phone]);
    }

    private function makeRm(string $phone = '254700000502'): User
    {
        return User::factory()->create(['role' => User::ROLE_RM, 'phone_number' => $phone, 'is_active' => true]);
    }

    private function makeSupervisor(string $phone = '254700000503'): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPERVISOR, 'phone_number' => $phone]);
    }

    private function makeClient(string $name): StateCorporation
    {
        return StateCorporation::create(['name' => $name, 'classification' => 'State Corporation', 'phase' => StateCorporation::PHASE_TWO]);
    }

    public function test_admin_can_add_a_new_client(): void
    {
        $admin = $this->makeAdmin();
        $rm = $this->makeRm();

        $this->actingAs($admin)->post(route('admin.assign-rms.clients.store'), [
            'name' => 'ICT Authority of Kenya Test',
            'classification' => 'State Corporation',
            'assigned_rm_id' => $rm->id,
        ])->assertRedirect();

        $client = StateCorporation::where('name', 'ICT Authority of Kenya Test')->first();
        $this->assertNotNull($client);
        $this->assertSame('State Corporation', $client->classification);
        $this->assertSame($rm->id, $client->assigned_rm_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client.created']);
    }

    public function test_adding_a_duplicate_client_name_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $this->makeClient('Existing Client');

        $this->actingAs($admin)->post(route('admin.assign-rms.clients.store'), [
            'name' => 'Existing Client',
            'classification' => 'State Corporation',
        ])->assertSessionHasErrors('name');

        $this->assertSame(1, StateCorporation::where('name', 'Existing Client')->count());
    }

    public function test_a_supervisor_cannot_add_a_client(): void
    {
        $supervisor = $this->makeSupervisor();

        $this->actingAs($supervisor)->post(route('admin.assign-rms.clients.store'), [
            'name' => 'Should Not Exist',
            'classification' => 'State Corporation',
        ])->assertForbidden();

        $this->assertDatabaseMissing('state_corporations', ['name' => 'Should Not Exist']);
    }

    public function test_an_rm_cannot_add_a_client(): void
    {
        $rm = $this->makeRm();

        $this->actingAs($rm)->post(route('admin.assign-rms.clients.store'), [
            'name' => 'Should Not Exist Either',
            'classification' => 'State Corporation',
        ])->assertForbidden();
    }

    public function test_admin_can_delete_a_client_with_no_activity(): void
    {
        $admin = $this->makeAdmin();
        $client = $this->makeClient('Unused Client');

        $this->actingAs($admin)->delete(route('admin.assign-rms.clients.destroy', $client))->assertRedirect();

        $this->assertDatabaseMissing('state_corporations', ['id' => $client->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client.deleted']);
    }

    /**
     * A client with real recorded activity must never be deletable -
     * silently cascading away a client's collection history (or orphaning
     * it) is never acceptable, even for an admin.
     */
    public function test_a_client_with_recorded_collections_cannot_be_deleted(): void
    {
        $admin = $this->makeAdmin();
        $rm = $this->makeRm();
        $client = $this->makeClient('Client With History');

        Collection::create([
            'entity_type' => 'client', 'state_corporation_id' => $client->id, 'entity_name' => $client->name,
            'contact_person_name' => 'Jane Doe', 'contact_person_number' => '0712345678',
            'lot' => 2, 'category' => 'medical_waste', 'subcategory' => 'sharps', 'unit' => 'kg', 'quantity' => 10,
            'collection_date' => now(), 'user_id' => $rm->id, 'relationship_manager' => $rm->name, 'collected_by' => $rm->name,
        ]);

        $this->actingAs($admin)->delete(route('admin.assign-rms.clients.destroy', $client))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseHas('state_corporations', ['id' => $client->id]);
    }

    public function test_a_supervisor_cannot_delete_a_client(): void
    {
        $supervisor = $this->makeSupervisor();
        $client = $this->makeClient('Protected Client');

        $this->actingAs($supervisor)->delete(route('admin.assign-rms.clients.destroy', $client))->assertForbidden();

        $this->assertDatabaseHas('state_corporations', ['id' => $client->id]);
    }

    public function test_new_client_can_be_linked_to_a_state_department(): void
    {
        $admin = $this->makeAdmin();
        $ministry = GovernmentEntity::create(['name' => 'Test Ministry', 'level' => GovernmentEntity::LEVEL_MINISTRY, 'type' => 'ministry']);
        $department = GovernmentEntity::create(['name' => 'Test State Department', 'level' => GovernmentEntity::LEVEL_STATE_DEPARTMENT, 'type' => 'state_department', 'parent_id' => $ministry->id]);

        $this->actingAs($admin)->post(route('admin.assign-rms.clients.store'), [
            'name' => 'Departmental Client',
            'classification' => 'State Corporation',
            'ministry_id' => $department->id,
        ])->assertRedirect();

        $client = StateCorporation::where('name', 'Departmental Client')->first();
        $this->assertSame($department->id, $client->ministry_id);
        $this->assertSame('Test State Department', $client->stateDepartmentDisplay());
    }
}
