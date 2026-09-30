<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Support\Dashboard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * URL form of an auditable type: a morph alias as-is, a class name as
 * "b64.<base64url>" (backslashes are awkward in URLs).
 */
final class ModelRef
{
    public static function encode(string $type): string
    {
        return preg_match('/\A[A-Za-z0-9_-]+\z/', $type) === 1
            ? $type
            : 'b64.'.rtrim(strtr(base64_encode($type), '+/', '-_'), '=');
    }

    public static function decode(string $value): string
    {
        if (! str_starts_with($value, 'b64.')) {
            return $value;
        }

        $decoded = base64_decode(strtr(substr($value, 4), '-_', '+/'), true);

        return $decoded === false ? $value : $decoded;
    }

    public static function url(string $type, string $id): string
    {
        return route('auditor.models.history', ['type' => self::encode($type), 'id' => $id]);
    }

    /**
     * The model class for a morph alias or class name, only if it really is
     * an Eloquent model (never instantiate arbitrary classes from a URL).
     *
     * @return class-string<Model>|null
     */
    public static function modelClass(string $type): ?string
    {
        $class = Relation::getMorphedModel($type) ?? $type;

        return class_exists($class) && is_subclass_of($class, Model::class) ? $class : null;
    }

    /**
     * The live record, when it still exists (soft deleted included).
     */
    public static function find(string $type, string $id): ?Model
    {
        $class = self::modelClass($type);

        if ($class === null) {
            return null;
        }

        try {
            $query = $class::query();

            if (in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
                $query->withoutGlobalScope(SoftDeletingScope::class);
            }

            return $query->whereKey($id)->first();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * A human title for a record: name, title, label, email or number.
     */
    public static function title(?Model $model): ?string
    {
        if ($model === null) {
            return null;
        }

        foreach (['name', 'title', 'label', 'email', 'number', 'slug'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_scalar($value) && $value !== '' && ! in_array($attribute, $model->getHidden(), true)) {
                return (string) $value;
            }
        }

        return null;
    }
}
