<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\Technician;
use App\Services\OrderAssignmentService;
use App\Services\OrderStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * Tab daftar order: kunci => [label, status, urut jadwal naik?].
     *
     * @var array<string, array{0: string, 1: list<OrderStatus>, 2: bool}>
     */
    public const TABS = [
        'menunggu' => ['Menunggu', [OrderStatus::MENUNGGU], true],
        'dijadwalkan' => ['Dijadwalkan', [OrderStatus::DITUGASKAN, OrderStatus::DIKONFIRMASI], true],
        'berjalan' => ['Berjalan', [OrderStatus::DALAM_PERJALANAN, OrderStatus::DIKERJAKAN], true],
        'belum-lunas' => ['Belum lunas', [OrderStatus::SELESAI], false],
        'lunas' => ['Lunas', [OrderStatus::LUNAS], false],
        'batal' => ['Ditolak / batal', [OrderStatus::DITOLAK, OrderStatus::DIBATALKAN], false],
        'semua' => ['Semua', [], false],
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(array_keys(self::TABS))],
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'type' => ['nullable', Rule::enum(OrderType::class)],
            'technician' => ['nullable', 'string', 'max:20'],
        ]);
        $tab = $filters['tab'] ?? 'menunggu';
        [, $statuses, $ascending] = self::TABS[$tab];

        $filtered = $this->filtered($filters);

        // Jumlah per tab mengikuti filter lain (tanpa filter tab).
        $counts = (clone $filtered)
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $tabCounts = collect(self::TABS)->map(fn (array $definition) => $definition[1] === []
            ? (int) $counts->sum()
            : (int) collect($definition[1])->sum(fn (OrderStatus $status) => $counts[$status->value] ?? 0));

        $orders = $filtered
            ->when($statuses !== [], fn (Builder $query) => $query->whereIn('status', $statuses))
            ->with(['client:id,name,phone', 'technician.user:id,name', 'helper:id,name', 'items:id,order_id,service_name'])
            ->withCount('items')
            ->orderBy('scheduled_start', $ascending ? 'asc' : 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'tab' => $tab,
            'tabCounts' => $tabCounts,
            'filters' => $filters,
            'technicians' => Technician::query()->with('user:id,name')->get()->sortBy('user.name'),
        ]);
    }

    public function show(Request $request, Order $order, OrderAssignmentService $assignments, OrderStatusService $statuses): View
    {
        $order->load([
            'client:id,name,email,phone,avatar',
            'technician.user:id,name,phone',
            'helper:id,name,phone',
            'items.photos',
            'parentOrder:id,code,technician_id',
            'statusLogs.changedBy:id,name,role',
            'review',
            'paidConfirmedBy:id,name',
        ]);

        return view('admin.orders.show', [
            'order' => $order,
            'canAssign' => $assignments->canAssign($order),
            'candidates' => $assignments->canAssign($order) ? $assignments->candidates($order) : null,
            'settings' => CompanySettings::current(),
            'allowed' => $statuses->allowedTransitions($order, $request->user()),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Order>
     */
    private function filtered(array $filters): Builder
    {
        $search = trim((string) ($filters['q'] ?? ''));

        return Order::query()
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhereHas('client', fn (Builder $client) => $client
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"))))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('scheduled_start', '>=', CarbonImmutable::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('scheduled_start', '<', CarbonImmutable::parse($to)->addDay()->startOfDay()))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('order_type', $type))
            ->when($filters['technician'] ?? null, fn (Builder $query, string $technician) => $technician === 'none'
                ? $query->whereNull('technician_id')
                : $query->where('technician_id', (int) $technician));
    }
}
