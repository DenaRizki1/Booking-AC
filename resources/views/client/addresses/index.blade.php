@use('App\Support\Format')

@php
    $fee = app(\App\Services\TransportFeeCalculator::class);
@endphp

<x-layouts.client title="Alamat saya">
    <x-slot:actions>
        <x-button :href="route('client.addresses.create')" size="sm" icon="plus">Tambah</x-button>
    </x-slot:actions>

    @if ($addresses->isEmpty())
        <x-card>
            <x-empty-state icon="map-pin" title="Belum ada alamat" description="Tambahkan alamat beserta titik lokasinya agar teknisi bisa menemukan rumah Anda.">
                <x-button :href="route('client.addresses.create')" icon="plus">Tambah alamat</x-button>
            </x-empty-state>
        </x-card>
    @else
        <ul class="space-y-3">
            @foreach ($addresses as $address)
                @php
                    $inRange = $fee->isWithinRange((float) $address->distance_km, $settings);
                    $transport = $inRange ? $fee->feeFor((float) $address->distance_km, $settings) : null;
                @endphp
                <li class="rounded-2xl border-[1.5px] bg-white p-4 {{ $address->is_default ? 'border-primary' : 'border-line' }}">
                    <div class="flex items-start gap-3">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-primary">
                            <x-icon :name="match ($address->label) { 'Rumah' => 'house', 'Kantor' => 'building-2', default => 'map-pin' }" class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-bold text-ink">{{ $address->label }}</p>
                                @if ($address->is_default)
                                    <x-badge variant="primary" size="sm">Utama</x-badge>
                                @endif
                            </div>
                            <p class="mt-0.5 text-sm text-ink-soft">{{ $address->address }}</p>
                            @if ($address->landmark)
                                <p class="mt-0.5 text-[13px] text-muted">Patokan: {{ $address->landmark }}</p>
                            @endif
                            <p @class(['mt-2 text-[13px] font-semibold', 'text-success-700' => $inRange, 'text-danger' => ! $inRange])>{{ Format::km($address->distance_km) }} · {{ match (true) { ! $inRange => 'di luar jangkauan saat ini', $transport === 0 => 'transport gratis', default => 'transport '.Format::rupiah($transport) } }}</p>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2 border-t border-line pt-3">
                        <x-button :href="route('client.addresses.edit', $address)" variant="secondary" size="sm" icon="pencil">Ubah</x-button>
                        @unless ($address->is_default)
                            <form method="POST" action="{{ route('client.addresses.default', $address) }}">
                                @csrf
                                @method('PATCH')
                                <x-button type="submit" variant="ghost" size="sm" icon="star">Jadikan utama</x-button>
                            </form>
                        @endunless
                        <x-button variant="ghost" size="sm" icon="trash-2" class="ml-auto !text-danger" x-on:click="$dispatch('open-modal', 'delete-address-{{ $address->id }}')">Hapus</x-button>
                    </div>

                    <x-modal :name="'delete-address-'.$address->id" title="Hapus alamat?">
                        <p class="text-[15px] text-ink-soft">Alamat <strong>{{ $address->label }}</strong> akan dihapus dari daftar. Order yang sudah dibuat tetap menyimpan alamat ini.</p>
                        <x-slot:footer>
                            <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'delete-address-{{ $address->id }}')">Batal</x-button>
                            <form method="POST" action="{{ route('client.addresses.destroy', $address) }}">
                                @csrf
                                @method('DELETE')
                                <x-button type="submit" variant="danger-solid" block>Hapus</x-button>
                            </form>
                        </x-slot:footer>
                    </x-modal>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.client>
