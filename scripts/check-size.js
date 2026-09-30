// Fails when the built dashboard assets exceed the gzip budget.
import { readFileSync } from 'node:fs'
import { gzipSync } from 'node:zlib'

// CSS budget from the plan (25 KB). JS is 30 KB: the Alpine CSP build alone
// is ~24 KB gzip since it ships its own expression parser.
const BUDGET = { css: 25 * 1024, js: 30 * 1024 }

const manifest = JSON.parse(readFileSync('dist/manifest.json', 'utf8'))
let failed = false

for (const chunk of Object.values(manifest)) {
    const type = chunk.file.endsWith('.css') ? 'css' : 'js'
    const size = gzipSync(readFileSync(`dist/${chunk.file}`)).length
    const ok = size <= BUDGET[type]
    failed ||= !ok
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${chunk.file}: ${(size / 1024).toFixed(1)} KB gzip (budget ${BUDGET[type] / 1024} KB)`)
}

process.exit(failed ? 1 : 0)
