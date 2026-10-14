<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiFoundationTest extends TestCase
{
    /**
     * Test health check endpoint returns standardized JSON response.
     */
    public function test_api_v1_health_check_returns_success_structure(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'status',
                    'api_version',
                    'app_name',
                    'environment',
                    'timestamp',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'operational',
                    'api_version' => 'v1',
                ],
            ]);
    }

    /**
     * Test 404 endpoint returns standardized JSON response.
     */
    public function test_non_existent_api_route_returns_standard_404_json(): void
    {
        $response = $this->getJson('/api/v1/endpoint-introuvable');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'La ressource ou l\'endpoint demandé est introuvable.',
            ]);
    }

    /**
     * Test protected route with auth.jwt returns standardized 401 when unauthenticated.
     */
    public function test_protected_jwt_route_without_token_returns_standard_401_json(): void
    {
        \Illuminate\Support\Facades\Route::middleware('auth.jwt')->get('/api/v1/test-protected', function () {
            return response()->json(['ok' => true]);
        });

        $response = $this->getJson('/api/v1/test-protected');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }
}
