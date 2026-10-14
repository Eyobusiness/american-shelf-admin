<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Role;
use App\Models\Site;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminUserManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Site $site;
    protected Role $superAdminRole;
    protected Role $adminRole;

    protected Utilisateur $superAdmin1;
    protected Utilisateur $superAdmin2;
    protected Utilisateur $siteAdmin;

    protected string $superAdmin1Token;
    protected string $siteAdminToken;

    /**
     * @return \PHPOpenSourceSaver\JWTAuth\JWTGuard
     */
    protected function jwtGuard()
    {
        /** @var \PHPOpenSourceSaver\JWTAuth\JWTGuard $guard */
        $guard = auth('api');
        return $guard;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->jwtGuard()->logout();

        $this->site = Site::create([
            'code' => 'SITE-SA-MGMT',
            'nom' => 'Site Central SA Mgmt',
            'statut' => 'ACTIF',
        ]);

        $this->superAdminRole = Role::create([
            'site_id' => $this->site->id,
            'code' => 'SUPERADMIN',
            'nom' => 'Super Administrateur',
            'statut' => 'ACTIF',
        ]);

        $this->adminRole = Role::create([
            'site_id' => $this->site->id,
            'code' => 'ADMIN',
            'nom' => 'Administrateur Local',
            'statut' => 'ACTIF',
        ]);

        // Super Admin 1 (principal pour les requêtes)
        $this->superAdmin1 = Utilisateur::create([
            'site_id' => $this->site->id,
            'role_id' => $this->superAdminRole->id,
            'nom' => 'Super',
            'prenom' => 'Un',
            'email' => 'super1.mgmt@americanshelf.ci',
            'mot_de_passe' => Hash::make('Password123!'),
            'statut' => 'ACTIF',
        ]);

        // Super Admin 2 (secondaire pour les tests de suppression/désactivation)
        $this->superAdmin2 = Utilisateur::create([
            'site_id' => $this->site->id,
            'role_id' => $this->superAdminRole->id,
            'nom' => 'Super',
            'prenom' => 'Deux',
            'email' => 'super2.mgmt@americanshelf.ci',
            'mot_de_passe' => Hash::make('Password123!'),
            'statut' => 'ACTIF',
        ]);

        // Administrateur local (non super-admin)
        $this->siteAdmin = Utilisateur::create([
            'site_id' => $this->site->id,
            'role_id' => $this->adminRole->id,
            'nom' => 'Local',
            'prenom' => 'Admin',
            'email' => 'local.admin.mgmt@americanshelf.ci',
            'mot_de_passe' => Hash::make('Password123!'),
            'statut' => 'ACTIF',
        ]);
    }

    protected function tokenFor(Utilisateur $user): string
    {
        $this->jwtGuard()->logout();
        return $this->jwtGuard()->login($user);
    }

    /**
     * Le Super Admin peut lister les comptes Super Administrateurs.
     */
    public function test_super_admin_can_list_super_admins(): void
    {
        $token = $this->tokenFor($this->superAdmin1);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/super-admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'nom',
                        'prenom',
                        'email',
                        'statut',
                        'role',
                    ],
                ],
                'meta' => ['total', 'current_page'],
            ]);

        // Tous les résultats doivent avoir le rôle SUPERADMIN
        foreach ($response->json('data') as $item) {
            $this->assertEquals('SUPERADMIN', $item['role']['code']);
        }
    }

    /**
     * Le Super Admin peut créer un nouveau compte Super Administrateur.
     */
    public function test_super_admin_can_create_new_super_admin(): void
    {
        $token = $this->tokenFor($this->superAdmin1);

        $payload = [
            'nom' => 'Nouveau',
            'prenom' => 'SuperAdmin',
            'email' => 'nouveau.sa@americanshelf.ci',
            'mot_de_passe' => 'MonSuperMotDePasse123!',
            'telephone' => '+225 0102030405',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/super-admin/users', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Compte Super Administrateur créé avec succès.',
                'data' => [
                    'email' => 'nouveau.sa@americanshelf.ci',
                    'statut' => 'ACTIF',
                ],
            ]);

        $created = Utilisateur::where('email', 'nouveau.sa@americanshelf.ci')->first();
        $this->assertNotNull($created);
        $this->assertEquals('SUPERADMIN', $created->role->code);
        $this->assertTrue(Hash::check('MonSuperMotDePasse123!', $created->mot_de_passe));
    }

    /**
     * La création échoue si l'adresse email existe déjà.
     */
    public function test_creation_rejects_duplicate_email(): void
    {
        $token = $this->tokenFor($this->superAdmin1);

        $payload = [
            'nom' => 'Doublon',
            'prenom' => 'Test',
            'email' => 'super2.mgmt@americanshelf.ci', // Déjà utilisé par superAdmin2
            'mot_de_passe' => 'MotDePasseFort123!',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/super-admin/users', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Le Super Admin peut consulter les détails d'un compte Super Admin.
     */
    public function test_super_admin_can_view_super_admin_details(): void
    {
        $token = $this->tokenFor($this->superAdmin1);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/super-admin/users/' . $this->superAdmin2->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->superAdmin2->id,
                    'email' => 'super2.mgmt@americanshelf.ci',
                    'role' => [
                        'code' => 'SUPERADMIN',
                    ],
                ],
            ]);
    }

    /**
     * Le Super Admin peut modifier les informations d'un autre Super Admin.
     */
    public function test_super_admin_can_update_another_super_admin(): void
    {
        $token = $this->tokenFor($this->superAdmin1);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/super-admin/users/' . $this->superAdmin2->id, [
                'nom' => 'NomModifié',
                'prenom' => 'PrénomModifié',
                'telephone' => '+225 0909090909',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'nom' => 'NomModifié',
                    'prenom' => 'PrénomModifié',
                    'telephone' => '+225 0909090909',
                ],
            ]);

        $this->assertDatabaseHas('utilisateurs', [
            'id' => $this->superAdmin2->id,
            'nom' => 'NomModifié',
        ]);
    }

    /**
     * Le Super Admin peut modifier le statut d'un autre compte Super Admin (INACTIF, BLOQUE).
     */
    public function test_super_admin_can_change_status_of_another_super_admin(): void
    {
        $token = $this->tokenFor($this->superAdmin1);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/super-admin/users/' . $this->superAdmin2->id . '/status', [
                'statut' => 'INACTIF',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'statut' => 'INACTIF',
                ],
            ]);

        $this->assertDatabaseHas('utilisateurs', [
            'id' => $this->superAdmin2->id,
            'statut' => 'INACTIF',
        ]);
    }

    /**
     * Garde-fou : Le Super Admin ne peut pas désactiver ou bloquer son propre compte.
     */
    public function test_super_admin_cannot_deactivate_themselves(): void
    {
        $token = $this->tokenFor($this->superAdmin1);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/super-admin/users/' . $this->superAdmin1->id . '/status', [
                'statut' => 'INACTIF',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        // Le compte doit rester ACTIF
        $this->assertEquals('ACTIF', $this->superAdmin1->fresh()->statut);
    }

    /**
     * Garde-fou : Le Super Admin ne peut pas supprimer son propre compte.
     */
    public function test_super_admin_cannot_delete_themselves(): void
    {
        $token = $this->tokenFor($this->superAdmin1);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/super-admin/users/' . $this->superAdmin1->id);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertNotSoftDeleted('utilisateurs', ['id' => $this->superAdmin1->id]);
    }

    /**
     * Le Super Admin peut supprimer un autre compte Super Admin lorsqu'il en reste un actif.
     */
    public function test_super_admin_can_delete_another_super_admin(): void
    {
        $token = $this->tokenFor($this->superAdmin1);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/super-admin/users/' . $this->superAdmin2->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted('utilisateurs', ['id' => $this->superAdmin2->id]);
    }

    /**
     * Garde-fou : Impossible de supprimer le dernier Super Admin actif.
     */
    public function test_cannot_delete_last_active_super_admin(): void
    {
        // On désactive le superAdmin2
        $this->superAdmin2->update(['statut' => 'INACTIF']);

        // On crée un 3ème super admin pour effectuer l'appel
        $superAdmin3 = Utilisateur::create([
            'site_id' => $this->site->id,
            'role_id' => $this->superAdminRole->id,
            'nom' => 'Super',
            'prenom' => 'Trois',
            'email' => 'super3.mgmt@americanshelf.ci',
            'mot_de_passe' => Hash::make('Pass123!'),
            'statut' => 'ACTIF',
        ]);
        $token = $this->tokenFor($superAdmin3);

        // On supprime superAdmin1 et on désactive superAdmin2
        $this->superAdmin1->delete();
        $this->superAdmin2->update(['statut' => 'INACTIF']);

        // Un appel pour supprimer superAdmin3 qui est le SEUL actif restant:
        $delResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/super-admin/users/' . $superAdmin3->id);

        // Bloqué par les garde-fous (422)
        $delResponse->assertStatus(422);
    }

    /**
     * Un administrateur local ou bibliothécaire est rejeté (403 Forbidden).
     */
    public function test_site_admin_is_forbidden_from_super_admin_user_management(): void
    {
        $token = $this->tokenFor($this->siteAdmin);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/super-admin/users');

        $response->assertStatus(403);
    }
}
