<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Tests\Fixtures;

class PostPolicy
{
    public function update(User $user, Post $post): bool
    {
        return $post->user_id === $user->id;
    }

    public function delete(User $user, Post $post): bool
    {
        return (bool) $user->is_admin;
    }
}
