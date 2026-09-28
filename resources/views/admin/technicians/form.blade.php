@php
    $editing = $technician->exists;
    $name = $editing ? $technician->user->name : 'Tambah teknisi';
@endphp

<x-layouts.admin
    :title="$name"
    :subtitle="$editing ? 'Akun login: '.$technician->user->email : 'Teknisi baru langsung mendapat akun login panel teknisi.'"
    :breadcrumbs="[['Master data', null], ['Teknisi', route('admin.technicians.index')], [$name, null]]"
>
    <x-slot:actions>
        @if ($editing)
            <x-badge :variant="$technician->is_active ? 'success' : 'neutral'">{{ $technician->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge>
        @endif
        <x-button :href="route('admin.technicians.index')" variant="secondary">{{ $editing ? 'Kembali' : 'Batal' }}</x-button>
        <x-button type="submit" form="technician-form" icon="save">{{ $editing ? 'Simpan perubahan' : 'Simpan teknisi' }}</x-button>
    </x-slot:actions>

    <form id="technician-form" method="POST" action="{{ $editing ? route('admin.technicians.update', $technician) : route('admin.technicians.store') }}" enctype="multipart/form-data" class="grid items-start gap-6 lg:grid-cols-3" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="flex flex-col gap-6 lg:col-span-2">
            <x-card title="Data teknisi">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <x-input name="name" label="Nama lengkap" :value="$editing ? $technician->user->name : null" maxlength="120" required :autofocus="! $editing" />
                    </div>
                    <x-input name="email" type="email" label="Email (untuk login)" :value="$editing ? $technician->user->email : null" autocomplete="off" required />
                    <x-input name="phone" type="tel" inputmode="tel" label="No. HP / WhatsApp" :value="$editing ? $technician->user->phone : null" placeholder="08…" required />
                    <div class="md:col-span-2">
                        <x-textarea name="notes" label="Catatan internal (opsional)" :value="$technician->notes" rows="3" hint="Hanya terlihat oleh admin, mis. keahlian atau area kerja." />
                    </div>
                </div>
            </x-card>

            @if ($editing)
                <x-card title="Ringkasan kinerja">
                    <dl class="grid gap-4 sm:grid-cols-3">
                        <div class="rounded-xl bg-surface-bg px-4 py-3">
                            <dt class="text-xs font-semibold text-muted">Rating</dt>
                            <dd class="mt-1 text-xl font-extrabold text-ink">{{ $technician->review_count ? number_format((float) $technician->avg_rating, 1, ',', '') : '–' }}</dd>
                            <dd class="text-xs text-muted">{{ $technician->review_count }} ulasan</dd>
                        </div>
                        <div class="rounded-xl bg-surface-bg px-4 py-3">
                            <dt class="text-xs font-semibold text-muted">Order aktif</dt>
                            <dd class="mt-1 text-xl font-extrabold text-ink">{{ $technician->active_orders_count }}</dd>
                        </div>
                        <div class="rounded-xl bg-surface-bg px-4 py-3">
                            <dt class="text-xs font-semibold text-muted">Order selesai</dt>
                            <dd class="mt-1 text-xl font-extrabold text-ink">{{ $technician->done_orders_count }}</dd>
                        </div>
                    </dl>
                </x-card>
            @endif
        </div>

        <div class="flex flex-col gap-6">
            <x-card title="Akun login">
                @if ($editing)
                    <p class="text-sm text-ink-soft">Teknisi masuk di halaman login staf dengan email di samping.</p>
                    <x-button variant="secondary" icon="key-round" block class="mt-4" x-on:click="$dispatch('open-modal', 'reset-password')">Reset password</x-button>
                @else
                    @include('admin.partials.password-field', ['name' => 'password', 'id' => 'f-password', 'label' => 'Password awal', 'hint' => 'Minimal 8 karakter. Sampaikan ke teknisi secara langsung.'])
                @endif
                <div class="mt-4 border-t border-[#EEF2F8] pt-3">
                    <x-toggle name="is_active" :checked="(bool) $technician->is_active" label="Aktif" description="Bisa login dan muncul saat assign order." />
                </div>
            </x-card>

            <x-card title="Foto (opsional)">
                <div x-data="{ preview: @js($editing ? $technician->photoUrl() : null), remove: false }" class="flex items-center gap-4">
                    <template x-if="preview && !remove">
                        <img :src="preview" alt="" class="size-16 shrink-0 rounded-full object-cover">
                    </template>
                    <template x-if="!preview || remove">
                        <x-avatar :name="$editing ? $technician->user->name : 'Teknisi Baru'" size="lg" />
                    </template>
                    <div class="min-w-0 flex-1">
                        <label for="f-photo" class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border-[1.5px] border-blue-200 px-4 text-sm font-bold text-primary hover:bg-blue-50">
                            <x-icon name="image-plus" class="size-4" /> Pilih foto
                        </label>
                        <input id="f-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only" x-on:change="const f = $event.target.files[0]; if (f) { preview = URL.createObjectURL(f); remove = false; }">
                        @if ($editing && $technician->photo)
                            <label class="mt-1 flex min-h-11 items-center gap-2 text-[13px] font-semibold text-ink-soft">
                                <input type="checkbox" name="remove_photo" value="1" x-model="remove" class="size-4 accent-primary"> Hapus foto
                            </label>
                        @endif
                        <p class="mt-1 text-xs text-muted">JPG/PNG/WEBP, maks. 2 MB.</p>
                        <x-field-error name="photo" />
                    </div>
                </div>
            </x-card>
        </div>
    </form>

    @if ($editing)
        <x-modal name="reset-password" title="Reset password" :show="$errors->resetPassword->any()">
            <form id="reset-password-form" method="POST" action="{{ route('admin.technicians.password', $technician) }}" class="flex flex-col gap-3" novalidate>
                @csrf
                @method('PUT')
                <p class="text-sm text-ink-soft">Password lama {{ $technician->user->name }} tidak berlaku lagi dan semua sesi login-nya diakhiri.</p>
                @include('admin.partials.password-field', ['name' => 'password', 'id' => 'f-new-password', 'label' => 'Password baru', 'error' => $errors->resetPassword->first('password'), 'hint' => 'Minimal 8 karakter.'])
            </form>
            <x-slot:footer>
                <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'reset-password')">Batal</x-button>
                <x-button type="submit" form="reset-password-form" icon="key-round">Reset password</x-button>
            </x-slot:footer>
        </x-modal>
    @endif
</x-layouts.admin>
