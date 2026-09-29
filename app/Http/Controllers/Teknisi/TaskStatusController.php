<?php

namespace App\Http\Controllers\Teknisi;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teknisi\RejectTaskRequest;
use App\Models\Order;
use App\Services\OrderStatusService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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

    public function reject(RejectTaskRequest $request, Order $order): RedirectResponse
    {
        return $this->change($request, $order, OrderStatus::MENUNGGU, 'Ditolak teknisi: '.$request->reason(), 'Tugas ditolak. Admin akan menugaskan teknisi lain.', route('teknisi.tasks'));
    }

    public function depart(Request $request, Order $order): RedirectResponse
    {
        return $this->change($request, $order, OrderStatus::DALAM_PERJALANAN, null, 'Status: dalam perjalanan. Client bisa melihat Anda sedang menuju lokasi.');
    }

    public function start(Request $request, Order $order): RedirectResponse
    {
        return $this->change($request, $order, OrderStatus::DIKERJAKAN, null, 'Pekerjaan dimulai. Isi laporan tiap unit setelah selesai.');
    }

    private function change(Request $request, Order $order, OrderStatus $to, ?string $note, string $message, ?string $redirect = null): RedirectResponse
    {
        Gate::authorize('view', $order);

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
