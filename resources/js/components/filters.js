import { storage } from './storage.js'

/**
 * Saved views: named filter combinations kept in this browser.
 */
export default function filters() {
    return {
        views: [],
        name: '',
        saving: false,

        init() {
            this.views = storage.getJson('views.' + this.$root.dataset.scope, [])
        },

        startSaving() {
            this.saving = true
            this.$nextTick(() => this.$refs.name && this.$refs.name.focus())
        },

        save() {
            const name = this.name.trim()
            if (!name) {
                return
            }
            this.views = [...this.views.filter((v) => v.name !== name), { name, query: window.location.search }]
            storage.setJson('views.' + this.$root.dataset.scope, this.views)
            this.name = ''
            this.saving = false
            this.$store.toasts.push(this.$root.dataset.savedLabel)
        },

        remove(name) {
            this.views = this.views.filter((v) => v.name !== name)
            storage.setJson('views.' + this.$root.dataset.scope, this.views)
        },

        href(view) {
            return window.location.pathname + view.query
        },

        get hasViews() {
            return this.views.length > 0
        },
    }
}
