<?php

namespace Tests\Feature;

use App\Models\GovernmentEntity;
use App\Models\StateCorporation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditClientAssignmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_flags_clients_hidden_by_a_ministry_only_assignment_and_reports_counties(): void
    {
        $rm = User::factory()->create(['role' => User::ROLE_RM, 'phone_number' => '254700000601', 'is_active' => true, 'name' => 'Jane RM']);

        $ministry = GovernmentEntity::create(['name' => 'Ministry of Energy', 'type' => 'Ministry', 'level' => GovernmentEntity::LEVEL_MINISTRY, 'status' => 'active', 'assigned_rm_id' => $rm->id]);
        $dept = GovernmentEntity::create(['name' => 'State Department for Energy', 'type' => 'State Department', 'level' => GovernmentEntity::LEVEL_STATE_DEPARTMENT, 'status' => 'active', 'parent_id' => $ministry->id]);
        $cog = GovernmentEntity::create(['name' => 'Council of Governors', 'type' => 'Ministry', 'level' => GovernmentEntity::LEVEL_MINISTRY, 'status' => 'active']);

        StateCorporation::create(['name' => 'Kenya Power', 'classification' => 'State Corporation', 'ministry_id' => $dept->id, 'phase' => StateCorporation::PHASE_TWO]);
        StateCorporation::create(['name' => 'County Government of Nakuru', 'classification' => 'County Government', 'ministry_id' => $cog->id, 'phase' => StateCorporation::PHASE_TWO, 'assigned_rm_id' => $rm->id]);
        StateCorporation::create(['name' => 'County Government of Kisumu', 'classification' => 'County Government', 'ministry_id' => $cog->id, 'phase' => StateCorporation::PHASE_TWO]);

        $csv = tempnam(sys_get_temp_dir(), 'audit');

        $this->artisan('clients:audit-assignments', ['--csv' => $csv])
            ->expectsOutputToContain('MINISTRY has an RM but show as UNASSIGNED (1)')
            ->expectsOutputToContain('County Governments: 1 assigned, 1 unassigned')
            ->assertSuccessful();

        $contents = file_get_contents($csv);
        $this->assertMatchesRegularExpression('/ministry has RM but client unassigned",?"?Kenya Power/', $contents);
        $this->assertStringContainsString('County Government of Kisumu', $contents);
        $this->assertSame(1, StateCorporation::whereNotNull('assigned_rm_id')->count(), 'the report must not write anything');
    }
}
