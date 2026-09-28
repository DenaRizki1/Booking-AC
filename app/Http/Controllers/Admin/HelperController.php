<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveHelperRequest;
use App\Models\Helper;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Helper pendamping teknisi (tanpa akun login).
 */
class HelperController extends Controller
{
    public function index(): View
    {
        return view('admin.helpers.index', [
            'helpers' => Helper::query()
                ->withCount($this->orderCounts())
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.helpers.form', ['helper' => new Helper(['is_active' => true])]);
    }

    public function store(SaveHelperRequest $request): RedirectResponse
    {
        $helper = Helper::query()->create($request->helperData());

        return to_route('admin.helpers.edit', $helper)->with('success', "Helper {$helper->name} ditambahkan.");
    }

    public function edit(Helper $helper): View
    {
        return view('admin.helpers.form', ['helper' => $helper->loadCount($this->orderCounts())]);
    }

    public function update(SaveHelperRequest $request, Helper $helper): RedirectResponse
    {
        $deactivating = $helper->is_active && ! $request->boolean('is_active');
        $helper->update($request->helperData());

        $redirect = to_route('admin.helpers.edit', $helper)->with('success', 'Data helper disimpan.');

        return $deactivating ? $this->withReassignWarning($redirect, $helper) : $redirect;
    }

    public function toggle(Helper $helper): RedirectResponse
    {
        $helper->update(['is_active' => ! $helper->is_active]);

        if ($helper->is_active) {
            return back()->with('success', "{$helper->name} diaktifkan.");
        }

        return $this->withReassignWarning(back()->with('success', "{$helper->name} dinonaktifkan dan tidak muncul saat assign."), $helper);
    }

    /**
     * @return array<string, callable(Builder<Order>): mixed>
     */
    private function orderCounts(): array
    {
        return [
            'orders as active_orders_count' => fn (Builder $query) => $query->whereIn('status', OrderStatus::activeCases()),
            'orders as done_orders_count' => fn (Builder $query) => $query->whereIn('status', [OrderStatus::SELESAI, OrderStatus::LUNAS]),
        ];
    }

    private function withReassignWarning(RedirectResponse $redirect, Helper $helper): RedirectResponse
    {
        $count = $helper->orders()->whereIn('status', OrderStatus::activeCases())->count();

        return $count > 0
            ? $redirect->with('warning', "Masih ada {$count} order aktif yang memakai helper ini. Ganti helper dari halaman order.")
            : $redirect;
    }
}
