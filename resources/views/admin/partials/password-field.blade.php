{{-- Password untuk akun staf: tampilkan/sembunyikan + "Buat acak". Param: $name, $label, $id, $error, $hint. --}}
<div x-data="{
    show: false,
    value: '',
    generate() {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        const bytes = crypto.getRandomValues(new Uint32Array(10));
        this.value = Array.from(bytes, (n) => chars[n % chars.length]).join('');
        this.show = true;
    },
}">
    <x-input
        :name="$name"
        :id="$id"
        type="password"
        :label="$label"
        :error="$error ?? null"
        :hint="$hint ?? null"
        icon="lock"
        autocomplete="new-password"
        x-model="value"
        x-bind:type="show ? 'text' : 'password'"
        required
    >
        <x-slot:suffix>
            <button
                type="button"
                x-on:click="show = !show"
                x-bind:aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'"
                aria-label="Tampilkan password"
                class="flex size-11 items-center justify-center rounded-lg text-muted hover:text-ink focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/25"
            >
                <x-icon name="eye" class="size-5" x-show="!show" />
                <x-icon name="eye-off" class="size-5" x-show="show" x-cloak />
            </button>
        </x-slot:suffix>
    </x-input>
    <button type="button" x-on:click="generate()" class="mt-1 inline-flex min-h-11 items-center gap-1.5 text-[13px] font-bold text-primary hover:text-primary-hover">
        <x-icon name="dices" class="size-4" /> Buat password acak
    </button>
</div>
