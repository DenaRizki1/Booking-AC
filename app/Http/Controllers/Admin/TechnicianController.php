<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveTechnicianRequest;
use App\Models\Technician;
use App\Services\TechnicianAccountService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class TechnicianController extends Controller
{
    public function __construct(private readonly TechnicianAccountService $accounts) {}

    public function index(): View
    {
        $technicians = Technician::query()
            ->with('user')
            ->withCount([
                'orders as active_orders_count' => fn (Builder $query) => $query->whereIn('status', OrderStatus::activeCases()),
                'orders as done_orders_count' => fn (Builder $query) => $query->whereIn('status', [OrderStatus::SELESAI, OrderStatus::LUNAS]),
            ])
            ->get()
            ->sortBy([['is_active', 'desc'], [fn (Technician $technician) => $technician->user->name, 'asc']])
            ->values();

        return view('admin.technicians.index', ['technicians' => $technicians]);
    }

    public function create(): View
    {
        return view('admin.technicians.form', ['technician' => new Technician(['is_active' => true])]);
    }

    public function store(SaveTechnicianRequest $request): RedirectResponse
    {
        $technician = $this->accounts->create(
            $request->profile() + ['password' => (string) $request->validated('password')],
            $request->file('photo'),
        );

        return to_route('admin.technicians.edit', $technician)
            ->with('success', "Teknisi {$technician->user->name} ditambahkan. Berikan email & password awal agar ia bisa masuk di halaman login staf.");
    }

    public function edit(Technician $technician): View
    {
        $technician->load('user')->loadCount([
            'orders as active_orders_count' => fn (Builder $query) => $query->whereIn('status', OrderStatus::activeCases()),
            'orders as done_orders_count' => fn (Builder $query) => $query->whereIn('status', [OrderStatus::SELESAI, OrderStatus::LUNAS]),
        ]);

        return view('admin.technicians.form', ['technician' => $technician]);
    }

    public function update(SaveTechnicianRequest $request, Technician $technician): RedirectResponse
    {
        $deactivating = $technician->is_active && ! $request->boolean('is_active');
        $this->accounts->update($technician, $request->profile(), $request->file('photo'), $request->boolean('remove_photo'));

        $redirect = to_route('admin.technicians.edit', $technician)->with('success', 'Data teknisi disimpan.');

        return $deactivating ? $this->withReassignWarning($redirect, $technician) : $redirect;
    }

    public function toggle(Technician $technician): RedirectResponse
    {
        $this->accounts->setActive($technician, ! $technician->is_active);
        $name = $technician->user->name;

        if ($technician->is_active) {
            return back()->with('success', "{$name} diaktifkan dan bisa login kembali.");
        }

        return $this->withReassignWarning(back()->with('success', "{$name} dinonaktifkan: tidak bisa login dan tidak muncul saat assign."), $technician);
    }

    public function resetPassword(Request $request, Technician $technician): RedirectResponse
    {
        $password = $request->validateWithBag('resetPassword', [
            'password' => ['required', 'string', Password::min(8)],
        ], [
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password baru minimal 8 karakter.',
        ])['password'];

        $this->accounts->resetPassword($technician, $password);

        return to_route('admin.technicians.edit', $technician)
            ->with('success', "Password {$technician->user->name} direset. Sesi login lamanya sudah diakhiri.");
    }

    private function withReassignWarning(RedirectResponse $redirect, Technician $technician): RedirectResponse
    {
        $count = $this->accounts->activeOrderCount($technician);

        return $count > 0
            ? $redirect->with('warning', "Masih ada {$count} order aktif yang ditugaskan ke teknisi ini. Assign ulang ke teknisi lain dari halaman order.")
            : $redirect;
    }
}
