<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\GovernmentEntity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * WIC's Orders dashboard: every order (sale LSOs and disposal service orders) on the boss's dashboard link, which
 * Agent 360 then reads (the same rows, OrderBook).
 */
class OrdersDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $grace;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.public_dashboard.key' => 'boss-key', 'services.agent360.key' => 'test-key-123']);
        $this->grace = User::factory()->create(['role' => User::ROLE_RM, 'name' => 'Grace Wambui', 'phone_number' => '254700000301', 'is_active' => true]);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'phone_number' => '254700000302']);
        $ministry = GovernmentEntity::create(['name' => 'Health Institutions', 'level' => GovernmentEntity::LEVEL_MINISTRY, 'type' => 'ministry']);

        $ids = [];
        foreach ([3460, 3560] as $kg) {
            $this->actingAs($this->grace)->post(route('rm.collections.store'), [
                'entity_type' => 'ministry', 'ministry_id' => $ministry->id, 'contact_person_name' => 'Gideon', 'contact_person_number' => '0724629641',
                'lot' => 2, 'category' => 'medical_waste', 'subcategory' => 'infectious', 'unit' => 'kg', 'quantity' => $kg, 'collection_date' => '2026-10-03',
            ]);
            $ids[] = Collection::latest('id')->value('id');
        }
        $this->actingAs($this->admin)->post(route('admin.service-orders.store'), [
            'rm_id' => $this->grace->id, 'reference_number' => '0044', 'customer_name' => 'Mwai Kibaki Referral Hospital', 'order_date' => '2026-09-30',
            'amount' => 1404000, 'document' => UploadedFile::fake()->create('lso.pdf', 50, 'application/pdf'), 'collection_ids' => $ids,
        ]);
        auth()->logout();
    }

    public function test_the_boss_sees_every_order_on_the_dashboard_link_without_documents(): void
    {
        $this->get('/orders')->assertRedirect('/login');

        $this->get('/orders?key=boss-key')->assertOk()
            ->assertSee('0044')->assertSee('Mwai Kibaki Referral Hospital')->assertSee('Grace Wambui')->assertSee('KES 1,404,000')
            ->assertSee('Disposal (Lot 2 service order)')->assertSee('Agent 360 reads these from here')->assertDontSee('Open document');

        $this->get('/orders?kind=sale')->assertOk()->assertDontSee('Mwai Kibaki Referral Hospital');
        $csv = $this->get('/orders/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('0044,"Disposal (Lot 2 service order)","Mwai Kibaki Referral Hospital"', $csv);
    }

    public function test_staff_open_the_document_and_agent_360_reads_the_same_orders(): void
    {
        $this->actingAs($this->admin)->get('/orders')->assertOk()->assertSee('Open document');
        $this->actingAs($this->admin)->get('/')->assertSee('Orders');

        $api = collect($this->getJson('/api/agent360/v1/agents/records?type=orders', ['Authorization' => 'Bearer test-key-123'])->json('data'));
        $this->assertSame(['0044'], $api->pluck('title')->all());
        $this->assertSame('1404000.00', $api->first()['amount']);
    }
}
