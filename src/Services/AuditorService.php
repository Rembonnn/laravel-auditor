<?php

namespace Rembon\LaravelAuditor\Services;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Rembon\LaravelAuditor\Models\Audit;
use Illuminate\Notifications\Notification;
use Rembon\LaravelAuditor\Contracts\Auditor;

class AuditorService implements Auditor
{
    /**
     * @var ?float
     */
    private ?float $requestTime = null;

    /**
     * @var ?User
     */
    private ?User $user = null;

    /**
     * @var string
     */
    private string $url = '';

    /**
     * @var ?string
     */
    private ?string $route = 'unknown';

    /**
     * @var Collection
     */
    private Collection $abilities;

    /**
     * @var Collection
     */
    private Collection $emails;

    /**
     * @var Collection
     */
    private Collection $models;

    /**
     * @var Collection
     */
    private Collection $notifications;

    /**
     * @var Collection
     */
    private Collection $properties;

    /**
     * @return void
     */
    public function __construct()
    {
        $this->abilities = collect();
        $this->emails = collect();
        $this->models = collect();
        $this->notifications = collect();
        $this->properties = collect();
    }

    /**
     * @param User $user
     * @return self
     */
    public function addUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @param ?string
     * @return self
     */
    public function onRoute(?string $route = null): self
    {
        if ($route) {
            $this->route = $route;
        }

        return $this;
    }

    /**
     * @param string $url
     * @return self
     */
    public function onUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @param string $ability
     * @param ?bool $result
     * @return self
     */
    public function addAbility(string $ability, ?bool $result = null): self
    {
        $this->abilities->put($ability, $result);

        return $this;
    }

    /**
     * @param string $email
     * @return self
     */
    public function addEmail(string $email): self
    {
        $this->abilities->push($email);

        return $this;
    }

    /**
     * @param Model $model
     * @return self
     */
    public function addModel(Model $model): self
    {
        if (! $this->models->has($model::class)) {
            $this->models->put($model::class, collect());
        }

        $models = $this->models->get($model::class);
        $models->add($model);

        $this->models[$model::class][] = $models->unique('id');

        return $this;
    }

    /**
     * @param Collection $models
     * @return self
     */
    public function addModels(Collection $models): self
    {
        $models->each(function (Model $model) {
            $this->addModel($model);
        });

        return $this;
    }

    /**
     * @param string $email
     * @return self
     */
    public function addMail(string $email): self
    {
        $this->emails->push($email);

        return $this;
    }

    /**
     * @param Notification $notification
     * @param string $channel
     * @return self
     */
    public function addNotification(Notification $notification, string $channel): self
    {
        $this->notifications->put($notification->id, [
            'notification' => $notification::class,
            'channel' => $channel,
        ]);

        return $this;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function addProperty(string $key, mixed $value): self
    {
        $this->properties->put($key, $value);

        return $this;
    }

    /**
     * @return void
     */
    public function finish(): void
    {
        $this->requestTime = microtime(true) - LARAVEL_START;

        $this->toDatabase();
    }

    /**
     * @return void
     */
    private function toDatabase(): void
    {
        $audit = new Audit();
        $audit->url = $this->url;
        $audit->datetime = Carbon::now();
        $audit->request_time = $this->requestTime;
        $audit->route = $this->route;
        $audit->abilities = $this->abilities;
        $audit->emails = $this->emails;
        $audit->models = $this->models;
        $audit->notifications = $this->notifications;
        $audit->properties = $this->properties;

        if ($this->user) {
            $audit->user()->associate($this->user);
        }

        $audit->save();
    }
}
