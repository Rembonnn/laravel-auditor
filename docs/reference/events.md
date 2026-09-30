# Events

All events are in `Rembon\LaravelAuditor\Events` and are dispatched **after** the data
was persisted successfully.

| Event | Payload | When |
|---|---|---|
| `EntryRecorded` | `public EntryData $entry` | A complete entry was stored. |
| `ModelChangeRecorded` | `public ModelChangeData $change` | A model change was stored. |
| `IntegrityViolationDetected` | `string $table, int $id, string $reason, ?string $expectedHash, ?string $actualHash` | `auditor:verify` found a broken row. |

```php
Event::listen(IntegrityViolationDetected::class, function ($event) {
    Notification::route('slack', config('services.slack.security'))
        ->notify(new AuditTampered($event->table, $event->id, $event->reason));
});
```
