<?php

namespace App\Services\Authentication;

use App\Models\ExternalIdentity;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleOAuthService
{
    private const AUTHORIZATION_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const USERINFO_ENDPOINT = 'https://openidconnect.googleapis.com/v1/userinfo';
    private const STATE_SESSION_KEY = 'nexorin.oauth.google.state';

    public function isEnabled(): bool
    {
        return (bool) config('nexorin.authentication.google_enabled', false);
    }

    public function isConfigured(): bool
    {
        return $this->isEnabled()
            && filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled($this->redirectUri());
    }

    public function authorizationUrl(Request $request): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Google Sign-In is not configured for this environment. Contact a NEXORIN administrator.'
            );
        }

        $state = Str::random(64);
        $request->session()->put(self::STATE_SESSION_KEY, $state);

        $parameters = [
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ];

        if (filled(config('services.google.hosted_domain'))) {
            $parameters['hd'] = config('services.google.hosted_domain');
        }

        return self::AUTHORIZATION_ENDPOINT.'?'.http_build_query(
            $parameters,
            '',
            '&',
            PHP_QUERY_RFC3986
        );
    }

    /**
     * Resolve and link a Google identity to a pre-provisioned NEXORIN user.
     * No company membership or role is created here.
     */
    public function resolveUser(Request $request): User
    {
        $expectedState = (string) $request->session()->pull(self::STATE_SESSION_KEY, '');
        $receivedState = (string) $request->query('state', '');

        if ($expectedState === '' || ! hash_equals($expectedState, $receivedState)) {
            throw new RuntimeException('The Google authentication session expired. Please try again.');
        }

        if ($request->filled('error')) {
            throw new RuntimeException('Google authentication was cancelled or could not be completed.');
        }

        $code = trim((string) $request->query('code', ''));

        if ($code === '') {
            throw new RuntimeException('Google did not return an authorization code.');
        }

        $token = $this->http()
            ->asForm()
            ->post(self::TOKEN_ENDPOINT, [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => $this->redirectUri(),
                'grant_type' => 'authorization_code',
                'code' => $code,
            ])
            ->throw()
            ->json();

        $accessToken = (string) ($token['access_token'] ?? '');

        if ($accessToken === '') {
            throw new RuntimeException('Google did not return a usable access token.');
        }

        $profile = $this->http()
            ->withToken($accessToken)
            ->get(self::USERINFO_ENDPOINT)
            ->throw()
            ->json();

        $providerId = trim((string) ($profile['sub'] ?? ''));
        $email = Str::lower(trim((string) ($profile['email'] ?? '')));
        $emailVerified = filter_var(
            $profile['email_verified'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        if ($providerId === '' || $email === '' || ! $emailVerified) {
            throw new RuntimeException('Google did not return a verified email address.');
        }

        return DB::transaction(function () use ($profile, $providerId, $email): User {
            $identity = ExternalIdentity::query()
                ->where('provider', 'google')
                ->where('provider_user_id', $providerId)
                ->lockForUpdate()
                ->first();

            if ($identity) {
                $user = $identity->user()->firstOrFail();

                if (Str::lower($user->email) !== $email) {
                    throw new RuntimeException(
                        'This Google identity no longer matches the provisioned NEXORIN account.'
                    );
                }
            } else {
                $user = User::query()
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->lockForUpdate()
                    ->first();

                if (! $user) {
                    throw new RuntimeException(
                        'Access is not provisioned for this Google account. Contact your company administrator.'
                    );
                }

                $existingProvider = $user->externalIdentities()
                    ->where('provider', 'google')
                    ->first();

                if ($existingProvider && $existingProvider->provider_user_id !== $providerId) {
                    throw new RuntimeException(
                        'A different Google identity is already linked to this NEXORIN account.'
                    );
                }

                $identity = $existingProvider ?? new ExternalIdentity([
                    'provider' => 'google',
                    'provider_user_id' => $providerId,
                    'linked_at' => now(),
                ]);
            }

            if (! $user->isActive()) {
                throw new RuntimeException(
                    'This account is inactive. Contact a NEXORIN administrator.'
                );
            }

            $identity->fill([
                'provider_email' => $email,
                'provider_name' => trim((string) ($profile['name'] ?? $user->name)),
                'avatar_url' => $profile['picture'] ?? null,
                'last_login_at' => now(),
            ]);
            $identity->user()->associate($user);
            $identity->save();

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            return $user;
        });
    }

    private function redirectUri(): string
    {
        return (string) (config('services.google.redirect') ?: route('auth.google.callback'));
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->timeout(15)->retry(2, 200);
    }
}
