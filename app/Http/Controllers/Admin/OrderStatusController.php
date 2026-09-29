<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderReasonRequest;
use App\Models\Order;
use App\Services\OrderStatusService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;

/**
 * Tolak (MENUNGGU → DITOLAK) & batalkan (sebelum DALAM_PERJALANAN) oleh admin, alasan wajib.
 */
class OrderStatusController extends Controller
{
    public function __construct(private readonly OrderStatusService $statuses) {}

    public function reject(OrderReasonRequest $request, Order $order): RedirectResponse
    {
        return $this->change($request, $order, OrderStatus::DITOLAK, 'Order ditolak. Client melihat alasan penolakan di detail order.');
    }

    public function cancel(OrderReasonRequest $request, Order $order): RedirectResponse
    {
        return $this->change($request, $order, OrderStatus::DIBATALKAN, 'Order dibatalkan. Jadwal teknisi & helper dilepas.');
    }

    private function change(OrderReasonRequest $request, Order $order, OrderStatus $to, string $message): RedirectResponse
    {
        try {
            $this->statuses->transition($order, $to, $request->user(), $request->reason());
        } catch (InvalidStatusTransitionException|AuthorizationException $exception) {
            return to_route('admin.orders.show', $order)->with('error', $exception->getMessage());
        }

        return to_route('admin.orders.show', $order)->with('success', $message);
    }
}
