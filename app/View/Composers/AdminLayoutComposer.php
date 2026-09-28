<?php

namespace App\View\Composers;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\View\View;

/**
 * Data sidebar admin: menu + jumlah order MENUNGGU (badge menu Order).
 */
class AdminLayoutComposer
{
    public function compose(View $view): void
    {
        $view->with([
            'pendingOrders' => Order::query()->where('status', OrderStatus::MENUNGGU)->count(),
            'menu' => self::menu(),
        ]);
    }

    /**
     * Grup menu → daftar item [route, pola route aktif, ikon, label, badge?].
     * Route yang belum dibuat (fitur task berikutnya) tampil sebagai item nonaktif.
     *
     * @return array<string, list<array{route: string, active: string, icon: string, label: string, badge?: bool}>>
     */
    public static function menu(): array
    {
        return [
            '' => [
                ['route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
                ['route' => 'admin.orders.index', 'active' => 'admin.orders.*', 'icon' => 'clipboard-list', 'label' => 'Order', 'badge' => true],
                ['route' => 'admin.schedule', 'active' => 'admin.schedule*', 'icon' => 'calendar-days', 'label' => 'Jadwal'],
                ['route' => 'admin.services.index', 'active' => 'admin.services.*', 'icon' => 'tag', 'label' => 'Layanan & Harga'],
            ],
            'Master data' => [
                ['route' => 'admin.ac.index', 'active' => 'admin.ac.*', 'icon' => 'air-vent', 'label' => 'Data AC'],
                ['route' => 'admin.technicians.index', 'active' => 'admin.technicians.*', 'icon' => 'wrench', 'label' => 'Teknisi'],
                ['route' => 'admin.helpers.index', 'active' => 'admin.helpers.*', 'icon' => 'users', 'label' => 'Helper'],
                ['route' => 'admin.clients.index', 'active' => 'admin.clients.*', 'icon' => 'user-round', 'label' => 'Client'],
                ['route' => 'admin.reviews.index', 'active' => 'admin.reviews.*', 'icon' => 'star', 'label' => 'Ulasan'],
                ['route' => 'admin.settings.edit', 'active' => 'admin.settings.*', 'icon' => 'sliders-horizontal', 'label' => 'Pengaturan'],
            ],
        ];
    }
}
