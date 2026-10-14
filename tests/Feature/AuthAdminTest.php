<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Site;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAdminTest extends TestCase
{
    use DatabaseTransactions;
    protected Site $site;
    protected Role $role;
    protected Utilisateur $utilisateur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::create([
            'code' => 'SITE-ADMIN-TEST',
            'nom' => 'Site Admin Test',
            'statut' => 'ACTIF',
        ]);

        $this->role = Role::create([
            'site_id' => $this->site->id,
            'code' => 'ADMIN',
            'nom' => 'Administrateur',
            'statut' => 'ACTIF',
        ]);

        $this->utilisateur = Utilisateur::create([
            'site_id' => $this->site->id,
            'nom' => 'Kouadio',
            'prenom' => 'Ferdinand',
            'email' => 'admin.test@example.com',
            'mot_de_passe' => Hash::make('Secret123!'),
            'role_id' => $this->role->id,
            'statut' => 'ACTIF',
        ]);
    }

    /**
     * Test successful administrative login.
     */
    public function test_admin_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin.test@example.com',
            'mot_de_passe' => 'Secret123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'access_token',
                    'token_type',
                    'expires_in',
                    'utilisateur' => [
                        'id',
                        'nom',
                        'prenom',
                        'email',
                        'statut',
                        'role',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'token_type' => 'Bearer',
                    'utilisateur' => [
                        'email' => 'admin.test@example.com',
                    ],
                ],
            ]);

        $this->assertArrayNotHasKey('mot_de_passe', $response->json('data.utilisateur'));
    }

    /**
     * Test login failure with wrong password.
     */
    public function test_admin_login_fails_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin.test@example.com',
            'mot_de_passe' => 'MauvaisMotDePasse',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test login fails for inactive administrator.
     */
    public function test_admin_login_fails_if_account_inactive(): void
    {
        $this->utilisateur->update(['statut' => 'INACTIF']);

        $response = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin.test@example.com',
            'mot_de_passe' => 'Secret123!',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test admin can view personal profile with valid token.
     */
    public function test_admin_can_access_me_endpoint_with_valid_token(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin.test@example.com',
            'mot_de_passe' => 'Secret123!',
        ]);

        $token = $loginResponse->json('data.access_token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/admin/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'email' => 'admin.test@example.com',
                ],
            ]);
    }

    /**
     * Test me endpoint rejects requests without token.
     */
    public function test_admin_me_endpoint_rejects_unauthenticated_request(): void
    {
        $response = $this->getJson('/api/v1/auth/admin/me');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test token refresh.
     */
    public function test_admin_can_refresh_token(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin.test@example.com',
            'mot_de_passe' => 'Secret123!',
        ]);

        $token = $loginResponse->json('data.access_token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/admin/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'access_token',
                    'token_type',
                    'expires_in',
                ],
            ]);
    }

    /**
     * Test admin logout.
     */
    public function test_admin_can_logout(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin.test@example.com',
            'mot_de_passe' => 'Secret123!',
        ]);

        $token = $loginResponse->json('data.access_token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/admin/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Déconnexion effectuée avec succès.',
            ]);
    }
}
