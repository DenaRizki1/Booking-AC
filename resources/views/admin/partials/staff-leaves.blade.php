{{-- Kartu cuti teknisi/helper. Param: $leaves (cuti mulai hari ini), $action (url simpan). --}}
@use('App\Support\Format')

@php
    $leaveErrors = $errors->leave;
    $today = now()->toDateString();
@endphp

<section id="cuti" class="scroll-mt-6">
    <x-card title="Cuti" description="Tanggal cuti tidak bisa dipilih saat assign. Isi rentang tanggal; tiap hari dicatat terpisah.">
        <div class="grid gap-6 lg:grid-cols-5">
            <form method="POST" action="{{ $action }}" class="flex flex-col gap-4 lg:col-span-2" novalidate>
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input name="start_date" type="date" label="Mulai" :min="$today" :error="$leaveErrors->first('start_date') ?: null" :value="old('start_date')" required />
                    <x-input name="end_date" type="date" label="Sampai (opsional)" :min="$today" :error="$leaveErrors->first('end_date') ?: null" :value="old('end_date')" />
                </div>
                <x-input name="reason" label="Keterangan (opsional)" :error="$leaveErrors->first('reason') ?: null" :value="old('reason')" maxlength="255" placeholder="Mis. sakit, acara keluarga" />
                <div>
                    <x-button type="submit" variant="secondary" icon="calendar-plus">Catat cuti</x-button>
                </div>
            </form>

            <div class="lg:col-span-3">
                <h3 class="mb-2 text-[13px] font-semibold text-ink-soft">Jadwal cuti mendatang</h3>
                @if ($leaves->isEmpty())
                    <p class="rounded-xl bg-surface-bg px-4 py-3 text-sm text-muted">Tidak ada cuti terjadwal.</p>
                @else
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($leaves as $leave)
                            <li class="flex items-center gap-1 rounded-xl border border-line bg-white py-1 pr-1 pl-3 text-[13px]">
                                <span>
                                    <b class="font-bold text-ink">{{ Format::date($leave->leave_date) }}</b>
                                    @if ($leave->reason)<span class="text-muted">· {{ $leave->reason }}</span>@endif
                                </span>
                                <form method="POST" action="{{ route('admin.leaves.destroy', $leave) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="flex size-9 items-center justify-center rounded-lg text-muted hover:bg-danger-50 hover:text-danger focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-danger/25">
                                        <x-icon name="x" class="size-4" /><span class="sr-only">Hapus cuti {{ Format::date($leave->leave_date) }}</span>
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </x-card>
</section>
