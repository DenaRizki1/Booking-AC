<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MoveSortOrderRequest;
use App\Http\Requests\Admin\SaveServiceRequest;
use App\Models\CompanySettings;
use App\Models\Service;
use App\Support\SortOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        return view('admin.services.index', [
            'services' => $this->ordered()
                ->withCount(['prices', 'prices as active_prices_count' => fn (Builder $query) => $query->where('is_active', true)])
                ->get(),
            'defaultWarranty' => CompanySettings::current()->warranty_days,
        ]);
    }

    public function create(): View
    {
        return view('admin.services.form', [
            'service' => new Service(['is_active' => true, 'icon' => Service::ICONS[0]]),
            'defaultWarranty' => CompanySettings::current()->warranty_days,
        ]);
    }

    public function store(SaveServiceRequest $request): RedirectResponse
    {
        $data = $request->serviceData();

        $service = Service::query()->create($data + [
            'slug' => Service::uniqueSlug($data['name']),
            'sort_order' => (int) Service::query()->max('sort_order') + 1,
        ]);

        return to_route('admin.services.edit', $service)->with('success', 'Layanan ditambahkan. Lengkapi matriks harganya agar bisa dipesan client.');
    }

    public function edit(Service $service): View
    {
        return view('admin.services.form', [
            'service' => $service,
            'defaultWarranty' => CompanySettings::current()->warranty_days,
        ]);
    }

    public function update(SaveServiceRequest $request, Service $service): RedirectResponse
    {
        $data = $request->serviceData();

        if ($data['name'] !== $service->name) {
            $data['slug'] = Service::uniqueSlug($data['name'], $service->id);
        }

        $service->update($data);

        return to_route('admin.services.edit', $service)->with('success', 'Layanan disimpan.');
    }

    public function toggle(Service $service): RedirectResponse
    {
        $service->update(['is_active' => ! $service->is_active]);

        return to_route('admin.services.index')->with('success', $service->is_active
            ? "{$service->name} diaktifkan."
            : "{$service->name} dinonaktifkan dan tidak bisa dipesan client.");
    }

    public function move(MoveSortOrderRequest $request, Service $service): RedirectResponse
    {
        SortOrder::move($this->ordered(), $service->id, $request->direction());

        return to_route('admin.services.index');
    }

    /**
     * Soft delete: riwayat order tetap menyimpan nama & harga layanan (snapshot).
     */
    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return to_route('admin.services.index')->with('success', "{$service->name} dihapus. Riwayat order yang memakai layanan ini tetap tersimpan.");
    }

    /**
     * @return Builder<Service>
     */
    private function ordered(): Builder
    {
        return Service::query()->orderBy('sort_order')->orderBy('name');
    }
}
