<?php

namespace Tests\Feature\Admin;

use App\Models\Configuration;
use App\Models\Role;
use App\Models\Site;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConfigurationCrudTest extends TestCase
{
    use DatabaseTransactions;

    protected Site $site;
    protected Role $adminRole;
    protected Utilisateur $admin;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::create([
            'code' => 'SITE-CONFIG-TEST',
            'nom' => 'Site Config Test',
            'statut' => 'ACTIF',
        ]);

        $this->adminRole = Role::create([
            'site_id' => $this->site->id,
            'code' => 'ADMIN',
            'nom' => 'Administrateur',
            'statut' => 'ACTIF',
        ]);

        $this->admin = Utilisateur::create([
            'site_id' => $this->site->id,
            'nom' => 'Admin',
            'prenom' => 'Config',
            'email' => 'admin.config@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $this->adminRole->id,
            'statut' => 'ACTIF',
        ]);

        $this->token = auth('api')->login($this->admin);
    }

    public function test_can_read_default_configuration(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/configuration');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'duree_emprunt' => 14,
                    'nombre_jours_grace' => 3,
                    'quota_emprunt' => 3,
                ]
            ]);
    }

    public function test_can_update_configuration(): void
    {
        $payload = [
            'duree_emprunt' => 21,
            'nombre_jours_grace' => 5,
            'duree_consultation' => 4,
            'quota_emprunt' => 5,
            'duree_reservation' => 3,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/configuration', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'duree_emprunt' => 21,
                    'nombre_jours_grace' => 5,
                    'quota_emprunt' => 5,
                ]
            ]);

        $this->assertDatabaseHas('configurations', [
            'site_id' => $this->site->id,
            'duree_emprunt' => 21,
        ]);
    }

    public function test_configuration_rejects_negative_or_zero_values(): void
    {
        $payload = [
            'duree_emprunt' => 0, // Invalide (min 1)
            'nombre_jours_grace' => -1, // Invalide (min 0)
            'duree_consultation' => 0,
            'quota_emprunt' => 0,
            'duree_reservation' => 0,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/configuration', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'duree_emprunt',
                'nombre_jours_grace',
                'duree_consultation',
                'quota_emprunt',
                'duree_reservation',
            ]);
    }
}
