<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffLeaveRequest;
use App\Models\Helper;
use App\Models\StaffLeave;
use App\Models\Technician;
use App\Services\StaffLeaveService;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;

/**
 * Cuti teknisi & helper, dikelola dari halaman ubah teknisi/helper.
 */
class StaffLeaveController extends Controller
{
    public function __construct(private readonly StaffLeaveService $leaves) {}

    public function storeForTechnician(StoreStaffLeaveRequest $request, Technician $technician): RedirectResponse
    {
        return $this->store($request, $technician, route('admin.technicians.edit', $technician));
    }

    public function storeForHelper(StoreStaffLeaveRequest $request, Helper $helper): RedirectResponse
    {
        return $this->store($request, $helper, route('admin.helpers.edit', $helper));
    }

    public function destroy(StaffLeave $leave): RedirectResponse
    {
        $staff = $leave->staff;
        $leave->delete();

        $url = $staff instanceof Technician ? route('admin.technicians.edit', $staff) : route('admin.helpers.edit', $staff);

        return redirect($url.'#cuti')->with('success', 'Cuti '.Format::date($leave->leave_date).' dihapus.');
    }

    private function store(StoreStaffLeaveRequest $request, Technician|Helper $staff, string $url): RedirectResponse
    {
        $from = $request->from();
        $to = $request->to();
        $result = $this->leaves->add($staff, $from, $to, $request->validated('reason'));

        $days = (int) $from->diffInDays($to) + 1;
        $message = $result['added'] === $days
            ? "Cuti {$days} hari dicatat."
            : "Cuti dicatat ({$result['added']} hari baru; tanggal lain sudah tercatat sebelumnya).";

        $redirect = redirect($url.'#cuti')->with('success', $message);

        if ($result['conflicts']->isNotEmpty()) {
            $orders = $result['conflicts']
                ->map(fn ($order) => $order->code.' ('.Format::shortDate($order->scheduled_start).')')
                ->implode(', ');

            $redirect->with('warning', "Cuti bentrok dengan {$result['conflicts']->count()} order yang sudah ditugaskan: {$orders}. Assign ulang order tersebut dari halaman order.");
        }

        return $redirect;
    }
}
