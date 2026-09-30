/**
 * Any element with data-copy="text" copies that text; a toast confirms.
 */
export function installCopy(Alpine) {
    const copy = async (text) => {
        try {
            await navigator.clipboard.writeText(text)
            Alpine.store('toasts').push(document.documentElement.dataset.copiedLabel || 'Copied', 'success', 1800)
        } catch (e) {
            Alpine.store('toasts').push(document.documentElement.dataset.copyFailedLabel || 'Copy failed', 'danger')
        }
    }

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-copy]')
        if (button) {
            event.preventDefault()
            event.stopPropagation()
            copy(button.dataset.copy)
        }
    })

    window.addEventListener('auditor:copy', (event) => copy(event.detail))
}
