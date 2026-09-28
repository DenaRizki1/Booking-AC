/**
 * Pencarian alamat OpenStreetMap (Nominatim). Kebijakan: maks. 1 request/detik.
 */
const NOMINATIM = 'https://nominatim.openstreetmap.org';
const MIN_REQUEST_GAP_MS = 1100;

let lastRequestAt = 0;

export async function nominatim(path) {
    const wait = lastRequestAt + MIN_REQUEST_GAP_MS - Date.now();
    if (wait > 0) {
        await new Promise((resolve) => setTimeout(resolve, wait));
    }
    lastRequestAt = Date.now();

    const response = await fetch(NOMINATIM + path, { headers: { Accept: 'application/json' } });
    if (!response.ok) {
        throw new Error(`Nominatim ${response.status}`);
    }

    return response.json();
}

export function searchAddress(query) {
    const params = new URLSearchParams({ format: 'jsonv2', q: query, countrycodes: 'id', limit: '5', 'accept-language': 'id' });

    return nominatim(`/search?${params}`);
}

export function reverseGeocode(lat, lng) {
    const params = new URLSearchParams({ format: 'jsonv2', lat, lon: lng, zoom: '18', 'accept-language': 'id' });

    return nominatim(`/reverse?${params}`);
}
