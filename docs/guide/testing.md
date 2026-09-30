# Testing with Auditor::fake()

`Auditor::fake()` swaps the storage for an in-memory one and gives you assertions:

```php
use Rembon\LaravelAuditor\Data\EntryData;
use Rembon\LaravelAuditor\Data\ModelChangeData;
use Rembon\LaravelAuditor\Enums\ChangeEvent;
use Rembon\LaravelAuditor\Facades\Auditor;

it('records who renamed the post', function () {
    Auditor::fake();

    $this->actingAs($user)->put("/posts/{$post->id}", ['title' => 'New']);

    Auditor::assertChangeRecorded(Post::class, $post->id, ChangeEvent::Updated,
        fn (ModelChangeData $change) => $change->newValues['title'] === 'New');

    Auditor::assertEntryRecorded(fn (EntryData $entry) => $entry->userId === (string) $user->id);
});
```

| Assertion |
|---|
| `assertEntryRecorded(?Closure $callback = null)` |
| `assertEntryNotRecorded(?Closure $callback = null)` |
| `assertChangeRecorded(string $type, $id = null, ChangeEvent\|string\|null $event = null, ?Closure $callback = null)` |
| `assertChangeNotRecorded(string $type, $id = null, $event = null)` |
| `assertAbilityDenied(string $ability)` |
| `assertAbilityGranted(string $ability)` |
| `assertNothingRecorded()` |

Set `AUDITOR_THROW=true` in `phpunit.xml` so recording errors fail your tests instead of
being reported silently.
