<x-layouts.base title="Halaman tidak ditemukan" class="flex min-h-screen items-center justify-center p-6">
    <x-card class="w-full max-w-md">
        <x-empty-state icon="search-x" title="Halaman tidak ditemukan" description="Halaman yang Anda cari tidak ada atau sudah dipindahkan.">
            <x-button href="{{ url('/') }}" variant="secondary" size="sm" icon="house">Kembali ke beranda</x-button>
        </x-empty-state>
    </x-card>
</x-layouts.base>
