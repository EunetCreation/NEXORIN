<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Authentication\AuthenticationEventRecorder;
use App\Services\Authentication\GoogleOAuthService;
use App\Services\Company\ActiveCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(
        Request $request,
        GoogleOAuthService $google,
        AuthenticationEventRecorder $events,
    ): RedirectResponse {
        try {
            $events->record($request, 'oauth_started', provider: 'google');

            return redirect()->away($google->authorizationUrl($request));
        } catch (Throwable $exception) {
            $events->record(
                $request,
                'oauth_configuration_failed',
                provider: 'google',
                metadata: ['message' => $exception->getMessage()]
            );

            return redirect()->route('login')->withErrors([
                'google' => $exception->getMessage(),
            ]);
        }
    }

    public function callback(
        Request $request,
        GoogleOAuthService $google,
        AuthenticationEventRecorder $events,
        ActiveCompanyResolver $companies,
    ): RedirectResponse {
        try {
            $user = $google->resolveUser($request);

            if (
                filled($user->two_factor_secret)
                && filled($user->two_factor_confirmed_at)
            ) {
                $request->session()->put([
                    'login.id' => $user->getKey(),
                    'login.remember' => true,
                ]);

                $events->record(
                    $request,
                    'two_factor_challenge_required',
                    $user,
                    'google'
                );

                return redirect()->route('two-factor.login');
            }

            Auth::guard('web')->login($user, true);
            $request->session()->regenerate();

            $events->record($request, 'login_succeeded', $user, 'google');

            if ($companies->requiresSelection($user, $request)) {
                return redirect()->route('workspace.select');
            }

            return redirect()->intended(route('dashboard'));
        } catch (Throwable $exception) {
            $events->record(
                $request,
                'login_failed',
                provider: 'google',
                metadata: ['message' => $exception->getMessage()]
            );

            return redirect()->route('login')->withErrors([
                'google' => $exception->getMessage(),
            ]);
        }
    }
}
