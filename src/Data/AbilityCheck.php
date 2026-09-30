<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Data;

final readonly class AbilityCheck
{
    /**
     * @param  list<mixed>  $arguments  Compact form: models become {type, id}.
     */
    public function __construct(
        public string $ability,
        public ?bool $result,
        public array $arguments = [],
    ) {}

    public function denied(): bool
    {
        return $this->result !== true;
    }

    /**
     * @return array{ability: string, result: bool|null, arguments: list<mixed>}
     */
    public function toArray(): array
    {
        return ['ability' => $this->ability, 'result' => $this->result, 'arguments' => $this->arguments];
    }

    /**
     * @param  array{ability: string, result?: bool|null, arguments?: list<mixed>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['ability'], $data['result'] ?? null, $data['arguments'] ?? []);
    }
}
