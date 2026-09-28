/**
 * Admin: matriks harga PK × Tipe + panel "Isi cepat" (terapkan ke semua / baris PK / kolom tipe).
 * Harga ditampilkan berformat "75.000"; server membuang karakter non-angka.
 */
const digits = (value) => String(value ?? '').replace(/\D/g, '');
const formatMoney = (value) => (digits(value) === '' ? '' : new Intl.NumberFormat('id-ID').format(Number(digits(value))));
const toNumber = (value) => Number(String(value ?? '').replace(',', '.'));

export default function priceMatrix(config) {
    return {
        cells: config.cells,
        capacities: config.capacities,
        types: config.types,
        quick: { min: '', max: '', hours: '' },
        row: config.capacities[0]?.id ?? null,
        col: config.types[0]?.id ?? null,
        preview: null,
        quickError: '',

        get totalCount() {
            return Object.keys(this.cells).length;
        },
        get activeCount() {
            return Object.values(this.cells).filter((cell) => cell.active && this.filled(cell)).length;
        },
        get rowLabel() {
            return this.capacities.find((c) => c.id === Number(this.row))?.label ?? '';
        },
        get colLabel() {
            return this.types.find((t) => t.id === Number(this.col))?.name ?? '';
        },

        filled(cell) {
            return digits(cell.min) !== '' || digits(cell.max) !== '' || String(cell.hours ?? '').trim() !== '';
        },

        isTarget(capacityId, typeId) {
            return (
                this.preview === 'all' ||
                (this.preview === 'row' && capacityId === Number(this.row)) ||
                (this.preview === 'col' && typeId === Number(this.col))
            );
        },

        targetCount(target) {
            return target === 'all' ? this.totalCount : target === 'row' ? this.types.length : this.capacities.length;
        },

        apply(target) {
            const min = digits(this.quick.min);
            const max = digits(this.quick.max);
            const hours = toNumber(this.quick.hours);

            if (min === '' || max === '' || !(hours > 0)) {
                this.quickError = 'Isi harga mulai, harga sampai, dan estimasi durasi terlebih dahulu.';
                return;
            }
            if (Number(min) > Number(max)) {
                this.quickError = 'Harga mulai tidak boleh lebih besar dari harga sampai.';
                return;
            }
            this.quickError = '';

            for (const capacity of this.capacities) {
                for (const type of this.types) {
                    if (target === 'row' && capacity.id !== Number(this.row)) continue;
                    if (target === 'col' && type.id !== Number(this.col)) continue;

                    Object.assign(this.cells[`${capacity.id}-${type.id}`], {
                        min: formatMoney(min),
                        max: formatMoney(max),
                        hours: String(this.quick.hours).replace('.', ','),
                        active: true,
                    });
                }
            }
        },

        formatCell(key, field) {
            this.cells[key][field] = formatMoney(this.cells[key][field]);
        },

        formatQuick(field) {
            this.quick[field] = formatMoney(this.quick[field]);
        },

        clear(key) {
            Object.assign(this.cells[key], { min: '', max: '', hours: '', active: true });
        },
    };
}
