import L from 'leaflet';

/** Centre of Sri Lanka, used when a route has no stops yet. */
export const SRI_LANKA = [7.8731, 80.7718];

const OSRM_URL = 'https://router.project-osrm.org/route/v1/driving/';

/**
 * Create a Leaflet map with OpenStreetMap tiles.
 */
export function createMap(element, { center = SRI_LANKA, zoom = 8 } = {}) {
    const map = L.map(element, { scrollWheelZoom: false }).setView(center, zoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    // Enable wheel zoom only after the user clicks the map, so the page scrolls normally.
    map.once('focus', () => map.scrollWheelZoom.enable());

    return map;
}

/**
 * Numbered stop marker; first and last stops are drawn as termini.
 */
export function stopIcon(index, total) {
    const terminus = index === 0 || index === total - 1;

    return L.divIcon({
        className: '',
        html: `<div class="stop-marker ${terminus ? 'is-terminus' : ''}">${index + 1}</div>`,
        iconSize: [26, 26],
        iconAnchor: [13, 13],
    });
}

/**
 * Ask the public OSRM router for the road path through the stops.
 * Resolves to { coordinates, distanceKm, durationMin } or null if offline.
 */
export async function fetchRoadPath(stops) {
    if (stops.length < 2) {
        return null;
    }

    const coords = stops.map((s) => `${Number(s.lng).toFixed(6)},${Number(s.lat).toFixed(6)}`).join(';');

    try {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 8000);
        const response = await fetch(`${OSRM_URL}${coords}?overview=full&geometries=geojson`, { signal: controller.signal });
        clearTimeout(timer);

        if (!response.ok) {
            return null;
        }

        const data = await response.json();
        const route = data.routes?.[0];

        if (!route) {
            return null;
        }

        return {
            coordinates: route.geometry.coordinates.map(([lng, lat]) => [lat, lng]),
            distanceKm: Math.round(route.distance / 100) / 10,
            durationMin: Math.round(route.duration / 60),
        };
    } catch {
        return null;
    }
}

/**
 * Draw the route line into a layer group: the road path when available,
 * otherwise straight dashed segments between stops.
 */
export async function drawRoute(layer, stops) {
    layer.clearLayers();

    if (stops.length < 2) {
        return null;
    }

    const straight = stops.map((s) => [s.lat, s.lng]);
    const fallback = L.polyline(straight, { color: '#22272b', weight: 3, opacity: 0.6, dashArray: '6 6' }).addTo(layer);

    const road = await fetchRoadPath(stops);

    if (road) {
        layer.removeLayer(fallback);
        L.polyline(road.coordinates, { color: '#ffffff', weight: 8, opacity: 0.9 }).addTo(layer);
        L.polyline(road.coordinates, { color: '#b3261e', weight: 4, opacity: 0.95 }).addTo(layer);
    }

    return road;
}

/** Look up places in Sri Lanka by name (OpenStreetMap Nominatim). */
export async function searchPlaces(query) {
    const url = `https://nominatim.openstreetmap.org/search?format=json&limit=6&countrycodes=lk&q=${encodeURIComponent(query)}`;
    const response = await fetch(url, { headers: { Accept: 'application/json' } });

    if (!response.ok) {
        return [];
    }

    return (await response.json()).map((place) => ({
        name: place.display_name.split(',')[0],
        detail: place.display_name.split(',').slice(1, 3).join(',').trim(),
        lat: Number(place.lat),
        lng: Number(place.lon),
    }));
}

/** Best-effort place name for a clicked point. */
export async function reverseGeocode(lat, lng) {
    try {
        const url = `https://nominatim.openstreetmap.org/reverse?format=json&zoom=16&lat=${lat}&lon=${lng}`;
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        const data = await response.json();
        const a = data.address ?? {};

        return a.suburb || a.village || a.town || a.city || a.neighbourhood || a.road || null;
    } catch {
        return null;
    }
}

export { L };
