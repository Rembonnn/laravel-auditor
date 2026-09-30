import { storage } from './storage.js'

/**
 * Inline vs side-by-side diff (side-by-side by default on wide screens).
 */
export default function diff() {
    return {
        mode: storage.get('diff', window.matchMedia('(min-width: 1024px)').matches ? 'split' : 'inline'),

        setMode(mode) {
            this.mode = mode
            storage.set('diff', mode)
        },

        get isSplit() {
            return this.mode === 'split'
        },

        get isInline() {
            return this.mode !== 'split'
        },
    }
}
