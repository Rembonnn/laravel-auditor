// Inlined in <head> before the stylesheet: applies the theme before first
// paint so a dark-mode reload never flashes white.
(function () {
    var root = document.documentElement
    var mode = root.getAttribute('data-theme-default') || 'system'

    try {
        mode = localStorage.getItem('auditor.theme') || mode
    } catch (e) {}

    var dark = mode === 'dark' || (mode !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches)

    root.classList.toggle('dark', dark)
    root.setAttribute('data-theme', mode)

    try {
        root.setAttribute('data-density', localStorage.getItem('auditor.density') || 'comfortable')
        if (localStorage.getItem('auditor.sidebar') === 'collapsed') root.setAttribute('data-sidebar', 'collapsed')
    } catch (e) {}
})()
