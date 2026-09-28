/**
 * Pilih titik alamat di peta (Leaflet + OpenStreetMap).
 * Marker tetap di tengah; peta yang digeser. Jarak & biaya transport dihitung langsung
 * dengan aturan yang sama seperti server (DistanceService & TransportFeeCalculator).
 */
import { reverseGeocode, searchAddress } from '../lib/nominatim';

const EARTH_RADIUS_KM = 6371;

export function haversineKm(fromLat, fromLng, toLat, toLng) {
    const rad = (deg) => (deg * Math.PI) / 180;
    const dLat = rad(toLat - fromLat);
    const dLng = rad(toLng - fromLng);
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(rad(fromLat)) * Math.cos(rad(toLat)) * Math.sin(dLng / 2) ** 2;

    return Math.round(2 * EARTH_RADIUS_KM * Math.asin(Math.min(1, Math.sqrt(a))) * 100) / 100;
}

export function transportFee(distanceKm, freeRadiusKm, feePerKm) {
    const excess = Math.round(distanceKm * 100) - Math.round(freeRadiusKm * 100);

    return excess <= 0 ? 0 : Math.floor((excess + 99) / 100) * feePerKm;
}

const rupiah = (amount) => 'Rp' + new Intl.NumberFormat('id-ID').format(amount);
const km = (value) => new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(value) + ' km';
const radius = (value) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(value) + ' km';

export default function mapPicker(config) {
    let map = null;
    let searchTimer = null;
    let reverseTimer = null;

    return {
        lat: config.lat ?? config.office.lat,
        lng: config.lng ?? config.office.lng,
        address: config.address ?? '',
        addressTouched: Boolean(config.address),
        query: '',
        results: [],
        searching: false,
        locating: false,
        mapReady: false,
        message: '',

        get distance() {
            return haversineKm(config.office.lat, config.office.lng, this.lat, this.lng);
        },
        get inRange() {
            return this.distance <= config.maxRadiusKm;
        },
        get fee() {
            return transportFee(this.distance, config.freeRadiusKm, config.feePerKm);
        },
        get rangeTitle() {
            return this.inRange ? `Dalam jangkauan · ${km(this.distance)}` : `Di luar jangkauan · ${km(this.distance)}`;
        },
        get rangeDetail() {
            if (!this.inRange) {
                return `Maksimal ${radius(config.maxRadiusKm)} dari kantor. Pilih titik lain.`;
            }

            return this.fee === 0
                ? `Transport gratis (${radius(config.freeRadiusKm)} pertama gratis)`
                : `Transport ${rupiah(this.fee)} (${radius(config.freeRadiusKm)} pertama gratis)`;
        },
        get coordinates() {
            return `${Number(this.lat).toFixed(4)}, ${Number(this.lng).toFixed(4)}`;
        },

        async init() {
            const { default: L } = await import('leaflet');
            await import('leaflet/dist/leaflet.css');

            map = L.map(this.$refs.map, { zoomControl: false, attributionControl: true }).setView([this.lat, this.lng], 16);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(map);
            L.control.zoom({ position: 'bottomleft' }).addTo(map);

            map.on('move', () => {
                const center = map.getCenter();
                this.lat = Math.round(center.lat * 1e7) / 1e7;
                this.lng = Math.round(center.lng * 1e7) / 1e7;
            });
            map.on('moveend', () => this.scheduleReverse());

            this.mapReady = true;
            this.$nextTick(() => map.invalidateSize());
        },

        touchAddress() {
            this.addressTouched = true;
        },

        onSearchInput() {
            clearTimeout(searchTimer);
            if (this.query.trim().length < 3) {
                this.results = [];
                return;
            }
            searchTimer = setTimeout(() => this.search(), 600);
        },

        async search() {
            this.searching = true;
            try {
                this.results = (await searchAddress(this.query)) ?? [];
                this.message = this.results.length ? '' : 'Alamat tidak ditemukan. Coba kata kunci lain.';
            } catch {
                this.message = 'Pencarian sedang tidak tersedia. Geser peta secara manual.';
            } finally {
                this.searching = false;
            }
        },

        choose(result) {
            this.results = [];
            this.query = '';
            if (!this.addressTouched) {
                this.address = result.display_name;
            }
            map?.setView([Number(result.lat), Number(result.lon)], 17);
        },

        locate() {
            if (!navigator.geolocation) {
                this.message = 'Perangkat tidak mendukung lokasi otomatis.';
                return;
            }
            this.locating = true;
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.locating = false;
                    this.message = '';
                    map?.setView([position.coords.latitude, position.coords.longitude], 17);
                },
                () => {
                    this.locating = false;
                    this.message = 'Izin lokasi ditolak. Geser peta untuk memilih titik.';
                },
                { enableHighAccuracy: true, timeout: 10000 },
            );
        },

        // Isi otomatis detail alamat dari titik peta selama client belum mengetik sendiri.
        scheduleReverse() {
            if (this.addressTouched) {
                return;
            }
            clearTimeout(reverseTimer);
            reverseTimer = setTimeout(async () => {
                try {
                    const result = await reverseGeocode(this.lat, this.lng);
                    if (result?.display_name && !this.addressTouched) {
                        this.address = result.display_name;
                    }
                } catch {
                    // Abaikan: client tetap bisa mengetik alamat sendiri.
                }
            }, 900);
        },
    };
}
