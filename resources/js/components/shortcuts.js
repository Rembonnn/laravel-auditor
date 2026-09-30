/**
 * Global keyboard shortcuts (see the "?" help modal).
 */
export default function shortcuts() {
    return {
        pending: null,
        pendingTimer: null,

        onKey(event) {
            const target = event.target
            const typing = target && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))

            if (typeof event.key !== 'string') {
                return
            }

            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault()
                return this.emit('palette')
            }

            if (event.key === 'Escape') {
                return this.emit('close')
            }

            if (typing || event.metaKey || event.ctrlKey || event.altKey) {
                return
            }

            if (this.pending === 'g') {
                this.pending = null
                const target = { o: 'overview', e: 'entries', c: 'changes', i: 'integrity' }[event.key]
                const url = target && document.querySelector('[data-nav="' + target + '"]')
                if (url) {
                    event.preventDefault()
                    window.location.href = url.getAttribute('href')
                }
                return
            }

            switch (event.key) {
                case 'g':
                    this.pending = 'g'
                    clearTimeout(this.pendingTimer)
                    this.pendingTimer = setTimeout(() => (this.pending = null), 1200)
                    break
                case '/': {
                    const filter = document.querySelector('[data-filter-input]')
                    if (filter) {
                        event.preventDefault()
                        filter.focus()
                    }
                    break
                }
                case 'j':
                    this.moveRow(1)
                    break
                case 'k':
                    this.moveRow(-1)
                    break
                case 'Enter':
                    this.openRow(false, event)
                    break
                case ' ':
                    this.openRow(true, event)
                    break
                case 't':
                    this.$store.theme.cycle()
                    break
                case 'l':
                    this.emit('toggle-live')
                    break
                case '?':
                    this.emit('help')
                    break
            }
        },

        emit(name, detail = null) {
            window.dispatchEvent(new CustomEvent('auditor:' + name, { detail }))
        },

        rows() {
            return Array.from(document.querySelectorAll('[data-row]'))
        },

        moveRow(step) {
            const rows = this.rows()
            if (rows.length === 0) {
                return
            }

            const current = rows.findIndex((row) => row.getAttribute('aria-selected') === 'true')
            const next = Math.min(Math.max(current + step, 0), rows.length - 1)

            rows.forEach((row, index) => row.setAttribute('aria-selected', index === next ? 'true' : 'false'))
            rows[next].scrollIntoView({ block: 'nearest' })

            // With the drawer open, j/k walks through the entries.
            if (document.documentElement.dataset.drawerOpen === 'true' && rows[next].dataset.peekUrl) {
                this.emit('peek', rows[next].dataset.peekUrl)
            }
        },

        openRow(peek, event) {
            const row = this.rows().find((r) => r.getAttribute('aria-selected') === 'true')
            if (!row) {
                return
            }

            event.preventDefault()

            if (peek && row.dataset.peekUrl) {
                this.emit('peek', row.dataset.peekUrl)
            } else if (row.dataset.href) {
                window.location.href = row.dataset.href
            }
        },
    }
}
