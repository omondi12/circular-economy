<?php

namespace App\Http\Controllers;

use App\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Every logged-in account's own small profile page - a photo, and (since
 * the Nawiri payroll integration landed, 2026-09-22) their own Nawiri
 * phone number, so a requester can set it themselves instead of an admin
 * having to edit every account one by one from Team Accounts.
 */
class AccountController extends Controller
{
    public function edit(): View
    {
        return view('account.profile', ['user' => Auth::user()]);
    }

    public function updatePhone(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $request->merge(['phone_number' => PhoneNumber::normalizeKenyan($request->input('phone_number'))]);

        $data = $request->validate([
            'phone_number' => ['required', 'regex:/^254(?:7|1)\d{8}$/', Rule::unique('users', 'phone_number')->ignore($user->id)],
        ]);

        $user->update($data);

        return redirect()->route('account.profile.edit')->with('status', 'Nawiri phone number updated.');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $path = $request->file('photo')->store('profile-photos', 'public');
        $user->update(['profile_photo_path' => $path]);

        return redirect()->route('account.profile.edit')->with('status', 'Profile photo updated.');
    }

    public function destroy(): RedirectResponse
    {
        $user = Auth::user();

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
            $user->update(['profile_photo_path' => null]);
        }

        return redirect()->route('account.profile.edit')->with('status', 'Profile photo removed.');
    }
}
