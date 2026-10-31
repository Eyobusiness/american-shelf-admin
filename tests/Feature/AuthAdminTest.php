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

    /**
     * Test login failure for a non-existent account.
     */
    public function test_admin_login_fails_with_non_existent_account(): void
    {
        $response = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'inconnu@example.com',
            'mot_de_passe' => 'Secret123!',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Identifiants de connexion invalides.',
            ]);
    }

    /**
     * Test login failure if site is inactive.
     */
    public function test_admin_login_fails_if_site_is_inactive(): void
    {
        $this->site->update(['statut' => 'INACTIF']);

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
     * Test login succeeds when explicit valid site_id is passed.
     */
    public function test_admin_can_login_with_valid_site_id(): void
    {
        $response = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin.test@example.com',
            'mot_de_passe' => 'Secret123!',
            'site_id' => $this->site->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    /**
     * Test login fails when mismatched site_id is passed (multi-tenant boundary check).
     */
    public function test_admin_login_fails_with_mismatched_site_id(): void
    {
        $otherSite = Site::create([
            'code' => 'SITE-AUTRE-TEST',
            'nom' => 'Autre Site',
            'statut' => 'ACTIF',
        ]);

        $response = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin.test@example.com',
            'mot_de_passe' => 'Secret123!',
            'site_id' => $otherSite->id,
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test that UtilisateurResource serializes dates safely without crashing,
     * including string dates, null dates, empty strings, and Carbon instances.
     */
    public function test_utilisateur_resource_safely_serializes_various_date_types(): void
    {
        $user = clone $this->utilisateur;

        // 1. Avec dates nulles
        $user->derniere_connexion = null;
        $user->created_at = null;
        $user->updated_at = null;
        $resource = (new \App\Http\Resources\UtilisateurResource($user))->toArray(request());
        $this->assertNull($resource['derniere_connexion']);
        $this->assertNull($resource['created_at']);
        $this->assertNull($resource['updated_at']);

        // 2. Avec chaînes de caractères (format date SQL ou ISO)
        $user->derniere_connexion = '2026-09-16 14:03:09';
        $user->created_at = '2026-01-01T10:00:00Z';
        $resource = (new \App\Http\Resources\UtilisateurResource($user))->toArray(request());
        $this->assertIsString($resource['derniere_connexion']);
        $this->assertStringContainsString('2026-09-16', $resource['derniere_connexion']);
        $this->assertIsString($resource['created_at']);

        // 3. Avec chaîne vide
        $user->derniere_connexion = '';
        $resource = (new \App\Http\Resources\UtilisateurResource($user))->toArray(request());
        $this->assertNull($resource['derniere_connexion']);

        // 4. Avec objets Carbon
        $user->derniere_connexion = now();
        $user->created_at = now()->subDays(5);
        $resource = (new \App\Http\Resources\UtilisateurResource($user))->toArray(request());
        $this->assertIsString($resource['derniere_connexion']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $resource['derniere_connexion']);
    }

    /**
     * Test that UtilisateurResource does not crash if site relation is null (super-admin case).
     */
    public function test_utilisateur_resource_handles_null_site_relation(): void
    {
        $user = clone $this->utilisateur;
        $user->site_id = null;
        $user->setRelation('site', null);

        $resource = (new \App\Http\Resources\UtilisateurResource($user))->toArray(request());
        $this->assertNull($resource['site']);
    }
}

