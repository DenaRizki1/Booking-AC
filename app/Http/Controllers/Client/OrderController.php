<?php

namespace App\Http\Controllers\Client;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\CancelOrderRequest;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderStatusService;
use App\Services\ReviewService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
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

    public function show(Request $request, Order $order, OrderStatusService $statuses, ReviewService $reviews): View
    {
        Gate::authorize('view', $order);

        /** @var User $client */
        $client = $request->user();
        $order->load(['items.photos', 'technician.user', 'helper', 'statusLogs', 'parentOrder:id,code', 'review']);

        return view('client.orders.show', [
            'order' => $order,
            'canCancel' => in_array(OrderStatus::DIBATALKAN, $statuses->allowedTransitions($order, $client), true),
            'settings' => CompanySettings::current(),
            'canReview' => $reviews->canReview($order, $client),
        ]);
    }

    /**
     * Client membatalkan order sendiri (≥ cancel_limit_hours sebelum jadwal, alasan wajib).
     */
    public function cancel(CancelOrderRequest $request, Order $order, OrderStatusService $statuses): RedirectResponse
    {
        Gate::authorize('view', $order);

        try {
            $statuses->transition($order, OrderStatus::DIBATALKAN, $request->user(), $request->string('reason')->toString());
        } catch (InvalidStatusTransitionException|AuthorizationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('client.orders.show', $order)->with('success', 'Order dibatalkan.');
    }
}
