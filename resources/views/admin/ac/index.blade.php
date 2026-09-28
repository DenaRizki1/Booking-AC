@use('App\Enums\AcMasterKind')

@php
    $isCapacity = $kind === AcMasterKind::CAPACITY;
    $nameColumn = $kind->nameColumn();
    $blank = ['id' => null, 'name' => '', 'label' => '', 'pk_value' => '', 'is_active' => true];
    $reopen = $errors->any() && old('kind') === $kind->value;
    $initial = $reopen
        ? ['id' => old('record_id') ?: null, 'name' => old('name', ''), 'label' => old('label', ''), 'pk_value' => old('pk_value', ''), 'is_active' => (bool) old('is_active')]
        : $blank;
@endphp

<x-layouts.admin title="Data AC" :subtitle="$kind->description()" :breadcrumbs="[['Master data', null], ['Data AC', route('admin.ac.index')], [$kind->label(), null]]">
    <div
        x-data="{
            blank: @js($blank),
            form: @js($initial),
            storeUrl: @js(route('admin.ac.store', $kind)),
            updateUrl: @js(route('admin.ac.update', [$kind, '__ID__'])),
            deleting: { name: '', url: '' },
            create() { this.form = { ...this.blank }; this.$dispatch('open-modal', 'ac-form'); },
            edit(record) { this.form = { ...this.blank, ...record }; this.$dispatch('open-modal', 'ac-form'); },
            confirmDelete(name, url) { this.deleting = { name, url }; this.$dispatch('open-modal', 'ac-delete'); },
            get action() { return this.form.id ? this.updateUrl.replace('__ID__', this.form.id) : this.storeUrl; },
        }"
        class="flex flex-col gap-5"
    >
        <div class="flex flex-wrap items-center gap-3">
            <nav class="flex flex-1 gap-2 overflow-x-auto" aria-label="Jenis data AC">
                @foreach (AcMasterKind::cases() as $tab)
                    <a
                        href="{{ route('admin.ac.index', $tab) }}"
                        @if ($tab === $kind) aria-current="page" @endif
                        @class([
                            'inline-flex min-h-11 shrink-0 items-center rounded-xl border-[1.5px] px-4 text-sm font-bold',
                            'border-primary bg-primary text-white' => $tab === $kind,
                            'border-line bg-white text-ink-soft hover:text-ink' => $tab !== $kind,
                        ])
                    >{{ $tab->label() }}</a>
                @endforeach
            </nav>
            <x-button icon="plus" x-on:click="create()">Tambah {{ strtolower($kind->noun()) }}</x-button>
        </div>

        <section class="overflow-hidden rounded-card border border-line bg-white">
            @if ($records->isEmpty())
                <x-empty-state icon="air-vent" :title="'Belum ada '.strtolower($kind->label())" description="Tambahkan data agar bisa dipilih client saat membuat order." />
            @else
                <div class="relative overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left">
                        <thead class="border-b border-line bg-[#F8FAFD] text-xs font-bold tracking-[0.4px] whitespace-nowrap text-muted uppercase">
                            <tr>
                                <th class="w-28 px-4 py-2.5">Urutan</th>
                                <th class="px-4 py-2.5">{{ $isCapacity ? 'Label' : 'Nama' }}</th>
                                @if ($isCapacity)<th class="px-4 py-2.5">Nilai PK</th>@endif
                                <th class="px-4 py-2.5">Dipakai</th>
                                <th class="px-4 py-2.5">Status</th>
                                <th class="px-4 py-2.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#EEF2F8] text-[13px]">
                            @foreach ($records as $record)
                                @php
                                    $name = $record->getAttribute($nameColumn);
                                    $payload = ['id' => $record->id, 'name' => (string) $record->getAttribute('name'), 'label' => (string) $record->getAttribute('label'), 'pk_value' => (string) $record->getAttribute('pk_value'), 'is_active' => $record->is_active];
                                    $used = $record->order_items_count > 0;
                                @endphp
                                <tr @class(['bg-surface-bg/60' => ! $record->is_active])>
                                    <td class="px-4 py-2">
                                        <div class="flex items-center">
                                            @foreach (['up' => 'chevron-up', 'down' => 'chevron-down'] as $direction => $icon)
                                                <form method="POST" action="{{ route('admin.ac.move', [$kind, $record->id]) }}">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="direction" value="{{ $direction }}">
                                                    <button
                                                        type="submit"
                                                        @disabled(($direction === 'up' && $loop->parent->first) || ($direction === 'down' && $loop->parent->last))
                                                        class="flex size-11 items-center justify-center rounded-[10px] text-muted hover:bg-surface-bg hover:text-ink disabled:opacity-30 disabled:hover:bg-transparent focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25"
                                                    >
                                                        <x-icon :name="$icon" class="size-4" />
                                                        <span class="sr-only">{{ $direction === 'up' ? 'Naikkan' : 'Turunkan' }} {{ $name }}</span>
                                                    </button>
                                                </form>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 text-sm font-bold text-ink">{{ $name }}</td>
                                    @if ($isCapacity)<td class="px-4 py-2 tabular-nums">{{ rtrim(rtrim((string) $record->pk_value, '0'), '.') }}</td>@endif
                                    <td class="px-4 py-2 whitespace-nowrap text-muted">
                                        {{ $record->order_items_count }} unit order
                                        @if ($kind->hasPrices()) · {{ $record->service_prices_count }} harga @endif
                                    </td>
                                    <td class="px-4 py-2">
                                        <x-badge :variant="$record->is_active ? 'success' : 'neutral'" size="sm">{{ $record->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="flex items-center justify-end gap-1">
                                            <form method="POST" action="{{ route('admin.ac.toggle', [$kind, $record->id]) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="inline-flex min-h-11 items-center rounded-[10px] px-3 text-[13px] font-bold text-ink-soft hover:bg-surface-bg hover:text-ink focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                                                    {{ $record->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </button>
                                            </form>
                                            <button type="button" x-on:click="edit(@js($payload))" class="flex size-11 items-center justify-center rounded-[10px] text-primary hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25">
                                                <x-icon name="pencil" class="size-4" /><span class="sr-only">Ubah {{ $name }}</span>
                                            </button>
                                            @if ($used)
                                                <span class="flex size-11 cursor-not-allowed items-center justify-center text-line-strong" title="Sudah dipakai di order — nonaktifkan saja">
                                                    <x-icon name="trash-2" class="size-4" /><span class="sr-only">{{ $name }} tidak bisa dihapus karena sudah dipakai di order</span>
                                                </span>
                                            @else
                                                <button type="button" x-on:click="confirmDelete(@js($name), @js(route('admin.ac.destroy', [$kind, $record->id])))" class="flex size-11 items-center justify-center rounded-[10px] text-danger hover:bg-danger-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-danger/25">
                                                    <x-icon name="trash-2" class="size-4" /><span class="sr-only">Hapus {{ $name }}</span>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <p class="text-[13px] text-muted">Urutan di atas = urutan pilihan di form order client. Data yang sudah dipakai di order tidak bisa dihapus; nonaktifkan agar tidak bisa dipilih lagi.</p>

        {{-- Modal tambah / ubah --}}
        <x-modal name="ac-form" :show="$reopen">
            <x-slot:title><span x-text="form.id ? 'Ubah {{ strtolower($kind->noun()) }}' : 'Tambah {{ strtolower($kind->noun()) }}'"></span></x-slot:title>
            <form id="ac-form" method="POST" :action="action" class="flex flex-col gap-4" novalidate>
                @csrf
                <input type="hidden" name="_method" :value="form.id ? 'PUT' : 'POST'">
                <input type="hidden" name="kind" value="{{ $kind->value }}">
                <input type="hidden" name="record_id" :value="form.id ?? ''">

                @if ($isCapacity)
                    <x-input name="label" label="Label" x-model="form.label" maxlength="20" placeholder="Mis. 1½ PK" hint="Teks yang dilihat client." required autofocus />
                    <x-input name="pk_value" type="number" step="0.25" min="0.25" label="Nilai PK" x-model="form.pk_value" placeholder="1.5" hint="Angka untuk mengurutkan, mis. ½ PK = 0.5, 1½ PK = 1.5." required />
                @else
                    <x-input name="name" :label="$kind === AcMasterKind::BRAND ? 'Nama merk' : 'Nama tipe'" x-model="form.name" :maxlength="$kind === AcMasterKind::BRAND ? 60 : 40" required autofocus />
                @endif

                <label class="inline-flex min-h-11 cursor-pointer items-center gap-3">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" role="switch" name="is_active" value="1" x-model="form.is_active" class="peer sr-only">
                    <span aria-hidden="true" class="relative h-6 w-10 shrink-0 rounded-full bg-line-strong transition-colors after:absolute after:top-1 after:left-1 after:size-4 after:rounded-full after:bg-white after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-4 peer-focus-visible:ring-4 peer-focus-visible:ring-primary/25"></span>
                    <span>
                        <span class="block text-[15px] font-semibold text-ink">Aktif</span>
                        <span class="block text-[13px] text-muted">Bisa dipilih client saat membuat order.</span>
                    </span>
                </label>
            </form>
            <x-slot:footer>
                <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'ac-form')">Batal</x-button>
                <x-button type="submit" form="ac-form">Simpan</x-button>
            </x-slot:footer>
        </x-modal>

        {{-- Modal hapus --}}
        <x-modal name="ac-delete" title="Hapus data?">
            <p class="text-sm text-ink-soft">
                <b class="text-ink" x-text="deleting.name"></b> akan dihapus permanen.
                @if ($kind->hasPrices()) Harga di matriks untuk {{ strtolower($kind->noun()) }} ini ikut dihapus. @endif
            </p>
            <x-slot:footer>
                <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'ac-delete')">Batal</x-button>
                <form method="POST" :action="deleting.url">
                    @csrf @method('DELETE')
                    <x-button type="submit" variant="danger-solid" icon="trash-2" class="w-full">Hapus</x-button>
                </form>
            </x-slot:footer>
        </x-modal>
    </div>
</x-layouts.admin>
