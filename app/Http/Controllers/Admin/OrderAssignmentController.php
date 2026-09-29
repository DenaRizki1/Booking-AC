<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\OrderAssignmentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignOrderRequest;
use App\Models\Helper;
use App\Models\Order;
use App\Models\Technician;
use App\Services\OrderAssignmentService;
use Illuminate\Http\RedirectResponse;

class OrderAssignmentController extends Controller
{
    public function __invoke(AssignOrderRequest $request, Order $order, OrderAssignmentService $assignments): RedirectResponse
    {
        $technician = Technician::query()->with('user')->findOrFail($request->integer('technician_id'));
        $helper = $request->filled('helper_id') ? Helper::query()->findOrFail($request->integer('helper_id')) : null;

        try {
            $message = $assignments->assign($order, $technician, $helper, $request->user());
        } catch (OrderAssignmentException $exception) {
            return back()->withInput()->withErrors([$exception->field => $exception->getMessage()]);
        }

        return to_route('admin.orders.show', $order)->with('success', $message);
    }
}
