import { storage } from './storage.js'

/**
 * Layout shell: sidebar (collapsible / mobile drawer), density, time zone,
 * help modal. Lives on <body>.
 */
export default function shell() {
    return {
        mobileNavOpen: false,
        helpOpen: false,
        sidebarCollapsed: document.documentElement.dataset.sidebar === 'collapsed',
        density: document.documentElement.dataset.density || 'comfortable',

        init() {
            window.addEventListener('auditor:help', () => (this.helpOpen = true))
            window.addEventListener('auditor:close', () => {
                this.helpOpen = false
                this.mobileNavOpen = false
            })
        },

        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed
            document.documentElement.dataset.sidebar = this.sidebarCollapsed ? 'collapsed' : 'expanded'
            storage.set('sidebar', this.sidebarCollapsed ? 'collapsed' : 'expanded')
        },

        openMobileNav() {
            this.mobileNavOpen = true
        },

        closeMobileNav() {
            this.mobileNavOpen = false
        },

        toggleDensity() {
            this.density = this.density === 'compact' ? 'comfortable' : 'compact'
            document.documentElement.dataset.density = this.density
            storage.set('density', this.density)
        },

        get isCompact() {
            return this.density === 'compact'
        },

        openHelp() {
            this.helpOpen = true
        },

        closeHelp() {
            this.helpOpen = false
        },
    }
}
