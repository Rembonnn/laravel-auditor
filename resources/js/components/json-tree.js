/**
 * Collapsible JSON tree rendered server-side as nested <details>. Adds
 * expand/collapse all, a key/value filter and copy.
 */
export default function jsonTree() {
    return {
        term: '',

        expandAll() {
            this.$root.querySelectorAll('details').forEach((d) => (d.open = true))
        },

        collapseAll() {
            this.$root.querySelectorAll('details').forEach((d) => (d.open = false))
        },

        filter() {
            const term = this.term.trim().toLowerCase()
            this.$root.querySelectorAll('[data-json-row]').forEach((row) => {
                const match = term === '' || row.textContent.toLowerCase().includes(term)
                row.style.display = match ? '' : 'none'
                if (match && term !== '') {
                    let parent = row.parentElement.closest('details')
                    while (parent) {
                        parent.open = true
                        parent = parent.parentElement.closest('details')
                    }
                }
            })
        },

        copy() {
            window.dispatchEvent(new CustomEvent('auditor:copy', { detail: this.$root.dataset.json }))
        },
    }
}
