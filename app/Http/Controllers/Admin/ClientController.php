<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Daftar client (login Google): lihat data & nonaktifkan.
 */
class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        $clients = User::query()
            ->where('role', UserRole::CLIENT)
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->when($status === 'aktif', fn (Builder $query) => $query->where('is_active', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('is_active', false))
            ->with(['addresses' => fn ($query) => $query->orderByDesc('is_default')->orderBy('id')])
            ->withCount([
                'addresses',
                'orders',
                'orders as active_orders_count' => fn (Builder $query) => $query->whereIn('status', OrderStatus::activeCases()),
            ])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.clients.index', ['clients' => $clients, 'search' => $search, 'status' => $status]);
    }

    public function toggle(User $client): RedirectResponse
    {
        abort_unless($client->role === UserRole::CLIENT, 404);

        $client->update(['is_active' => ! $client->is_active]);

        if ($client->is_active) {
            return back()->with('success', "{$client->name} diaktifkan dan bisa login kembali.");
        }

        $redirect = back()->with('success', "{$client->name} dinonaktifkan: tidak bisa login dan membuat order.");
        $active = $client->orders()->whereIn('status', OrderStatus::activeCases())->count();

        return $active > 0
            ? $redirect->with('warning', "Client ini masih punya {$active} order aktif. Order tersebut tetap berjalan; batalkan dari halaman order bila perlu.")
            : $redirect;
    }
}
