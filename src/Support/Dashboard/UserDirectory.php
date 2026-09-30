<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Support\Dashboard;

use Illuminate\Database\Eloquent\Model;
use Rembon\LaravelAuditor\Auditor;
use Rembon\LaravelAuditor\Support\Values;

/**
 * Loads display names for the users shown on a page in one query per type.
 */
final class UserDirectory
{
    /** @var array<string, array{name?: string|null, avatar?: string|null}> */
    private array $users = [];

    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @param  iterable<Model>  $rows  models with user_type / user_id
     */
    public function load(iterable $rows): self
    {
        $wanted = [];

        foreach ($rows as $row) {
            $type = $row->getAttribute('user_type');
            $id = $row->getAttribute('user_id');

            $id = Values::toString($id);

            if (is_string($type) && $id !== null) {
                $wanted[$type][$id] = true;
            }
        }

        foreach ($wanted as $type => $ids) {
            $class = ModelRef::modelClass($type);

            if ($class === null) {
                continue;
            }

            try {
                $models = $class::query()->whereKey(array_keys($ids))->get();
            } catch (\Throwable) {
                continue;
            }

            foreach ($models as $model) {
                $this->users[$type.':'.Values::toString($model->getKey())] = $this->auditor->displayUser($model);
            }
        }

        return $this;
    }

    /**
     * @return array{name: string|null, avatar: string|null, id: string|null, type: string|null}
     */
    public function get(?string $type, ?string $id): array
    {
        $user = $type !== null && $id !== null ? ($this->users[$type.':'.$id] ?? []) : [];

        return [
            'name' => $user['name'] ?? null,
            'avatar' => $user['avatar'] ?? null,
            'id' => $id,
            'type' => $type,
        ];
    }
}
