<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Every logged-in account's own small profile page - currently just a
 * photo, since that's the whole ask (2026-09-22): give each account
 * (RMs especially, since they're the ones with a public portfolio page
 * now) a picture instead of the plain initials circle everywhere.
 */
class AccountController extends Controller
{
    public function edit(): View
    {
        return view('account.profile', ['user' => Auth::user()]);
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
