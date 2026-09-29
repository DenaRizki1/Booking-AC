<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\Review;
use App\Models\Technician;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = CarbonImmutable::today();
        $settings = CompanySettings::query()->first();
        $operatingMinutes = max(60, (int) CarbonImmutable::parse((string) ($settings->open_time ?? '08:00'))
            ->diffInMinutes(CarbonImmutable::parse((string) ($settings->close_time ?? '18:00'))));

        $todayOrders = Order::query()
            ->locksSchedule()
            ->where('scheduled_start', '>=', $today)
            ->where('scheduled_start', '<', $today->addDay());

        /** @param Builder<Order> $query */
        $scheduledToday = fn (Builder $query) => $query
            ->whereIn('status', OrderStatus::scheduleLockingValues())
            ->where('scheduled_start', '>=', $today)
            ->where('scheduled_start', '<', $today->addDay());

        $unpaid = Order::query()->where('status', OrderStatus::SELESAI)->where('grand_total', '>', 0);

        $technicians = Technician::query()
            ->where('is_active', true)
            ->with('user:id,name')
            ->withCount(['leaves as on_leave' => fn (Builder $query) => $query->whereDate('leave_date', $today->toDateString())])
            ->withCount(['orders as today_orders' => $scheduledToday])
            ->withSum(['orders as today_minutes' => $scheduledToday], 'total_est_minutes')
            ->get()
            ->sortBy([['on_leave', 'asc'], ['today_minutes', 'desc']])
            ->values();

        return view('admin.dashboard', [
            'waitingOrders' => Order::query()->where('status', OrderStatus::MENUNGGU)->count(),
            'waitingToday' => (clone $todayOrders)->where('status', OrderStatus::MENUNGGU)->count(),
            'todayCount' => (clone $todayOrders)->count(),
            'activeTechnicians' => $technicians->where('on_leave', 0)->count(),
            'onLeaveTechnicians' => $technicians->where('on_leave', '>', 0)->count(),
            'unpaidTotal' => (int) (clone $unpaid)->sum('grand_total'),
            'unpaidCount' => (clone $unpaid)->count(),
            'avgRating' => (float) Review::query()->avg('rating'),
            'reviewCount' => Review::query()->count(),
            'waiting' => Order::query()
                ->where('status', OrderStatus::MENUNGGU)
                ->with(['client:id,name', 'items:id,order_id,service_name', 'parentOrder:id,code'])
                ->withCount('items')
                ->orderBy('scheduled_start')
                ->limit(6)
                ->get(),
            'technicians' => $technicians,
            'operatingMinutes' => $operatingMinutes,
            'latestReviews' => Review::query()->with(['client:id,name', 'technician.user:id,name'])->latest()->limit(3)->get(),
        ]);
    }
}
