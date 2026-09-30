const ORDER = ['light', 'dark', 'system']

/**
 * Light / Dark / System. The initial class is set by theme-init.js before
 * first paint; this store keeps it in sync afterwards (including live OS
 * changes while in System mode).
 */
export default function theme(storage) {
    return {
        mode: document.documentElement.dataset.theme || 'system',
        media: null,

        init() {
            this.media = window.matchMedia('(prefers-color-scheme: dark)')
            this.media.addEventListener('change', () => this.apply())
            this.apply()
        },

        set(mode) {
            this.mode = ORDER.includes(mode) ? mode : 'system'
            storage.set('theme', this.mode)
            this.apply()
        },

        cycle() {
            this.set(ORDER[(ORDER.indexOf(this.mode) + 1) % ORDER.length])
        },

        apply() {
            const dark = this.mode === 'dark' || (this.mode === 'system' && this.media.matches)
            const root = document.documentElement
            root.classList.toggle('dark', dark)
            root.dataset.theme = this.mode
        },

        get isLight() {
            return this.mode === 'light'
        },

        get isDark() {
            return this.mode === 'dark'
        },

        get isSystem() {
            return this.mode === 'system'
        },

        get label() {
            const key = 'themeLabel' + this.mode.charAt(0).toUpperCase() + this.mode.slice(1)
            return document.documentElement.dataset[key] || this.mode
        },
    }
}
