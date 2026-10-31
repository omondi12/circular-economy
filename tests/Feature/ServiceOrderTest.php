<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\GovernmentEntity;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Lot 2 service orders: Finance/Admin attach a client's order (e.g. a hospital's Local Service Order) and its scanned
 * document to the RM's Lot 2 collections it covers; the document opens only for people who can see that RM.
 */
class ServiceOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $grace;

    private User $admin;

    private GovernmentEntity $ministry;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->grace = User::factory()->create(['role' => User::ROLE_RM, 'name' => 'Grace Wambui', 'phone_number' => '254700000301', 'is_active' => true]);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'phone_number' => '254700000302']);
        $this->ministry = GovernmentEntity::create(['name' => 'National Referral and Specialized Health Institutions', 'level' => GovernmentEntity::LEVEL_MINISTRY, 'type' => 'ministry']);
    }

    private function collect(User $rm, int $kg): Collection
    {
        $this->actingAs($rm)->post(route('rm.collections.store'), [
            'entity_type' => 'ministry', 'ministry_id' => $this->ministry->id, 'contact_person_name' => 'Gideon Kiragu', 'contact_person_number' => '0724629641',
            'lot' => 2, 'category' => 'medical_waste', 'subcategory' => 'infectious', 'unit' => 'kg', 'quantity' => $kg, 'collection_date' => now()->toDateString(),
        ])->assertRedirect();

        return Collection::latest('id')->first();
    }

    public function test_finance_attaches_a_service_order_and_its_document_to_the_collections_it_covers(): void
    {
        $first = $this->collect($this->grace, 3460);
        $second = $this->collect($this->grace, 3560);
        $other = $this->collect($this->grace, 100);

        $this->actingAs($this->admin)->get(route('admin.service-orders.create', ['rm_id' => $this->grace->id]))->assertOk()
            ->assertSee('3,460.0 kg', false)->assertSee('KES 692,000');

        $this->actingAs($this->admin)->post(route('admin.service-orders.store'), [
            'rm_id' => $this->grace->id, 'reference_number' => '0044', 'customer_name' => 'Mwai Kibaki Referral Hospital', 'order_date' => '2026-09-30',
            'amount' => 1404000, 'document' => UploadedFile::fake()->create('LSO 1.PDF', 90, 'application/pdf'),
            'collection_ids' => [$first->id, $second->id],
        ])->assertRedirect(route('admin.lso-lots.index'))->assertSessionHas('status', fn ($m) => str_contains($m, 'attached to 2 collections') && ! str_contains($m, 'Note'));

        $order = ServiceOrder::sole();
        $this->assertSame([$first->id, $second->id], $order->collections()->orderBy('id')->pluck('id')->all());
        $this->assertNull($other->fresh()->service_order_id);
        Storage::disk('local')->assertExists($order->document_path);

        // Listed on Lot 2 Revenue with a link to the document
        $this->actingAs($this->admin)->get(route('admin.lso-lots.index'))->assertSee('0044')->assertSee(route('service-orders.document', $order), false);

        // The RM and those who can see them open it; another RM cannot
        $this->actingAs($this->grace)->get(route('service-orders.document', $order))->assertOk();
        $this->actingAs($this->admin)->get(route('service-orders.document', $order))->assertOk();
        $stranger = User::factory()->create(['role' => User::ROLE_RM, 'phone_number' => '254700000303', 'is_active' => true]);
        $this->actingAs($stranger)->get(route('service-orders.document', $order))->assertForbidden();
    }

    public function test_it_flags_a_total_that_differs_and_refuses_collections_already_covered_or_of_another_rm(): void
    {
        $first = $this->collect($this->grace, 3460);
        $otherRm = User::factory()->create(['role' => User::ROLE_RM, 'phone_number' => '254700000304', 'is_active' => true]);
        $theirs = $this->collect($otherRm, 100);
        $payload = fn (array $ids, int $amount) => [
            'rm_id' => $this->grace->id, 'reference_number' => '0044', 'customer_name' => 'Mwai Kibaki', 'amount' => $amount,
            'document' => UploadedFile::fake()->create('lso.pdf', 50, 'application/pdf'), 'collection_ids' => $ids,
        ];

        $this->actingAs($this->admin)->post(route('admin.service-orders.store'), $payload([$first->id, $theirs->id], 692000))->assertSessionHasErrors('collection_ids');
        $this->assertSame(0, ServiceOrder::count());
        $this->assertSame([], Storage::disk('local')->allFiles('service-order-documents'));

        $this->actingAs($this->admin)->post(route('admin.service-orders.store'), $payload([$first->id], 700000))
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'the order says KES 700,000 but the collections come to KES 692,000'));
        $this->actingAs($this->admin)->post(route('admin.service-orders.store'), $payload([$first->id], 692000))->assertSessionHasErrors('collection_ids');

        // RMs cannot attach orders
        $this->actingAs($this->grace)->get(route('admin.service-orders.create'))->assertForbidden();
    }
}
