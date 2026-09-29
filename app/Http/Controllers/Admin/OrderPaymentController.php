<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfirmPaymentRequest;
use App\Models\Order;
use App\Services\OrderStatusService;
use App\Support\Format;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Konfirmasi pembayaran order SELESAI → LUNAS. Bukti bayar disimpan di disk privat.
 */
class OrderPaymentController extends Controller
{
    public const PROOF_DIRECTORY = 'payment-proofs';

    public function store(ConfirmPaymentRequest $request, Order $order, OrderStatusService $statuses): RedirectResponse
    {
        $method = $request->paymentMethod();
        $proof = $request->file('payment_proof')?->store(self::PROOF_DIRECTORY, 'local');

        try {
            $statuses->transition($order, OrderStatus::LUNAS, $request->user(), "Dibayar {$method->label()} ".Format::rupiah((int) $order->grand_total), [
                'payment_method' => $method,
                'payment_proof' => $proof,
                'paid_confirmed_by' => $request->user()->id,
            ]);
        } catch (InvalidStatusTransitionException|AuthorizationException $exception) {
            if ($proof) {
                Storage::disk('local')->delete($proof);
            }

            return to_route('admin.orders.show', $order)->with('error', $exception->getMessage());
        }

        return to_route('admin.orders.show', $order)->with('success', 'Pembayaran dikonfirmasi. Order LUNAS.');
    }

    public function proof(Order $order): StreamedResponse
    {
        abort_unless($order->payment_proof && Storage::disk('local')->exists($order->payment_proof), 404);

        return Storage::disk('local')->response($order->payment_proof, "bukti-bayar-{$order->code}.".pathinfo($order->payment_proof, PATHINFO_EXTENSION));
    }
}
