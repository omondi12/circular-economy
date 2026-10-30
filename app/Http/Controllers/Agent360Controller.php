<?php

namespace App\Http\Controllers;

use App\Models\Requisition;
use App\Models\User;
use App\Services\Agent360Payables;
use App\Services\Agent360Records;
use App\Services\Agent360Service;
use App\Services\RequisitionPaymentService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Agent 360 API: Westport's RMs and supervisors, their assigned clients and activity, and their logins.
 * Contract: docs/agent360-api.md
 */
class Agent360Controller extends Controller
{
    public function __construct(private Agent360Service $agents) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->agents->agents()]);
    }

    public function metrics(): JsonResponse
    {
        return response()->json(['data' => $this->agents->metrics(), 'generated_at' => now()->toIso8601String()]);
    }

    /**
     * Every record of a type (reports, lsos, payments, collections, clients), with its RM and best-known county.
     */
    public function records(Request $request, Agent360Records $records): JsonResponse
    {
        $type = (string) $request->query('type');
        abort_unless(in_array($type, Agent360Records::TYPES, true), 422, 'type must be one of: '.implode(', ', Agent360Records::TYPES));

        return response()->json(['type' => $type, 'data' => $records->records($type), 'generated_at' => now()->toIso8601String()]);
    }

    /**
     * What Agent 360 pays: approved requisition amounts still owed per recipient, and RM commissions on confirmed LSO
     * payments, in minor units.
     */
    public function payables(Agent360Payables $payables): JsonResponse
    {
        return response()->json([
            'requisitions' => $payables->requisitions(),
            'commissions' => $payables->commissions(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Agent 360 paid a requisition recipient: record it so the balance counts it. 409 when it is no longer owed.
     */
    public function recordRequisitionPayment(Request $request, string $id, RequisitionPaymentService $payments): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', 'in:transport,airtime'],
            'phone' => ['required', 'regex:/^254(?:7|1)\d{8}$/'],
            'amount_minor' => ['required', 'integer', 'min:100'],
            'payment_key' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/'],
            'reference' => ['required', 'string', 'max:150'],
        ]);

        try {
            $payment = $payments->recordAgent360Payment(Requisition::findOrFail((int) $id), $data['category'], $data['phone'], (int) $data['amount_minor'], $data['payment_key'], $data['reference']);
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 409);
        }

        return response()->json(['data' => ['id' => $payment->id, 'status' => $payment->status, 'amount_minor' => $payment->amount_minor]], $payment->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Nawiri reversed a payment Agent 360 made: the requisition owes the recipient again.
     */
    public function reverseRequisitionPayment(Request $request, string $key, RequisitionPaymentService $payments): JsonResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9_-]{1,60}$/', $key) === 1, 404);
        $payment = $payments->reverseAgent360Payment($key, (string) $request->input('reason', 'Reversed by Nawiri'));
        abort_if($payment === null, 404);

        return response()->json(['data' => ['id' => $payment->id, 'status' => $payment->status]]);
    }

    /**
     * The user with exactly this email (any role), so Agent 360 can link a staff member to their own login here.
     * One exact match only: nothing is listed. data is null when there is none.
     */
    public function lookupUser(Request $request): JsonResponse
    {
        $email = mb_strtolower(trim((string) $request->query('email')));
        abort_unless(filter_var($email, FILTER_VALIDATE_EMAIL), 422, 'email is required');

        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        return response()->json(['data' => $user ? [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role, 'active' => (bool) $user->is_active,
        ] : null]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge(['phone' => PhoneNumber::normalizeKenyan($request->input('phone'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'regex:/^254(?:7|1)\d{8}$/'],
            'email' => ['required', 'email', 'max:255'],
            'supervisor_id' => ['nullable', 'integer'],
        ]);

        $result = $this->agents->create($data);

        return response()->json(['data' => $result['agent'], 'created' => $result['created'], 'temporary_password' => $result['temporary_password']], $result['created'] ? 201 : 200);
    }

    public function deactivate(string $id): JsonResponse
    {
        return response()->json(['data' => $this->agents->setActive($this->agents->find($id) ?? abort(404), false)]);
    }

    public function activate(string $id): JsonResponse
    {
        return response()->json(['data' => $this->agents->setActive($this->agents->find($id) ?? abort(404), true)]);
    }
}
