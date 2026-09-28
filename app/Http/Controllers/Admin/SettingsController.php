<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCompanySettingsRequest;
use App\Jobs\RecalculateAddressDistances;
use App\Models\CompanySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', ['settings' => CompanySettings::query()->firstOrFail()]);
    }

    public function update(UpdateCompanySettingsRequest $request): RedirectResponse
    {
        $settings = CompanySettings::query()->firstOrFail();
        $data = $request->validated();
        sort($data['working_days']);

        $settings->fill($data);
        $officeMoved = $settings->isDirty(['lat', 'lng']);
        $settings->save();

        if ($officeMoved) {
            RecalculateAddressDistances::dispatch();

            return to_route('admin.settings.edit')->with('success', 'Pengaturan disimpan. Jarak semua alamat client sedang dihitung ulang dari titik kantor baru.');
        }

        return to_route('admin.settings.edit')->with('success', 'Pengaturan disimpan.');
    }
}
