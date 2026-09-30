/**
 * Live mode for a list: polls for new rows and shows a "N new — show" banner
 * instead of shifting the table. Pauses while the tab is hidden or the user
 * has scrolled down. The on/off switch is the shared $store.live.
 */
export default function live() {
    return {
        count: 0,
        timer: null,
        interval: 0,

        init() {
            this.interval = Number(this.$root.dataset.interval || 0) * 1000

            if (!this.interval || !this.$root.dataset.pollUrl) {
                return
            }

            this.$watch('$store.live.enabled', () => this.schedule())
            document.addEventListener('visibilitychange', () => this.schedule())
            this.schedule()
        },

        schedule() {
            clearTimeout(this.timer)

            if (this.$store.live.enabled && document.visibilityState === 'visible') {
                this.timer = setTimeout(() => this.poll(), this.interval)
            }
        },

        async poll() {
            if (window.scrollY > 200) {
                return this.schedule()
            }

            try {
                const response = await fetch(this.$root.dataset.pollUrl, { headers: { Accept: 'application/json' } })
                if (response.ok) {
                    const data = await response.json()
                    this.count = Number(data.count) || 0
                }
            } catch (e) {
                // offline: try again later
            }

            this.schedule()
        },

        reload() {
            window.location.reload()
        },

        get hasNew() {
            return this.count > 0
        },

        get label() {
            return (this.$root.dataset.newLabel || ':count').replace(':count', this.count)
        },
    }
}
