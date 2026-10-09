<?php

namespace Tests\Feature;

use App\Models\Requisition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin Requisitions page's "Today" stat row and table previously
 * always meant literally today (2026-10-02, per the boss's date-filter
 * request) - this proves the selected working_day becomes the page's
 * whole reporting date, server-side, not just a cosmetic label.
 */
class AdminRequisitionsDateFilterTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'phone_number' => '254700000601']);
    }

    private function makeRequester(): User
    {
        return User::factory()->create(['role' => User::ROLE_RM, 'phone_number' => '254700000602']);
    }

    private function makeRequisition(User $requester, string $workingDay, int $transportAmount = 1000): Requisition
    {
        return Requisition::create([
            'requester_id' => $requester->id,
            'institution_visiting' => 'Treasury',
            'working_day' => $workingDay,
            'recipient_phone_numbers' => ['254733333333'],
            'transport_requested_at' => now(),
            'transport_amount_requested' => $transportAmount,
            'transport_status' => Requisition::STATUS_PENDING,
            'airtime_requested_at' => now(),
            'airtime_amount_requested' => 0,
        ]);
    }

    public function test_default_view_shows_only_todays_requisitions(): void
    {
        $admin = $this->makeAdmin();
        $requester = $this->makeRequester();

        $today = $this->makeRequisition($requester, now()->toDateString(), 1000);
        $yesterday = $this->makeRequisition($requester, now()->subDay()->toDateString(), 2000);

        $response = $this->actingAs($admin)->get(route('admin.requisitions.index'));
        $response->assertOk();

        $ids = $response->viewData('requisitions')->pluck('id');
        $this->assertTrue($ids->contains($today->id));
        $this->assertFalse($ids->contains($yesterday->id));

        $this->assertSame(1, $response->viewData('todayStats')['totalCount']);
    }

    public function test_selecting_a_specific_date_shows_only_that_days_requisitions_and_excludes_today(): void
    {
        $admin = $this->makeAdmin();
        $requester = $this->makeRequester();

        $today = $this->makeRequisition($requester, now()->toDateString(), 1000);
        $selectedDay = now()->subDays(3)->toDateString();
        $selected = $this->makeRequisition($requester, $selectedDay, 2500);

        $response = $this->actingAs($admin)->get(route('admin.requisitions.index', ['date' => $selectedDay]));
        $response->assertOk();

        $ids = $response->viewData('requisitions')->pluck('id');
        $this->assertTrue($ids->contains($selected->id));
        $this->assertFalse($ids->contains($today->id));
    }

    public function test_stat_cards_reflect_the_selected_date_not_today(): void
    {
        $admin = $this->makeAdmin();
        $requester = $this->makeRequester();

        $this->makeRequisition($requester, now()->toDateString(), 1000);
        $selectedDay = now()->subDays(2)->toDateString();
        $this->makeRequisition($requester, $selectedDay, 2500);
        $this->makeRequisition($requester, $selectedDay, 1500);

        $response = $this->actingAs($admin)->get(route('admin.requisitions.index', ['date' => $selectedDay]));

        $stats = $response->viewData('todayStats');
        $this->assertSame(2, $stats['totalCount']);
        $this->assertSame(4000.0, (float) $stats['transportRequested']);
    }

    public function test_selected_date_persists_in_the_filter_and_heading(): void
    {
        $admin = $this->makeAdmin();
        $selectedDay = now()->subDays(5)->toDateString();

        $response = $this->actingAs($admin)->get(route('admin.requisitions.index', ['date' => $selectedDay]));
        $response->assertOk();

        $this->assertSame($selectedDay, $response->viewData('filters')['date']);
        $response->assertSee('value="'.$selectedDay.'"', false);
        $response->assertSee('Selected Day');
    }

    /**
     * The daily stat tiles ("Requests Today", "Paid Today", etc.) must
     * never keep saying "Today" once a different working day is selected -
     * that would misrepresent a past (or future) day's figures as today's
     * activity.
     */
    public function test_stat_tile_labels_drop_today_wording_when_a_different_date_is_selected(): void
    {
        $admin = $this->makeAdmin();
        $selectedDay = now()->subDays(4);

        $response = $this->actingAs($admin)->get(route('admin.requisitions.index', ['date' => $selectedDay->toDateString()]));
        $response->assertOk();

        $response->assertDontSee('Requests Today');
        $response->assertDontSee('Requested Today');
        $response->assertDontSee('Approved Today');
        $response->assertDontSee('Paid Today');
        $response->assertDontSee('Outstanding Today');
        $response->assertSee('Requests '.$selectedDay->format('d M'));
    }

    public function test_stat_tile_labels_keep_today_wording_for_the_default_view(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('admin.requisitions.index'));
        $response->assertOk();

        $response->assertSee('Requests Today');
        $response->assertSee('Paid Today');
    }

    /**
     * The date input must stay empty when no explicit filter is active -
     * pre-filling it with today's date would get resubmitted the moment
     * the admin changes an unrelated filter (status/requester auto-submit
     * on change), silently pinning the page to whatever "today" was when
     * it first loaded instead of tracking the real default.
     */
    public function test_date_input_is_empty_when_no_explicit_date_filter_is_active(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('admin.requisitions.index'));
        $response->assertOk();

        $response->assertSee('name="date" value=""', false);
        $response->assertDontSee('name="date" value="'.now()->toDateString().'"', false);
    }

    public function test_date_input_keeps_the_explicit_value_when_a_date_filter_is_active(): void
    {
        $admin = $this->makeAdmin();
        $selectedDay = now()->subDays(2)->toDateString();

        $response = $this->actingAs($admin)->get(route('admin.requisitions.index', ['date' => $selectedDay]));
        $response->assertOk();

        $response->assertSee('name="date" value="'.$selectedDay.'"', false);
    }

    public function test_an_unparsable_date_falls_back_to_today_rather_than_erroring(): void
    {
        $admin = $this->makeAdmin();
        $requester = $this->makeRequester();
        $today = $this->makeRequisition($requester, now()->toDateString());

        $response = $this->actingAs($admin)->get(route('admin.requisitions.index', ['date' => 'not-a-real-date']));
        $response->assertOk();

        $ids = $response->viewData('requisitions')->pluck('id');
        $this->assertTrue($ids->contains($today->id));
    }

    public function test_clearing_the_date_returns_to_todays_default_view(): void
    {
        $admin = $this->makeAdmin();
        $requester = $this->makeRequester();
        $today = $this->makeRequisition($requester, now()->toDateString());

        // Simulates following the "Clear date" link.
        $response = $this->actingAs($admin)->get(route('admin.requisitions.index'));
        $response->assertOk();

        $this->assertNull($response->viewData('filters')['date']);
        $this->assertTrue($response->viewData('workingDate')->isToday());
        $this->assertTrue($response->viewData('requisitions')->pluck('id')->contains($today->id));
    }

    public function test_pagination_links_preserve_the_selected_date(): void
    {
        $admin = $this->makeAdmin();
        $requester = $this->makeRequester();
        $selectedDay = now()->subDay()->toDateString();

        for ($i = 0; $i < 35; $i++) {
            $this->makeRequisition($requester, $selectedDay);
        }

        $response = $this->actingAs($admin)->get(route('admin.requisitions.index', ['date' => $selectedDay]));
        $response->assertOk();
        $response->assertSee('date='.$selectedDay, false);
    }
}
