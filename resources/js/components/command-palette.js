/**
 * ⌘K / Ctrl+K palette. Pages and actions are listed locally; typed queries
 * are resolved by the server (ULID, correlation id, Post#12, user:5, route:x).
 * Results are rendered with x-text only, never as HTML.
 */
export default function commandPalette() {
    return {
        open: false,
        query: '',
        results: [],
        active: 0,
        loading: false,
        timer: null,
        controller: null,

        init() {
            window.addEventListener('auditor:palette', () => this.show())
            window.addEventListener('auditor:close', () => this.hide())
            this.results = this.staticItems('')
        },

        show() {
            this.open = true
            this.query = ''
            this.active = 0
            this.results = this.staticItems('')
            this.$nextTick(() => this.$refs.input && this.$refs.input.focus())
        },

        hide() {
            this.open = false
        },

        staticItems(query) {
            const q = query.toLowerCase()
            const pages = JSON.parse(this.$root.dataset.pages || '[]')
            const actions = [
                { group: this.$root.dataset.actionsLabel, label: this.$root.dataset.themeLabel, action: 'theme' },
                { group: this.$root.dataset.actionsLabel, label: this.$root.dataset.liveLabel, action: 'live' },
                { group: this.$root.dataset.actionsLabel, label: this.$root.dataset.copyLabel, action: 'copy-url' },
            ]

            return [...pages, ...actions].filter((item) => q === '' || item.label.toLowerCase().includes(q))
        },

        onInput() {
            this.active = 0
            this.results = this.staticItems(this.query)
            clearTimeout(this.timer)

            if (this.query.trim().length < 2) {
                return
            }

            this.timer = setTimeout(() => this.search(), 150)
        },

        async search() {
            if (this.controller) {
                this.controller.abort()
            }

            this.controller = new AbortController()
            this.loading = true

            try {
                const url = this.$root.dataset.searchUrl + '?q=' + encodeURIComponent(this.query.trim())
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    signal: this.controller.signal,
                })
                const remote = response.ok ? await response.json() : []
                this.results = [...remote, ...this.staticItems(this.query)]
                this.active = 0
            } catch (e) {
                // aborted or offline: keep the local results
            } finally {
                this.loading = false
            }
        },

        onKey(event) {
            if (event.key === 'ArrowDown') {
                event.preventDefault()
                this.active = Math.min(this.active + 1, this.results.length - 1)
            } else if (event.key === 'ArrowUp') {
                event.preventDefault()
                this.active = Math.max(this.active - 1, 0)
            } else if (event.key === 'Enter') {
                event.preventDefault()
                this.choose(this.active)
            } else if (event.key === 'Escape') {
                this.hide()
            }
        },

        isActive(index) {
            return index === this.active
        },

        hover(index) {
            this.active = index
        },

        choose(index) {
            const item = this.results[index]

            if (!item) {
                return
            }

            this.hide()

            if (item.action === 'theme') {
                this.$store.theme.cycle()
            } else if (item.action === 'live') {
                window.dispatchEvent(new CustomEvent('auditor:toggle-live'))
            } else if (item.action === 'copy-url') {
                window.dispatchEvent(new CustomEvent('auditor:copy', { detail: window.location.href }))
            } else if (item.url) {
                window.location.href = item.url
            }
        },

        get empty() {
            return this.results.length === 0 && !this.loading
        },
    }
}
