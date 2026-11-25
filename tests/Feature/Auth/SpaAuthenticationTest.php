<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpaAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_spa_login_creates_a_session_without_an_api_token(): void
    {
        $user = User::factory()->create();
        $this->postJson('/spa/login', ['email' => $user->email, 'password' => 'password'])->assertNoContent();
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertCount(0, $user->tokens);
    }
}
