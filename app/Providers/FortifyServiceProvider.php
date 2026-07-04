<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.login'));

        Fortify::authenticateUsing(function (Request $request): ?User {
            $email = Str::lower(
                trim((string) $request->input('email'))
            );

            $user = User::query()
                ->where('email', $email)
                ->first();

            /*
             * Return the default authentication error when:
             * - The email is not registered.
             * - The password is incorrect.
             */
            if (
                ! $user
                || ! Hash::check(
                    (string) $request->input('password'),
                    $user->password
                )
            ) {
                return null;
            }

            /*
             * The credentials are correct, but the account
             * has been deactivated.
             */
            if (! $user->isActive()) {
                throw ValidationException::withMessages([
                    'email' => [
                        'This account is inactive. Contact a NEXORIN administrator.',
                    ],
                ]);
            }

            return $user;
        });

        Fortify::createUsersUsing(CreateNewUser::class);

        Fortify::updateUserProfileInformationUsing(
            UpdateUserProfileInformation::class
        );

        Fortify::updateUserPasswordsUsing(
            UpdateUserPassword::class
        );

        Fortify::resetUserPasswordsUsing(
            ResetUserPassword::class
        );

        Fortify::redirectUserForTwoFactorAuthenticationUsing(
            RedirectIfTwoFactorAuthenticatable::class
        );

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(
                Str::lower($request->input(Fortify::username()))
                .'|'
                .$request->ip()
            );

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by(
                $request->session()->get('login.id')
            );
        });
    }
}