<x-layouts.teknisi title="Akun" :back="route('teknisi.tasks')">
    <x-card>
        <div class="flex items-center gap-4">
            <x-avatar :name="auth()->user()->name" :src="$technician?->photoUrl()" size="lg" />
            <div class="min-w-0">
                <p class="text-lg font-extrabold text-ink">{{ auth()->user()->name }}</p>
                <p class="truncate text-sm text-muted">{{ auth()->user()->email }}</p>
                <p class="text-sm text-muted">{{ auth()->user()->phone }}</p>
            </div>
        </div>
        <p class="mt-4 rounded-xl bg-surface-bg px-4 py-3 text-[13px] text-ink-soft">Data akun & password dikelola admin. Hubungi admin bila ada perubahan.</p>
    </x-card>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <x-button type="submit" variant="danger" icon="log-out" block>Keluar</x-button>
    </form>
</x-layouts.teknisi>
