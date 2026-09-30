const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

/**
 * x-trap="expression": while truthy, Tab / Shift+Tab stay inside the element
 * and focus moves into it; on release focus returns to where it was.
 * (A small replacement for @alpinejs/focus, which costs ~9 KB gzip.)
 */
export default function focusTrap(Alpine) {
    Alpine.directive('trap', (el, { expression }, { effect, evaluateLater, cleanup }) => {
        const evaluate = evaluateLater(expression)
        let previous = null

        const onKey = (event) => {
            if (event.key !== 'Tab') {
                return
            }
            const items = Array.from(el.querySelectorAll(FOCUSABLE)).filter((item) => item.offsetParent !== null)
            if (items.length === 0) {
                event.preventDefault()
                return
            }
            const first = items[0]
            const last = items[items.length - 1]
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault()
                last.focus()
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault()
                first.focus()
            }
        }

        effect(() =>
            evaluate((active) => {
                if (active) {
                    previous = document.activeElement
                    el.addEventListener('keydown', onKey)
                    requestAnimationFrame(() => {
                        const target = el.querySelector('[autofocus]') || el.querySelector(FOCUSABLE) || el
                        target.focus()
                    })
                } else {
                    el.removeEventListener('keydown', onKey)
                    if (previous && previous.focus && el.contains(document.activeElement)) {
                        previous.focus()
                    }
                }
            }),
        )

        cleanup(() => el.removeEventListener('keydown', onKey))
    })
}
