<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Rembon\LaravelAuditor\Contracts\UserResolver;
use Rembon\LaravelAuditor\Facades\Auditor;
use Rembon\LaravelAuditor\Support\UserResolver as DefaultUserResolver;
use Rembon\LaravelAuditor\Tests\Fixtures\User;

it('returns null without a user', function (): void {
    expect(app(UserResolver::class)->resolve())->toBeNull();
});

it('resolves the user of the default guard', function (): void {
    $user = User::make();
    Auth::login($user);

    expect(app(UserResolver::class)->resolve())->toBe([
        'type' => $user->getMorphClass(),
        'id' => (string) $user->id,
        'guard' => 'web',
    ]);
});

it('checks configured guards in order', function (): void {
    config([
        'auth.guards.admin' => ['driver' => 'session', 'provider' => 'users'],
        'auditor.user.guards' => ['missing-guard', 'web', 'admin'],
    ]);

    $user = User::make();
    Auth::guard('admin')->login($user);

    expect(app(UserResolver::class)->resolve())->toMatchArray(['id' => (string) $user->id, 'guard' => 'admin']);
});

it('honours resolveUserUsing()', function (): void {
    $user = User::make();

    Auditor::resolveUserUsing(fn (): User => $user);

    expect(app(UserResolver::class)->resolve())->toMatchArray(['id' => (string) $user->id, 'guard' => null]);
});

it('describes only authenticatable users with an identifier', function (): void {
    expect(DefaultUserResolver::describe(new stdClass, 'web'))->toBeNull()
        ->and(DefaultUserResolver::describe(null, 'web'))->toBeNull()
        ->and(DefaultUserResolver::describe(new GenericUser(['id' => null]), 'web'))->toBeNull();
});

it('describes non-Eloquent users by class name', function (): void {
    expect(DefaultUserResolver::describe(new GenericUser(['id' => 9]), 'api'))->toBe([
        'type' => GenericUser::class,
        'id' => '9',
        'guard' => 'api',
    ]);
});

it('describes Eloquent users by morph alias', function (): void {
    Relation::morphMap(['member' => User::class]);

    try {
        $user = User::make();

        expect(DefaultUserResolver::describe($user, 'web'))->toBe([
            'type' => 'member',
            'id' => (string) $user->id,
            'guard' => 'web',
        ]);
    } finally {
        Relation::morphMap([], false);
    }
});

it('falls back to an empty id when the identifier is not scalar', function (): void {
    expect(DefaultUserResolver::describe(new GenericUser(['id' => ['x']]), null))
        ->toMatchArray(['id' => '']);
});

it('returns null when resolveUserUsing() returns nothing', function (): void {
    Auditor::resolveUserUsing(fn (): null => null);

    expect(app(UserResolver::class)->resolve())->toBeNull();
});
