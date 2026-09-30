# Laravel Auditor v3 — Implementation Plan

> **Status:** Draft · **Target rilis:** `v3.0.0` · **Dibuat:** 29 September 2026 · **Revisi 1:** tambah desain UI/UX & dark mode (bagian 10)
> **Cakupan:** Rewrite total dari v2 menjadi audit trail per request yang aman untuk production dan siap untuk compliance.

---

## Daftar Isi

1. [Ringkasan & Tujuan](#1-ringkasan--tujuan)
2. [Positioning Package](#2-positioning-package)
3. [Target Kompatibilitas](#3-target-kompatibilitas)
4. [Audit Kondisi v2 (yang harus diselesaikan)](#4-audit-kondisi-v2-yang-harus-diselesaikan)
5. [Keputusan Arsitektur](#5-keputusan-arsitektur)
6. [Struktur Package v3](#6-struktur-package-v3)
7. [Skema Database](#7-skema-database)
8. [Konfigurasi](#8-konfigurasi)
9. [Public API](#9-public-api)
10. [Desain UI/UX Dashboard](#10-desain-uiux-dashboard)
11. [Milestone Implementasi](#11-milestone-implementasi)
12. [Strategi Testing](#12-strategi-testing)
13. [CI/CD & Quality Gates](#13-cicd--quality-gates)
14. [Upgrade Path dari v2](#14-upgrade-path-dari-v2)
15. [Dokumentasi & Peluncuran](#15-dokumentasi--peluncuran)
16. [Risiko & Mitigasi](#16-risiko--mitigasi)
17. [Keputusan yang Perlu Dikonfirmasi](#17-keputusan-yang-perlu-dikonfirmasi)
18. [Definition of Done v3.0.0](#18-definition-of-done-v300)

---

## 1. Ringkasan & Tujuan

### Masalah
v2 punya ide yang bagus, yaitu merekam user, model, ability, mail, dan notifikasi dalam satu request. Tapi implementasinya belum layak dipakai di production:
- Dashboard terbuka untuk publik.
- Data sensitif ikut tersimpan (isi email, hash password).
- Setiap request melambat 1 detik karena `sleep(1)`.
- Banyak bug fungsional.
- Tidak ada test sama sekali.
- Hanya kompatibel dengan Laravel ≤ 9.

### Tujuan v3
| # | Tujuan | Ukuran keberhasilan |
|---|---|---|
| G1 | Kompatibel dengan versi PHP & Laravel yang masih disupport | CI hijau di matrix PHP 8.3–8.5 × Laravel 12–13 |
| G2 | Aman untuk production secara default | Dashboard tertutup di luar `local`, redaction aktif, tidak ada data rahasia tersimpan |
| G3 | Overhead rendah | Overhead median < 1 ms per request (tanpa hitungan persist); persist berjalan setelah response terkirim atau lewat queue |
| G4 | Mudah diadopsi | Dari `composer require` sampai dashboard jalan cukup 3 perintah, tanpa mengedit file aplikasi |
| G5 | Testable & teruji | Coverage ≥ 90%, mutation score ≥ 80% untuk core, Larastan level max |
| G6 | Punya fitur pembeda | Diff perubahan model, correlation ID lintas request→job, audit job & command, tamper-evident hash chain, `Auditor::fake()` |
| G7 | Dashboard modern & mudah dipakai | Dark mode (light/dark/system) tanpa flash; lulus audit aksesibilitas WCAG 2.2 AA di kedua tema; 3 tugas uji usability (bagian 10.13) selesai < 60 detik oleh developer yang baru pertama kali memakai |

### Non-goals (sengaja dibuang dari v2)
- **Performance metrics** (CPU, memory, response time). Laravel Pulse sudah mengerjakan ini dengan lebih baik.
- **Route viewer**. Sudah ada `php artisan route:list`.
- **Migration viewer** dan parser migration. Ini bukan fungsi audit, dan cara kerjanya membaca file dari disk.
- **Daftar model** hasil scan `app/Models`.
- Dependency **yajra/laravel-datatables** dan **jQuery**.

---

## 2. Positioning Package

**Tagline:** *"Request-level audit trail for Laravel — who did what, what changed, and what they were allowed to do. Production-safe and compliance-ready."*

| | Laravel Auditor v3 | spatie/laravel-activitylog | owen-it/laravel-auditing | Laravel Telescope |
|---|---|---|---|---|
| Unit pencatatan | **Request / job / command** | Event manual / model | Event model | Debug entry |
| Diff perubahan model | ✅ | ✅ | ✅ | ⚠️ (debug) |
| Model yang dibaca (read audit) | ✅ | ❌ | ⚠️ (retrieved) | ⚠️ |
| Ability/Gate yang dicek (granted/denied) | ✅ | ❌ | ❌ | ✅ (debug) |
| Mail & notifikasi terkirim | ✅ (tanpa body) | ❌ | ❌ | ✅ (debug) |
| Correlation request → queued job | ✅ | ❌ | ❌ | ⚠️ (batch id) |
| Tamper-evident (hash chain) | ✅ | ❌ | ❌ | ❌ |
| Aman untuk production | ✅ (tujuan utama) | ✅ | ✅ | ⚠️ (tidak disarankan) |

Tabel ini nantinya masuk ke README (bagian 15).

---

## 3. Target Kompatibilitas

Berdasarkan support policy Laravel ([laravel.com/docs/releases](https://laravel.com/docs/releases)):

| Laravel | PHP | Status per Sep 2026 | Didukung v3? |
|---|---|---|---|
| 11 | 8.2–8.4 | EOL (security berakhir Mar 2026) | ❌ |
| 12 | 8.2–8.5 | Security fix sampai Feb 2027 | ✅ |
| 13 | 8.3–8.5 | Aktif | ✅ |

**Keputusan:** `php: ^8.3`, `illuminate/*: ^12.0|^13.0`.

Alasannya: Laravel 13 butuh PHP 8.3+. Dengan menetapkan 8.3 sebagai minimum, kita bisa memakai typed class constants, `#[\Override]`, dan `json_validate()` tanpa polyfill. Selain itu, PHP 8.2 sudah hanya menerima security fix.

**Database yang diuji di CI:** SQLite, MySQL 8.4, PostgreSQL 17, MariaDB 11.

**Kompatibilitas runtime yang diuji:** PHP-FPM (default), Laravel Octane (Swoole/FrankenPHP; pengujian state reset lewat simulasi), dan queue worker.

> ⚠️ Sebelum mulai M0, cek ulang versi terbaru `orchestra/testbench`, `pestphp/pest`, dan `larastan/larastan` yang kompatibel dengan Laravel 13.

---

## 4. Audit Kondisi v2 (yang harus diselesaikan)

Semua item di bawah ini harus beres di v3. Kolom "Solusi" menunjuk ke bagian plan yang menanganinya.

### Keamanan
| # | Masalah | Lokasi v2 | Solusi v3 |
|---|---|---|---|
| S1 | Dashboard `/auditor` tanpa auth | `src/Routes/web.php` | Gate `viewAuditor` + `Auditor::auth()`, default hanya `local` (M5) |
| S2 | Raw email disimpan (`$event->sent->toString()`) | `Listeners/AuthorizeMail.php` | Hanya metadata: mailable, subject, penerima (bisa di-hash) (M3) |
| S3 | Seluruh atribut model disimpan (password hash, token) | `AuditorService::addModel` | Retrieved hanya menyimpan class + key; diff melalui `Redactor` (M1, M2) |
| S4 | Data dari user (URL, user agent) berisiko XSS di dashboard | `DataTableService` (`rawColumns`) | Blade escape di semua tempat, plus test XSS (M5) |
| S5 | Parser file migration membaca path dari input | `BaseController::accessMigrationData` | Fitur dihapus |

### Performa
| # | Masalah | Solusi v3 |
|---|---|---|
| P1 | `sleep(1)` di setiap request (Linux) | Fitur performance dihapus |
| P2 | Insert DB synchronous di dalam `handle()` | Persist di `terminate()`, dengan opsi queue (M1) |
| P3 | `addModel` membuat collection bersarang secara kuadratik | Map `class => [ids]` dengan batas `max_ids_per_model` (M2) |
| P4 | Dashboard memanggil `Performance::all()` dan `->get()` tanpa pagination | Pagination server-side (M5) |
| P5 | Request ke `/auditor` ikut diaudit | Default `http.except` (M1) |

### Bug fungsional
| # | Masalah | Solusi v3 |
|---|---|---|
| B1 | `addEmail()` menulis ke `abilities` | API ditulis ulang, ada test per method |
| B2 | Trait hanya mendengarkan `retrieved`, padahal README menjanjikan old/new values | `AuditableObserver` untuk created/updated/deleted/restored/forceDeleted (M2) |
| B3 | `App\Models\User` di-hardcode di 4 file | User di-resolve lewat guard, disimpan polimorfik (M1) |
| B4 | Listener `AuthorizeAbility` tidak pernah terdaftar | Semua listener didaftarkan otomatis oleh provider (M3) |
| B5 | `getModels()` tidak terdefinisi | Fitur dihapus |
| B6 | `whereDay` hanya membandingkan tanggal dalam bulan | `whereDate` / range `created_at` (M5) |
| B7 | Typo prefix route `migraton` | Route ditulis ulang |
| B8 | `LARAVEL_START` tidak terdefinisi di CLI/test | Pakai `hrtime()` saat context dimulai (M1) |
| B9 | Singleton bocor state di Octane/queue | Binding `scoped()` (M1) |
| B10 | Carbon mutable membuat cleanup performance tidak pernah jalan | Fitur dihapus; pruning lewat `auditor:prune` (M4) |
| B11 | `BaseController extends App\Http\Controllers\Controller` | Controller package berdiri sendiri (M5) |

### Packaging
| # | Masalah | Solusi v3 |
|---|---|---|
| K1 | Tidak ada constraint `php`/`illuminate` | Lihat composer.json (M0) |
| K2 | Field `version` di composer.json | Dihapus; versi dari git tag |
| K3 | `minimum-stability: dev` | Dihapus (default stable) |
| K4 | `composer.lock` ikut di-commit | Dihapus dan dimasukkan ke `.gitignore` |
| K5 | Tag publish generik (`config`, `migrations`, `public`, `views`) dan ada array publish kosong | Tag berprefix `auditor-*` |
| K6 | Nama migration diawali `2050_` | `publishesMigrations()` (timestamp saat publish) |
| K7 | Nama tabel `audits` bentrok dengan owen-it/laravel-auditing | Prefix `auditor_` |

---

## 5. Keputusan Arsitektur

Setiap keputusan dicatat singkat (ADR-lite). Nantinya disalin ke `docs/adr/` bila diperlukan.

### ADR-01: Unit audit = "Entry" per lifecycle
Setiap request HTTP, queued job, dan artisan command menghasilkan **satu Entry**. Model changes adalah **baris terpisah** yang terhubung ke Entry. Alasannya: model changes perlu di-query per record (`$post->audits`), sedangkan detail request lebih efisien dalam bentuk JSON di satu baris.

### ADR-02: State per lifecycle menggunakan `scoped` binding
`Recorder` didaftarkan dengan `$this->app->scoped(...)`. Laravel otomatis mem-flush scoped instance saat Octane menerima request baru dan saat queue worker memproses job baru. Ini menutup bug B9 tanpa perlu kode reset manual.

### ADR-03: Correlation ID melalui `Illuminate\Support\Facades\Context`
- Ketika request dimulai, correlation ID diambil dari header (hanya jika `trust_incoming_correlation_id=true`) atau dibuat sebagai ULID baru.
- Nilai disimpan dengan `Context::addHidden('auditor.correlation_id', ...)` dan `Context::addHidden('auditor.causer', [type, id])`.
- Laravel otomatis membawa Context ke queued job, sehingga job tahu request mana yang men-dispatch-nya dan siapa usernya **tanpa** perlu mengubah class job.
- Correlation ID juga dikirim ke response lewat header `X-Request-Id` (bisa diatur).

### ADR-04: Persist dilakukan di akhir lifecycle, tidak pernah di tengah request
- HTTP: `terminate()` di terminable middleware. Di PHP-FPM, ini berjalan setelah response terkirim ke client.
- Job: event `JobProcessed` / `JobFailed`.
- Command: event `CommandFinished`.
- Opsi `queue.enabled=true`: Entry diserialisasi menjadi DTO berisi skalar saja, lalu dikirim sebagai job `PersistEntry`.
- **Buffer flush:** jika jumlah model change yang di-buffer melebihi `buffer_size` (default 100), perubahan langsung di-flush. Ini menjaga command yang berjalan lama (misalnya `tinker` atau import besar) agar tidak menumpuk di memori.

### ADR-05: Auditing tidak boleh mematahkan aplikasi
Setiap titik masuk (middleware, observer, listener, persist) dibungkus `try/catch`, lalu error dilaporkan lewat `report($e)`. Pengecualiannya: `auditor.throw_exceptions=true` (disarankan di env testing) akan melempar ulang exception.

### ADR-06: Storage driver menggunakan `Illuminate\Support\Manager`
- Driver bawaan: `database`, `log` (JSON line ke channel log), `null`.
- Driver custom lewat `Auditor::extend('s3', fn ($app) => ...)`.
- Dashboard, integrity, dan pruning **hanya** tersedia untuk driver `database`. Batasan ini didokumentasikan.

### ADR-07: Kolom user & auditable menyimpan key sebagai string
`user_id` dan `auditable_id` bertipe `string(64)`, sehingga key integer, UUID, ULID, maupun custom bisa masuk ke satu kolom tanpa perlu mengedit migration. Tipe kolom bisa diubah lewat config `database.morph_key_type` (`string` | `int` | `uuid` | `ulid`) sebelum migrate. Konsekuensinya: perlu test khusus untuk relasi morph dengan key integer di PostgreSQL (R3).

### ADR-08: Hash chain dihitung di proses terpisah ("sealing")
Menghitung hash di hot path butuh lock global, dan lock itu mematikan concurrency. Sebagai gantinya:
- Row disimpan dengan `hash = null`.
- Command `auditor:seal` (dijadwalkan setiap menit, `withoutOverlapping`) memproses row yang belum di-seal secara berurutan menurut `id`, lalu mengisi `previous_hash` dan `hash`.
- Rumus: `hash = HMAC-SHA256(key, previous_hash . canonical_json(row))`. Kuncinya ada di env, **bukan** di database, sehingga orang yang punya akses DB tidak bisa menghitung ulang chain.
- Chain dipisah per tabel (entries, model_changes).
- Entry hanya di-seal setelah `completed_at` terisi.
- `auditor:verify` memeriksa seluruh chain. `auditor:prune` menulis checkpoint sebelum menghapus row, supaya verifikasi tetap bisa dimulai dari checkpoint tersebut.

### ADR-09: Dashboard memakai Blade + Alpine.js dengan aset pre-built
- Tidak ada Livewire atau Inertia, supaya dependency user tetap nol.
- CSS (Tailwind v4) dan JS (Alpine) di-build di repo package ke `dist/`, lalu dilayani lewat route `auditor/assets/{file}` dengan nama berisi hash konten dan header cache panjang. User **tidak perlu** mem-publish aset.
- Memakai **build CSP Alpine** (`@alpinejs/csp`), sehingga dashboard tetap jalan di aplikasi dengan Content Security Policy ketat (tanpa `unsafe-eval`). Konsekuensinya: semua logika Alpine ditulis sebagai komponen `Alpine.data()` di `resources/js`, bukan ekspresi inline di Blade.
- Tidak ada request ke CDN atau font eksternal, supaya dashboard tetap berfungsi di intranet/offline dan tidak membocorkan data kunjungan ke pihak ketiga.
- Detail desain visual dan interaksi ada di bagian 10.
- URL detail memakai ULID, bukan id auto-increment, untuk menghindari enumeration.

### ADR-10: Integrasi ekosistem sebagai package terpisah
Plugin Filament (`rembon/laravel-auditor-filament`) dan kartu Pulse (`rembon/laravel-auditor-pulse`) dibuat di repo terpisah, sehingga core tidak ikut membawa dependency Filament atau Pulse. Keduanya dirilis bersamaan atau segera setelah `v3.0.0` (M9).

### ADR-11: Pertahankan namespace dan nama trait
Namespace `Rembon\LaravelAuditor` dan trait `Rembon\LaravelAuditor\Traits\Auditable` tetap sama, agar upgrade tidak perlu mengubah model yang sudah memakai trait.

---

## 6. Struktur Package v3

```
laravel-auditor/
├── .github/
│   ├── workflows/{tests.yml, static-analysis.yml, code-style.yml, coverage.yml, release.yml}
│   ├── ISSUE_TEMPLATE/{bug_report.yml, feature_request.yml, config.yml}
│   ├── PULL_REQUEST_TEMPLATE.md
│   └── dependabot.yml
├── config/
│   └── auditor.php
├── database/
│   └── migrations/
│       ├── create_auditor_entries_table.php.stub
│       ├── create_auditor_model_changes_table.php.stub
│       └── create_auditor_checkpoints_table.php.stub
├── dist/                                  # hasil build (di-commit), dilayani lewat route
│   ├── auditor.css
│   ├── auditor.js
│   └── manifest.json
├── docs/                                  # sumber dokumentasi (lihat bagian 15)
├── lang/
│   ├── en/auditor.php
│   └── id/auditor.php
├── resources/
│   ├── css/
│   │   ├── auditor.css                    # entry Tailwind v4 (@theme, @custom-variant dark)
│   │   └── tokens.css                     # design tokens light/dark (bagian 10.3)
│   ├── js/
│   │   ├── auditor.js                     # entry: Alpine CSP build + plugins (focus, collapse, persist)
│   │   ├── theme-init.js                  # di-inline di <head> (dengan nonce) untuk mencegah flash
│   │   └── components/{theme, command-palette, shortcuts, filters, live, drawer, json-tree, diff, copy, toast, relative-time}.js
│   └── views/
│       ├── layout.blade.php               # shell: sidebar, topbar, slot, toast region
│       ├── components/
│       │   ├── {button, badge, card, stat-card, table, empty-state, skeleton, tabs, drawer, dropdown,
│       │   │    tooltip, kbd, copy, relative-time, icon, filter-bar, filter-chip, pagination}.blade.php
│       │   ├── {diff, json-tree, timeline, bar-chart, sparkline, method-badge, status-badge,
│       │   │    event-badge, user-chip, env-badge, setup-checklist}.blade.php
│       │   └── icons.php                  # subset path SVG Lucide yang dipakai saja
│       ├── partials/{command-palette, shortcuts-help, entry-peek}.blade.php
│       ├── overview.blade.php
│       ├── entries/{index, show}.blade.php
│       ├── changes/index.blade.php
│       ├── models/history.blade.php
│       └── integrity.blade.php
├── routes/
│   └── web.php
├── src/
│   ├── Auditor.php                        # API publik (config statis + delegasi ke Recorder)
│   ├── AuditorServiceProvider.php
│   ├── Facades/Auditor.php
│   ├── Recorder.php                       # state per lifecycle (scoped)
│   ├── Contracts/
│   │   ├── Storage.php                    # store(EntryData), storeChanges(array<ModelChangeData>)
│   │   └── UserResolver.php
│   ├── Data/
│   │   ├── EntryData.php                  # readonly, hanya skalar & array
│   │   ├── ModelChangeData.php
│   │   ├── AbilityCheck.php
│   │   ├── MailRecord.php
│   │   └── NotificationRecord.php
│   ├── Enums/{EntryType.php, ChangeEvent.php}
│   ├── Support/
│   │   ├── Redactor.php
│   │   ├── CorrelationId.php
│   │   ├── CanonicalJson.php
│   │   ├── IpAnonymizer.php
│   │   └── UserResolver.php               # implementasi default (multi-guard)
│   ├── Storage/
│   │   ├── StorageManager.php             # extends Illuminate\Support\Manager
│   │   ├── DatabaseStorage.php
│   │   ├── LogStorage.php
│   │   └── NullStorage.php
│   ├── Models/
│   │   ├── Entry.php                      # MassPrunable
│   │   ├── ModelChange.php                # MassPrunable
│   │   └── Checkpoint.php
│   ├── Traits/Auditable.php
│   ├── Observers/AuditableObserver.php
│   ├── Http/
│   │   ├── Middleware/{RecordRequest.php, Authorize.php}
│   │   └── Controllers/{OverviewController, EntryController, ChangeController, ModelHistoryController, IntegrityController, ExportController, AssetController}.php
│   ├── Listeners/
│   │   ├── RecordAbilityCheck.php         # GateEvaluated
│   │   ├── RecordMail.php                 # MessageSent
│   │   ├── RecordNotification.php         # NotificationSent
│   │   ├── JobLifecycle.php               # JobProcessing/JobProcessed/JobFailed
│   │   └── CommandLifecycle.php           # CommandStarting/CommandFinished
│   ├── Jobs/PersistEntry.php
│   ├── Integrity/{Sealer.php, Verifier.php, VerificationResult.php}
│   ├── Console/{InstallCommand, PruneCommand, SealCommand, VerifyCommand, ImportV2Command}.php
│   ├── Events/{EntryRecorded.php, ModelChangeRecorded.php, IntegrityViolationDetected.php}
│   └── Testing/AuditorFake.php
├── tests/                                 # lihat bagian 12
├── workbench/                             # Testbench workbench untuk develop dashboard
├── .editorconfig  .gitattributes  .gitignore
├── CHANGELOG.md  CONTRIBUTING.md  SECURITY.md  UPGRADE.md  LICENSE  README.md
├── composer.json  package.json  vite.config.js
├── phpstan.neon.dist  phpunit.xml.dist  pint.json  rector.php
└── testbench.yaml
```

File v2 yang **dihapus**: `src/Components/*`, `src/Services/*`, `src/Contracts/Auditor.php` (diganti), `src/Models/Performance.php`, `src/Http/Middleware/PerformanceMetrics.php`, `src/Http/Controllers/BaseController.php`, `src/Views/*` (ditulis ulang di `resources/views`), `src/Config`, `src/Routes`, `src/Database` (dipindah ke root sesuai konvensi), `dist/assets/*` (diganti hasil build baru), `composer.lock`.

---

## 7. Skema Database

Nama tabel dan koneksi bisa diatur lewat config. Migration membaca `config('auditor.storage.database.connection')`.

### `auditor_entries`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigIncrements | Urutan untuk hash chain |
| `ulid` | ulid, unique | Dipakai di URL dashboard |
| `correlation_id` | string(64), index | Sama untuk request dan job turunannya |
| `type` | string(16), index | `http` \| `job` \| `command` \| `other` |
| `name` | string, nullable, index | Nama route / class job / nama command |
| `user_type` | string, nullable | Morph class user |
| `user_id` | string(64), nullable | Index gabungan `(user_type, user_id)` |
| `guard` | string(64), nullable | |
| `http_method` | string(10), nullable | |
| `url` | text, nullable | Query string di-redact sesuai `redaction.keys` |
| `route_action` | string, nullable | |
| `status_code` | smallInteger unsigned, nullable | HTTP status / exit code command |
| `failed` | boolean, default false, index | Job gagal / exception / status ≥ 500 |
| `ip` | string(45), nullable | Bisa dianonimkan |
| `user_agent` | string(512), nullable | |
| `os_user` | string(64), nullable | Untuk command (siapa yang menjalankan `tinker`) |
| `hostname` | string, nullable | |
| `duration_ms` | unsignedInteger, nullable | |
| `abilities` | json, nullable | `[{ability, result, arguments:[{type,id}]}]` |
| `models_accessed` | json, nullable | `{ "App\\Models\\Post": {"ids": [1,2], "count": 120} }` |
| `mails` | json, nullable | `[{mailable, subject, to, cc, bcc}]` |
| `notifications` | json, nullable | `[{notification, channel, notifiable_type, notifiable_id}]` |
| `input` | json, nullable | Hanya jika `http.capture.input=true`, sudah di-redact |
| `properties` | json, nullable | Dari `Auditor::withProperty()` |
| `tags` | json, nullable | |
| `denied_abilities_count` | unsignedSmallInteger, default 0, index | Denormalisasi untuk filter cepat |
| `model_changes_count` | unsignedInteger, default 0 | Denormalisasi |
| `started_at` | timestamp(6) | |
| `completed_at` | timestamp(6), nullable | Row di-seal hanya jika sudah terisi |
| `previous_hash` | char(64), nullable | |
| `hash` | char(64), nullable, index | |
| `created_at` | timestamp(6), index | Dipakai untuk prune & filter tanggal |

### `auditor_model_changes`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigIncrements | |
| `ulid` | ulid, unique | |
| `entry_id` | foreignId, nullable → `auditor_entries.id`, `nullOnDelete` | |
| `correlation_id` | string(64), index | |
| `auditable_type` | string | |
| `auditable_id` | string(64) | Index `(auditable_type, auditable_id, id)` |
| `event` | string(16), index | `created` \| `updated` \| `deleted` \| `restored` \| `force_deleted` |
| `old_values` | json, nullable | |
| `new_values` | json, nullable | |
| `user_type`, `user_id` | string, nullable | Disalin dari entry, agar query "apa saja yang diubah user X" tidak perlu join |
| `created_at` | timestamp(6), index | |
| `previous_hash`, `hash` | char(64), nullable | |

### `auditor_checkpoints`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigIncrements | |
| `table` | string(64) | `entries` \| `model_changes` |
| `last_id` | unsignedBigInteger | Id terakhir yang dihapus oleh prune |
| `last_hash` | char(64) | Hash row terakhir yang dihapus, menjadi titik awal verifikasi |
| `created_at` | timestamp | |

---

## 8. Konfigurasi

File `config/auditor.php` (key config diganti dari `laravel-auditor` menjadi `auditor`):

```php
<?php

return [

    // Master switch. Jika false, tidak ada yang direkam dan dashboard tetap bisa dibuka.
    'enabled' => env('AUDITOR_ENABLED', true),

    'storage' => [
        'driver' => env('AUDITOR_DRIVER', 'database'), // database | log | null | custom

        'database' => [
            'connection' => env('AUDITOR_DB_CONNECTION'),
            'morph_key_type' => 'string', // string | int | uuid | ulid (dibaca migration)
            'tables' => [
                'entries' => 'auditor_entries',
                'model_changes' => 'auditor_model_changes',
                'checkpoints' => 'auditor_checkpoints',
            ],
        ],

        'log' => [
            'channel' => env('AUDITOR_LOG_CHANNEL'),
        ],
    ],

    'queue' => [
        'enabled' => env('AUDITOR_QUEUE', false),
        'connection' => env('AUDITOR_QUEUE_CONNECTION'),
        'queue' => env('AUDITOR_QUEUE_NAME'),
    ],

    // Jika model changes di-buffer melebihi angka ini, langsung di-flush (untuk command yang berjalan lama).
    'buffer_size' => 100,

    'user' => [
        'guards' => null, // null = guard default; atau ['web', 'sanctum', 'admin']
    ],

    'http' => [
        'enabled' => true,
        'middleware_groups' => ['web'], // tambahkan 'api' bila perlu; [] = daftarkan manual
        'except' => [
            'auditor', 'auditor/*', 'up', 'telescope*', 'horizon*', 'pulse*',
            '_debugbar*', 'livewire*/livewire*.js', 'build/*', 'favicon.ico',
        ],
        'except_methods' => ['OPTIONS', 'HEAD'],
        // Porsi request yang direkam (0.0–1.0). Entry yang punya model changes
        // atau ability yang ditolak SELALU direkam, berapa pun nilainya.
        'sample_rate' => 1.0,
        'capture' => [
            'ip' => true,
            'anonymize_ip' => false,
            'user_agent' => true,
            'input' => false,
        ],
        'correlation_header' => 'X-Request-Id',
        'trust_incoming_correlation_id' => false,
    ],

    'jobs' => [
        'enabled' => true,
        'except' => [\Rembon\LaravelAuditor\Jobs\PersistEntry::class],
    ],

    'console' => [
        'enabled' => true,
        'except' => [
            'queue:work', 'queue:listen', 'horizon*', 'schedule:work', 'schedule:run',
            'octane:*', 'reverb:*', 'pulse:*', 'serve', 'auditor:*', 'list', 'help',
            'package:discover', 'vendor:publish', 'config:*', 'route:*', 'view:*', 'event:*', 'optimize*',
        ],
        'record_os_user' => true,
    ],

    'models' => [
        'track_retrieved' => true,
        'max_ids_per_model' => 50,
        'events' => ['created', 'updated', 'deleted', 'restored', 'force_deleted'],
        'exclude' => ['updated_at'], // global; event update yang hanya mengubah atribut ini diabaikan
    ],

    'listeners' => [
        'gate' => true,
        'mail' => true,
        'notifications' => true,
    ],

    'mail' => [
        'record_recipients' => true,
        'hash_recipients' => false, // simpan sha256(email), bukan email asli (GDPR)
    ],

    'redaction' => [
        'keys' => [
            '*password*', '*token*', '*secret*', 'api_key', 'apikey', 'authorization',
            'two_factor_*', 'credit_card*', 'card_number', 'cvv', 'cvc', 'pin', 'ssn',
        ],
        'redact_hidden_attributes' => true,   // $hidden di model
        'redact_encrypted_casts' => true,     // cast encrypted, encrypted:array, AsEncrypted*
        'replacement' => '[REDACTED]',
    ],

    'integrity' => [
        'enabled' => env('AUDITOR_INTEGRITY', false),
        'key' => env('AUDITOR_INTEGRITY_KEY'), // wajib jika enabled; buat dengan auditor:install --integrity
    ],

    'prune' => [
        'keep_days' => env('AUDITOR_KEEP_DAYS', 90),
    ],

    'dashboard' => [
        'enabled' => env('AUDITOR_DASHBOARD', true),
        'path' => env('AUDITOR_PATH', 'auditor'),
        'domain' => env('AUDITOR_DOMAIN'),
        'middleware' => ['web'],
        'per_page' => 25,
        'theme' => env('AUDITOR_THEME', 'system'), // system | light | dark (default awal; user tetap bisa toggle)
        'accent' => null,          // hex, contoh '#6366f1'; null = default emerald
        'brand' => [
            'name' => null,        // null = config('app.name')
            'logo' => null,        // URL gambar; null = logo bawaan
        ],
        'poll_interval' => 5,      // detik, untuk live mode; 0 = live mode dimatikan
        'timezone' => null,        // null = zona waktu browser; atau 'Asia/Jakarta'
    ],

    'throw_exceptions' => env('AUDITOR_THROW', false),
];
```

**Aturan pencocokan redaction:** nama key diubah ke lowercase, lalu dicocokkan dengan `Str::is($pattern, $key)`. Aturan ini berlaku rekursif untuk array bersarang (input, properties, old/new values) dan untuk query string di URL.

---

## 9. Public API

### 9.1 Pemakaian dasar (target DX)

```bash
composer require rembon/laravel-auditor
php artisan auditor:install   # publish config + migration, tanya migrate, tampilkan snippet gate & schedule
```

```php
use Rembon\LaravelAuditor\Traits\Auditable;

class Post extends Model
{
    use Auditable;

    // Opsional
    protected array $auditExclude = ['view_count'];
    protected array $auditEvents = ['updated', 'deleted'];
    protected bool $auditRetrieved = false;
}
```

```php
// AppServiceProvider::boot()
Gate::define('viewAuditor', fn (User $user) => $user->is_admin);
```

### 9.2 Facade `Auditor`

```php
use Rembon\LaravelAuditor\Facades\Auditor;

// Memperkaya entry yang sedang berjalan
Auditor::withProperty('order_id', $order->id);
Auditor::withProperties(['channel' => 'mobile']);
Auditor::tag('checkout', 'payment');

// Mengontrol perekaman
Auditor::ignore();                               // entry saat ini tidak disimpan (model changes tetap disimpan)
Auditor::withoutAuditing(fn () => $post->save()); // perubahan di dalam callback tidak direkam
Auditor::correlationId();                        // string

// Kustomisasi (di service provider)
Auditor::auth(fn (Request $request) => $request->user()?->isAdmin());
Auditor::resolveUserUsing(fn () => auth('admin')->user());
Auditor::filter(fn (EntryData $entry) => $entry->name !== 'health.check');
Auditor::redactUsing(fn (string $key, mixed $value) => /* bool */);
Auditor::extend('s3', fn (Application $app) => new S3Storage(...));
Auditor::displayUserUsing(fn ($user) => ['name' => $user->name, 'avatar' => $user->avatar_url]); // chip user di dashboard
```

### 9.3 Relasi & query model

```php
$post->audits;                                   // MorphMany<ModelChange>, terbaru di atas
$post->audits()->where('event', ChangeEvent::Updated)->get();

ModelChange::query()->causedBy($user)->since(now()->subWeek())->get();
Entry::query()->forCorrelation($id)->get();      // request + job turunannya
Entry::query()->withDeniedAbilities()->get();
```

### 9.4 Testing helper untuk user package

```php
Auditor::fake();

$this->actingAs($user)->put("/posts/{$post->id}", ['title' => 'Baru']);

Auditor::assertChangeRecorded(Post::class, $post->id, ChangeEvent::Updated,
    fn (ModelChangeData $change) => $change->newValues['title'] === 'Baru');
Auditor::assertEntryRecorded(fn (EntryData $e) => $e->userId === (string) $user->id);
Auditor::assertAbilityDenied('delete-post');
Auditor::assertNothingRecorded();
```

### 9.5 Events

| Event | Kapan | Payload |
|---|---|---|
| `EntryRecorded` | Setelah entry tersimpan | `EntryData` |
| `ModelChangeRecorded` | Setelah change tersimpan | `ModelChangeData` |
| `IntegrityViolationDetected` | `auditor:verify` menemukan kerusakan | tabel, id, hash yang diharapkan vs aktual |

### 9.6 Artisan commands

| Command | Fungsi |
|---|---|
| `auditor:install [--integrity] [--force]` | Publish config & migration, generate `AUDITOR_INTEGRITY_KEY` ke `.env`, tanya migrate, tampilkan snippet gate & schedule |
| `auditor:prune [--days=]` | Tulis checkpoint, lalu hapus data lama (chunked). Hanya row yang sudah di-seal jika integrity aktif |
| `auditor:seal [--limit=]` | Menghitung hash chain untuk row yang belum di-seal |
| `auditor:verify [--table=] [--from=]` | Memverifikasi chain; exit code ≠ 0 jika ditemukan kerusakan |
| `auditor:import-v2 [--chunk=] [--drop-old]` | Migrasi data dari tabel `audits` v2 |

Snippet jadwal yang ditampilkan oleh `auditor:install`:
```php
// routes/console.php
Schedule::command('auditor:prune')->daily();
Schedule::command('auditor:seal')->everyMinute()->withoutOverlapping(); // jika integrity aktif
Schedule::command('auditor:verify')->daily();                          // jika integrity aktif
```

---

## 10. Desain UI/UX Dashboard

Dashboard v2 berupa tabel DataTables dengan jQuery, tanpa dark mode, tanpa filter yang berarti, dan halaman detailnya hanya menampilkan dump JSON. Di v3, dashboard menjadi **wajah utama package**: yang pertama dilihat calon pengguna di README, dan yang dipakai setiap hari untuk menjawab pertanyaan *"siapa mengubah apa, kapan?"*.

### 10.1 Prinsip desain

| Prinsip | Artinya dalam praktik |
|---|---|
| **Jawab pertanyaan, bukan tampilkan data** | Setiap halaman dirancang untuk pertanyaan konkret: "siapa yang menghapus Post #12?", "ability apa yang ditolak hari ini?", "job ini dipicu request apa?" |
| **Mudah dipindai (scan-first)** | Padat informasi tapi tenang: tipografi jelas, angka tabular, warna hanya untuk makna |
| **Progressive disclosure** | Ringkasan di list → drawer "quick peek" → halaman detail lengkap → JSON mentah |
| **Keyboard-first, mouse-friendly** | Semua aksi bisa lewat keyboard (⌘K, `j`/`k`, `/`), tapi tetap nyaman dengan mouse & layar sentuh |
| **Warna punya makna** | Hijau = created/granted, kuning = updated, merah = deleted/denied/failed, biru = restored/info. Warna tidak pernah menjadi satu-satunya penanda; selalu ada ikon + teks |
| **Aman secara default terlihat** | Nilai yang di-redact tampil sebagai badge 🔒 `REDACTED`. Badge environment (merah untuk `production`) selalu terlihat di topbar |
| **Familiar** | Pola navigasi mirip Telescope/Horizon/Pulse supaya pengguna Laravel langsung paham, dengan estetika modern ala Linear/Vercel |

### 10.2 Dark mode

- **Tiga mode:** Light, Dark, System (default). Toggle ada di topbar (ikon matahari/bulan/monitor) dan shortcut `t`.
- **Penyimpanan preferensi:** `localStorage['auditor.theme']`. Default awal diambil dari config `dashboard.theme`.
- **Tanpa flash (FOUC):** script kecil `theme-init.js` di-inline di `<head>` **sebelum** CSS. Script ini membaca localStorage dan `matchMedia('(prefers-color-scheme: dark)')`, lalu memasang class `.dark` di `<html>`. Script inline memakai nonce CSP dari `Vite::cspNonce()` jika tersedia, atau dari `Auditor::nonce()`.
- **Mengikuti sistem secara live:** listener `matchMedia(...).addEventListener('change')` aktif saat mode = System.
- **Tailwind v4:** `@custom-variant dark (&:where(.dark, .dark *));`. Semua warna memakai token semantik (10.3), bukan warna mentah per komponen, sehingga dark mode tidak perlu menulis `dark:` di setiap elemen.
- **Elemen native:** `color-scheme: light dark` di `:root` supaya scrollbar, input date, dan select ikut tema.
- **Aturan khusus dark mode:**
  - Background tidak hitam pekat (zinc-950); elevasi ditunjukkan dengan surface yang sedikit lebih terang, bukan shadow.
  - Warna diff dan badge memakai latar transparan (misalnya `emerald-500/15`) dengan teks yang lebih terang, agar kontras tetap memenuhi AA.
  - Chart dan syntax highlight JSON membaca CSS variable, sehingga langsung berubah saat tema di-toggle tanpa reload.
  - Logo punya varian terang dan gelap (atau SVG `currentColor`).

### 10.3 Design tokens

Didefinisikan sebagai CSS variables di `resources/css/tokens.css`, lalu dipetakan ke Tailwind lewat `@theme`.

| Token | Light | Dark | Dipakai untuk |
|---|---|---|---|
| `--bg` | `zinc-50` | `zinc-950` | Latar halaman |
| `--surface` | `white` | `zinc-900` | Card, tabel, drawer |
| `--surface-raised` | `white` + shadow-sm | `zinc-800` | Dropdown, command palette, tooltip |
| `--border` | `zinc-200` | `zinc-800` | Garis pemisah |
| `--text` | `zinc-900` | `zinc-100` | Teks utama |
| `--text-muted` | `zinc-500` | `zinc-400` | Label, metadata |
| `--accent` | `emerald-600` | `emerald-400` | Link, tombol utama, fokus (bisa di-override lewat `dashboard.accent`) |
| `--success` | `emerald-600` | `emerald-400` | created, granted, 2xx |
| `--warning` | `amber-600` | `amber-400` | updated, 4xx |
| `--danger` | `rose-600` | `rose-400` | deleted, denied, failed, 5xx |
| `--info` | `sky-600` | `sky-400` | restored, job, info |
| `--diff-add-bg` / `--diff-del-bg` | `emerald-50` / `rose-50` | `emerald-500/15` / `rose-500/15` | Diff viewer |

- **Accent tetap emerald** sebagai kesinambungan brand dari v2. Jika `dashboard.accent` di-set, nilai hex tersebut dipasang sebagai `--accent` lewat style inline ber-nonce, dan varian hover/fokus diturunkan dengan `color-mix()`.
- **Radius:** 8px (card), 6px (input/tombol), 999px (badge/chip).
- **Spacing:** skala 4px.
- **Density:** mode *Comfortable* (default) dan *Compact* (tinggi baris tabel 44px → 32px), disimpan di localStorage.
- **Kontras:** semua pasangan teks/latar wajib ≥ 4.5:1 (teks kecil) di **kedua** tema, dan diverifikasi otomatis (12.6).

### 10.4 Tipografi & ikon

- **Font:** system font stack (`ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, …`) dengan ukuran file nol, tanpa request eksternal, dan tampil native di tiap OS. Opsi bundling Inter ada di D8.
- **Monospace** untuk ID, URL, JSON, dan correlation ID: `ui-monospace, SFMono-Regular, Menlo, Consolas, monospace`.
- **Angka:** `font-variant-numeric: tabular-nums` untuk durasi, jumlah, dan waktu, supaya kolom rata dan tidak "loncat" saat live update.
- **Skala ukuran teks:** 12 / 13 / 14 (body) / 16 / 20 / 24 px.
- **Ikon:** [Lucide](https://lucide.dev) (lisensi ISC), dirender sebagai SVG inline lewat `<x-auditor::icon name="shield-alert" />`. Hanya ikon yang dipakai yang disalin ke `components/icons.php` (sekitar 40 ikon), tanpa icon font dan tanpa sprite eksternal.

### 10.5 Layout & navigasi

**Shell (desktop ≥ 1280px):**
```
┌──────────────┬───────────────────────────────────────────────────────────────────┐
│ ◆ Acme       │  Entries › 01J9Z…K2        [🔍 Search…  ⌘K]   ● Live   ☾   PRODUCTION │
│   Auditor    ├───────────────────────────────────────────────────────────────────┤
│              │                                                                   │
│ ▣ Overview   │                                                                   │
│ ≡ Entries    │                          (konten halaman)                         │
│ ⇄ Changes    │                                                                   │
│ ⛨ Integrity  │                                                                   │
│              │                                                                   │
│ ───────────  │                                                                   │
│ ? Shortcuts  │                                                                   │
│ ↗ Docs       │                                                                   │
│ v3.0.0  ◐    │                                                                   │
└──────────────┴───────────────────────────────────────────────────────────────────┘
```
- **Sidebar:** bisa di-collapse menjadi ikon saja; state disimpan di localStorage. Item aktif ditandai garis accent + latar subtle. Menu Integrity menampilkan titik merah jika verifikasi terakhir gagal.
- **Topbar:** breadcrumb, tombol search/command palette, toggle live mode, toggle tema, dan **badge environment** (`local` abu-abu, `staging` kuning, `production` merah).
- **Tablet (768–1279px):** sidebar otomatis collapsed (ikon saja).
- **Mobile (< 768px):** sidebar menjadi drawer (tombol ☰), tabel berubah menjadi daftar kartu, filter bar menjadi tombol "Filter (3)" yang membuka bottom sheet. Target sentuh minimal 44×44px.

### 10.6 Pola interaksi

**Command palette (⌘K / Ctrl+K)**
- Navigasi ke halaman mana pun.
- Pencarian cerdas berdasarkan pola input:
  - ULID atau correlation ID → langsung buka entry atau timeline.
  - `Post#12` / `App\Models\Post:12` → riwayat model.
  - `user:5` → entries milik user tersebut.
  - `route:orders.store` → entries route tersebut.
- Aksi cepat: ganti tema, toggle live mode, salin URL halaman.

**Keyboard shortcuts** (bisa dilihat di modal `?`)
| Tombol | Aksi |
|---|---|
| `⌘K` / `Ctrl+K` | Command palette |
| `g` lalu `o` / `e` / `c` / `i` | Buka Overview / Entries / Changes / Integrity |
| `/` | Fokus ke filter |
| `j` / `k` | Pindah baris di tabel |
| `Enter` / `Space` | Buka detail / quick peek baris terpilih |
| `Esc` | Tutup drawer, modal, atau palette |
| `t` | Ganti tema |
| `l` | Toggle live mode |
| `?` | Bantuan shortcut |

**Filter**
- Filter bar berisi chip yang bisa dihapus satu per satu (`Type: http ✕`, `User: #5 ✕`), plus tombol "Reset".
- Semua state filter disimpan di **query string URL**, sehingga link bisa dibagikan ke rekan tim dan tombol back browser berfungsi.
- **Preset cepat:** Hari ini · Denied · Failed · Punya changes · Oleh saya.
- **Saved views:** simpan kombinasi filter dengan nama (disimpan di localStorage per browser).
- Input rentang tanggal punya pintasan: 15m, 1h, 24h, 7d, 30d, custom.

**Quick peek (drawer)**
- Klik baris atau tekan `Space` → drawer dari kanan menampilkan ringkasan entry tanpa meninggalkan list. Isinya di-fetch dari partial `GET /entries/{ulid}/peek`.
- Tombol "Buka detail lengkap ↗". Navigasi `j`/`k` tetap berfungsi saat drawer terbuka, sehingga bisa menelusuri entry satu per satu dengan cepat.

**Live mode**
- Polling tiap `dashboard.poll_interval` detik di Overview dan Entries.
- Baris baru **tidak** langsung menggeser tabel. Yang muncul adalah banner "↑ 3 entry baru — tampilkan", supaya posisi baca tidak hilang.
- Polling berhenti otomatis saat tab tidak aktif (`visibilitychange`) dan saat user sedang menggulir ke bawah.
- Update diumumkan lewat `aria-live="polite"`.

**Waktu**
- Ditampilkan relatif ("2 menit lalu", diperbarui tiap menit). Hover/fokus menampilkan tooltip waktu absolut + zona waktu.
- Toggle zona waktu: browser ↔ UTC ↔ `dashboard.timezone`.

**Detail kecil yang membuat nyaman**
- Tombol **copy** (ikon, muncul saat hover/fokus) pada ULID, correlation ID, URL, user id, dan blok JSON, dengan toast "Disalin".
- Nilai panjang (URL, user agent) dipotong dengan elipsis + tooltip teks penuh; klik untuk expand.
- Method HTTP berwarna konsisten (GET abu-abu, POST hijau, PUT/PATCH kuning, DELETE merah).
- Status code dalam badge (2xx hijau, 3xx abu-abu, 4xx kuning, 5xx merah).
- Durasi diberi warna ringan jika > 1 detik.
- User ditampilkan sebagai chip: avatar inisial + nama (jika atribut `name`/`email` tersedia lewat resolver) + id.
- Header tabel sticky, baris bisa diklik, dan kolom bisa disembunyikan (preferensi disimpan).

### 10.7 Komponen kunci

**Diff viewer** (untuk model changes)
- Tampilan per atribut: `nama atribut | nilai lama → nilai baru`, dengan dua mode: **Inline** (default di mobile) dan **Side-by-side** (default di desktop).
- Event `created` hanya menampilkan kolom baru; `deleted` hanya kolom lama.
- Nilai JSON bersarang di-diff per key secara rekursif, dengan key yang tidak berubah di-collapse ("4 key tidak berubah").
- Teks panjang (> 200 karakter) di-collapse dengan tombol expand. Word-level diff masuk backlog v3.1.
- `null`, boolean, dan string kosong ditampilkan dengan gaya berbeda (`null` miring abu-abu, `""` → "(kosong)"), supaya tidak ambigu.
- Nilai redacted → badge 🔒 `REDACTED`, tidak pernah dibandingkan.

**JSON tree viewer** (properties, input, arguments)
- Tree yang bisa collapse/expand, dengan syntax highlight berbasis token tema, pencarian key/value, "Expand all/Collapse all", dan tombol copy (pretty JSON).
- Rendering sisi server (Blade rekursif) + Alpine untuk toggle, sehingga tetap terbaca tanpa JavaScript.

**Timeline**
- Dipakai untuk correlation (request → job → job lanjutan) dan riwayat model.
- Garis vertikal dengan ikon per tipe/event, waktu relatif, durasi, dan status.
- Item aktif (entry yang sedang dibuka) ditandai.

**Chart**
- Bar chart & sparkline berupa **SVG yang dirender server-side oleh Blade**: nol dependency JS, warna mengikuti `currentColor`/CSS variable, dan dilengkapi `<title>` + tabel data tersembunyi (`sr-only`) untuk screen reader.
- Tooltip per bar via Alpine.

**Empty, loading, dan error state**
- **Empty state** selalu memberi langkah berikutnya. Contoh di Changes: *"Belum ada perubahan model. Tambahkan trait `Auditable` ke model yang ingin diaudit →"*, dengan snippet kode + tombol copy + link dokumentasi.
- **Setup checklist** di Overview, tampil hanya jika ada yang belum beres dan bisa ditutup. Isinya:
  - ✓ Migration sudah dijalankan
  - ✓ Middleware aktif (ada entry dalam 24 jam terakhir)
  - ○ Belum ada model dengan trait `Auditable`
  - ○ Gate `viewAuditor` belum didefinisikan (dashboard hanya terbuka di local)
  - ○ Integrity aktif tapi `auditor:seal` belum pernah berjalan
  - ○ Queue diaktifkan tapi ada job `PersistEntry` yang gagal
- **Skeleton** untuk drawer & live update (tidak ada spinner layar penuh).
- **Error** tampil sebagai toast/inline alert dengan pesan manusiawi + tombol "Coba lagi". Halaman khusus untuk driver ≠ database dan tabel belum di-migrate (lihat M6).

### 10.8 Halaman

**Overview**
```
Overview                                                         [ 24h ▾ ]
┌────────────────┐┌────────────────┐┌────────────────┐┌────────────────┐
│ Entries        ││ Model changes  ││ Denied         ││ Failed         │
│ 12.408   ▲ 8%  ││ 342      ▼ 3%  ││ 17  ⚠          ││ 4  ✕           │
│ ▁▂▃▅▇▆▅▃▂▁▂▃▅ ││ ▁▁▂▁▃▂▅▂▁▁▂▁▁ ││ ▁▁▁▁▂▁▁▅▁▁▁▁▁ ││ ▁▁▁▁▁▁▁▂▁▁▁▁▁ │
└────────────────┘└────────────────┘└────────────────┘└────────────────┘
Activity  ■ http ■ job ■ command
▁▁▂▃▅▆▇▇▆▅▅▆▇▇▆▅▃▂▂▁▁▁▂▃   (bar chart bertumpuk per jam)

┌ Recent entries ─────────────────────┐ ┌ Recent denied abilities ───────┐
│ POST orders.store   #5 Budi  201 2m │ │ delete-post   #9 Sari   3m     │
│ JOB  SendInvoice    #5 Budi  ✓   2m │ │ view-report   #12 Andi  15m    │
│ …                        View all → │ │ …                   View all → │
└─────────────────────────────────────┘ └────────────────────────────────┘
┌ Most active users ──────────────────┐ ┌ Most changed models ───────────┐
└─────────────────────────────────────┘ └────────────────────────────────┘
```
Setiap kartu statistik dan item list bisa diklik dan membawa ke halaman list dengan filter yang sesuai sudah terpasang.

**Entries (list)**
```
Entries                                             [⤓ Export CSV]  [☰ Kolom]
[ Hari ini ] [ Denied ] [ Failed ] [ Punya changes ] [ Oleh saya ]  ⭐ Saved views ▾
┌───────────────────────────────────────────────────────────────────────────┐
│ 🔍 Filter…   Type: http ✕   Status: 5xx ✕   Last 24h ✕          Reset     │
└───────────────────────────────────────────────────────────────────────────┘
          ↑ 3 entry baru — tampilkan
  TYPE  NAME / URL                        USER        STATUS  DURASI  WAKTU
  HTTP  POST orders.store  /orders         ● Budi #5    201    142ms   2m
  JOB   App\Jobs\SendInvoice               ● Budi #5    ✓      1.2s    2m
  HTTP  DELETE posts.destroy /posts/12     ● Sari #9    403 ⚠  38ms    3m
  CMD   tinker                             🖥 deploy     0      4m 12s  1h
                                                      ‹ Newer   Older ›
```
Ikon kecil di kolom nama: ⇄ (punya changes), ⚠ (ada denied), ✉ (kirim mail), 🔔 (notifikasi).

**Entry detail**
```
← Entries
POST  /orders                                              201 · 142 ms
orders.store · App\Http\Controllers\OrderController@store
● Budi (#5, web)  ·  29 Sep 2026 10:42:13 WIB (2 menit lalu)  ·  203.0.113.x
Correlation 01J9Z…K2 ⧉   ·   ULID 01J9Z…9A ⧉   ·   Tags: checkout, payment

[ Ringkasan ] [ Changes 3 ] [ Abilities 4 ] [ Models 12 ] [ Mail & Notif 2 ] [ Properties ] [ Timeline 2 ]
────────────────────────────────────────────────────────────────────────────
Changes
  ⊕ created  App\Models\Order #88                                   ▾
  ✎ updated  App\Models\Product #7
       stock     10  →  9
       updated…  (1 atribut disembunyikan)
  ✎ updated  App\Models\User #5
       password  🔒 REDACTED
```
- Tab disimpan di URL hash (`#changes`), sehingga link bisa langsung menuju tab tertentu.
- Tab kosong ditampilkan redup dengan angka 0, bukan disembunyikan, agar posisi tab konsisten.
- Tab Timeline menampilkan request induk dan semua job turunan dengan correlation ID yang sama.

**Changes (list):** kolom Event, Model, Key, Atribut yang berubah (maksimal 3 nama + "+2"), User, Waktu. Klik → drawer diff. Link "Riwayat model ↗".

**Model history:** header berisi nama model + key + (jika record masih ada) atribut `name`/`title`. Di bawahnya timeline vertikal semua perubahan dengan diff inline dan filter event.

**Integrity**
```
┌───────────────────────────────────────────────────────────────┐
│  ✓  Chain valid                                                │
│     Diverifikasi 29 Sep 2026 02:00 · 1.204.332 entries ·       │
│     88.120 model changes                                       │
└───────────────────────────────────────────────────────────────┘
Belum di-seal: 42 entries · 3 changes        Seal terakhir: 12 detik lalu
Checkpoint terakhir: #1.100.000 (prune 28 Sep 2026)
```
Jika gagal: banner merah berisi id row pertama yang rusak + panduan langkah investigasi. Jika integrity nonaktif: penjelasan manfaatnya + cara mengaktifkan (`auditor:install --integrity`).

### 10.9 Aksesibilitas (WCAG 2.2 AA)
- Landmark semantik (`<nav>`, `<main>`, `<header>`), link "Skip to content", dan heading berurutan.
- Focus ring terlihat jelas di kedua tema (`outline` accent 2px + offset).
- Drawer/modal/palette: focus trap (Alpine Focus plugin), `Esc` untuk menutup, fokus kembali ke pemicu, `aria-modal`.
- Tabel memakai `<th scope>`. Baris yang bisa diklik tetap memakai link asli di kolom utama (bukan hanya `onclick`).
- Status tidak hanya ditunjukkan lewat warna: selalu ada ikon + teks (misalnya "403 Denied").
- `prefers-reduced-motion`: animasi drawer/toast dimatikan.
- Semua ikon dekoratif memakai `aria-hidden`, dan tombol ikon punya `aria-label`.
- Diuji otomatis dengan axe (12.6) + manual dengan VoiceOver/NVDA sebelum RC.

### 10.10 Performa frontend
| Budget | Target |
|---|---|
| CSS (gzip) | ≤ 25 KB |
| JS (gzip) | ≤ 25 KB (Alpine CSP ± 15 KB + komponen) |
| Font eksternal / CDN | 0 request |
| Largest Contentful Paint (halaman Entries, 25 baris, lokal) | < 1 detik |

- Server-rendered: halaman sudah lengkap dan terbaca tanpa JS. JS hanya menambah interaktivitas (progressive enhancement).
- Script di-load dengan `defer`; aset memakai nama berhash dan cache `immutable` (ADR-09).
- Budget dicek di CI (`assets.yml`): build gagal jika ukuran melebihi batas.

### 10.11 Lokalisasi
- Semua teks UI lewat `__('auditor::…')`, dengan bahasa Inggris & Indonesia bawaan. Bahasa mengikuti `app()->getLocale()`.
- Format angka & tanggal memakai `Intl` di browser (`Intl.NumberFormat`, `Intl.RelativeTimeFormat`) sesuai locale.
- String dirancang siap diterjemahkan (tidak ada teks yang disusun dari potongan kalimat).

### 10.12 Kustomisasi oleh pengguna
- **Tanpa publish view:** `dashboard.theme`, `dashboard.accent`, `dashboard.brand.name`, `dashboard.brand.logo`.
- **Publish view** (`auditor-views`) hanya untuk kebutuhan mendalam. Dokumentasi memberi peringatan bahwa view yang di-publish harus di-merge manual saat upgrade.
- **Hook tampilan user:** `Auditor::displayUserUsing(fn ($user) => ['name' => $user->name, 'avatar' => $user->avatar_url])` untuk chip user.

### 10.13 Proses desain & validasi
1. **Referensi & moodboard:** Laravel Pulse/Horizon/Telescope, Linear, Vercel dashboard, Sentry (issue detail), GitHub (diff).
2. **Wireframe low-fi:** wireframe di 10.5 & 10.8 → validasi alur dengan 2–3 developer.
3. **Design tokens + styleguide:** halaman `/auditor/_styleguide`, **hanya aktif di workbench**, yang menampilkan semua komponen di kedua tema. Halaman ini juga menjadi basis visual regression test (12.6).
4. **Implementasi halaman** (M5b).
5. **Uji usability** dengan 3–5 developer Laravel yang belum pernah memakai package. Tugasnya:
   - (a) Temukan siapa yang menghapus Post #12 dan kapan.
   - (b) Temukan semua ability yang ditolak untuk user #9 hari ini.
   - (c) Dari sebuah job `SendInvoice`, temukan request yang memicunya.

   Target: setiap tugas selesai < 60 detik tanpa bantuan. Hasil & perbaikannya dicatat di PR.
6. **Aset marketing:** screenshot light & dark + GIF 20 detik (filter → peek → diff → toggle tema) untuk README dan artikel peluncuran.

---

## 11. Milestone Implementasi

Ukuran: **S** ≈ 1–2 hari, **M** ≈ 3–5 hari, **L** ≈ 1–2 minggu (estimasi kasar untuk 1 developer).
Setiap milestone = 1 PR ke `main` (atau beberapa PR kecil). CI harus hijau sebelum merge.

### M0 — Persiapan repo & tooling (S)

**Tugas**
- [ ] Buat branch `2.x` dari `main` saat ini (untuk security fix v2). Tambahkan catatan deprecation di README branch tersebut.
- [ ] Di `main`, hapus seluruh isi `src/`, `dist/`, dan `composer.lock`. Mulai dari struktur di bagian 6 (acuan: `spatie/package-skeleton-laravel`).
- [ ] Tulis `composer.json` baru:
  ```json
  {
      "name": "rembon/laravel-auditor",
      "description": "Request-level audit trail for Laravel: who did what, what changed, and what they were allowed to do.",
      "keywords": ["laravel", "audit", "audit-trail", "auditing", "activity-log", "compliance", "security"],
      "license": "MIT",
      "require": {
          "php": "^8.3",
          "illuminate/contracts": "^12.0|^13.0",
          "illuminate/database": "^12.0|^13.0",
          "illuminate/support": "^12.0|^13.0",
          "spatie/laravel-package-tools": "^1.16"
      },
      "require-dev": {
          "larastan/larastan": "^3.0",
          "laravel/pint": "^1.18",
          "orchestra/testbench": "^10.0|^11.0",
          "pestphp/pest": "^4.0",
          "pestphp/pest-plugin-laravel": "^4.0",
          "pestphp/pest-plugin-arch": "^4.0",
          "pestphp/pest-plugin-browser": "^4.0",
          "rector/rector": "^2.0",
          "driftingly/rector-laravel": "^2.0"
      },
      "autoload": { "psr-4": { "Rembon\\LaravelAuditor\\": "src/" } },
      "autoload-dev": {
          "psr-4": {
              "Rembon\\LaravelAuditor\\Tests\\": "tests/",
              "Workbench\\App\\": "workbench/app/"
          }
      },
      "scripts": {
          "test": "pest --parallel",
          "test:coverage": "pest --coverage --min=90",
          "test:mutate": "pest --mutate --min=80",
          "test:browser": "pest --group=browser",
          "analyse": "phpstan analyse --memory-limit=1G",
          "format": "pint",
          "refactor": "rector",
          "serve": ["@php vendor/bin/testbench workbench:build --ansi", "@php vendor/bin/testbench serve"]
      },
      "extra": {
          "laravel": {
              "providers": ["Rembon\\LaravelAuditor\\AuditorServiceProvider"],
              "aliases": { "Auditor": "Rembon\\LaravelAuditor\\Facades\\Auditor" }
          }
      },
      "suggest": {
          "rembon/laravel-auditor-filament": "Filament panel for browsing audit entries",
          "rembon/laravel-auditor-pulse": "Laravel Pulse cards for audit activity"
      },
      "config": { "sort-packages": true, "allow-plugins": { "pestphp/pest-plugin": true } },
      "prefer-stable": true
  }
  ```
  *(Versi pada `require-dev` perlu diverifikasi ulang saat mengerjakan milestone ini.)*
- [ ] Tambahkan `.gitattributes` dengan `export-ignore` untuk `tests/`, `workbench/`, `.github/`, `docs/`, `resources/css`, `resources/js`, `package.json`, `vite.config.js`, serta file config tool.
- [ ] Siapkan config tooling:
  - `phpstan.neon.dist`: level 8 dulu, target max di M7.
  - `pint.json`: preset `laravel`.
  - `rector.php`: set PHP 8.3 + Laravel.
  - `phpunit.xml.dist`: source `src/`.
  - `testbench.yaml`: provider & workbench.
- [ ] Buat `tests/TestCase.php` (Testbench, `RefreshDatabase`, load migration stub) dan `tests/Pest.php`.
- [ ] Buat `tests/ArchTest.php` (lihat 12.4).
- [ ] Siapkan GitHub Actions: tests matrix, static analysis, code style (bagian 13).
- [ ] Buat `SECURITY.md` (email pelaporan kerentanan), `CONTRIBUTING.md`, issue & PR template.

**Acceptance criteria:** `composer test`, `composer analyse`, dan `vendor/bin/pint --test` lulus di CI (dengan test arch + test smoke "provider boots").

---

### M1 — Core: Recorder, context HTTP, storage, redaction (L)

**Tugas**
- [ ] `Enums/EntryType`, `Enums/ChangeEvent`.
- [ ] `Data/*`: readonly class berisi skalar/array saja, dengan `toArray()` dan `fromArray()` untuk kebutuhan serialisasi queue.
- [ ] `Support/Redactor`:
  - Redaction rekursif berdasarkan pola key.
  - Redaction query string di URL.
  - Redaction atribut model (`$hidden`, cast encrypted, `$auditExclude`).
  - Hook custom `redactUsing`.
- [ ] `Support/CorrelationId`: membuat atau membaca ID, lalu menyimpannya ke `Context::addHidden`.
- [ ] `Support/UserResolver`: multi-guard, mengembalikan `[morphClass, (string) key, guard]`, dan menghormati `resolveUserUsing`.
- [ ] `Support/IpAnonymizer`: IPv4 menjadi `/24`, IPv6 menjadi `/48`.
- [ ] `Recorder` (scoped):
  - `start(EntryType, name, attrs)`
  - `recordModelAccess()`, `recordModelChange()`, `recordAbility()`, `recordMail()`, `recordNotification()`
  - `withProperty()`, `tag()`, `ignore()`, `withoutAuditing()`, `finish(attrs)`
  - Buffer flush sesuai `buffer_size`.
  - Semua method `record*` menjadi no-op jika `enabled=false` atau tidak ada context aktif. Pengecualian: model change tanpa context dibuatkan entry `other` yang langsung di-flush.
- [ ] `Auditor` + Facade: config statis (auth, filter, resolver, redactUsing), dengan delegasi ke `Recorder`.
- [ ] `Storage/StorageManager` dengan driver `database`, `log`, dan `null`. `DatabaseStorage` menyimpan entry beserta changes dalam satu transaksi, lalu meng-update counter denormalisasi.
- [ ] `Jobs/PersistEntry` untuk mode queue. Payload hanya berisi DTO array, tanpa model Eloquent.
- [ ] `Http/Middleware/RecordRequest` (terminable):
  - `handle()`: cek `except`/`except_methods`, `start(Http)`, set correlation ID & header response, catat user awal.
  - `terminate()`: status code, durasi (`hrtime`), user akhir (fallback ke user awal, supaya logout tetap tercatat user-nya), route name/action, URL (sudah di-redact), IP, UA, input opsional, keputusan sampling, lalu `finish()`.
- [ ] Pendaftaran middleware otomatis ke `http.middleware_groups` lewat `Router::pushMiddlewareToGroup`.
- [ ] Wrapper safety (ADR-05) di satu tempat: `Auditor::guard(callable)`.
- [ ] Migration stub (bagian 7) dengan `publishesMigrations`. Migration menghormati `connection`, `tables`, dan `morph_key_type`.
- [ ] Model `Entry` dan `ModelChange` beserta casts, scopes (`causedBy`, `since`, `forCorrelation`, `withDeniedAbilities`), dan `getConnectionName()`/`getTable()` dari config.

**Acceptance criteria**
- Request ke route `web` menghasilkan tepat 1 entry dengan user, route, status, durasi, dan correlation ID yang benar.
- Header `X-Request-Id` ada di response.
- Request ke `/auditor/*`, `/up`, dan method `OPTIONS` tidak menghasilkan entry.
- Exception di storage tidak membuat request gagal (kecuali `throw_exceptions=true`).
- Mode queue men-dispatch `PersistEntry` dan hasil akhirnya identik dengan mode sync.

---

### M2 — Model auditing (M)

**Tugas**
- [ ] `Traits/Auditable`:
  - `bootAuditable()` → `static::observe(AuditableObserver::class)`.
  - Relasi `audits(): MorphMany`.
  - Helper `getAuditExclude()`, `getAuditEvents()`, `shouldAuditRetrieved()`.
- [ ] `Observers/AuditableObserver`:
  - `retrieved`: catat `class => ids` dengan batas `max_ids_per_model`, dan tetap menghitung `count` total.
  - `created`: `new_values` = atribut raw yang sudah di-redact.
  - `updated`: `new_values` = `getChanges()` minus exclude, `old_values` = `getRawOriginal()` untuk key yang sama. **Event diabaikan** jika setelah exclude tidak ada yang tersisa (misalnya `touch()`).
  - `deleted`: `old_values` = semua atribut. Untuk soft delete dicatat sebagai `deleted` juga, dengan kolom `deleted_at` di `new_values`.
  - `restored`, `forceDeleted`.
- [ ] Nilai JSON column disimpan dalam bentuk decoded. Tanggal disimpan dalam format `Y-m-d H:i:s` sesuai storage. Cast encrypted **selalu** di-redact jika `redact_encrypted_casts=true`; hal ini perlu diuji karena `getOriginal()` akan men-decrypt nilainya.
- [ ] `Auditor::withoutAuditing()` menonaktifkan observer untuk sementara, dengan state di `Recorder` (aman dipakai bersarang dan tetap reset meski terjadi exception, karena memakai `try/finally`).
- [ ] Dokumentasikan batasan: mass update/delete lewat query builder, `insert()`, dan `attach/detach` pivot tanpa custom pivot model **tidak** memicu event.

**Acceptance criteria**
- CRUD pada model ber-trait menghasilkan baris `auditor_model_changes` dengan diff yang benar.
- Password, `remember_token`, dan kolom encrypted tidak pernah muncul dalam bentuk plaintext.
- Model dengan key integer, UUID, dan ULID berfungsi di semua DB CI, termasuk `$post->audits` di PostgreSQL.
- Me-load 10.000 model hanya menyimpan maksimal 50 id per class di `models_accessed`, dengan `count` = 10.000.

---

### M3 — Listeners, jobs, commands, correlation (M)

**Tugas**
- [ ] `RecordAbilityCheck` (event `GateEvaluated`): menyimpan ability, result, dan argumen dalam bentuk ringkas (model → `{type, id}`, class string → string, skalar → nilai yang sudah di-redact).
- [ ] `RecordMail` (`MessageSent`):
  - Menyimpan class mailable (jika tersedia di data event), subject, to/cc/bcc (atau hash-nya bila `hash_recipients`).
  - **Body tidak pernah disimpan.**
- [ ] `RecordNotification` (`NotificationSent`): class, channel, notifiable type/id.
- [ ] `JobLifecycle`:
  - `JobProcessing` → `start(Job, displayName)`. Correlation ID dan causer diambil dari Context yang diwarisi dari request.
  - `JobProcessed` / `JobFailed` → `finish(failed, duration)`.
  - Menghormati `jobs.except`. Job `PersistEntry` tidak ikut diaudit.
- [ ] `CommandLifecycle`: `CommandStarting` → `start(Command, name)` + `os_user` + hostname; `CommandFinished` → exit code. Menghormati `console.except` (glob).
- [ ] Semua listener didaftarkan di provider, masing-masing bisa dimatikan lewat `listeners.*`, `jobs.enabled`, dan `console.enabled`.
- [ ] Sinkron (`QUEUE_CONNECTION=sync`): job yang di-dispatch di dalam request dianggap sub-context. Tentukan perilakunya: ikut entry request, **atau** buat entry sendiri dengan correlation yang sama. **Rekomendasi:** entry sendiri, supaya perilakunya sama dengan queue async. Implementasinya memakai stack context di `Recorder`.

**Acceptance criteria**
- Request yang men-dispatch job menghasilkan 2 entry dengan `correlation_id` sama. Entry job mencatat user yang men-dispatch sebagai causer.
- `Gate::denies('x')` tercatat dengan `result=false`, dan `denied_abilities_count` bertambah.
- Mengirim mail tidak menyimpan body dan tidak menyimpan token dari URL reset password.
- `php artisan tinker`, lalu `$post->update()`, menghasilkan entry `command` berisi `os_user` dan change yang benar. Command `queue:work` tidak membuat entry.

---

### M4 — Integrity, pruning, retensi (M)

**Tugas**
- [ ] `Support/CanonicalJson`: ksort rekursif, `JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR`, dan timestamp dalam format tetap (UTC, mikrodetik).
- [ ] `Integrity/Sealer`:
  - Mengambil row `hash IS NULL` (dan `completed_at IS NOT NULL` untuk entries) urut `id`, per chunk.
  - Bekerja dalam satu transaksi per chunk.
  - `previous_hash` = hash row sebelumnya, atau `last_hash` checkpoint, atau `str_repeat('0', 64)`.
- [ ] `Integrity/Verifier`: menghitung ulang chain dan melaporkan id pertama yang rusak, beserta row yang hilang (gap id setelah checkpoint).
- [ ] `SealCommand`, `VerifyCommand` (output tabel + exit code; memicu `IntegrityViolationDetected`).
- [ ] `PruneCommand`:
  - Menggunakan `MassPrunable` pada `Entry` & `ModelChange`.
  - Menulis `Checkpoint` sebelum menghapus.
  - Jika integrity aktif, hanya menghapus row yang sudah di-seal.
  - Opsi `--days`.
- [ ] Validasi di `boot()`: `integrity.enabled=true` tanpa `key` → peringatan jelas saat command integrity dijalankan (jangan memicu exception saat boot aplikasi).

**Acceptance criteria**
- Mengubah 1 kolom di row lama lewat SQL mentah → `auditor:verify` gagal dan menunjuk id yang tepat.
- Menghapus row di tengah → terdeteksi.
- Setelah prune, verify tetap lulus.
- Seal yang dijalankan paralel (2 proses) tidak menghasilkan chain bercabang, karena memakai `withoutOverlapping` plus lock DB/cache di `Sealer`.

---

### M5a — Design system & app shell (M)

Detail desain mengacu ke bagian 10.

**Tugas**
- [ ] Setup build frontend: `package.json`, Vite, Tailwind v4, `@alpinejs/csp` + plugin `focus`, `collapse`, dan `persist`. Output ke `dist/` + `manifest.json`.
- [ ] `tokens.css`: semua token light/dark (10.3), `color-scheme`, `@custom-variant dark`, dukungan override `--accent`.
- [ ] `theme-init.js` di-inline di `<head>` dengan nonce; komponen `theme` (Light/Dark/System + listener `matchMedia`).
- [ ] `AssetController` + helper `Auditor::asset()` / `Auditor::nonce()` + `Auditor::css()`/`js()` untuk layout.
- [ ] Komponen Blade dasar: `button`, `badge`, `card`, `stat-card`, `table`, `empty-state`, `skeleton`, `tabs`, `drawer`, `dropdown`, `tooltip`, `kbd`, `copy`, `relative-time`, `icon` (+ `icons.php` subset Lucide), `method-badge`, `status-badge`, `event-badge`, `user-chip`, `env-badge`.
- [ ] Layout shell: sidebar (collapsible, drawer di mobile), topbar (breadcrumb, search, live toggle, theme toggle, env badge), toast region (`aria-live`), skip link.
- [ ] Command palette (⌘K) + modal shortcut `?` + handler shortcut global (10.6).
- [ ] Density toggle (Comfortable/Compact).
- [ ] i18n `lang/en` & `lang/id` untuk semua string shell.
- [ ] Halaman `/_styleguide` (hanya di workbench) yang menampilkan semua komponen di kedua tema.

**Acceptance criteria**
- Toggle tema berfungsi di ketiga mode. Reload halaman dalam mode Dark tidak menampilkan kilatan putih (diverifikasi browser test).
- Mode System mengikuti perubahan tema OS tanpa reload.
- Styleguide lulus axe (0 pelanggaran serious/critical) di light & dark.
- Dashboard berjalan di bawah CSP `script-src 'self' 'nonce-…'` tanpa `unsafe-eval`/`unsafe-inline` (tanpa error di console).
- Ukuran aset sesuai budget 10.10.

---

### M5b — Halaman dashboard (L)

**Tugas**
- [ ] `Http/Middleware/Authorize`: jika Gate `viewAuditor` terdefinisi, pakai gate itu; selain itu hanya izinkan `app()->isLocal()`. `Auditor::auth()` meng-override keduanya. Jika ditolak → 403 (halaman 403 bergaya dashboard, dengan petunjuk cara mendefinisikan gate hanya saat `local`).
- [ ] Route (prefix/domain/middleware dari config, name prefix `auditor.`):
  | Method | URI | Controller |
  |---|---|---|
  | GET | `/` | `OverviewController` |
  | GET | `/entries` | `EntryController@index` |
  | GET | `/entries/{ulid}` | `EntryController@show` |
  | GET | `/entries/{ulid}/peek` | `EntryController@peek` (partial HTML untuk drawer) |
  | GET | `/changes` | `ChangeController@index` |
  | GET | `/changes/{ulid}/peek` | `ChangeController@peek` |
  | GET | `/models/{type}/{id}` | `ModelHistoryController` (`type` = morph alias atau class ter-encode base64url) |
  | GET | `/integrity` | `IntegrityController` (status seal & hasil verify terakhir yang di-cache) |
  | GET | `/search` | `SearchController` (JSON untuk command palette; resolusi pola ULID, `Post#12`, `user:5`, `route:x`) |
  | GET | `/poll/{entries\|overview}` | `PollController` (jumlah & partial baris baru sejak cursor tertentu) |
  | GET | `/export/{entries\|changes}` | `ExportController` (CSV streamed) |
  | GET | `/assets/{file}` | `AssetController` (cache-busting) |
- [ ] Halaman sesuai wireframe 10.8:
  - **Overview:** stat card + sparkline + delta, activity chart SVG bertumpuk, recent entries, recent denied abilities, most active users, most changed models, dan setup checklist (10.7). Semua angka memakai `whereDate`/range `created_at`, dan setiap angka bisa diklik menuju list terfilter.
  - **Entries:** filter bar + chip + preset + saved views, query string sebagai state, cursor pagination, kolom yang bisa disembunyikan, ikon indikator (⇄ ⚠ ✉ 🔔), quick peek drawer, navigasi `j`/`k`.
  - **Entry detail:** header metadata + tombol copy, tab berisi hitungan (URL hash), diff viewer, tabel abilities, models accessed, mail & notifikasi, JSON tree untuk properties/input, timeline correlation.
  - **Changes:** list + drawer diff + link riwayat model.
  - **Model history:** timeline diff per record + filter event.
  - **Integrity:** banner status, statistik, checkpoint, panduan saat gagal atau nonaktif.
- [ ] Komponen lanjutan: `diff` (inline/side-by-side, JSON rekursif, redacted badge), `json-tree`, `timeline`, `bar-chart`, `sparkline`, `filter-bar`, `filter-chip`, `pagination`, `setup-checklist`.
- [ ] Live mode: polling via `PollController` dengan banner "N entry baru", jeda saat tab tersembunyi, dan `aria-live`.
- [ ] Tampilan waktu relatif + tooltip absolut + toggle zona waktu.
- [ ] Mobile: tabel → kartu, filter → bottom sheet.
- [ ] Export CSV: `response()->streamDownload` + `lazy()`. **Lindungi dari CSV/formula injection**: prefix `'` untuk sel yang diawali `= + - @ \t \r`. Export mengikuti filter aktif.
- [ ] Semua data audit dirender dengan `{{ }}`. Larang `{!! !!}` untuk data audit lewat arch test yang memeriksa view.
- [ ] Workbench: seeder data demo realistis (≥ 3 user, beberapa model, request sukses/gagal, job berantai, denied ability, mail, notifikasi, perubahan dengan atribut redacted) supaya `composer serve` langsung menampilkan dashboard terisi. Seeder ini juga dipakai untuk screenshot README & visual regression.
- [ ] Uji usability (10.13 langkah 5), lalu perbaiki temuan.

**Acceptance criteria**
- Guest di environment production → 403. User yang lolos gate → 200.
- Entry dengan URL `/<script>alert(1)</script>` ditampilkan dalam bentuk ter-escape (juga di drawer peek dan hasil search palette).
- Sel CSV yang diawali `=` ter-prefix.
- `/auditor/assets/../../.env` → 404.
- Halaman entries dengan 1 juta baris dimuat < 300 ms di MySQL CI (seed + index), memakai cursor pagination.
- Semua halaman lulus axe (0 pelanggaran serious/critical) di light & dark, desktop & mobile viewport.
- Visual regression baseline disetujui untuk semua halaman × 2 tema × 2 viewport.
- Tiga tugas usability selesai < 60 detik oleh ≥ 3 dari 4 penguji.

---

### M6 — Developer Experience (M)

**Tugas**
- [ ] `InstallCommand`:
  - Publish config & migration (tag `auditor-config`, `auditor-migrations`).
  - `--integrity` membuat key acak 32 byte (base64) dan menulisnya ke `.env` (+ `.env.example` tanpa nilai).
  - Konfirmasi untuk migrate.
  - Menampilkan snippet Gate & Schedule.
  - Memakai `Laravel\Prompts`.
  - Idempotent: aman dijalankan ulang, dengan `--force` untuk overwrite.
- [ ] Tag publish lain: `auditor-views`, `auditor-lang`.
- [ ] `Testing/AuditorFake` + `Auditor::fake()`: mengganti storage dengan in-memory, plus assertion di 9.4.
- [ ] `Auditor::filter()`, `Auditor::redactUsing()`, `Auditor::extend()`.
- [ ] Events `EntryRecorded` dan `ModelChangeRecorded`, di-dispatch setelah persist berhasil.
- [ ] `ImportV2Command`:
  - Membaca tabel `audits` v2 per chunk.
  - Pemetaan: `url`, `route` → `name`, `user_id` → `user_type`/`user_id` (dari config `user_model` lama atau opsi `--user-model`), `datetime` → `started_at`, `request_time` → `duration_ms`, `properties`, `notifications`.
  - `models` v2 diringkas menjadi `models_accessed` (class → ids).
  - `emails` v2 **tidak diimpor** karena berisi raw email.
  - Opsi `--drop-old` men-drop tabel `audits` dan `performances` setelah konfirmasi.
- [ ] Pesan error yang jelas untuk kesalahan konfigurasi yang umum, misalnya tabel belum di-migrate, atau driver ≠ database saat dashboard dibuka (tampilkan halaman penjelasan, bukan 500).

**Acceptance criteria**
- Di aplikasi Laravel 12 dan 13 yang baru, `composer require` + `auditor:install` + menambahkan trait sudah cukup untuk melihat entry di `/auditor` (lingkungan local).
- `Auditor::fake()` bisa dipakai di test aplikasi pengguna (didemokan di test package sendiri).

---

### M7 — Hardening (M)

**Tugas**
- [ ] Larastan dinaikkan ke level **max**, dengan generics (`@return MorphMany<ModelChange, $this>`) dan tanpa baseline.
- [ ] Mutation testing (`pest --mutate`) untuk `src/Support`, `src/Recorder.php`, `src/Observers`, `src/Integrity`, `src/Http/Middleware` dengan target ≥ 80%.
- [ ] Simulasi Octane: dua request berurutan di satu aplikasi dengan `app()->forgetScopedInstances()`; state tidak boleh bocor.
- [ ] Simulasi queue worker: dua job berurutan di satu proses; state tidak boleh bocor.
- [ ] Benchmark (grup Pest `benchmark`, tidak masuk run default, dijalankan di CI sebagai job non-blocking): overhead middleware + observer untuk request yang me-load 100 model, dibandingkan baseline tanpa package. Target median < 1 ms (tanpa persist).
- [ ] Uji memori: command dengan 10.000 update tetap stabil berkat `buffer_size`.
- [ ] Review keamanan internal (checklist OWASP untuk dashboard, redaction, header correlation dari input tak dipercaya: validasi pola `[A-Za-z0-9-_]{1,64}`).
- [ ] `composer audit` di CI.

**Acceptance criteria:** semua quality gate di bagian 13 hijau.

---

### M8 — Dokumentasi & rilis (M)

**Tugas**
- [ ] README baru (struktur di bagian 15).
- [ ] Situs dokumentasi (lihat 14.2).
- [ ] `UPGRADE.md` v2 → v3 (bagian 14).
- [ ] `CHANGELOG.md` (format Keep a Changelog).
- [ ] Rilis bertahap:
  | Tag | Syarat |
  |---|---|
  | `v3.0.0-alpha.1` | M1–M3 selesai |
  | `v3.0.0-beta.1` | M4–M6 (termasuk M5a & M5b) selesai, API dibekukan |
  | `v3.0.0-rc.1` | M7 selesai, dokumentasi lengkap, diuji di ≥ 2 aplikasi nyata |
  | `v3.0.0` | Tidak ada bug blocker selama ≥ 1 minggu di RC |
- [ ] GitHub Release dengan catatan rilis. Workflow otomatis meng-update `CHANGELOG.md`.
- [ ] Pastikan Packagist auto-update (webhook GitHub).

---

### M9 — Ekosistem (M, repo terpisah)

- [ ] **`rembon/laravel-auditor-filament`:** plugin panel berisi Resource Entries & Changes (read-only), relation manager `AuditsRelationManager` untuk resource model apa pun, dan widget stats. Menghormati gate `viewAuditor`.
- [ ] **`rembon/laravel-auditor-pulse`:** recorder yang mendengarkan `EntryRecorded`, dengan kartu "Denied abilities", "Top auditable changes", dan "Most active users".
- [ ] Keduanya memakai standar tooling & CI yang sama dengan core.

---

## 12. Strategi Testing

### 12.1 Setup
- **Framework:** Pest 4 + `orchestra/testbench`, dengan SQLite in-memory sebagai default. CI menjalankan juga MySQL, PostgreSQL, dan MariaDB.
- **`TestCase`:** memuat provider, menjalankan migration stub, dan men-set `auditor.throw_exceptions=true` supaya error tidak tertelan diam-diam.
- **Fixtures (`tests/Fixtures`):**
  - `User` (key integer), `UuidPost`, `UlidPost`, `Post` (soft deletes, kolom json), `Secret` (kolom `$hidden` + cast encrypted).
  - `PostPolicy`, `ProcessPost` (job), `PostPublished` (notification), `WelcomeMail` (mailable).

### 12.2 Struktur test

```
tests/
├── ArchTest.php
├── Pest.php
├── TestCase.php
├── Fixtures/...
├── Unit/
│   ├── RedactorTest.php            # pola wildcard, rekursif, URL query, hidden, encrypted, custom
│   ├── CorrelationIdTest.php       # generate, trust/untrust header, validasi format
│   ├── CanonicalJsonTest.php       # urutan key tidak memengaruhi hasil, unicode, float
│   ├── IpAnonymizerTest.php
│   ├── EntryDataTest.php           # roundtrip toArray/fromArray
│   └── UserResolverTest.php        # multi-guard, custom resolver
├── Feature/
│   ├── Http/
│   │   ├── RecordsRequestTest.php          # 1 entry, field lengkap
│   │   ├── ExceptPathsAndMethodsTest.php
│   │   ├── SamplingTest.php                # sample 0 tetap merekam jika ada change/denied
│   │   ├── CorrelationHeaderTest.php
│   │   ├── LoginLogoutUserTest.php         # user awal/akhir
│   │   ├── InputCaptureTest.php
│   │   └── FailureIsolationTest.php        # storage error tidak mematahkan response
│   ├── Models/
│   │   ├── RecordsChangesTest.php          # created/updated/deleted/restored/forceDeleted
│   │   ├── SkipsNoopUpdatesTest.php        # touch() / hanya updated_at
│   │   ├── RedactsSensitiveAttributesTest.php
│   │   ├── RetrievedTrackingTest.php       # batas ids + count
│   │   ├── KeyTypesTest.php                # int/uuid/ulid × relasi audits
│   │   ├── WithoutAuditingTest.php         # bersarang + exception
│   │   └── BufferFlushTest.php
│   ├── Listeners/
│   │   ├── GateTest.php
│   │   ├── MailTest.php                    # tanpa body, hash recipients
│   │   └── NotificationTest.php
│   ├── Jobs/
│   │   ├── JobLifecycleTest.php            # sukses/gagal, durasi
│   │   ├── CorrelationPropagationTest.php  # request → job, causer
│   │   └── QueuedPersistTest.php           # mode queue.enabled
│   ├── Console/
│   │   ├── CommandLifecycleTest.php        # os_user, except glob
│   │   ├── InstallCommandTest.php
│   │   ├── PruneCommandTest.php
│   │   ├── SealAndVerifyTest.php           # tamper, delete, checkpoint
│   │   └── ImportV2CommandTest.php
│   ├── Storage/
│   │   ├── DatabaseStorageTest.php         # connection & table custom
│   │   ├── LogStorageTest.php
│   │   └── CustomDriverTest.php
│   ├── Dashboard/
│   │   ├── AuthorizationTest.php           # local/prod, gate, auth callback
│   │   ├── OverviewTest.php
│   │   ├── EntriesTest.php                 # filter, pagination
│   │   ├── EntryDetailTest.php
│   │   ├── ChangesTest.php
│   │   ├── ModelHistoryTest.php
│   │   ├── ExportTest.php                  # CSV injection
│   │   ├── XssTest.php
│   │   └── AssetsTest.php                  # traversal, cache headers
│   ├── Runtime/
│   │   ├── OctaneStateResetTest.php
│   │   └── QueueWorkerStateResetTest.php
│   └── Testing/
│       └── AuditorFakeTest.php
├── Browser/                                # Pest 4 browser testing (Playwright), ->group('browser')
│   ├── ThemeTest.php                       # 3 mode, tanpa flash, System ikut OS, persist
│   ├── AccessibilityTest.php               # axe di semua halaman × light/dark × desktop/mobile
│   ├── VisualRegressionTest.php            # screenshot per halaman × tema × viewport
│   ├── CommandPaletteTest.php              # ⌘K, pencarian ULID/Post#12/user:5, Esc
│   ├── KeyboardNavigationTest.php          # g+e, j/k, Space (peek), ?, t
│   ├── FiltersTest.php                     # chip, preset, query string, back button, saved views
│   ├── LiveModeTest.php                    # banner entry baru, jeda saat tab tersembunyi
│   ├── MobileLayoutTest.php                # drawer sidebar, kartu, bottom sheet filter
│   └── CspTest.php                         # halaman di bawah CSP ketat, tanpa error console
└── Benchmark/
    └── OverheadTest.php                    # ->group('benchmark')
```

### 12.3 Target kualitas
| Metrik | Target | Enforcement |
|---|---|---|
| Line coverage | ≥ 90% | `pest --coverage --min=90` di CI (PCOV) |
| Mutation score (core) | ≥ 80% | `pest --mutate --min=80` di CI (job terpisah) |
| Larastan | level max, tanpa baseline | CI |
| Pint | lulus `--test` | CI |
| Rector | tidak ada perubahan (`--dry-run`) | CI |
| Aksesibilitas | 0 pelanggaran axe serious/critical, light & dark | Browser test di CI |
| Visual regression | Tidak ada diff yang belum disetujui | Browser test di CI |
| Ukuran aset | CSS ≤ 25 KB, JS ≤ 25 KB (gzip) | `assets.yml` |

### 12.4 Arch tests
```php
arch()->preset()->php();
arch()->preset()->security();

arch('tidak bergantung pada namespace aplikasi')
    ->expect('Rembon\LaravelAuditor')
    ->not->toUse(['App', 'App\Models\User', 'App\Http\Controllers\Controller']);

arch('tidak ada debug helper')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die'])
    ->not->toBeUsed();

arch('DTO bersifat readonly')
    ->expect('Rembon\LaravelAuditor\Data')
    ->toBeReadonly();

arch('strict types')
    ->expect('Rembon\LaravelAuditor')
    ->toUseStrictTypes();
```
Tambahan: test sederhana yang men-scan `resources/views/**/*.blade.php` dan gagal jika menemukan `{!!` di luar whitelist (misalnya ikon SVG statis).

### 12.5 Prinsip penulisan test
- Test perilaku lewat API publik (HTTP request, `save()`, event), bukan method private.
- Gunakan urutan red-green-refactor per bug v2. Setiap bug di bagian 4 punya minimal satu test yang dinamai sesuai ID-nya (misalnya `it('B1: records mail into mails, not abilities')`).
- Test yang bergantung pada DB diberi grup `->group('db')`, supaya matrix DB CI bisa menjalankan subset ini saja jika perlu.

### 12.6 Browser, aksesibilitas & visual regression
Memakai plugin browser Pest 4 (berbasis Playwright) dengan data seeder workbench. Contoh:

```php
it('tidak menampilkan flash putih saat reload dalam dark mode', function () {
    $page = visit('/auditor')->inDarkMode();

    $page->assertScript('document.documentElement.classList.contains("dark")', true)
        ->assertNoJavaScriptErrors();
});

it('lulus audit aksesibilitas', function (string $url) {
    visit($url)->assertNoAccessibilityIssues();
    visit($url)->inDarkMode()->assertNoAccessibilityIssues();
})->with(['/auditor', '/auditor/entries', '/auditor/changes', '/auditor/integrity']);

it('tampilan halaman entries tidak berubah tanpa disengaja', function () {
    visit('/auditor/entries')->assertScreenshotMatches();
    visit('/auditor/entries')->inDarkMode()->assertScreenshotMatches();
    visit('/auditor/entries')->on()->mobile()->assertScreenshotMatches();
});
```
*(Nama method di atas mengikuti API plugin browser Pest 4. Verifikasi ulang saat implementasi.)*

- Waktu di data seeder dibekukan (`Carbon::setTestNow`) dan animasi dimatikan, supaya screenshot deterministik.
- Baseline screenshot disimpan di repo. Perubahan baseline wajib di-review di PR (lampirkan before/after).
- Browser test tidak masuk `composer test` (lambat). Jalankan dengan `composer test:browser` dan di job CI terpisah.

---

## 13. CI/CD & Quality Gates

### `tests.yml`
```yaml
strategy:
  fail-fast: false
  matrix:
    php: [8.3, 8.4, 8.5]
    laravel: [12.*, 13.*]
    stability: [prefer-lowest, prefer-stable]
    include:
      - laravel: 12.*
        testbench: 10.*
      - laravel: 13.*
        testbench: 11.*
```
- Langkah: checkout → setup-php (ekstensi `pdo_sqlite`, `pcov` untuk coverage job) → `composer require "laravel/framework:${{ matrix.laravel }}" "orchestra/testbench:${{ matrix.testbench }}" --no-update` → `composer update --${{ matrix.stability }}` → `composer test`.
- Job **databases** (PHP 8.4 × Laravel 13): service container `mysql:8.4`, `postgres:17`, `mariadb:11`, dan jalankan `pest --group=db`.
- Job **browser** (PHP 8.4 × Laravel 13): `npm ci && npx playwright install --with-deps chromium`, lalu `pest --group=browser`. Artifact screenshot diff di-upload bila gagal.

### Workflow lainnya
| Workflow | Isi |
|---|---|
| `static-analysis.yml` | Larastan, Rector `--dry-run`, `composer validate --strict`, `composer audit` |
| `code-style.yml` | `pint --test` |
| `coverage.yml` | Coverage `--min=90` + upload ke Codecov (badge README); mutation testing |
| `release.yml` | Saat GitHub Release dipublikasikan, update `CHANGELOG.md` otomatis |
| `assets.yml` | `npm ci && npm run build`, gagal jika `dist/` berbeda dari yang di-commit atau ukuran melebihi budget 10.10 |
| `dependabot.yml` | Composer, npm, dan GitHub Actions, mingguan |

**Branch protection `main`:** wajib lulus semua workflow di atas + 1 review (atau self-review lewat PR bila solo).

---

## 14. Upgrade Path dari v2

Isi `UPGRADE.md`:

1. **Syarat:** PHP ≥ 8.3 dan Laravel 12/13.
2. Update constraint dengan `composer require rembon/laravel-auditor:^3.0`.
3. **Hapus** hal-hal berikut dari aplikasi:
   - Pendaftaran provider manual di `config/app.php` (auto-discovery sudah cukup).
   - Listener `AuthorizeMail` dan `AuthorizeNotification` di `EventServiceProvider`.
   - `config/laravel-auditor.php`.
   - `public/vendor/laravel-auditor`.
   - `resources/views/vendor/auditor`.
4. Jalankan `php artisan auditor:install`.
5. Definisikan Gate `viewAuditor`. **Tanpa gate ini, dashboard tertutup di production.**
6. (Opsional) Jalankan `php artisan auditor:import-v2 --user-model="App\Models\User"`, lalu `--drop-old`.
7. Trait `Rembon\LaravelAuditor\Traits\Auditable` **tidak berubah namespace**. Perubahan perilakunya: sekarang juga merekam diff perubahan. Untuk mematikan pencatatan retrieved per model, set `$auditRetrieved = false`.
8. Pemetaan konfigurasi:
   | v2 | v3 |
   |---|---|
   | `AUDITOR_ENABLE_PERFORMANCE` | Dihapus (gunakan Laravel Pulse) |
   | `AUDITOR_ENABLE_VIEWS` | `AUDITOR_DASHBOARD` |
   | `user_model`, `user_owner_key` | Dihapus; user di-resolve lewat guard dan disimpan polimorfik |
9. Perubahan API: interface `Contracts\Auditor` diganti oleh Facade `Auditor`:
   | v2 | v3 |
   |---|---|
   | `addProperty()` | `withProperty()` |
   | `addUser()`, `onRoute()`, `onUrl()`, `finish()` | Internal, tidak publik lagi |

---

## 15. Dokumentasi & Peluncuran

### 14.1 Struktur README
1. Logo, tagline, badges (tests, coverage, Packagist version, downloads, PHP/Laravel versions).
2. Screenshot dashboard **light & dark** berdampingan (`<picture>` + `prefers-color-scheme`, supaya README GitHub menampilkan versi yang sesuai tema pembaca) + GIF 20 detik (10.13).
3. "Why Laravel Auditor?" + tabel perbandingan (bagian 2).
4. Instalasi (3 perintah).
5. Quick start: trait, gate, lihat `/auditor`.
6. Link ke dokumentasi lengkap.
7. Testing, Changelog, Contributing, Security, Credits, License.

### 14.2 Situs dokumentasi
Pilih salah satu: **VitePress** di GitHub Pages (gratis, versi dokumentasi ikut repo), atau GitBook. Isi minimal:
- Getting started: instalasi, konfigurasi, upgrade dari v2.
- Konsep: entry, model change, correlation, sampling.
- Model auditing: include/exclude, events, retrieved, batasan.
- Redaction & privasi (GDPR): anonymize IP, hash recipients, retensi.
- Jobs & commands.
- Dashboard & otorisasi.
- Integrity (hash chain): cara kerja, penyimpanan key, verifikasi, dan apa yang **tidak** dijamin (misalnya attacker yang punya akses ke key dan DB sekaligus).
- Storage drivers & driver custom.
- Queue & performa: panduan untuk trafik tinggi (queue, sampling, koneksi DB terpisah, partisi).
- Testing dengan `Auditor::fake()`.
- Integrasi Filament & Pulse.
- API reference: facade, events, commands, config.

### 14.3 Peluncuran
- [ ] Tulis artikel "Introducing Laravel Auditor v3", lalu kirim ke Laravel News (form submit).
- [ ] Posting ke X/Twitter, Reddit r/laravel, Laravel.io, grup Laravel Indonesia (Telegram/Facebook), dan Dev.to/Medium.
- [ ] Buat video demo singkat (2–3 menit).
- [ ] Buka 5–10 issue berlabel `good first issue` (misalnya terjemahan bahasa lain, driver storage baru, kartu Pulse tambahan).
- [ ] Aktifkan GitHub Discussions.
- [ ] Rencanakan roadmap v3.1 yang publik (GitHub Projects).

---

## 16. Risiko & Mitigasi

| # | Risiko | Dampak | Mitigasi |
|---|---|---|---|
| R1 | Overhead `retrieved` pada query besar | Request melambat | Batas `max_ids_per_model`, `$auditRetrieved=false`, `models.track_retrieved`, dan benchmark di CI |
| R2 | Volume data tabel entries sangat besar | DB penuh, query dashboard lambat | Sampling (kecuali change/denied), prune default 90 hari, koneksi DB terpisah, cursor pagination, index terencana |
| R3 | Morph key string vs integer di PostgreSQL | Relasi `audits` error | Opsi `morph_key_type` + test `KeyTypesTest` di PostgreSQL CI |
| R4 | Hash chain bercabang karena seal berjalan paralel | Verify palsu gagal | `withoutOverlapping` + lock di `Sealer` + test paralel |
| R5 | Data sensitif lolos redaction | Kebocoran data | Default konservatif, redaction `$hidden` & encrypted, test per kasus, dokumentasi privasi |
| R6 | State bocor di Octane/queue | Data audit tertukar antar user | `scoped` binding + test runtime reset |
| R7 | Command berjalan lama menumpuk memori | OOM | `buffer_size` + `console.except` untuk worker |
| R8 | Laravel merilis versi mayor baru | Package tertinggal | Dependabot, CI `prefer-stable` mingguan (cron), rilis minor untuk dukungan versi baru |
| R9 | Scope v3 sangat besar untuk 1 developer | Rilis molor | Rilis alpha/beta bertahap; M9 boleh menyusul setelah 3.0.0 |
| R10 | Mail/notification class tidak tersedia di data event pada versi tertentu | Metadata kosong | Fallback ke `null`, diuji di matrix Laravel 12 & 13 |
| R11 | Scope UI membengkak (terlalu banyak fitur interaksi) | M5 molor | Prioritas: shell + dark mode + Entries + detail + diff lebih dulu. Saved views, density toggle, dan zona waktu bisa digeser ke v3.1 tanpa mengubah API |
| R12 | Alpine CSP build membatasi ekspresi inline | Kode komponen lebih verbose | Semua komponen sebagai `Alpine.data()` sejak awal; lint aturan ini lewat review |
| R13 | Visual regression flaky (font/antialiasing beda antar OS) | CI merah palsu | Screenshot hanya di CI Linux + Chromium, waktu & animasi dibekukan, threshold diff kecil |

---

## 17. Keputusan yang Perlu Dikonfirmasi

Nilai default di plan ini akan dipakai kecuali kamu memilih lain:

| # | Pertanyaan | Default di plan |
|---|---|---|
| D1 | Minimal PHP 8.3 (drop 8.2)? | Ya, 8.3 |
| D2 | Nama key config `auditor` (berubah dari `laravel-auditor`)? | Ya, `auditor` |
| D3 | Dashboard memakai Blade + Alpine (CSP build), bukan Livewire? | Blade + Alpine CSP |
| D4 | Filament & Pulse sebagai repo terpisah? | Terpisah, menyusul |
| D5 | Job sync saat request: entry sendiri atau digabung? | Entry sendiri, correlation sama |
| D6 | Situs dokumentasi: VitePress atau GitBook? | VitePress (GitHub Pages) |
| D7 | Tetap memakai vendor `rembon/`? | Ya |
| D8 | Font: system stack atau bundle Inter (± 35 KB woff2 subset latin)? | System stack |
| D9 | Warna accent default: tetap emerald (brand v2) atau ganti? | Emerald |
| D10 | Tema default untuk pengguna baru | System (mengikuti OS) |

---

## 18. Definition of Done v3.0.0

- [ ] Semua item audit v2 (bagian 4) tertutup dan punya test.
- [ ] Semua acceptance criteria M0–M8 (termasuk M5a & M5b) terpenuhi.
- [ ] Dashboard: dark mode (light/dark/system) tanpa flash, lulus axe AA di kedua tema, visual regression baseline disetujui, aset sesuai budget, dan berjalan di bawah CSP ketat.
- [ ] Uji usability 10.13 lulus.
- [ ] CI hijau: matrix PHP 8.3–8.5 × Laravel 12–13 × lowest/stable, plus SQLite/MySQL/PostgreSQL/MariaDB.
- [ ] Coverage ≥ 90%, mutation ≥ 80% (core), Larastan max, Pint & Rector bersih.
- [ ] Instalasi di aplikasi Laravel baru selesai ≤ 3 perintah, tanpa mengedit file aplikasi selain menambahkan trait & gate.
- [ ] Default aman: dashboard tertutup di non-local, tidak ada body email, password/token/encrypted selalu ter-redact.
- [ ] README, dokumentasi, `UPGRADE.md`, `CHANGELOG.md`, dan `SECURITY.md` lengkap.
- [ ] Diuji di ≥ 2 aplikasi nyata selama fase RC.
- [ ] Tag `v3.0.0` dirilis, Packagist ter-update, branch `2.x` ditandai security-only (misalnya sampai 6 bulan setelah rilis v3).
