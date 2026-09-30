<?php

declare(strict_types=1);

use Rembon\LaravelAuditor\Support\Values;

it('reads strings with a fallback', function (): void {
    expect(Values::string(['a' => 'x'], 'a'))->toBe('x')
        ->and(Values::string(['a' => 5], 'a'))->toBe('5')
        ->and(Values::string([], 'a'))->toBe('')
        ->and(Values::string(['a' => ['nope']], 'a', 'fallback'))->toBe('fallback')
        ->and(Values::nullableString([], 'a'))->toBeNull()
        ->and(Values::toString(true))->toBe('1')
        ->and(Values::toString(null))->toBeNull()
        ->and(Values::toString(new stdClass))->toBeNull();
});

it('reads integers with a fallback', function (): void {
    expect(Values::int(['a' => '12'], 'a'))->toBe(12)
        ->and(Values::int(['a' => 3.9], 'a'))->toBe(3)
        ->and(Values::int([], 'a'))->toBe(0)
        ->and(Values::int(['a' => 'abc'], 'a', 7))->toBe(7)
        ->and(Values::nullableInt([], 'a'))->toBeNull()
        ->and(Values::toInt(true))->toBe(1)
        ->and(Values::toInt(false))->toBe(0)
        ->and(Values::toInt([]))->toBeNull();
});

it('reads booleans from the representations databases return', function (mixed $value, bool $expected): void {
    expect(Values::bool(['a' => $value], 'a'))->toBe($expected);
})->with([
    [true, true],
    [1, true],
    ['1', true],
    ['t', true],
    ['true', true],
    [false, false],
    [0, false],
    [2, false],
    ['0', false],
    ['f', false],
    ['false', false],
    ['yes', false],
    [null, false],
]);

it('treats a missing boolean as false', function (): void {
    expect(Values::bool([], 'a'))->toBeFalse();
});

it('reads maps with string keys', function (): void {
    expect(Values::map(['a' => [1 => 'x', 'k' => 'y']], 'a'))->toBe(['1' => 'x', 'k' => 'y'])
        ->and(Values::map(['a' => 'scalar'], 'a'))->toBe([])
        ->and(Values::map([], 'a'))->toBe([])
        ->and(Values::nullableMap(['a' => ['k' => 1]], 'a'))->toBe(['k' => 1])
        ->and(Values::nullableMap(['a' => 'scalar'], 'a'))->toBeNull()
        ->and(Values::nullableMap([], 'a'))->toBeNull();
});

it('reads lists and string lists', function (): void {
    expect(Values::list(['a' => ['x' => 1, 'y' => 2]], 'a'))->toBe([1, 2])
        ->and(Values::list(['a' => 'scalar'], 'a'))->toBe([])
        ->and(Values::list([], 'a'))->toBe([])
        ->and(Values::strings(['a' => ['x', 5, ['nested'], null, 'y']], 'a'))->toBe(['x', '5', 'y']);
});
