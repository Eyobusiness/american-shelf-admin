<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Configuration;
use App\Models\Livre;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SuperAdminSecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected Site $siteA;
    protected Site $siteB;

    protected Role $superAdminRole;
    protected Role $adminRole;
    protected Role $biblioRole;
    protected Role $administrateurRole;

    protected Utilisateur $superAdminUser;
    protected Utilisateur $adminUser;
    protected Utilisateur $biblioUser;
    protected Utilisateur $administrateurUser;

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

        // Réinitialisation de l'état d'authentification en mémoire
        $this->jwtGuard()->logout();

        // Création des sites A et B
        $this->siteA = Site::create([
            'code' => 'SITE-TEST-A',
            'nom' => 'Site Test A',
            'statut' => 'ACTIF',
        ]);

        $this->siteB = Site::create([
            'code' => 'SITE-TEST-B',
            'nom' => 'Site Test B',
            'statut' => 'ACTIF',
        ]);

        Configuration::create([
            'site_id' => $this->siteA->id,
            'duree_emprunt' => 14,
            'nombre_jours_grace' => 2,
            'duree_consultation' => 2,
            'quota_emprunt' => 3,
            'duree_reservation' => 3,
        ]);

        Configuration::create([
            'site_id' => $this->siteB->id,
            'duree_emprunt' => 14,
            'nombre_jours_grace' => 2,
            'duree_consultation' => 2,
            'quota_emprunt' => 3,
            'duree_reservation' => 3,
        ]);

        // Création des rôles
        $this->superAdminRole = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'SUPERADMIN',
            'nom' => 'Super Administrateur Plateforme',
            'statut' => 'ACTIF',
        ]);

        $this->adminRole = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'ADMIN',
            'nom' => 'Administrateur Site A',
            'statut' => 'ACTIF',
        ]);

        $this->biblioRole = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'BIBLIO',
            'nom' => 'Bibliothécaire Site A',
            'statut' => 'ACTIF',
        ]);

        $this->administrateurRole = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'ADMINISTRATEUR',
            'nom' => 'Administrateur Local A',
            'statut' => 'ACTIF',
        ]);

        // Permissions
        $dashPerm = Permission::firstOrCreate(
            ['site_id' => $this->siteA->id, 'code' => 'DASHBOARD_READ'],
            ['libelle' => 'Consulter le dashboard', 'action' => 'READ']
        );
        $this->adminRole->permissions()->syncWithoutDetaching([$dashPerm->id]);

        $livrePerm = Permission::firstOrCreate(
            ['site_id' => $this->siteA->id, 'code' => 'LIVRE_READ'],
            ['libelle' => 'Lire livres', 'action' => 'READ']
        );
        $this->adminRole->permissions()->syncWithoutDetaching([$livrePerm->id]);

        // Création des utilisateurs
        $this->superAdminUser = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'nom' => 'Super',
            'prenom' => 'Admin',
            'email' => 'superadmin.sec@americanshelf.ci',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $this->superAdminRole->id,
            'statut' => 'ACTIF',
        ]);

        $this->adminUser = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'nom' => 'Site',
            'prenom' => 'Admin',
            'email' => 'admin.sec@americanshelf.ci',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $this->adminRole->id,
            'statut' => 'ACTIF',
        ]);

        $this->biblioUser = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'nom' => 'Biblio',
            'prenom' => 'Agent',
            'email' => 'biblio.sec@americanshelf.ci',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $this->biblioRole->id,
            'statut' => 'ACTIF',
        ]);

        $this->administrateurUser = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'nom' => 'Local',
            'prenom' => 'Admin',
            'email' => 'administrateur.sec@americanshelf.ci',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $this->administrateurRole->id,
            'statut' => 'ACTIF',
        ]);

        // Déclaration d'une route de test pour observer le contexte de tenant
        Route::middleware(['auth.jwt:api', 'tenant'])->get('/api/v1/test-tenant-context', function (Request $request) {
            return response()->json([
                'success' => true,
                'current_site_id' => $request->attributes->get('current_site_id'),
            ]);
        });
    }

    protected function tokenFor(Utilisateur $user): string
    {
        $this->jwtGuard()->logout();
        return $this->jwtGuard()->login($user);
    }

    /**
     * Un Super Admin authentifié passe le middleware super_admin avec succès.
     */
    public function test_super_admin_authenticated_can_access_super_admin_route(): void
    {
        $token = $this->tokenFor($this->superAdminUser);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/super-admin/ping');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Accès Super Administrateur autorisé.',
            ]);
    }

    /**
     * Un administrateur de site (code ADMIN) reçoit HTTP 403 sur le groupe Super Admin.
     */
    public function test_site_admin_receives_403_forbidden_on_super_admin_route(): void
    {
        $token = $this->tokenFor($this->adminUser);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/super-admin/ping');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Un bibliothécaire (code BIBLIO) reçoit HTTP 403 sur le groupe Super Admin.
     */
    public function test_bibliothecaire_receives_403_forbidden_on_super_admin_route(): void
    {
        $token = $this->tokenFor($this->biblioUser);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/super-admin/ping');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Un utilisateur ayant le code de rôle ADMINISTRATEUR reçoit HTTP 403 sur le groupe Super Admin.
     */
    public function test_role_administrateur_receives_403_forbidden_on_super_admin_route(): void
    {
        $token = $this->tokenFor($this->administrateurUser);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/super-admin/ping');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Une requête non authentifiée reçoit HTTP 401 sur le groupe Super Admin.
     */
    public function test_unauthenticated_request_receives_401_on_super_admin_route(): void
    {
        auth('api')->logout();

        $response = $this->getJson('/api/v1/super-admin/ping');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Un administrateur de site ne peut pas changer de site via X-Site-ID ou X-Tenant-ID.
     * Le contexte tenant reste strictement verrouillé sur son site d'origine.
     */
    public function test_site_admin_cannot_switch_site_via_x_site_id(): void
    {
        $token = $this->tokenFor($this->adminUser);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-Site-ID', $this->siteB->id)
            ->getJson('/api/v1/test-tenant-context');

        $response->assertStatus(200);
        $this->assertEquals($this->siteA->id, $response->json('current_site_id'), 'Le site ID doit rester celui du site A');
        $this->assertNotEquals($this->siteB->id, $response->json('current_site_id'));
    }

    /**
     * Un utilisateur avec rôle ADMINISTRATEUR ne peut pas changer de site via X-Site-ID.
     */
    public function test_administrateur_cannot_switch_site_via_x_site_id(): void
    {
        $token = $this->tokenFor($this->administrateurUser);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-Tenant-ID', $this->siteB->id)
            ->getJson('/api/v1/test-tenant-context');

        $response->assertStatus(200);
        $this->assertEquals($this->siteA->id, $response->json('current_site_id'));
        $this->assertNotEquals($this->siteB->id, $response->json('current_site_id'));
    }

    /**
     * Un Super Admin peut basculer de site via le header X-Site-ID ou X-Tenant-ID.
     */
    public function test_super_admin_can_switch_site_via_x_site_id(): void
    {
        $token = $this->tokenFor($this->superAdminUser);

        // 1. Sans header, son site par défaut est le site A
        $responseDefault = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/test-tenant-context');

        $responseDefault->assertStatus(200);
        $this->assertEquals($this->siteA->id, $responseDefault->json('current_site_id'));

        // 2. Avec header X-Site-ID ciblant le site B, le contexte bascule sur le site B
        $responseSwitch = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-Site-ID', $this->siteB->id)
            ->getJson('/api/v1/test-tenant-context');

        $responseSwitch->assertStatus(200);
        $this->assertEquals($this->siteB->id, $responseSwitch->json('current_site_id'), 'Le Super Admin doit pouvoir cibler le site B');

        // 3. Avec header X-Tenant-ID ciblant le site B
        $responseTenantSwitch = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-Tenant-ID', $this->siteB->id)
            ->getJson('/api/v1/test-tenant-context');

        $responseTenantSwitch->assertStatus(200);
        $this->assertEquals($this->siteB->id, $responseTenantSwitch->json('current_site_id'));
    }

    /**
     * Vérification de non-régression sur le multi-tenant :
     * Un administrateur de site ne peut pas accéder aux données d'un autre site même en forçant X-Site-ID.
     */
    public function test_site_admin_cannot_bypass_data_isolation_with_x_site_id(): void
    {
        $catB = \App\Models\CategorieLivre::create([
            'site_id' => $this->siteB->id,
            'code' => 'CAT-SEC-B',
            'nom' => 'Catégorie B',
            'statut' => 'ACTIF',
        ]);

        $livreB = Livre::create([
            'site_id' => $this->siteB->id,
            'code' => 'LIV-SEC-B01',
            'titre' => 'Livre Exclusif Site B',
            'auteur' => 'Auteur B',
            'langue' => 'Français',
            'categorie_livre_id' => $catB->id,
            'nb_exemplaires_total' => 1,
            'nb_exemplaires_disponibles' => 1,
        ]);

        $token = $this->tokenFor($this->adminUser);

        // L'admin du Site A tente de lire le livre du Site B avec X-Site-ID du Site B
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-Site-ID', $this->siteB->id)
            ->getJson('/api/v1/livres/' . $livreB->id);

        // Doit échouer avec 404 (non trouvé dans le tenant A car X-Site-ID a été ignoré)
        $response->assertStatus(404);
    }
}
