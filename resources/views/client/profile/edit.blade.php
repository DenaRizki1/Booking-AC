<x-layouts.client title="Profil">
    <x-card class="mb-4">
        <div class="flex items-center gap-3">
            <x-avatar :name="$user->name" :src="$user->avatar" size="lg" />
            <div class="min-w-0">
                <p class="truncate text-lg font-extrabold text-ink">{{ $user->name }}</p>
                <p class="truncate text-sm text-muted">{{ $user->email }}</p>
                <p class="mt-0.5 inline-flex items-center gap-1 text-xs font-semibold text-muted">
                    <x-icon name="shield-check" class="size-3.5 text-success" /> Masuk dengan Google
                </p>
            </div>
        </div>
    </x-card>

    <x-card title="Data diri" class="mb-4">
        <form method="POST" action="{{ route('client.profile.update') }}" class="space-y-4" novalidate>
            @csrf
            @method('PUT')
            <x-input name="name" label="Nama" :value="$user->name" autocomplete="name" required />
            <x-input name="phone" type="tel" label="No. HP (WhatsApp)" icon="phone" inputmode="tel" autocomplete="tel" :value="$user->phone" hint="Format 08… atau +62…" required />
            <x-button type="submit" block icon="save">Simpan</x-button>
        </form>
    </x-card>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <x-button type="submit" variant="danger" block icon="log-out">Keluar</x-button>
    </form>
</x-layouts.client>
