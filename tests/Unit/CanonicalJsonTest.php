<?php

declare(strict_types=1);

use Rembon\LaravelAuditor\Support\CanonicalJson;

it('does not depend on key order', function (): void {
    expect(CanonicalJson::encode(['b' => 1, 'a' => ['y' => 2, 'x' => 1]]))
        ->toBe(CanonicalJson::encode(['a' => ['x' => 1, 'y' => 2], 'b' => 1]))
        ->toBe('{"a":{"x":1,"y":2},"b":1}');
});

it('keeps list order', function (): void {
    expect(CanonicalJson::encode([3, 1, 2]))->toBe('[3,1,2]');
});

it('keeps unicode and slashes unescaped', function (): void {
    expect(CanonicalJson::encode(['url' => 'https://x.test/a', 'name' => 'Sarí ✓']))
        ->toBe('{"name":"Sarí ✓","url":"https://x.test/a"}');
});

it('encodes floats deterministically', function (): void {
    expect(CanonicalJson::encode(['a' => 1.0, 'b' => 0.1]))->toBe('{"a":1.0,"b":0.1}');
});

it('keeps associative arrays with numeric keys as objects', function (): void {
    expect(CanonicalJson::encode([2 => 'b', 1 => 'a']))->toBe('{"1":"a","2":"b"}');
});

it('keeps associative arrays as objects even when sorting turns the keys into a list', function (): void {
    expect(CanonicalJson::encode([1 => 'b', 0 => 'a']))->toBe('{"0":"a","1":"b"}')
        ->and(CanonicalJson::encode(['x' => [1 => 'b', 0 => 'a']]))->toBe('{"x":{"0":"a","1":"b"}}');
});

it('normalizes JsonSerializable objects through jsonSerialize', function (): void {
    $value = new class implements JsonSerializable
    {
        public string $hidden = 'ignored';

        /** @return array<string, int> */
        public function jsonSerialize(): array
        {
            return ['z' => 2, 'a' => 1];
        }
    };

    expect(CanonicalJson::encode(['v' => $value]))->toBe('{"v":{"a":1,"z":2}}');
});

it('normalizes plain objects through their public properties', function (): void {
    $value = new class
    {
        public int $z = 2;

        public int $a = 1;
    };

    expect(CanonicalJson::encode($value))->toBe('{"a":1,"z":2}');
});

it('encodes scalars as is', function (): void {
    expect(CanonicalJson::encode('a/b'))->toBe('"a/b"')
        ->and(CanonicalJson::encode(null))->toBe('null')
        ->and(CanonicalJson::encode([]))->toBe('[]');
});
