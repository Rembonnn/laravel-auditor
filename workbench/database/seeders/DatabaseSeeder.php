<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Rembon\LaravelAuditor\Enums\EntryType;
use Rembon\LaravelAuditor\Facades\Auditor;
use Rembon\LaravelAuditor\Recorder;
use Rembon\LaravelAuditor\Support\CorrelationId;
use Workbench\App\Mail\InvoiceMail;
use Workbench\App\Models\Order;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\Notifications\OrderShipped;

/**
 * Realistic demo data for the dashboard (also used for screenshots and
 * visual regression). Time is frozen per step so the data is deterministic.
 */
class DatabaseSeeder extends Seeder
{
    private Recorder $recorder;

    public function run(): void
    {
        mt_srand(42);
        config(['mail.default' => 'array']);

        $this->recorder = app(Recorder::class);
        $now = Date::parse(env('AUDITOR_SEED_NOW', 'now'))->startOfMinute();

        [$budi, $sari, $andi] = Auditor::withoutAuditing(fn () => [
            User::query()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com', 'password' => 'password', 'is_admin' => true]),
            User::query()->create(['name' => 'Sari Wulandari', 'email' => 'sari@example.com', 'password' => 'password']),
            User::query()->create(['name' => 'Andi Pratama', 'email' => 'andi@example.com', 'password' => 'password']),
        ]);

        $users = [$budi, $sari, $andi];

        // A week of background traffic.
        for ($i = 0; $i < 160; $i++) {
            $at = $now->copy()->subMinutes(mt_rand(5, 60 * 24 * 7));
            $user = $users[mt_rand(0, 2)];
            $this->request($at, mt_rand(0, 5) === 0 ? null : $user, 'GET', '/posts', 'posts.index', 200, function () {
                Post::query()->limit(mt_rand(1, 12))->get();
            });
        }

        // The last 24 hours, with a story.
        $post = null;

        $this->request($now->copy()->subHours(20), $budi, 'POST', '/posts', 'posts.store', 201, function () use (&$post) {
            $post = Post::query()->create(['title' => 'Rilis v3', 'body' => 'Catatan rilis pertama', 'meta' => ['lang' => 'id', 'tags' => ['release']]]);
            Auditor::tag('content');
        });

        $this->request($now->copy()->subHours(18), $sari, 'PUT', '/posts/'.$post->id, 'posts.update', 200, function () use ($post) {
            Gate::allows('update-post', $post);
            $post->update(['title' => 'Rilis v3.0', 'meta' => ['lang' => 'id', 'tags' => ['release', 'major']]]);
        });

        $this->request($now->copy()->subHours(9), $andi, 'DELETE', '/posts/'.$post->id, 'posts.destroy', 403, function () use ($post) {
            Gate::denies('delete-post', $post);
        });

        $this->request($now->copy()->subHours(8), $andi, 'GET', '/reports/revenue', 'reports.show', 403, function () {
            Gate::denies('view-report');
        });

        $this->request($now->copy()->subHours(6), $budi, 'DELETE', '/posts/'.$post->id, 'posts.destroy', 200, function () use ($post) {
            Gate::allows('delete-post', $post);
            $post->delete();
        });

        // A checkout that dispatches a job chain with the same correlation id.
        $order = null;
        $correlation = CorrelationId::generate();

        $this->request($now->copy()->subHours(3), $budi, 'POST', '/orders', 'orders.store', 201, function () use (&$order) {
            $order = Order::query()->create(['number' => 'INV-2026-0042', 'total' => 1_250_000, 'items' => [['sku' => 'BOOK-1', 'qty' => 2]], 'card_token' => 'tok_live_4242', 'user_id' => Auth::id()]);
            Auditor::withProperties(['order_id' => $order->id, 'channel' => 'web'])->tag('checkout', 'payment');
        }, $correlation, ['ip' => '203.0.113.24', 'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Safari/605.1.15']);

        $this->job($now->copy()->subHours(3)->addSeconds(2), $budi, 'App\\Jobs\\SendInvoice', $correlation, function () use ($order, $budi) {
            Mail::to($budi->email)->send(new InvoiceMail($order));
            $order->update(['status' => 'invoiced']);
        });

        $this->job($now->copy()->subHours(3)->addSeconds(5), $budi, 'App\\Jobs\\ShipOrder', $correlation, function () use ($order, $budi) {
            $order->update(['status' => 'shipped']);
            $budi->notify(new OrderShipped($order));
        });

        // Failures.
        $this->job($now->copy()->subHours(2), $sari, 'App\\Jobs\\SyncInventory', CorrelationId::generate(), fn () => null, failed: true);
        $this->request($now->copy()->subMinutes(50), $sari, 'POST', '/orders/'.$order->id.'/refund', 'orders.refund', 500, fn () => null);

        // Sensitive attributes are redacted, never stored.
        $this->request($now->copy()->subMinutes(30), $sari, 'PUT', '/profile/password', 'profile.password', 200, function () use ($sari) {
            $sari->update(['password' => 'new-secret-password']);
        });

        // Someone opened tinker on the server.
        $this->command($now->copy()->subMinutes(15), 'tinker', 'deploy', function () use ($order) {
            $order->update(['total' => 1_200_000]);
        });

        $this->request($now->copy()->subMinutes(2), $budi, 'GET', '/orders/'.$order->id.'?token=abc123', 'orders.show', 200, function () use ($order) {
            Order::query()->find($order->id);
        });

        Date::setTestNow();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function request(\DateTimeInterface $at, ?User $user, string $method, string $path, string $name, int $status, callable $callback, ?string $correlation = null, array $extra = []): void
    {
        Date::setTestNow($at);
        $user ? Auth::setUser($user) : Auth::forgetGuards();

        $entry = $this->recorder->start(EntryType::Http, $name, [
            'http_method' => $method,
            'url' => 'https://acme.test'.$path,
            'ip' => $extra['ip'] ?? '198.51.100.'.mt_rand(2, 250),
            'user_agent' => $extra['user_agent'] ?? 'Mozilla/5.0 (X11; Linux x86_64) Chrome/140.0 Safari/537.36',
            'hostname' => 'web-1',
        ], correlationId: $correlation ?? CorrelationId::generate());

        $callback();

        Date::setTestNow($at->copy()->addMilliseconds($duration = mt_rand(18, $status >= 500 ? 2400 : 420)));
        $this->recorder->finish($entry, [
            'status_code' => $status,
            'failed' => $status >= 500,
            'route_action' => 'App\\Http\\Controllers\\'.ucfirst(explode('.', $name)[0]).'Controller@'.(explode('.', $name)[1] ?? 'index'),
            'duration_ms' => $duration,
        ]);

        Auth::forgetGuards();
    }

    private function job(\DateTimeInterface $at, User $causer, string $name, string $correlation, callable $callback, bool $failed = false): void
    {
        Date::setTestNow($at);

        $entry = $this->recorder->start(EntryType::Job, $name, [
            'hostname' => 'worker-1',
            'input' => ['connection' => 'redis', 'queue' => 'default', 'attempts' => 1],
        ], correlationId: $correlation, user: ['type' => $causer->getMorphClass(), 'id' => (string) $causer->id, 'guard' => 'web']);

        $callback();

        $this->recorder->finish($entry, ['failed' => $failed, 'duration_ms' => mt_rand(120, 1800)]);
    }

    private function command(\DateTimeInterface $at, string $name, string $osUser, callable $callback): void
    {
        Date::setTestNow($at);

        $entry = $this->recorder->start(EntryType::Command, $name, [
            'os_user' => $osUser,
            'hostname' => 'web-1',
        ], correlationId: CorrelationId::generate(), user: null);

        $callback();

        $this->recorder->finish($entry, ['status_code' => 0, 'duration_ms' => 252_000]);
    }
}
