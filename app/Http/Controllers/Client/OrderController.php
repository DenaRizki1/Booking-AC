<?php

namespace App\Http\Controllers\Client;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrderController extends Controller
{
    /** Tab daftar order => status yang termasuk. */
    public const TABS = [
        'aktif' => ['Aktif', [OrderStatus::MENUNGGU, OrderStatus::DITUGASKAN, OrderStatus::DIKONFIRMASI, OrderStatus::DALAM_PERJALANAN, OrderStatus::DIKERJAKAN]],
        'selesai' => ['Selesai', [OrderStatus::SELESAI, OrderStatus::LUNAS]],
        'dibatalkan' => ['Dibatalkan', [OrderStatus::DITOLAK, OrderStatus::DIBATALKAN]],
    ];

    public function index(Request $request): View
    {
        /** @var User $client */
        $client = $request->user();
        $tab = array_key_exists($request->string('tab')->toString(), self::TABS) ? $request->string('tab')->toString() : 'aktif';

        $orders = $client->orders()
            ->whereIn('status', self::TABS[$tab][1])
            ->with(['items:id,order_id,service_name,unit_label', 'technician.user:id,name'])
            ->withCount('items')
            ->when($tab === 'aktif', fn ($query) => $query->orderBy('scheduled_start'), fn ($query) => $query->orderByDesc('scheduled_start'))
            ->paginate(10)
            ->withQueryString();

        $counts = collect(self::TABS)->map(fn (array $definition) => $client->orders()->whereIn('status', $definition[1])->count());

        return view('client.orders.index', ['orders' => $orders, 'tab' => $tab, 'counts' => $counts]);
    }

    public function show(Order $order): View
    {
        Gate::authorize('view', $order);

        $order->load(['items', 'technician.user', 'helper', 'statusLogs', 'parentOrder:id,code']);

        return view('client.orders.show', ['order' => $order]);
    }
}
