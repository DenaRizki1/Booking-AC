<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\CompanySettings;
use App\Models\Helper;
use App\Models\Order;
use App\Models\StaffLeave;
use App\Models\Technician;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Jadwal harian: satu baris per teknisi/helper, blok per order (warna status) + buffer perjalanan.
 */
class ScheduleController extends Controller
{
    public function __invoke(Request $request): View
    {
        $validated = $request->validate(['tanggal' => ['nullable', 'date_format:Y-m-d']]);
        $day = isset($validated['tanggal']) ? CarbonImmutable::parse($validated['tanggal'])->startOfDay() : CarbonImmutable::today();
        $settings = CompanySettings::current();

        $orders = Order::query()
            ->locksSchedule()
            ->where('scheduled_start', '>=', $day)
            ->where('scheduled_start', '<', $day->addDay())
            ->with(['client:id,name', 'items:id,order_id,service_name', 'technician.user:id,name', 'helper:id,name'])
            ->withCount('items')
            ->orderBy('scheduled_start')
            ->get();

        $leaves = StaffLeave::query()->whereDate('leave_date', $day->toDateString())->get(['staff_type', 'staff_id', 'reason']);
        $onLeave = fn (string $type, int $id) => $leaves->first(fn (StaffLeave $leave) => $leave->staff_type === $type && $leave->staff_id === $id);

        $technicians = Technician::query()
            ->with('user:id,name')
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $orders->pluck('technician_id')->filter()))
            ->get()
            ->sortBy(fn (Technician $technician) => $technician->user->name)
            ->map(fn (Technician $technician) => [
                'staff' => $technician,
                'name' => $technician->user->name,
                'orders' => $orders->where('technician_id', $technician->id)->values(),
                'leave' => $onLeave('technician', $technician->id),
            ])
            ->values();

        $helpers = Helper::query()
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $orders->pluck('helper_id')->filter()))
            ->orderBy('name')
            ->get()
            ->map(fn (Helper $helper) => [
                'staff' => $helper,
                'name' => $helper->name,
                'orders' => $orders->where('helper_id', $helper->id)->values(),
                'leave' => $onLeave('helper', $helper->id),
            ]);

        return view('admin.schedule', [
            'day' => $day,
            'settings' => $settings,
            'technicians' => $technicians,
            'helpers' => $helpers,
            'unassigned' => $orders->where('status', OrderStatus::MENUNGGU)->values(),
        ]);
    }
}
