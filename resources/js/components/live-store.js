import { storage } from './storage.js'

/**
 * Shared live-mode switch (topbar button, "l" shortcut, palette action).
 */
export default function liveStore(Alpine) {
    return {
        enabled: storage.get('live', 'off') === 'on',

        init() {
            window.addEventListener('auditor:toggle-live', () => this.toggle())
        },

        toggle() {
            this.enabled = !this.enabled
            storage.set('live', this.enabled ? 'on' : 'off')
            const labels = document.documentElement.dataset
            Alpine.store('toasts').push(this.enabled ? labels.liveOnLabel : labels.liveOffLabel)
        },
    }
}
