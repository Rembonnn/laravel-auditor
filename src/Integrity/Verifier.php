<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Integrity;

use Rembon\LaravelAuditor\Models\Checkpoint;
use Rembon\LaravelAuditor\Support\Values;

/**
 * Recomputes the hash chain.
 *
 * Detects: a modified row (its hash no longer matches), removed rows in the
 * middle (the next row no longer links to its predecessor), sealed rows
 * after unsealed ones. Removing rows from the very end of the chain cannot
 * be detected by a hash chain alone.
 */
final readonly class Verifier
{
    private const int CHUNK = 1000; // @pest-mutate-ignore (declaration lines carry no coverage; chunking is covered by SealAndVerifyTest)

    private const int MAX_VIOLATIONS = 100; // @pest-mutate-ignore (asserted in IntegrityEdgeCasesTest)

    public function __construct(private RowHasher $hasher) {}

    public function verify(string $table, ?int $fromId = null): VerificationResult
    {
        $model = Sealer::model($table);
        $query = fn () => $model->getConnection()->table($model->getTable());

        $checkpoint = Checkpoint::latestFor($table);
        $afterId = $checkpoint?->last_id;
        $previous = $checkpoint->last_hash ?? RowHasher::GENESIS;

        if ($fromId !== null && $fromId > (int) $afterId) { // @pest-mutate-ignore RemoveIntegerCast (int > null behaves like int > 0)
            // Trust the chain up to the row before $fromId.
            $anchor = (array) $query()->where('id', '<', $fromId)->whereNotNull('hash')->orderByDesc('id')->first(['id', 'hash']);
            $afterId = Values::nullableInt($anchor, 'id') ?? $fromId - 1;
            $previous = Values::nullableString($anchor, 'hash') ?? $previous;
        }

        $result = new VerificationResult($table, $afterId);
        $sawUnsealed = false;
        $lastId = $afterId ?? 0; // @pest-mutate-ignore DecrementInteger (ids start at 1)

        do {
            $rows = $query()->where('id', '>', $lastId)->orderBy('id')->limit(self::CHUNK)->get();

            foreach ($rows as $object) {
                $row = (array) $object;
                $lastId = Values::int($row, 'id');
                $hash = Values::nullableString($row, 'hash');
                $storedPrevious = Values::nullableString($row, 'previous_hash');

                if ($hash === null) {
                    $sawUnsealed = true;
                    $result->unsealed++;

                    continue;
                }

                $result->checked++;

                if ($sawUnsealed) {
                    $result->addViolation($lastId, 'sealed_after_unsealed', null, $hash);
                }

                if ($storedPrevious !== $previous) {
                    // The row may be intact while something before it is gone.
                    $intact = $storedPrevious !== null
                        && hash_equals($this->hasher->hash($table, Values::stringKeys($row), $storedPrevious), $hash);

                    $result->addViolation($lastId, $intact ? 'missing_previous_rows' : 'tampered', $previous, $storedPrevious);
                } else {
                    $expected = $this->hasher->hash($table, Values::stringKeys($row), $previous);

                    if (! hash_equals($expected, $hash)) {
                        $result->addViolation($lastId, 'tampered', $expected, $hash);
                    }
                }

                // Continue from the stored hash so one bad row is reported once.
                $previous = $hash;

                if (count($result->violations) >= self::MAX_VIOLATIONS) {
                    break 2;
                }
            }
        } while ($rows->count() === self::CHUNK);

        return $result;
    }
}
