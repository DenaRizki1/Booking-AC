{{-- Statistik lengkap menyusul di T-706. --}}
<x-layouts.admin title="Dashboard" :subtitle="\App\Support\Format::date(now())">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-card class="border-primary! bg-primary! text-white">
            <p class="text-[13px] font-semibold text-white/85">Menunggu assign</p>
            <p class="mt-1.5 text-[28px] font-extrabold tracking-tight tabular-nums">{{ $waitingOrders }}</p>
            <p class="mt-1 text-xs text-white/85">order baru dari client</p>
        </x-card>
    </div>

    <x-card>
        <h2 class="text-base font-extrabold text-ink">Halo, {{ auth()->user()->name }}</h2>
        <p class="mt-1 text-sm text-muted">Mulai dengan melengkapi pengaturan perusahaan, data AC, layanan & harga, serta tim teknisi.</p>
    </x-card>
</x-layouts.admin>
