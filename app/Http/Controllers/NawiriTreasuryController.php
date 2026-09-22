<?php

namespace App\Http\Controllers;

use App\Exceptions\NawiriPayrollException;
use App\Models\AuditLog;
use App\Models\NawiriTreasuryCredential;
use App\Services\NawiriPayrollClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class NawiriTreasuryController extends Controller
{
    public function edit(): View
    {
        $credential = NawiriTreasuryCredential::current();

        return view('admin.nawiri-treasury', [
            'credential' => $credential,
        ]);
    }

    public function update(Request $request, NawiriPayrollClient $client): RedirectResponse
    {
        $credential = NawiriTreasuryCredential::current();
        $rules = [
            'email' => ['required', 'email', 'max:255'],
            'password' => [$credential ? 'nullable' : 'required', Password::min(8), 'max:255'],
            'pin' => [$credential ? 'nullable' : 'required', 'regex:/^\d{4,6}$/'],
        ];
        $messages = [
            'pin.required' => 'Enter the transaction PIN for this Nawiri account.',
            'pin.regex' => 'The Nawiri PIN must contain four to six digits.',
        ];
        $data = $request->validate($rules, $messages);
        $email = Str::lower(trim($data['email']));
        $password = filled($data['password'] ?? null) ? $data['password'] : $credential?->password;
        $pin = filled($data['pin'] ?? null) ? $data['pin'] : $credential?->pin;

        try {
            $client->verifyCredentials($email, $password, $pin);
        } catch (NawiriPayrollException $exception) {
            return back()
                ->withInput(['email' => $email])
                ->withErrors([$exception->field ?? 'email' => $exception->getMessage()]);
        }

        DB::transaction(function () use ($email, $password, $pin): void {
            $candidate = new NawiriTreasuryCredential;
            $candidate->fill([
                'email' => $email,
                'password' => $password,
                'pin' => $pin,
                'verified_at' => now(),
                'updated_by_id' => auth()->id(),
            ]);

            NawiriTreasuryCredential::query()->upsert(
                [$candidate->getAttributes()],
                ['singleton_key'],
                ['email', 'password', 'pin', 'verified_at', 'updated_by_id', 'updated_at'],
            );

            $credential = NawiriTreasuryCredential::current();

            AuditLog::record('nawiri_treasury.updated', $credential, [
                'email' => $email,
                'verified_at' => $credential->verified_at->toIso8601String(),
            ]);
        });

        return redirect()->route('admin.nawiri-treasury.edit')
            ->with('status', 'Nawiri treasury account saved. Future payroll payments will use this account.');
    }
}
