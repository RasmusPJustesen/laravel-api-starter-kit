<?php

namespace Tests\Feature\Docs;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_describes_both_authentication_modes_and_typed_errors(): void
    {
        $spec = $this->getJson('/docs/api.json')->assertOk()->json();
        $this->assertArrayHasKey('bearerAuth', $spec['components']['securitySchemes']);
        $this->assertArrayHasKey('cookieAuth', $spec['components']['securitySchemes']);
        $this->assertArrayHasKey('csrfHeader', $spec['components']['securitySchemes']);
        $this->assertArrayHasKey('ApiErrorResponse', $spec['components']['schemas']);
        $this->assertArrayHasKey(202, $spec['paths']['/login']['post']['responses']);
        $this->assertArrayHasKey(204, $spec['paths']['/spa/login']['post']['responses']);
        $this->assertArrayHasKey('/sanctum/csrf-cookie', $spec['paths']);
        $this->assertSame([['bearerAuth' => []], ['cookieAuth' => []]], $spec['paths']['/me']['get']['security']);
    }

    public function test_validation_and_authentication_failures_share_error_details(): void
    {
        $this->postJson('/login', [])->assertUnprocessable()->assertJsonStructure(['message', 'errors', 'error_details' => [['status', 'code', 'detail', 'source']]]);
        $this->getJson('/me')->assertUnauthorized()->assertJsonPath('error_details.0.code', 'UNAUTHENTICATED');
        $user = User::factory()->create();
        $this->withToken($user->createToken('device')->plainTextToken)->deleteJson('/user/sessions/others')->assertForbidden()
            ->assertJsonPath('error_details.0.code', 'PASSWORD_CONFIRMATION_REQUIRED');
    }
}
