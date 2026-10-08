import { createMap, drawRoute, reverseGeocode, searchPlaces, stopIcon, L } from '../lib/map';

/**
 * Interactive stop editor on the route form.
 *  - click the map (or search) to add a stop
 *  - drag markers to adjust, rename / reorder / remove in the list
 *  - "Use road distance" copies the OSRM distance and time into the form
 *
 * Leaflet objects live in closure variables, not in Alpine's reactive
 * state, because Alpine's proxies break Leaflet internals.
 */
export default (initialStops = []) => {
    let map;
    let markers;
    let line;
    let redrawTimer;

    return {
        stops: initialStops.map((s) => ({ name: s.name, lat: Number(s.lat), lng: Number(s.lng) })),
        query: '',
        results: [],
        searching: false,
        road: null,
        routing: false,

        init() {
            map = createMap(this.$refs.map);
            markers = L.layerGroup().addTo(map);
            line = L.layerGroup().addTo(map);

            map.on('click', (e) => this.addStop(e.latlng.lat, e.latlng.lng));

            this.render(true);
        },

        get json() {
            return JSON.stringify(this.stops);
        },

        async addStop(lat, lng, name = null) {
            const stop = { name: name ?? `Stop ${this.stops.length + 1}`, lat, lng };
            this.stops.push(stop);
            this.render();

            if (!name) {
                const found = await reverseGeocode(lat, lng);
                const index = this.stops.indexOf(stop);
                if (found && index !== -1 && this.stops[index].name.startsWith('Stop ')) {
                    this.stops[index].name = found;
                }
            }
        },

        remove(index) {
            this.stops.splice(index, 1);
            this.render();
        },

        move(index, direction) {
            const target = index + direction;
            if (target < 0 || target >= this.stops.length) return;
            [this.stops[index], this.stops[target]] = [this.stops[target], this.stops[index]];
            this.render();
        },

        reverse() {
            this.stops.reverse();
            this.render();
        },

        async search() {
            if (this.query.trim().length < 3) return;
            this.searching = true;
            try {
                this.results = await searchPlaces(this.query.trim());
            } finally {
                this.searching = false;
            }
        },

        pick(place) {
            this.addStop(place.lat, place.lng, place.name);
            map.setView([place.lat, place.lng], Math.max(map.getZoom(), 12));
            this.results = [];
            this.query = '';
        },

        focus(index) {
            const s = this.stops[index];
            map.setView([s.lat, s.lng], Math.max(map.getZoom(), 13));
        },

        useRoadFigures() {
            if (!this.road) return;
            this.$refs.distance.value = this.road.distanceKm;
            // Buses are slower than cars: add 35% plus a minute per stop.
            this.$refs.duration.value = Math.round(this.road.durationMin * 1.35 + this.stops.length);
        },

        render(fit = false) {
            markers.clearLayers();

            this.stops.forEach((stop, i) => {
                const marker = L.marker([stop.lat, stop.lng], {
                    icon: stopIcon(i, this.stops.length),
                    draggable: true,
                    title: stop.name,
                }).addTo(markers);

                marker.bindTooltip(() => `${i + 1}. ${this.stops[i]?.name ?? ''}`);
                marker.on('dragend', (e) => {
                    const { lat, lng } = e.target.getLatLng();
                    this.stops[i].lat = lat;
                    this.stops[i].lng = lng;
                    this.render();
                });
            });

            if (fit && this.stops.length) {
                map.fitBounds(L.latLngBounds(this.stops.map((s) => [s.lat, s.lng])), { padding: [30, 30] });
            }

            // Debounce routing requests while the user is still clicking.
            clearTimeout(redrawTimer);
            redrawTimer = setTimeout(async () => {
                this.routing = this.stops.length > 1;
                this.road = await drawRoute(line, JSON.parse(JSON.stringify(this.stops)));
                this.routing = false;
            }, 400);
        },
    };
};
