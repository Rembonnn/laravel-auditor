<?php

namespace Rembon\LaravelAuditor\Contracts;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

interface Auditor
{
    /**
     * @param User $user
     */
    public function addUser(User $user): self;

    /**
     * @param ?string $route
     */
    public function onRoute(?string $route = null): self;

    /**
     * @param string $url
     */
    public function onUrl(string $url): self;

    /**
     * @param string $ability
     * @param ?bool $result
     */
    public function addAbility(string $ability, ?bool $result = null): self;

    /**
     * @param string $email
     */
    public function addEmail(string $email): self;

    /**
     * @param Model $model
     */
    public function addModel(Model $model): self;

    /**
     * @param Collection $models
     */
    public function addModels(Collection $models): self;

    /**
     * @param string $email
     */
    public function addMail(string $email): self;

    /**
     * @param Notification $notification
     * @param string $channel
     */
    public function addNotification(Notification $notification, string $channel): self;

    /**
     * @param string $key
     * @param mixed $value
     */
    public function addProperty(string $key, mixed $value): self;

    /**
     * @return void
     */
    public function finish(): void;
}
