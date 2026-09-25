<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\SaveAddressRequest;
use App\Models\CompanySettings;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AddressController extends Controller
{
    public function index(Request $request): View
    {
        return view('client.addresses.index', [
            'addresses' => $this->client($request)->addresses()->orderByDesc('is_default')->oldest()->get(),
            'settings' => CompanySettings::current(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('client.addresses.form', [
            'address' => new CustomerAddress(['label' => 'Rumah', 'is_default' => ! $this->client($request)->addresses()->exists()]),
            'settings' => CompanySettings::current(),
            'redirect' => $this->safeRedirect($request),
        ]);
    }

    public function store(SaveAddressRequest $request): RedirectResponse
    {
        $client = $this->client($request);

        DB::transaction(function () use ($client, $request) {
            $isFirst = ! $client->addresses()->exists();
            $address = $client->addresses()->create($request->addressData() + ['is_default' => false]);

            if ($isFirst || $request->boolean('is_default')) {
                $this->makeDefault($client, $address);
            }
        });

        return redirect($this->safeRedirect($request) ?? route('client.addresses.index'))
            ->with('success', 'Alamat berhasil disimpan.');
    }

    public function edit(Request $request, CustomerAddress $address): View
    {
        Gate::authorize('update', $address);

        return view('client.addresses.form', [
            'address' => $address,
            'settings' => CompanySettings::current(),
            'redirect' => $this->safeRedirect($request),
        ]);
    }

    public function update(SaveAddressRequest $request, CustomerAddress $address): RedirectResponse
    {
        Gate::authorize('update', $address);

        DB::transaction(function () use ($request, $address) {
            $address->update($request->addressData());

            if ($request->boolean('is_default')) {
                $this->makeDefault($this->client($request), $address);
            }
        });

        return redirect($this->safeRedirect($request) ?? route('client.addresses.index'))
            ->with('success', 'Alamat berhasil diperbarui.');
    }

    public function setDefault(Request $request, CustomerAddress $address): RedirectResponse
    {
        Gate::authorize('update', $address);

        DB::transaction(fn () => $this->makeDefault($this->client($request), $address));

        return back()->with('success', "{$address->label} dijadikan alamat utama.");
    }

    public function destroy(Request $request, CustomerAddress $address): RedirectResponse
    {
        Gate::authorize('delete', $address);
        $client = $this->client($request);

        DB::transaction(function () use ($client, $address) {
            $wasDefault = $address->is_default;
            $address->update(['is_default' => false]);
            $address->delete();

            $next = $client->addresses()->oldest()->first();
            if ($wasDefault && $next !== null) {
                $this->makeDefault($client, $next);
            }
        });

        return redirect()->route('client.addresses.index')->with('success', 'Alamat dihapus.');
    }

    /**
     * Maks. 1 alamat utama per client.
     */
    private function makeDefault(User $client, CustomerAddress $address): void
    {
        $client->addresses()->whereKeyNot($address->getKey())->update(['is_default' => false]);
        $address->update(['is_default' => true]);
    }

    private function client(Request $request): User
    {
        /** @var User */
        return $request->user();
    }

    /**
     * Kembali ke halaman asal (mis. form order) hanya untuk path internal.
     */
    private function safeRedirect(Request $request): ?string
    {
        $redirect = $request->string('redirect')->toString();

        return str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//') ? $redirect : null;
    }
}
