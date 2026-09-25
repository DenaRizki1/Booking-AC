<?php

namespace App\Http\Controllers\Client;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\User;
use App\Services\WarrantyClaimService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(private readonly WarrantyClaimService $warranties) {}

    public function __invoke(Request $request): View
    {
        /** @var User $client */
        $client = $request->user();

        $activeOrders = $client->orders()
            ->whereIn('status', OrderStatus::activeCases())
            ->with(['technician.user', 'helper'])
            ->withCount('items')
            ->orderBy('scheduled_start')
            ->get();

        return view('client.home', [
            'client' => $client,
            'address' => $client->addresses()->orderByDesc('is_default')->oldest()->first(),
            'activeOrder' => $activeOrders->first(),
            'otherActiveCount' => max(0, $activeOrders->count() - 1),
            'services' => $this->servicesWithStartingPrice(),
            'warranty' => $this->warranties->latestClaimableItem($client),
        ]);
    }

    /**
     * Layanan aktif yang punya minimal 1 harga aktif, dengan harga & durasi "mulai".
     *
     * @return Collection<int, Service>
     */
    private function servicesWithStartingPrice()
    {
        $activePrice = fn (Builder $query) => $query->where('is_active', true)
            ->whereHas('capacity', fn (Builder $q) => $q->where('is_active', true))
            ->whereHas('type', fn (Builder $q) => $q->where('is_active', true));

        return Service::query()
            ->where('is_active', true)
            ->whereHas('prices', $activePrice)
            ->withMin(['prices as price_from' => $activePrice], 'price_min')
            ->withMin(['prices as minutes_from' => $activePrice], 'est_minutes')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
