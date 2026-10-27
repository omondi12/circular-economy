<?php

namespace Tests\Feature;

use App\Models\ClientReport;
use App\Models\StateCorporation;
use App\Models\User;
use App\Services\ReportActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reports page shows when each RM last logged a report, quietest first (2026-10-27, per the boss).
 */
class ReportActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_rm_is_listed_with_their_last_report_never_and_longest_first(): void
    {
        $recent = User::factory()->create(['role' => User::ROLE_RM, 'name' => 'Recent RM', 'phone_number' => '254700000301', 'is_active' => true]);
        $quiet = User::factory()->create(['role' => User::ROLE_RM, 'name' => 'Quiet RM', 'phone_number' => '254700000302', 'is_active' => true]);
        User::factory()->create(['role' => User::ROLE_RM, 'name' => 'Never RM', 'phone_number' => '254700000303', 'is_active' => true]);
        $client = StateCorporation::create(['name' => 'KenGen', 'classification' => 'State Corporation', 'phase' => StateCorporation::PHASE_TWO]);

        foreach ([[$recent, 1], [$recent, 3], [$quiet, 12]] as [$rm, $daysAgo]) {
            ClientReport::create(['state_corporation_id' => $client->id, 'rm_id' => $rm->id, 'report_date' => today()->subDays($daysAgo),
                'engagement_type' => 'Phone call', 'outcome' => 'Spoke to them', 'current_stage' => 'Awaiting response']);
        }

        $rows = app(ReportActivity::class)->lastByRm();

        $this->assertSame(['Never RM', 'Quiet RM', 'Recent RM'], $rows->map(fn ($r) => $r['rm']->name)->all());
        $this->assertSame([null, 12, 1], $rows->pluck('days')->all());
        $this->assertSame([true, true, false], $rows->pluck('quiet')->all());
        $this->assertSame(2, $rows[2]['week']);

        config(['services.public_dashboard.key' => 'k']);
        $this->get('/reports?key=k')->assertOk()->assertSeeInOrder(['Last report by RM', 'Never RM', 'Never', 'Quiet RM', '12 days ago', 'Recent RM', 'yesterday']);
    }
}
