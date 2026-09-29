<?php

namespace App\Http\Controllers\Teknisi;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderStatusService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Aksi status oleh teknisi. Hak akses (order miliknya) & transisi dicek di OrderStatusService.
 */
class TaskStatusController extends Controller
{
    public function __construct(private readonly OrderStatusService $statuses) {}

    public function accept(Request $request, Order $order): RedirectResponse
    {
        return $this->change($request, $order, OrderStatus::DIKONFIRMASI, null, 'Tugas diterima. Jangan lupa tekan "Berangkat" saat menuju lokasi.');
    }

    public function reject(Request $request, Order $order): RedirectResponse
    {
        $reason = $request->validateWithBag('reject', [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Alasan menolak wajib diisi.',
            'reason.min' => 'Alasan minimal 5 karakter.',
            'reason.max' => 'Alasan maksimal 500 karakter.',
        ])['reason'];

        return $this->change($request, $order, OrderStatus::MENUNGGU, 'Ditolak teknisi: '.trim($reason), 'Tugas ditolak. Admin akan menugaskan teknisi lain.', route('teknisi.tasks'));
    }

    private function change(Request $request, Order $order, OrderStatus $to, ?string $note, string $message, ?string $redirect = null): RedirectResponse
    {
        try {
            $this->statuses->transition($order, $to, $request->user(), $note);
        } catch (AuthorizationException) {
            abort(403, 'Tugas ini tidak ditugaskan kepada Anda.');
        } catch (InvalidStatusTransitionException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect($redirect ?? route('teknisi.tasks.show', $order))->with('success', $message);
    }
}
