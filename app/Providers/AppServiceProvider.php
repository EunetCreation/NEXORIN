<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Authentication\AuthenticationEventRecorder;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(AuthenticationEventRecorder $events): void
    {
        Password::defaults(fn (): Password => Password::min(10)
            ->mixedCase()
            ->numbers()
            ->symbols());

        Event::listen(Login::class, function (Login $event) use ($events): void {
            if (! $event->user instanceof User || request()->routeIs('auth.google.callback')) {
                return;
            }

            $events->record(
                request(),
                'login_succeeded',
                $event->user,
                'password',
                metadata: ['remember' => request()->boolean('remember')]
            );
        });

        Event::listen(Failed::class, function (Failed $event) use ($events): void {
            $events->record(
                request(),
                'login_failed',
                $event->user instanceof User ? $event->user : null,
                'password',
                is_string($event->credentials['email'] ?? null)
                    ? $event->credentials['email']
                    : null
            );
        });

        Event::listen(Logout::class, function (Logout $event) use ($events): void {
            $events->record(
                request(),
                'logout_succeeded',
                $event->user instanceof User ? $event->user : null,
                'session'
            );
        });
    }
}
