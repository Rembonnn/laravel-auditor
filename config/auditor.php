<?php

declare(strict_types=1);
use Rembon\LaravelAuditor\Jobs\PersistEntry;

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    | When disabled nothing is recorded. The dashboard stays reachable so you
    | can still browse what was recorded before.
    */
    'enabled' => env('AUDITOR_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    | Built-in drivers: "database", "log" and "null". Register your own with
    | Auditor::extend('name', fn ($app) => new MyStorage). The dashboard,
    | integrity and pruning features only work with the "database" driver.
    */
    'storage' => [
        'driver' => env('AUDITOR_DRIVER', 'database'),

        'database' => [
            'connection' => env('AUDITOR_DB_CONNECTION'),

            // Column type of user_id / auditable_id: string | int | uuid | ulid.
            // Read by the migration, so change it before you migrate.
            'morph_key_type' => 'string',

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

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    | Persist entries through a queued job instead of inside the terminating
    | phase of the request.
    */
    'queue' => [
        'enabled' => env('AUDITOR_QUEUE', false),
        'connection' => env('AUDITOR_QUEUE_CONNECTION'),
        'queue' => env('AUDITOR_QUEUE_NAME'),
    ],

    // Buffered model changes are flushed as soon as this many are waiting
    // (keeps long running commands and imports from growing in memory).
    'buffer_size' => 100,

    'user' => [
        // null = the default guard, or a list such as ['web', 'sanctum', 'admin'].
        'guards' => null,
    ],

    'http' => [
        'enabled' => true,

        // Groups the RecordRequest middleware is appended to. Add 'api' when
        // needed, or use [] and register the middleware yourself.
        'middleware_groups' => ['web'],

        'except' => [
            'auditor', 'auditor/*', 'up', 'telescope*', 'horizon*', 'pulse*',
            '_debugbar*', 'livewire*/livewire*.js', 'build/*', 'favicon.ico',
        ],

        'except_methods' => ['OPTIONS', 'HEAD'],

        // Share of requests that is recorded (0.0 - 1.0). Entries with model
        // changes or denied abilities are ALWAYS recorded.
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
        'except' => [
            PersistEntry::class,
        ],
    ],

    'console' => [
        'enabled' => true,
        'except' => [
            'queue:work', 'queue:listen', 'horizon*', 'schedule:work', 'schedule:run',
            'octane:*', 'reverb:*', 'pulse:*', 'serve', 'auditor:*', 'list', 'help',
            'package:discover', 'vendor:publish', 'config:*', 'route:*', 'view:*', 'event:*', 'optimize*',
            'db:wipe', // drops the audit tables themselves, so it can never be stored
        ],
        'record_os_user' => true,

        // Store the (redacted) arguments and options the command ran with.
        'capture_input' => true,
    ],

    'models' => [
        'track_retrieved' => true,
        'max_ids_per_model' => 50,
        'events' => ['created', 'updated', 'deleted', 'restored', 'force_deleted'],

        // Ignored everywhere. An update that only touches these is not recorded.
        'exclude' => ['updated_at'],
    ],

    'listeners' => [
        'gate' => true,
        'mail' => true,
        'notifications' => true,
    ],

    'mail' => [
        'record_recipients' => true,

        // Store sha256(lowercase email) instead of the address itself.
        'hash_recipients' => false,
    ],

    'redaction' => [
        // Matched case-insensitively with Str::is() against every key,
        // recursively, including URL query strings.
        'keys' => [
            '*password*', '*token*', '*secret*', 'api_key', 'apikey', 'authorization',
            'two_factor_*', 'credit_card*', 'card_number', 'cvv', 'cvc', 'pin', 'ssn',
        ],
        'redact_hidden_attributes' => true,
        'redact_encrypted_casts' => true,
        'replacement' => '[REDACTED]',
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrity (tamper-evident hash chain)
    |--------------------------------------------------------------------------
    | Rows are sealed by `auditor:seal` with HMAC-SHA256. Keep the key out of
    | the database. Generate one with `php artisan auditor:install --integrity`.
    */
    'integrity' => [
        'enabled' => env('AUDITOR_INTEGRITY', false),
        'key' => env('AUDITOR_INTEGRITY_KEY'),

        // Rows younger than this many seconds are not sealed yet, so rows
        // committed slightly out of id order never fork the chain.
        'seal_delay' => 10,

        // An entry that never completed (e.g. the process died) is sealed
        // anyway after this many minutes, so it cannot block the chain.
        'stale_after' => 60,
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

        // Initial theme: system | light | dark. Users can still toggle.
        'theme' => env('AUDITOR_THEME', 'system'),

        // Hex colour such as '#6366f1'. null = emerald.
        'accent' => null,

        'brand' => [
            'name' => null, // null = config('app.name')
            'logo' => null, // image URL, null = built-in logo
        ],

        // Seconds between live mode polls. 0 disables live mode.
        'poll_interval' => 5,

        // null = the browser time zone, or e.g. 'Asia/Jakarta'.
        'timezone' => null,
    ],

    'throw_exceptions' => env('AUDITOR_THROW', false),
];
