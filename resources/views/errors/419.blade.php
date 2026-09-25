<x-layouts.base title="Sesi berakhir" class="flex min-h-screen items-center justify-center p-6">
    <x-card class="w-full max-w-md">
        <x-empty-state icon="clock-alert" title="Sesi berakhir" description="Halaman terlalu lama dibuka. Muat ulang lalu coba lagi.">
            <x-button href="{{ url('/') }}" variant="secondary" size="sm" icon="house">Kembali ke beranda</x-button>
        </x-empty-state>
    </x-card>
</x-layouts.base>
