import Alpine from '@alpinejs/csp'
import focusTrap from './components/focus-trap.js'

import { storage } from './components/storage.js'
import theme from './components/theme.js'
import toasts from './components/toast.js'
import shell from './components/shell.js'
import commandPalette from './components/command-palette.js'
import shortcuts from './components/shortcuts.js'
import drawer from './components/drawer.js'
import live from './components/live.js'
import liveStore from './components/live-store.js'
import filters from './components/filters.js'
import columns from './components/columns.js'
import jsonTree from './components/json-tree.js'
import diff from './components/diff.js'
import tabs from './components/tabs.js'
import { installCopy } from './components/copy.js'
import { installRelativeTime } from './components/relative-time.js'

Alpine.plugin(focusTrap)

Alpine.store('theme', theme(storage))
Alpine.store('toasts', toasts())
Alpine.store('live', liveStore(Alpine))

Alpine.data('shell', shell)
Alpine.data('commandPalette', commandPalette)
Alpine.data('shortcuts', shortcuts)
Alpine.data('drawer', drawer)
Alpine.data('live', live)
Alpine.data('filters', filters)
Alpine.data('columns', columns)
Alpine.data('jsonTree', jsonTree)
Alpine.data('diff', diff)
Alpine.data('tabs', tabs)

// Enhancements must never stop Alpine from starting.
for (const install of [() => installCopy(Alpine), installRelativeTime]) {
    try {
        install()
    } catch (e) {
        console.error('[auditor]', e)
    }
}

window.Alpine = Alpine
Alpine.start()
