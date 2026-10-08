/**
 * Refreshes the dashboard trip board every minute while the tab is visible.
 */
export default (url, intervalSeconds = 60) => ({
    updatedAt: new Date(),
    timer: null,

    init() {
        this.timer = setInterval(() => {
            if (!document.hidden) this.refresh();
        }, intervalSeconds * 1000);
    },

    destroy() {
        clearInterval(this.timer);
    },

    async refresh() {
        try {
            const { data } = await window.axios.get(url);
            this.$refs.board.innerHTML = data;
            this.updatedAt = new Date();
        } catch {
            // Keep showing the last good board; try again next interval.
        }
    },

    get updatedLabel() {
        return this.updatedAt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    },
});
