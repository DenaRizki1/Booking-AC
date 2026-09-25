{{-- Halaman sementara — diganti di T-401/T-706. --}}
<x-layouts.base title="Admin" class="flex min-h-screen items-center justify-center p-6">
    <x-card class="w-full max-w-md text-center">
        <x-empty-state icon="layout-dashboard" title="Halo, {{ auth()->user()->name }}" description="Panel Admin sedang disiapkan.">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-button type="submit" variant="secondary" size="sm" icon="log-out">Keluar</x-button>
            </form>
        </x-empty-state>
    </x-card>
</x-layouts.base>
