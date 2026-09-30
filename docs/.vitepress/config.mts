import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Laravel Auditor',
  description: 'Request-level audit trail for Laravel: who did what, what changed, and what they were allowed to do.',
  base: '/laravel-auditor/',
  cleanUrls: true,
  lastUpdated: true,
  srcExclude: ['v3-implementation-plan.md'],
  head: [['meta', { name: 'theme-color', content: '#059669' }]],
  themeConfig: {
    nav: [
      { text: 'Guide', link: '/guide/installation' },
      { text: 'Reference', link: '/reference/facade' },
      { text: 'Changelog', link: 'https://github.com/Rembonnn/laravel-auditor/blob/main/CHANGELOG.md' },
    ],
    sidebar: [
      {
        text: 'Getting started',
        items: [
          { text: 'Installation', link: '/guide/installation' },
          { text: 'Configuration', link: '/guide/configuration' },
          { text: 'Upgrade from v2', link: '/guide/upgrade' },
        ],
      },
      {
        text: 'Concepts',
        items: [
          { text: 'Entries, changes & correlation', link: '/guide/concepts' },
          { text: 'Model auditing', link: '/guide/models' },
          { text: 'Jobs & commands', link: '/guide/jobs-and-commands' },
          { text: 'Privacy & redaction', link: '/guide/privacy' },
        ],
      },
      {
        text: 'Features',
        items: [
          { text: 'Dashboard', link: '/guide/dashboard' },
          { text: 'Integrity (hash chain)', link: '/guide/integrity' },
          { text: 'Storage drivers', link: '/guide/storage' },
          { text: 'Performance & high traffic', link: '/guide/performance' },
          { text: 'Testing with Auditor::fake()', link: '/guide/testing' },
          { text: 'Filament & Pulse', link: '/guide/ecosystem' },
        ],
      },
      {
        text: 'Reference',
        items: [
          { text: 'Facade', link: '/reference/facade' },
          { text: 'Events', link: '/reference/events' },
          { text: 'Artisan commands', link: '/reference/commands' },
          { text: 'Configuration', link: '/reference/config' },
        ],
      },
    ],
    socialLinks: [{ icon: 'github', link: 'https://github.com/Rembonnn/laravel-auditor' }],
    editLink: {
      pattern: 'https://github.com/Rembonnn/laravel-auditor/edit/main/docs/:path',
    },
    search: { provider: 'local' },
    footer: { message: 'Released under the MIT License.' },
  },
})
