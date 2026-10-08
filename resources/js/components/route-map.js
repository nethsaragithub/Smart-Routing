import { createMap, drawRoute, stopIcon, L } from '../lib/map';

/**
 * Read-only route map used on the route profile page.
 */
export default (stops = []) => ({
    roadInfo: null,

    async init() {
        const map = createMap(this.$refs.map);
        const markers = L.layerGroup().addTo(map);
        const line = L.layerGroup().addTo(map);

        stops.forEach((stop, i) => {
            L.marker([stop.lat, stop.lng], { icon: stopIcon(i, stops.length), title: stop.name })
                .bindTooltip(`${i + 1}. ${stop.name}`)
                .addTo(markers);
        });

        if (stops.length) {
            map.fitBounds(L.latLngBounds(stops.map((s) => [s.lat, s.lng])), { padding: [30, 30] });
        }

        this.roadInfo = await drawRoute(line, stops);
    },
});
