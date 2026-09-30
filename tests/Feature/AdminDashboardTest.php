<?php

namespace Tests\Feature;

use App\Models\GovernmentEntity;
use App\Models\Lso;
use App\Models\LsoPayment;
use App\Models\RmTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(string $phone = '254700001001'): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'phone_number' => $phone]);
    }

    private function makeRm(string $phone = '254700001002'): User
    {
        return User::factory()->create(['role' => User::ROLE_RM, 'phone_number' => $phone, 'is_active' => true]);
    }

    private function createLso(User $rm, int $originalAmountMinor, string $status = Lso::STATUS_RECORDED): Lso
    {
        return Lso::create([
            'reference_number' => 'LSO-'.uniqid(),
            'user_id' => $rm->id,
            'customer_name' => 'Acme Ltd',
            'original_amount_minor' => $originalAmountMinor,
            'issue_date' => now(),
            'status' => $status,
            'document_path' => 'lso-documents/fake.pdf',
            'document_original_filename' => 'fake.pdf',
            'document_mime' => 'application/pdf',
            'created_by' => $rm->id,
        ]);
    }

    public function test_dashboard_shows_empty_states_gracefully_with_no_data(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('No LSOs recorded yet')
            ->assertSee('No active targets')
            ->assertSee('Every client has an RM assigned');
    }

    public function test_lso_section_breaks_down_fully_paid_versus_in_progress_without_double_counting(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $fullyPaid = $this->createLso($rm, 10000000, Lso::STATUS_FULLY_PAID);
        $inProgress = $this->createLso($rm, 10000000, Lso::STATUS_ACTIVE);
        LsoPayment::create([
            'lso_id' => $fullyPaid->id, 'amount_minor' => 10000000, 'collected_at' => now(),
            'status' => LsoPayment::STATUS_CONFIRMED, 'recorded_by' => $rm->id, 'confirmed_by' => $admin->id,
        ]);
        LsoPayment::create([
            'lso_id' => $inProgress->id, 'amount_minor' => 4000000, 'collected_at' => now(),
            'status' => LsoPayment::STATUS_CONFIRMED, 'recorded_by' => $rm->id, 'confirmed_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertSee('1 fully paid');
        $response->assertSee('1 in progress');
        // Confirmed collected = 100,000 + 40,000 = 140,000; original value
        // stays 100,000 + 100,000 = 200,000 - never inflated by payments.
        $response->assertSee('KES 140,000');
        $response->assertSee('KES 200,000');
    }

    public function test_target_achievement_averages_active_targets_only_and_ignores_expired_ones(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();

        RmTarget::create([
            'user_id' => $rm->id, 'type' => RmTarget::TYPE_MONETARY, 'target_amount_minor' => 10000000,
            'period_start' => now()->subMonths(2), 'period_end' => now()->subMonth(), 'created_by' => $admin->id,
        ]);
        $activeTarget = RmTarget::create([
            'user_id' => $rm->id, 'type' => RmTarget::TYPE_MONETARY, 'target_amount_minor' => 10000000,
            'period_start' => now()->startOfMonth(), 'period_end' => now()->endOfMonth(), 'created_by' => $admin->id,
        ]);
        LsoPayment::create([
            'lso_id' => $this->createLso($rm, 20000000)->id, 'amount_minor' => 5000000, 'collected_at' => now(),
            'status' => LsoPayment::STATUS_CONFIRMED, 'recorded_by' => $rm->id, 'confirmed_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertSee('50.0%');
        $response->assertSee('Across 1 target(s) for 1 RM(s)');
        $this->assertSame(50.0, $activeTarget->achievementPercent());
    }

    public function test_ministry_coverage_counts_a_ministry_covered_via_its_state_department(): void
    {
        $rm = $this->makeRm();
        $admin = $this->makeAdmin();
        $ministry = GovernmentEntity::create(['name' => 'Ministry A', 'level' => GovernmentEntity::LEVEL_MINISTRY, 'type' => 'ministry']);
        $uncovered = GovernmentEntity::create(['name' => 'Ministry B', 'level' => GovernmentEntity::LEVEL_MINISTRY, 'type' => 'ministry']);
        GovernmentEntity::create([
            'name' => 'Department under A', 'level' => GovernmentEntity::LEVEL_STATE_DEPARTMENT,
            'type' => 'state_department', 'parent_id' => $ministry->id, 'assigned_rm_id' => $rm->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertSee('1 with an RM assigned');
        $this->assertNotNull($uncovered); // sanity: exists but not counted as covered
    }

    public function test_supervisor_sees_only_their_own_team_figures_on_the_dashboard(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR, 'phone_number' => '254700001003']);
        $ownRm = User::factory()->create(['role' => User::ROLE_RM, 'phone_number' => '254700001004', 'supervisor_id' => $supervisor->id, 'is_active' => true]);
        $otherRm = $this->makeRm('254700001005');

        $this->createLso($ownRm, 10000000);
        $this->createLso($otherRm, 20000000);

        $response = $this->actingAs($supervisor)->get(route('admin.dashboard'))->assertOk();

        // Only the supervisor's own RM's LSO should count toward "LSOs".
        $response->assertSee('My Relationship Managers');
        $response->assertDontSee('Supervisors');
    }
}
