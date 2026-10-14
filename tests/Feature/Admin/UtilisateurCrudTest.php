<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Site;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UtilisateurCrudTest extends TestCase
{
    use DatabaseTransactions;

    protected Site $siteA;
    protected Site $siteB;
    protected Role $adminRoleA;
    protected Role $roleB;
    protected Utilisateur $adminA;
    protected string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siteA = Site::create([
            'code' => 'SITE-USER-A',
            'nom' => 'Site User A',
            'statut' => 'ACTIF',
        ]);

        $this->siteB = Site::create([
            'code' => 'SITE-USER-B',
            'nom' => 'Site User B',
            'statut' => 'ACTIF',
        ]);

        $this->adminRoleA = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'ADMIN',
            'nom' => 'Administrateur',
            'statut' => 'ACTIF',
        ]);

        $this->roleB = Role::create([
            'site_id' => $this->siteB->id,
            'code' => 'ROLE-B',
            'nom' => 'Role B',
            'statut' => 'ACTIF',
        ]);

        $this->adminA = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'nom' => 'Admin',
            'prenom' => 'User',
            'email' => 'admin.user.crud@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $this->adminRoleA->id,
            'statut' => 'ACTIF',
        ]);

        $this->tokenA = auth('api')->login($this->adminA);
    }

    public function test_can_create_user_with_hashed_password_and_correct_role(): void
    {
        $payload = [
            'nom' => 'Kouassi',
            'prenom' => 'Marc',
            'email' => 'marc.kouassi@example.com',
            'mot_de_passe' => 'MonSecretSecurise123!',
            'role_id' => $this->adminRoleA->id,
            'statut' => 'ACTIF',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/utilisateurs', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'nom' => 'Kouassi',
                    'prenom' => 'Marc',
                    'email' => 'marc.kouassi@example.com',
                ]
            ]);

        // Vérification absolue : Le mot de passe ne doit JAMAIS apparaître dans la réponse JSON
        $this->assertArrayNotHasKey('mot_de_passe', $response->json('data'));

        // Vérification en base que le mot de passe est hashé
        $userInDb = Utilisateur::where('email', 'marc.kouassi@example.com')->first();
        $this->assertNotNull($userInDb);
        $this->assertNotEquals('MonSecretSecurise123!', $userInDb->mot_de_passe);
        $this->assertTrue(Hash::check('MonSecretSecurise123!', $userInDb->mot_de_passe));
    }

    public function test_cannot_assign_role_from_another_site(): void
    {
        $payload = [
            'nom' => 'Tentative',
            'prenom' => 'Intrusion',
            'email' => 'intrusion.role@example.com',
            'mot_de_passe' => 'Password123!',
            'role_id' => $this->roleB->id, // Rôle appartenant au Site B !
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/utilisateurs', $payload);

        // Validation FormRequest / Service scoped par site doit rejeter
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);
    }
}
