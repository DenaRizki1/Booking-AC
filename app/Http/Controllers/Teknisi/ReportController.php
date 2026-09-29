<?php

namespace App\Http\Controllers\Teknisi;

use App\Enums\OrderStatus;
use App\Exceptions\ReportIncompleteException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teknisi\SaveReportItemRequest;
use App\Http\Requests\Teknisi\StoreReportPhotosRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPhoto;
use App\Services\OrderReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Laporan pekerjaan per unit (mockup Teknisi-Laporan).
 */
class ReportController extends Controller
{
    public function __construct(private readonly OrderReportService $reports) {}

    public function edit(Request $request, Order $order): View|RedirectResponse
    {
        Gate::authorize('view', $order);

        if ($order->status !== OrderStatus::DIKERJAKAN) {
            return to_route('teknisi.tasks.show', $order)->with('error', 'Laporan hanya bisa diisi saat order berstatus Dikerjakan.');
        }

        $order->load(['client:id,name', 'items.photos']);
        $index = max(0, min($order->items->count() - 1, (int) $request->query('unit', 1) - 1));

        return view('teknisi.reports.edit', [
            'order' => $order,
            'index' => $index,
            'item' => $order->items[$index],
            'complete' => $order->items->map(fn (OrderItem $item) => $this->reports->itemComplete($item)),
        ]);
    }

    public function storePhotos(StoreReportPhotosRequest $request, Order $order, OrderItem $item): RedirectResponse
    {
        $this->authorizeItem($order, $item);

        $kind = $request->kind();

        try {
            foreach ($request->file('photos', []) as $file) {
                $this->reports->storePhoto($item, $kind, $file, $request->user());
            }
        } catch (ReportIncompleteException $exception) {
            return $this->backToUnit($order, $item)->with('error', $exception->getMessage());
        }

        return $this->backToUnit($order, $item)->with('success', 'Foto '.strtolower($kind->label()).' tersimpan.');
    }

    public function destroyPhoto(Order $order, OrderPhoto $photo): RedirectResponse
    {
        abort_unless($photo->order_id === $order->id && $photo->item !== null, 404);
        $this->authorizeItem($order, $photo->item);

        try {
            $this->reports->deletePhoto($photo);
        } catch (ReportIncompleteException $exception) {
            return $this->backToUnit($order, $photo->item)->with('error', $exception->getMessage());
        }

        return $this->backToUnit($order, $photo->item)->with('success', 'Foto dihapus.');
    }

    public function updateItem(SaveReportItemRequest $request, Order $order, OrderItem $item): RedirectResponse
    {
        $this->authorizeItem($order, $item);

        try {
            $this->reports->saveItem($item, (int) $request->validated('final_price'), $request->validated('price_note'), $request->validated('work_note'));
        } catch (ReportIncompleteException $exception) {
            return $this->backToUnit($order, $item)->with('error', $exception->getMessage());
        }

        $position = $order->items()->pluck('id')->search($item->id);
        $next = $order->items()->count() > $position + 1 ? $position + 2 : $position + 1;

        return to_route('teknisi.reports.edit', ['order' => $order, 'unit' => $next])->with('success', 'Unit '.($position + 1).' disimpan.');
    }

    public function submit(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('view', $order);

        try {
            $this->reports->submit($order, $request->user());
        } catch (ReportIncompleteException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('teknisi.tasks.show', $order)->with('success', 'Laporan terkirim. Order selesai — terima kasih!');
    }

    private function authorizeItem(Order $order, OrderItem $item): void
    {
        Gate::authorize('view', $order);
        abort_unless($item->order_id === $order->id, 404);
    }

    private function backToUnit(Order $order, OrderItem $item): RedirectResponse
    {
        $position = $order->items()->pluck('id')->search($item->id);

        return to_route('teknisi.reports.edit', ['order' => $order, 'unit' => $position + 1]);
    }
}
