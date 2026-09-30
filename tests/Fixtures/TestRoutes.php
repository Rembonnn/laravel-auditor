<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Rembon\LaravelAuditor\Facades\Auditor;
use RuntimeException;

final class TestRoutes
{
    public static function register(Router $router): void
    {
        $router->middleware('web')->group(function (Router $router): void {
            $router->get('/ping', fn () => 'pong')->name('ping');
            $router->get('/unnamed', fn () => 'ok');
            $router->get('/up', fn () => 'up');

            $router->get('/posts', function () {
                return Post::query()->get()->pluck('title');
            })->name('posts.index');

            $router->get('/posts/stream', function () {
                $count = 0;

                foreach (Post::query()->cursor() as $post) {
                    $count++;
                }

                return (string) $count;
            })->name('posts.stream');

            $router->post('/posts', function (Request $request) {
                $post = Post::query()->create(['title' => $request->string('title')->value(), 'body' => $request->input('body')]);
                Auditor::withProperty('source', 'test')->tag('posts', 'create');

                return response()->json(['id' => $post->id], 201);
            })->name('posts.store');

            $router->put('/posts/{post}', function (Request $request, Post $post) {
                Gate::authorize('update', $post);
                $post->update(['title' => $request->string('title')->value()]);

                return 'updated';
            })->name('posts.update');

            $router->delete('/posts/{post}', function (Post $post) {
                if (Gate::denies('delete', $post)) {
                    return response('forbidden', 403);
                }

                $post->delete();

                return 'deleted';
            })->name('posts.destroy');

            $router->post('/posts/{post}/process', function (Post $post) {
                ProcessPost::dispatch($post->id);

                return 'queued';
            })->name('posts.process');

            $router->post('/login/{user}', function (User $user) {
                Auth::login($user);

                return 'logged in';
            })->name('login');

            $router->post('/logout', function () {
                Auth::logout();

                return 'logged out';
            })->name('logout');

            $router->post('/mail', function () {
                Mail::to('jane@example.com')->cc('boss@example.com')->send(new WelcomeMail);

                return 'sent';
            })->name('mail');

            $router->post('/notify', function (Request $request) {
                $user = $request->user();
                abort_unless($user instanceof User, 401);
                $user->notify(new PostPublished);

                return 'notified';
            })->name('notify');

            $router->get('/boom', fn () => throw new RuntimeException('boom'))->name('boom');

            $router->get('/ignored', function () {
                Auditor::ignore();
                Post::query()->create(['title' => 'Created while ignored']);

                return 'ok';
            })->name('ignored');
        });
    }
}
