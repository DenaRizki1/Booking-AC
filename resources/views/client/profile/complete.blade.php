<x-layouts.client title="Lengkapi profil" subtitle="Satu langkah lagi sebelum membuat order." width="narrow">
    <div class="mb-6 flex items-center gap-3">
        <x-avatar :name="$user->name" :src="$user->avatar" size="lg" />
        <div>
            <p class="text-sm text-muted">Selamat datang,</p>
            <p class="text-lg font-extrabold text-ink">{{ $user->name }}</p>
        </div>
    </div>

    <x-card>
        <p class="mb-4 text-[15px] text-ink-soft">Masukkan no. HP aktif agar teknisi dan admin bisa menghubungi Anda saat mengerjakan order.</p>

        <form method="POST" action="{{ route('client.profile.complete.store') }}" class="space-y-4" novalidate>
            @csrf
            <x-input name="name" label="Nama" :value="$user->name" autocomplete="name" required />
            <x-input name="phone" type="tel" label="No. HP (WhatsApp)" icon="phone" inputmode="tel" autocomplete="tel" placeholder="08xxxxxxxxxx" hint="Format 08… atau +62…" required autofocus />
            <x-button type="submit" block size="lg">Simpan & lanjut</x-button>
        </form>
    </x-card>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="min-h-11 text-sm font-semibold text-muted hover:text-ink">Bukan Anda? Keluar</button>
    </form>
</x-layouts.client>
