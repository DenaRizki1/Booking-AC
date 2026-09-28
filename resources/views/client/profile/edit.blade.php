<x-layouts.client title="Profil" subtitle="Data diri untuk dihubungi teknisi & admin.">
    <div class="grid items-start gap-6 lg:grid-cols-3">
        <x-card>
            <div class="flex flex-col items-center text-center">
                <x-avatar :name="$user->name" :src="$user->avatar" size="lg" class="!size-20 !text-2xl" />
                <p class="mt-3 text-lg font-extrabold text-ink">{{ $user->name }}</p>
                <p class="text-sm text-muted">{{ $user->email }}</p>
                <p class="mt-2 inline-flex items-center gap-1 rounded-full bg-success-50 px-3 py-1 text-xs font-semibold text-success-700">
                    <x-icon name="shield-check" class="size-3.5" /> Masuk dengan Google
                </p>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mt-5 border-t border-line pt-5">
                @csrf
                <x-button type="submit" variant="danger" block icon="log-out">Keluar</x-button>
            </form>
        </x-card>

        <x-card title="Data diri" class="lg:col-span-2">
            <form method="POST" action="{{ route('client.profile.update') }}" class="grid gap-4 sm:grid-cols-2" novalidate>
                @csrf
                @method('PUT')
                <x-input name="name" label="Nama" :value="$user->name" autocomplete="name" required />
                <x-input name="phone" type="tel" label="No. HP (WhatsApp)" icon="phone" inputmode="tel" autocomplete="tel" :value="$user->phone" hint="Format 08… atau +62…" required />
                <x-input name="email_display" label="Email" :value="$user->email" disabled hint="Mengikuti akun Google Anda." />
                <div class="flex items-end justify-end sm:col-span-2">
                    <x-button type="submit" icon="save">Simpan perubahan</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.client>
