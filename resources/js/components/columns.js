import { storage } from './storage.js'

/**
 * Hideable table columns, remembered per table in this browser.
 */
export default function columns() {
    return {
        hidden: [],

        init() {
            this.hidden = storage.getJson('columns.' + this.$root.dataset.table, [])
            this.apply()
        },

        isVisible(column) {
            return !this.hidden.includes(column)
        },

        toggle(column) {
            this.hidden = this.isVisible(column) ? [...this.hidden, column] : this.hidden.filter((c) => c !== column)
            storage.setJson('columns.' + this.$root.dataset.table, this.hidden)
            this.apply()
        },

        apply() {
            document.querySelectorAll('[data-column]').forEach((cell) => {
                cell.classList.toggle('col-hidden', this.hidden.includes(cell.dataset.column))
            })
        },
    }
}
