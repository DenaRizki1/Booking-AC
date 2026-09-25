<?php

namespace App\Http\Controllers\Client;

use App\Exceptions\OrderPlacementException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreWarrantyClaimRequest;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\User;
use App\Services\WarrantyClaimService;
use App\Support\BookingCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WarrantyClaimController extends Controller
{
    public function __construct(
        private readonly WarrantyClaimService $claims,
        private readonly BookingCalendar $calendar,
    ) {}

    public function create(Order $order): View|RedirectResponse
    {
        Gate::authorize('view', $order);

        $items = $this->claims->claimableItems($order);
        if ($items->isEmpty()) {
            return redirect()->route('client.orders.show', $order)->with('error', 'Tidak ada unit yang masih bisa diklaim garansinya.');
        }

        return view('client.orders.warranty', [
            'order' => $order,
            'items' => $items,
            'selected' => array_map('intval', (array) old('items', [$items->first()->id])),
        ]);
    }

    /**
     * Tanggal & jam tersedia untuk unit yang dipilih (JSON).
     */
    public function slots(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        $items = $this->claims->claimableItems($order)->whereIn('id', array_map('intval', (array) $request->input('items', [])));
        if ($items->isEmpty()) {
            return response()->json(['dates' => []]);
        }

        ['minutes' => $minutes, 'requires_helper' => $needsHelper] = $this->claims->workload($items);

        return response()->json(['dates' => $this->calendar->dates($minutes, $needsHelper, CompanySettings::current())]);
    }

    public function store(StoreWarrantyClaimRequest $request, Order $order): RedirectResponse
    {
        Gate::authorize('view', $order);

        /** @var User $client */
        $client = $request->user();

        try {
            $claim = $this->claims->claim($client, $order, $request->itemIds(), $request->start(), $request->string('complaint')->toString(), $request->file('photo'));
        } catch (OrderPlacementException $e) {
            return back()->withInput()->withErrors([$e->field => $e->getMessage()]);
        }

        return redirect()->route('client.orders.show', $claim)
            ->with('success', "Klaim garansi {$claim->code} dibuat. Admin akan menugaskan teknisi.");
    }
}
