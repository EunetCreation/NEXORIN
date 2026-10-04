<?php

namespace App\Services\Authentication;

use App\Models\AuthenticationEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Throwable;

class AuthenticationEventRecorder
{
    /**
     * Authentication telemetry must never block the authentication flow.
     * Deployment can therefore apply the migration independently without
     * turning audit availability into a login outage.
     *
     * @param array<string, mixed> $metadata
     */
    public function record(
        Request $request,
        string $event,
        ?User $user = null,
        ?string $provider = null,
        ?string $email = null,
        array $metadata = [],
    ): void {
        try {
            AuthenticationEvent::query()->create([
                'user_id' => $user?->getKey(),
                'event' => $event,
                'provider' => $provider,
                'email' => $email ?? $user?->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'session_id' => $request->hasSession()
                    ? $request->session()->getId()
                    : null,
                'metadata' => $metadata === [] ? null : $metadata,
                'occurred_at' => now(),
            ]);
        } catch (Throwable) {
            // The primary authentication result remains authoritative.
        }
    }
}
