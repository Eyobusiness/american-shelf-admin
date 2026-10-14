<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\Usager;
use App\Models\UsagerSite;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthUsagerTest extends TestCase
{
    use DatabaseTransactions;
    protected Site $site;
    protected Usager $usager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::create([
            'code' => 'SITE-USG-TEST',
            'nom' => 'Site Usager Test',
            'statut' => 'ACTIF',
        ]);

        $this->usager = Usager::create([
            'numero' => 'USG-2026-0001',
            'nom' => 'N\'Goran',
            'prenom' => 'Koffi',
            'email' => 'usager.test@example.com',
            'mot_de_passe' => Hash::make('UsagerPass123!'),
            'contact' => '0701020304',
        ]);

        UsagerSite::create([
            'site_id' => $this->site->id,
            'usager_id' => $this->usager->id,
            'numero' => 'ABOBO-0001',
            'date_inscription' => now()->toDateString(),
            'type' => 'ADHERENT',
            'statut' => 'ACTIF',
        ]);
    }

    /**
     * Test usager login using email.
     */
    public function test_usager_can_login_with_email(): void
    {
        $response = $this->postJson('/api/v1/auth/usager/login', [
            'identifiant' => 'usager.test@example.com',
            'mot_de_passe' => 'UsagerPass123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'token_type' => 'Bearer',
                    'usager' => [
                        'numero' => 'USG-2026-0001',
                        'email' => 'usager.test@example.com',
                    ],
                ],
            ]);

        $this->assertArrayNotHasKey('mot_de_passe', $response->json('data.usager'));
    }

    /**
     * Test usager login using numero.
     */
    public function test_usager_can_login_with_numero(): void
    {
        $response = $this->postJson('/api/v1/auth/usager/login', [
            'identifiant' => 'USG-2026-0001',
            'mot_de_passe' => 'UsagerPass123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'usager' => [
                        'numero' => 'USG-2026-0001',
                    ],
                ],
            ]);
    }

    /**
     * Test login fails with wrong credentials.
     */
    public function test_usager_login_fails_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/usager/login', [
            'identifiant' => 'USG-2026-0001',
            'mot_de_passe' => 'WrongPass',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test authenticated usager profile.
     */
    public function test_usager_can_access_me_endpoint_with_valid_token(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/usager/login', [
            'identifiant' => 'USG-2026-0001',
            'mot_de_passe' => 'UsagerPass123!',
        ]);

        $token = $loginResponse->json('data.access_token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/usager/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'numero' => 'USG-2026-0001',
                ],
            ]);
    }

    /**
     * Test usager logout.
     */
    public function test_usager_can_logout(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/usager/login', [
            'identifiant' => 'USG-2026-0001',
            'mot_de_passe' => 'UsagerPass123!',
        ]);

        $token = $loginResponse->json('data.access_token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/usager/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Déconnexion effectuée avec succès.',
            ]);
    }
}
