{{-- Modal tolak tugas (alasan wajib → order kembali MENUNGGU). --}}
@php($rejectErrors = $errors->reject)
<x-modal :name="'reject-'.$order->id" title="Tolak tugas?" :show="$rejectErrors->any() && (int) old('order_id') === $order->id">
    <form id="reject-form-{{ $order->id }}" method="POST" action="{{ route('teknisi.tasks.reject', $order) }}" class="flex flex-col gap-3" novalidate>
        @csrf
        <input type="hidden" name="order_id" value="{{ $order->id }}">
        <p class="text-sm text-ink-soft">{{ $order->code }} akan dikembalikan ke admin untuk ditugaskan ke teknisi lain.</p>
        <x-textarea name="reason" :id="'reject-reason-'.$order->id" label="Alasan" rows="3" :error="(int) old('order_id') === $order->id ? ($rejectErrors->first('reason') ?: null) : null" placeholder="Mis. sakit, kendaraan rusak, jadwal bentrok" maxlength="500" required autofocus />
    </form>
    <x-slot:footer>
        <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'reject-{{ $order->id }}')">Batal</x-button>
        <x-button type="submit" :form="'reject-form-'.$order->id" variant="danger-solid">Tolak tugas</x-button>
    </x-slot:footer>
</x-modal>
