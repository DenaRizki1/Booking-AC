<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Form wajib no. HP setelah login pertama.
     */
    public function complete(Request $request): View|RedirectResponse
    {
        if (filled($request->user()?->phone)) {
            return redirect()->route('client.home');
        }

        return view('client.profile.complete', ['user' => $request->user()]);
    }

    public function storeComplete(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()?->update($request->profile());

        return redirect()->intended(route('client.home'))->with('success', 'Profil lengkap. Selamat datang!');
    }

    public function edit(Request $request): View
    {
        return view('client.profile.edit', ['user' => $request->user()]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()?->update($request->profile());

        return redirect()->route('client.profile')->with('success', 'Profil berhasil disimpan.');
    }
}
