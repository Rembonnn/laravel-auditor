import { storage } from './storage.js'

const UNITS = [
    ['year', 31536000],
    ['month', 2592000],
    ['week', 604800],
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
    ['second', 1],
]

/**
 * <time data-relative datetime="ISO"> shows "2 minutes ago" (Intl, in the
 * page locale) and keeps the absolute time + zone in its tooltip.
 * Zone: browser | UTC | the configured dashboard zone (toggle in the footer).
 */
export function installRelativeTime() {
    const locale = document.documentElement.lang || undefined
    const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' })
    const configured = document.documentElement.dataset.timezone || null

    const zone = () => {
        const mode = storage.get('timezone', configured ? 'configured' : 'browser')
        if (mode === 'utc') return 'UTC'
        if (mode === 'configured' && configured) return configured
        return undefined
    }

    const absolute = (date) =>
        new Intl.DateTimeFormat(locale, {
            // dateStyle/timeStyle cannot be combined with timeZoneName.
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            timeZone: zone(),
            timeZoneName: 'short',
        }).format(date)

    const relative = (date) => {
        const seconds = Math.round((date.getTime() - Date.now()) / 1000)
        for (const [unit, size] of UNITS) {
            if (Math.abs(seconds) >= size || unit === 'second') {
                return rtf.format(Math.round(seconds / size), unit)
            }
        }
    }

    // Only write when the value changes, so the MutationObserver below
    // never feeds itself.
    const write = (el, prop, value) => {
        if (el[prop] !== value) {
            el[prop] = value
        }
    }

    const update = (root = document) => {
        root.querySelectorAll('time[data-relative]').forEach((el) => {
            const date = new Date(el.getAttribute('datetime'))
            if (!Number.isNaN(date.getTime())) {
                write(el, 'textContent', relative(date))
                write(el, 'title', absolute(date))
            }
        })
        root.querySelectorAll('time[data-absolute]').forEach((el) => {
            const date = new Date(el.getAttribute('datetime'))
            if (!Number.isNaN(date.getTime())) {
                write(el, 'textContent', absolute(date))
            }
        })
        document.querySelectorAll('[data-timezone-label]').forEach((el) => {
            write(el, 'textContent', zone() || Intl.DateTimeFormat().resolvedOptions().timeZone)
        })
    }

    window.addEventListener('auditor:cycle-timezone', () => {
        const modes = configured ? ['browser', 'utc', 'configured'] : ['browser', 'utc']
        const current = storage.get('timezone', configured ? 'configured' : 'browser')
        storage.set('timezone', modes[(modes.indexOf(current) + 1) % modes.length])
        update()
    })

    // Content inserted later (drawer, palette) gets formatted too.
    new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType === 1 && node.tagName !== 'TIME' && node.querySelector('time[data-relative], time[data-absolute]')) {
                    update(node)
                }
            }
        }
    }).observe(document.body || document.documentElement, { childList: true, subtree: true })

    setInterval(() => update(), 60000)
    update()
}
