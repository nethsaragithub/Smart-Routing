/**
 * Schedule form behaviour:
 *  - ranks buses and drivers for the chosen route and time (AssignmentAdvisor)
 *  - suggests the arrival time from the route's running time
 *  - runs the conflict detector live, so clashes show before saving
 */
export default (config) => ({
    routeId: config.routeId ? String(config.routeId) : '',
    busId: config.busId ? String(config.busId) : '',
    driverId: config.driverId ? String(config.driverId) : '',
    departure: config.departure ?? '',
    arrival: config.arrival ?? '',
    recurrence: config.recurrence ?? 'daily',
    weekdays: (config.weekdays ?? []).map(String),
    monthDays: (config.monthDays ?? []).map(String),
    startDate: config.startDate ?? '',
    endDate: config.endDate ?? '',
    scheduleId: config.scheduleId ?? null,

    buses: config.buses ?? [],
    drivers: config.drivers ?? [],
    routeInfo: null,
    arrivalTouched: Boolean(config.arrival),

    report: config.initialReport ?? null,
    checking: false,
    checkTimer: null,

    init() {
        if (this.routeId) {
            this.loadOptions();
        }

        ['busId', 'driverId', 'arrival', 'recurrence', 'weekdays', 'monthDays', 'startDate', 'endDate'].forEach((field) =>
            this.$watch(field, () => this.queueCheck()),
        );
        this.$watch('routeId', () => {
            this.loadOptions();
            this.queueCheck();
        });
        this.$watch('departure', () => {
            this.suggestArrival();
            this.loadOptions();
            this.queueCheck();
        });

        if (!this.report) {
            this.queueCheck();
        }
    },

    async loadOptions() {
        if (!this.routeId) return;

        const { data } = await window.axios.get(config.optionsUrl, {
            params: { route: this.routeId, departure: this.departure || null, arrival: this.arrival || null, schedule_id: this.scheduleId },
        });

        this.routeInfo = data.route;
        this.buses = data.buses;
        this.drivers = data.drivers;
        this.suggestArrival();
    },

    suggestArrival() {
        if (this.arrivalTouched || !this.departure || !this.routeInfo) return;

        const [h, m] = this.departure.split(':').map(Number);
        const total = h * 60 + m + this.routeInfo.duration;

        if (total < 24 * 60) {
            this.arrival = `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
        }
    },

    queueCheck() {
        clearTimeout(this.checkTimer);
        this.checkTimer = setTimeout(() => this.check(), 450);
    },

    async check() {
        if (!this.routeId || !this.busId || !this.driverId || !this.departure || !this.arrival || !this.startDate) {
            this.report = null;
            return;
        }

        this.checking = true;

        try {
            const { data } = await window.axios.post(config.checkUrl, {
                bus_route_id: this.routeId,
                bus_id: this.busId,
                driver_id: this.driverId,
                departure_time: this.departure,
                arrival_time: this.arrival,
                recurrence: this.recurrence,
                weekdays: this.weekdays,
                month_days: this.monthDays,
                start_date: this.startDate,
                end_date: this.endDate || null,
                schedule_id: this.scheduleId,
            });

            this.report = data.incomplete ? null : data;
        } catch {
            this.report = null;
        } finally {
            this.checking = false;
        }
    },

    noteFor(list, id) {
        const item = list.find((o) => String(o.id) === String(id));
        return item && item.notes.length ? item.notes.join(' · ') : '';
    },

    get hasErrors() {
        return this.report && this.report.errors.length > 0;
    },

    get hasWarnings() {
        return this.report && this.report.warnings.length > 0;
    },
});
