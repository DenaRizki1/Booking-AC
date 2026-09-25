<x-layouts.base title="Akses ditolak" class="flex min-h-screen items-center justify-center p-6">
    <x-card class="w-full max-w-md">
        <x-empty-state icon="shield-x" title="Akses ditolak" description="Anda tidak memiliki akses ke halaman ini.">
            <x-button href="{{ url('/') }}" variant="secondary" size="sm" icon="house">Kembali ke beranda</x-button>
        </x-empty-state>
    </x-card>
</x-layouts.base>
