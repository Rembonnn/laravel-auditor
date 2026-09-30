<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use RuntimeException;

class ProcessPost implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function __construct(public int $postId, public bool $fail = false) {}

    public function handle(): void
    {
        if ($this->fail) {
            throw new RuntimeException('Processing failed');
        }

        Post::query()->findOrFail($this->postId)->update(['title' => 'Processed']);
    }
}
