<?php

namespace Tests\Feature;

use App\Models\Requisition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequisitionApprovalRulesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $phone): User
    {
        return User::factory()->create(['role' => $role, 'phone_number' => $phone]);
    }

    private function requestBy(User $requester): Requisition
    {
        return (new Requisition(['requester_id' => $requester->id]))->setRelation('requester', $requester);
    }

    public function test_office_admin_can_approve_other_office_admins_and_operations_but_not_themselves(): void
    {
        $nancy = $this->user(User::ROLE_OFFICE_ADMIN, '254700000701');
        $otherOfficeAdmin = $this->user(User::ROLE_OFFICE_ADMIN, '254700000702');
        $operations = $this->user(User::ROLE_OPERATIONS, '254700000703');

        $this->assertTrue($nancy->canApproveRequisition($this->requestBy($otherOfficeAdmin)));
        $this->assertTrue($nancy->canApproveRequisition($this->requestBy($operations)));
        $this->assertFalse($nancy->canApproveRequisition($this->requestBy($nancy)));
    }

    public function test_supervisor_still_cannot_approve_office_admin_or_operations_requests(): void
    {
        $supervisor = $this->user(User::ROLE_SUPERVISOR, '254700000704');
        $rm = $this->user(User::ROLE_RM, '254700000705');

        $this->assertFalse($supervisor->canApproveRequisition($this->requestBy($this->user(User::ROLE_OFFICE_ADMIN, '254700000706'))));
        $this->assertFalse($supervisor->canApproveRequisition($this->requestBy($this->user(User::ROLE_OPERATIONS, '254700000707'))));
        $this->assertTrue($supervisor->canApproveRequisition($this->requestBy($rm)));
    }
}
