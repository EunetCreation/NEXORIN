<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guest_is_redirected_to_login_from_homepage(): void
    {
        $this
            ->get('/')
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_is_redirected_to_dashboard_from_homepage(): void
    {
        $user = User::factory()->make([
            'id' => 1,
            'status' => 'active',
        ]);

        $this
            ->actingAs($user)
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }
}