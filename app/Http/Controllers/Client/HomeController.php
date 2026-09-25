<?php

namespace App\Http\Controllers\Client;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
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
            'warranty' => $this->latestActiveWarranty($client),
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

    /**
     * Unit dengan garansi masih berlaku yang paling lama masa berlakunya.
     */
    private function latestActiveWarranty(User $client): ?OrderItem
    {
        return OrderItem::query()
            ->whereDate('warranty_until', '>=', today())
            ->whereHas('order', fn (Builder $order) => $order
                ->where('user_id', $client->id)
                ->where('order_type', OrderType::REGULER)
                ->whereIn('status', [OrderStatus::SELESAI, OrderStatus::LUNAS]))
            ->with('order:id,code')
            ->orderByDesc('warranty_until')
            ->first();
    }
}
