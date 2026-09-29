<?php

namespace App\Http\Controllers\Teknisi;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Technician;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Daftar & detail tugas teknisi (hanya order yang ditugaskan kepadanya).
 */
class TaskController extends Controller
{
    public const TABS = ['hari-ini' => 'Hari ini', 'mendatang' => 'Mendatang', 'riwayat' => 'Riwayat'];

    /** Status yang masih perlu ditangani teknisi. */
    public const ACTIVE = [OrderStatus::DITUGASKAN, OrderStatus::DIKONFIRMASI, OrderStatus::DALAM_PERJALANAN, OrderStatus::DIKERJAKAN];

    public function index(Request $request): View
    {
        $technician = $this->technician($request);
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? (string) $request->query('tab') : 'hari-ini';
        $today = CarbonImmutable::today();
        $tomorrow = $today->addDay();

        $orders = $technician->orders()
            ->with(['client:id,name,phone', 'helper:id,name', 'items:id,order_id,service_name,unit_label,est_minutes'])
            ->withCount('items');

        $orders = match ($tab) {
            // Hari ini + tugas aktif dari hari sebelumnya yang belum tuntas.
            'hari-ini' => $orders->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->whereIn('status', OrderStatus::scheduleLockingValues())
                    ->where('scheduled_start', '>=', $today)
                    ->where('scheduled_start', '<', $tomorrow))
                ->orWhere(fn (Builder $query) => $query
                    ->whereIn('status', self::ACTIVE)
                    ->where('scheduled_start', '<', $today)))
                ->orderBy('scheduled_start')
                ->get(),
            'mendatang' => $orders->whereIn('status', self::ACTIVE)
                ->where('scheduled_start', '>=', $tomorrow)
                ->orderBy('scheduled_start')
                ->get(),
            default => $orders->whereIn('status', [OrderStatus::SELESAI, OrderStatus::LUNAS])
                ->orderByDesc('scheduled_start')
                ->limit(50)
                ->get(),
        };

        $todayScope = fn (HasMany $query) => $query
            ->whereIn('status', OrderStatus::scheduleLockingValues())
            ->where('scheduled_start', '>=', $today)
            ->where('scheduled_start', '<', $tomorrow);

        return view('teknisi.tasks.index', [
            'technician' => $technician,
            'tab' => $tab,
            'orders' => $orders,
            'todayCount' => $todayScope($technician->orders())->count(),
            'todayDone' => $todayScope($technician->orders())->whereIn('status', [OrderStatus::SELESAI, OrderStatus::LUNAS])->count(),
            'onDuty' => $todayScope($technician->orders())->whereIn('status', self::ACTIVE)->exists(),
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        Gate::authorize('view', $order);

        $order->load(['client:id,name,phone', 'helper:id,name,phone', 'items.photos', 'parentOrder:id,code']);

        return view('teknisi.tasks.show', ['order' => $order]);
    }

    private function technician(Request $request): Technician
    {
        return $request->user()->technician ?? abort(403, 'Akun ini belum terhubung ke data teknisi.');
    }
}
