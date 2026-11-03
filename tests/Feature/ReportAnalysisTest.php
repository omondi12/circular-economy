<?php

namespace Tests\Feature;

use App\Models\ClientReport;
use App\Models\StateCorporation;
use App\Models\User;
use App\Services\ReportAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The analysis of the RMs' client reports (2026-11-03, per the boss): clients from the most critical to the least,
 * the most-reported clients, and which RMs are working well, are new or are not active.
 */
class ReportAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private function report(StateCorporation $client, User $rm, int $daysAgo, string $stage, ?int $followUpInDays = null): void
    {
        ClientReport::create(['state_corporation_id' => $client->id, 'rm_id' => $rm->id, 'report_date' => today()->subDays($daysAgo),
            'engagement_type' => 'Physical visit', 'outcome' => 'Met them', 'current_stage' => $stage, 'next_action' => 'Call back',
            'follow_up_date' => $followUpInDays === null ? null : today()->addDays($followUpInDays)]);
    }

    public function test_it_ranks_clients_from_the_most_critical_and_tells_apart_working_new_and_inactive_rms(): void
    {
        $chris = User::factory()->create(['role' => User::ROLE_RM, 'name' => 'Chris Mosomi', 'phone_number' => '254700000301', 'is_active' => true, 'created_at' => now()->subDays(60)]);
        $quiet = User::factory()->create(['role' => User::ROLE_RM, 'name' => 'Quiet RM', 'phone_number' => '254700000302', 'is_active' => true, 'created_at' => now()->subDays(60)]);
        User::factory()->create(['role' => User::ROLE_RM, 'name' => 'Never RM', 'phone_number' => '254700000303', 'is_active' => true, 'created_at' => now()->subDays(40)]);
        User::factory()->create(['role' => User::ROLE_RM, 'name' => 'New RM', 'phone_number' => '254700000304', 'is_active' => true, 'created_at' => now()->subDays(3)]);

        $client = fn (string $name) => StateCorporation::create(['name' => $name, 'classification' => 'State Corporation', 'phase' => StateCorporation::PHASE_TWO, 'assigned_rm_id' => $chris->id]);
        $baringo = $client('County Government of Baringo');
        $kebs = $client('Kenya Bureau of Standards');
        $closed = $client('Closed Client');

        // Baringo: interested, follow-up passed, five reports. KEBS: an early contact. Closed: not interested.
        foreach ([1, 2, 3, 4] as $d) {
            $this->report($baringo, $chris, $d + 5, 'Meeting held');
        }
        $this->report($baringo, $chris, 1, 'Institution interested', followUpInDays: -2);
        $this->report($kebs, $chris, 2, 'Initial contact attempted');
        $this->report($closed, $quiet, 20, 'Not interested');

        $a = app(ReportAnalysis::class)->build();

        $this->assertSame(['County Government of Baringo', 'Kenya Bureau of Standards', 'Closed Client'], $a['clients']->map(fn ($c) => $c['client']->name)->all());
        $this->assertSame(['critical', 'low', 'closed'], $a['clients']->pluck('band')->all());
        $this->assertSame(2, $a['clients'][0]['overdue_days']);
        $this->assertSame('County Government of Baringo', $a['top_clients'][0]['client']->name);
        $this->assertSame(1, $a['stages']->firstWhere('stage', 'Institution interested')['count']);

        $status = $a['rms']->mapWithKeys(fn ($r) => [$r['rm']->name => $r['status']])->all();
        $this->assertSame(['Chris Mosomi' => 'working', 'New RM' => 'new_waiting', 'Quiet RM' => 'quiet', 'Never RM' => 'never'], $status);
        $this->assertSame(2, $a['headline']['rms_inactive']);
        $this->assertSame(1, $a['headline']['rms_new']);

        config(['services.public_dashboard.key' => 'k']);
        $this->get('/reports/analysis?key=k')->assertOk()
            ->assertSeeInOrder(['Critical clients', 'Clients to prioritise', 'County Government of Baringo', 'follow-up 2 days overdue', 'Critical', 'Kenya Bureau of Standards'])
            ->assertSee('Working well')->assertSee('New, no report yet')->assertSee('Never reported');
        $this->flushSession();
        $this->get('/reports/analysis')->assertRedirect();
    }
}
