/**
 * Quick-peek drawer. Content is a server-rendered (escaped) partial.
 */
export default function drawer() {
    return {
        open: false,
        loading: false,
        failed: false,
        url: null,
        returnFocus: null,

        init() {
            window.addEventListener('auditor:peek', (event) => this.show(event.detail))
            window.addEventListener('auditor:close', () => this.hide())
            document.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-peek-url]')
                if (!trigger) {
                    return
                }
                // Links and buttons inside a row keep their own behaviour,
                // unless they are the peek trigger themselves.
                const interactive = event.target.closest('a, button, input, select, label, summary, [data-copy]')
                if (interactive && interactive !== trigger) {
                    return
                }
                event.preventDefault()
                trigger.closest('[data-rows]')?.querySelectorAll('[data-row]').forEach((row) => {
                    row.setAttribute('aria-selected', row === trigger ? 'true' : 'false')
                })
                this.show(trigger.dataset.peekUrl)
            })
        },

        async show(url) {
            if (!url) {
                return
            }

            if (!this.open) {
                this.returnFocus = document.activeElement
            }

            this.url = url
            this.open = true
            this.loading = true
            this.failed = false
            document.documentElement.dataset.drawerOpen = 'true'

            try {
                const response = await fetch(url, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } })
                if (!response.ok) {
                    throw new Error(response.statusText)
                }
                const html = await response.text()
                if (this.url === url) {
                    this.$refs.body.innerHTML = html
                    window.Alpine.initTree(this.$refs.body)
                }
            } catch (e) {
                this.failed = true
            } finally {
                this.loading = false
            }
        },

        retry() {
            this.show(this.url)
        },

        hide() {
            if (!this.open) {
                return
            }
            this.open = false
            document.documentElement.dataset.drawerOpen = 'false'
            if (this.returnFocus && this.returnFocus.focus) {
                this.returnFocus.focus()
            }
        },
    }
}
