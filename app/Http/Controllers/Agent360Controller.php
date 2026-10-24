<?php

namespace App\Http\Controllers;

use App\Services\Agent360Service;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
