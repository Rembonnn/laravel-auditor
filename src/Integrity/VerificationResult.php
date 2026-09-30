<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Integrity;

final class VerificationResult
{
    /** @var list<array{id: int, reason: string, expected: string|null, actual: string|null}> */
    public array $violations = [];

    public int $checked = 0; // @pest-mutate-ignore (defaults are asserted in IntegrityEdgeCasesTest)

    public int $unsealed = 0; // @pest-mutate-ignore

    public function __construct(
        public readonly string $table,
        public readonly ?int $startedAfterId = null,
    ) {}

    public function addViolation(int $id, string $reason, ?string $expected, ?string $actual): void
    {
        $this->violations[] = ['id' => $id, 'reason' => $reason, 'expected' => $expected, 'actual' => $actual];
    }

    public function valid(): bool
    {
        return $this->violations === [];
    }

    /**
     * @return array{id: int, reason: string, expected: string|null, actual: string|null}|null
     */
    public function firstViolation(): ?array
    {
        return $this->violations[0] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'valid' => $this->valid(),
            'checked' => $this->checked,
            'unsealed' => $this->unsealed,
            'started_after_id' => $this->startedAfterId,
            'violations' => $this->violations,
        ];
    }
}
