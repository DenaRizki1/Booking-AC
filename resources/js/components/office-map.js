/**
 * Admin: pilih titik kantor (marker bisa digeser atau klik peta) + lingkaran radius gratis & maksimal.
 */
import { searchAddress } from '../lib/nominatim';

export default function officeMap(config) {
    let map = null;
    let marker = null;
    let freeCircle = null;
    let maxCircle = null;
    let searchTimer = null;

    return {
        lat: config.lat,
        lng: config.lng,
        freeRadius: config.freeRadiusKm,
        maxRadius: config.maxRadiusKm,
        query: '',
        results: [],
        searching: false,
        message: '',

        get coordinates() {
            return `${Number(this.lat).toFixed(5)}, ${Number(this.lng).toFixed(5)}`;
        },

        async init() {
            const { default: L } = await import('leaflet');
            await import('leaflet/dist/leaflet.css');

            map = L.map(this.$refs.map, { zoomControl: false }).setView([this.lat, this.lng], 12);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(map);
            L.control.zoom({ position: 'bottomleft' }).addTo(map);

            const icon = L.divIcon({
                className: '',
                iconSize: [44, 54],
                iconAnchor: [22, 50],
                html: '<svg width="44" height="54" viewBox="0 0 44 54" aria-hidden="true"><ellipse cx="22" cy="51" rx="7" ry="3" fill="#00084D" fill-opacity=".25"/><path d="M22 2C11.5 2 3 10.3 3 20.6 3 34 22 50 22 50s19-16 19-29.4C41 10.3 32.5 2 22 2Z" fill="#00084D" stroke="#fff" stroke-width="3"/><circle cx="22" cy="20" r="7" fill="#fff"/></svg>',
            });

            maxCircle = L.circle([this.lat, this.lng], { radius: 0, color: '#1D63ED', weight: 1.5, fillOpacity: 0.05, dashArray: '6 6', interactive: false }).addTo(map);
            freeCircle = L.circle([this.lat, this.lng], { radius: 0, color: '#0F7B4F', weight: 1.5, fillOpacity: 0.08, interactive: false }).addTo(map);
            marker = L.marker([this.lat, this.lng], { draggable: true, icon, keyboard: true, title: 'Titik kantor' }).addTo(map);

            marker.on('drag', () => this.setPoint(marker.getLatLng(), false));
            map.on('click', (event) => this.setPoint(event.latlng));

            this.$watch('freeRadius', () => this.drawCircles());
            this.$watch('maxRadius', () => this.drawCircles());
            this.drawCircles();
            this.$nextTick(() => map.invalidateSize());
        },

        setPoint(latlng, moveMarker = true) {
            this.lat = Math.round(latlng.lat * 1e7) / 1e7;
            this.lng = Math.round(latlng.lng * 1e7) / 1e7;
            if (moveMarker) {
                marker?.setLatLng([this.lat, this.lng]);
            }
            this.drawCircles();
        },

        drawCircles() {
            const toMeters = (km) => Math.max(0, toFinite(km)) * 1000;
            freeCircle?.setLatLng([this.lat, this.lng]).setRadius(toMeters(this.freeRadius));
            maxCircle?.setLatLng([this.lat, this.lng]).setRadius(toMeters(this.maxRadius));
        },

        fitRadius() {
            if (maxCircle && toFinite(this.maxRadius) > 0) {
                map.fitBounds(maxCircle.getBounds(), { padding: [16, 16] });
            }
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
                this.message = 'Pencarian sedang tidak tersedia. Geser marker secara manual.';
            } finally {
                this.searching = false;
            }
        },

        choose(result) {
            this.results = [];
            this.query = '';
            this.setPoint({ lat: Number(result.lat), lng: Number(result.lon) });
            map?.setView([this.lat, this.lng], 16);
        },
    };
}

function toFinite(value) {
    const number = Number(String(value).replace(',', '.'));

    return Number.isFinite(number) ? number : 0;
}
