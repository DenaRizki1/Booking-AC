/**
 * Peta kecil read-only dengan satu marker (detail order admin).
 */
export default function miniMap(config) {
    return {
        async init() {
            const { default: L } = await import('leaflet');
            await import('leaflet/dist/leaflet.css');

            const map = L.map(this.$refs.map, {
                zoomControl: false,
                dragging: false,
                scrollWheelZoom: false,
                doubleClickZoom: false,
                boxZoom: false,
                keyboard: false,
                touchZoom: false,
            }).setView([config.lat, config.lng], 15);

            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap',
            }).addTo(map);

            L.circleMarker([config.lat, config.lng], { radius: 9, color: '#FFFFFF', weight: 3, fillColor: '#1D63ED', fillOpacity: 1 }).addTo(map);
            this.$nextTick(() => map.invalidateSize());
        },
    };
}
