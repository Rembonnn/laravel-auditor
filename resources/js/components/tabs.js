/**
 * Tabs whose selection lives in the URL hash (#changes), so links can point
 * straight at a tab.
 */
export default function tabs() {
    return {
        current: null,

        init() {
            const ids = Array.from(this.$root.querySelectorAll('[role="tab"]')).map((tab) => tab.dataset.tab)
            const fromHash = window.location.hash.replace('#', '')
            this.current = ids.includes(fromHash) ? fromHash : ids[0]
            window.addEventListener('hashchange', () => {
                const hash = window.location.hash.replace('#', '')
                if (ids.includes(hash)) {
                    this.current = hash
                }
            })
        },

        select(id) {
            this.current = id
            history.replaceState(null, '', '#' + id)
        },

        isCurrent(id) {
            return this.current === id
        },

        onKey(event) {
            const tabs = Array.from(this.$root.querySelectorAll('[role="tab"]'))
            const index = tabs.findIndex((tab) => tab.dataset.tab === this.current)
            let next = null
            if (event.key === 'ArrowRight') next = tabs[(index + 1) % tabs.length]
            if (event.key === 'ArrowLeft') next = tabs[(index - 1 + tabs.length) % tabs.length]
            if (next) {
                event.preventDefault()
                this.select(next.dataset.tab)
                next.focus()
            }
        },
    }
}
