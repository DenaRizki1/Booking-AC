/**
 * Buat order — langkah 1: daftar unit AC (mockup Client-Order-Unit).
 * Estimasi dihitung server (GET /order/estimate); draft disimpan otomatis ke session.
 */
let uid = 0;
const withUid = (unit) => ({ uid: ++uid, ac_brand_id: null, ac_capacity_id: null, ac_type_id: null, service_id: null, ...unit });
const blank = (value) => value === null || value === undefined || value === '';

export default function orderUnits(config) {
    let timer = null;
    let requestId = 0;

    return {
        catalog: config.catalog,
        addresses: config.addresses,
        addressId: config.addressId,
        units: config.units.map(withUid),
        open: 0,
        pickingAddress: false,
        estimate: null,
        loading: false,
        applyField: 'service_id',
        applyValue: '',

        init() {
            this.units.forEach((unit) => this.normalize(unit));
            this.refresh(true);
            this.$watch('units', () => this.refresh(), { deep: true });
            this.$watch('addressId', () => this.refresh());
        },

        get address() {
            return this.addresses.find((address) => address.id === this.addressId) ?? null;
        },

        get applyOptions() {
            return {
                service_id: this.catalog.services,
                ac_brand_id: this.catalog.brands,
                ac_capacity_id: this.catalog.capacities.map((c) => ({ id: c.id, name: c.label })),
                ac_type_id: this.catalog.types,
            }[this.applyField];
        },

        get canContinue() {
            return Boolean(this.address?.in_range && this.estimate?.complete && this.units.every((u) => !blank(u.ac_brand_id)));
        },

        servicesFor(unit) {
            const available = this.catalog.availability[`${unit.ac_capacity_id}-${unit.ac_type_id}`] ?? [];
            return this.catalog.services.filter((service) => available.includes(service.id));
        },

        normalize(unit) {
            if (!blank(unit.service_id) && !this.servicesFor(unit).some((s) => s.id === Number(unit.service_id))) {
                unit.service_id = null;
            }
        },

        line(index) {
            return this.estimate?.units?.[index] ?? null;
        },

        name(list, id) {
            return this.catalog[list].find((item) => item.id === Number(id))?.[list === 'capacities' ? 'label' : 'name'] ?? null;
        },

        unitTitle(unit) {
            return [this.name('brands', unit.ac_brand_id) ?? 'Pilih merk', this.name('capacities', unit.ac_capacity_id), this.name('types', unit.ac_type_id)]
                .filter(Boolean)
                .join(' · ');
        },

        unitSummary(unit, index) {
            const service = this.name('services', unit.service_id);
            const line = this.line(index);
            if (!service) {
                return 'Belum pilih layanan';
            }
            return line?.available ? `${service} · ${line.price_label}` : service;
        },

        select(unit, field, value) {
            unit[field] = value;
            this.normalize(unit);
        },

        addUnit() {
            const last = this.units[this.units.length - 1] ?? {};
            this.units.push(withUid({ ac_capacity_id: last.ac_capacity_id, ac_type_id: last.ac_type_id }));
            this.open = this.units.length - 1;
        },

        copyUnit(index = this.open) {
            const source = this.units[index] ?? this.units[this.units.length - 1];
            const { uid: _, ...copy } = source;
            this.units.splice(index + 1, 0, withUid(copy));
            this.open = index + 1;
        },

        removeUnit(index) {
            if (this.units.length === 1) {
                return;
            }
            this.units.splice(index, 1);
            this.open = Math.min(this.open, this.units.length - 1);
        },

        applyToAll() {
            if (blank(this.applyValue)) {
                return;
            }
            this.units.forEach((unit) => {
                unit[this.applyField] = Number(this.applyValue);
                this.normalize(unit);
            });
        },

        payload() {
            const params = new URLSearchParams();
            params.set('customer_address_id', this.addressId ?? '');
            this.units.forEach((unit, i) => {
                ['ac_brand_id', 'ac_capacity_id', 'ac_type_id', 'service_id'].forEach((key) => {
                    params.set(`units[${i}][${key}]`, blank(unit[key]) ? '' : unit[key]);
                });
            });
            return params;
        },

        refresh(immediate = false) {
            clearTimeout(timer);
            timer = setTimeout(async () => {
                const id = ++requestId;
                const params = this.payload();
                this.loading = true;
                try {
                    const [{ data }] = await Promise.all([
                        window.axios.get(`${config.estimateUrl}?${params}`),
                        window.axios.put(config.draftUrl, params, { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }),
                    ]);
                    if (id === requestId) {
                        this.estimate = data;
                    }
                } finally {
                    if (id === requestId) {
                        this.loading = false;
                    }
                }
            }, immediate ? 0 : 300);
        },
    };
}
