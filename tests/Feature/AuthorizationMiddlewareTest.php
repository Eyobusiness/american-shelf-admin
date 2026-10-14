<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthorizationMiddlewareTest extends TestCase
{
    use DatabaseTransactions;
    protected Site $site;
    protected Role $adminRole;
    protected Role $bibliothecaireRole;
    protected Utilisateur $adminUser;
    protected Utilisateur $biblioUser;
    protected Permission $createLivrePermission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::create([
            'code' => 'SITE-AUTHZ-TEST',
            'nom' => 'Site Authz Test',
            'statut' => 'ACTIF',
        ]);

        $this->adminRole = Role::create([
            'site_id' => $this->site->id,
            'code' => 'ADMIN',
            'nom' => 'Admin',
            'statut' => 'ACTIF',
        ]);

        $this->bibliothecaireRole = Role::create([
            'site_id' => $this->site->id,
            'code' => 'BIBLIOTHECAIRE',
            'nom' => 'Bibliothécaire',
            'statut' => 'ACTIF',
        ]);

        $this->createLivrePermission = Permission::create([
            'site_id' => $this->site->id,
            'action' => 'CREATE',
            'code' => 'LIVRES_CREATE',
            'libelle' => 'Créer un livre',
        ]);

        // Attribue la permission au rôle bibliothécaire
        $this->bibliothecaireRole->permissions()->attach($this->createLivrePermission->id);

        $this->adminUser = Utilisateur::create([
            'site_id' => $this->site->id,
            'nom' => 'Admin',
            'prenom' => 'User',
            'email' => 'admin.authz@example.com',
            'mot_de_passe' => Hash::make('Secret123!'),
            'role_id' => $this->adminRole->id,
            'statut' => 'ACTIF',
        ]);

        $this->biblioUser = Utilisateur::create([
            'site_id' => $this->site->id,
            'nom' => 'Biblio',
            'prenom' => 'User',
            'email' => 'biblio.authz@example.com',
            'mot_de_passe' => Hash::make('Secret123!'),
            'role_id' => $this->bibliothecaireRole->id,
            'statut' => 'ACTIF',
        ]);

        // Déclaration de routes de test
        Route::middleware(['auth.jwt:api', 'role:ADMIN'])->get('/api/v1/test-role-admin', function () {
            return response()->json(['success' => true, 'message' => 'Admin access granted']);
        });

        Route::middleware(['auth.jwt:api', 'permission:LIVRES_CREATE'])->get('/api/v1/test-permission-livres', function () {
            return response()->json(['success' => true, 'message' => 'Livre create access granted']);
        });

        Route::middleware(['auth.jwt:api', 'permission:LIVRES_DELETE'])->get('/api/v1/test-permission-delete', function () {
            return response()->json(['success' => true, 'message' => 'Delete access granted']);
        });
    }

    /**
     * Test role middleware allows user with matching role.
     */
    public function test_role_middleware_allows_admin(): void
    {
        $token = auth('api')->login($this->adminUser);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/test-role-admin');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /**
     * Test role middleware forbids user with different role.
     */
    public function test_role_middleware_forbids_non_admin(): void
    {
        $token = auth('api')->login($this->biblioUser);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/test-role-admin');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test permission middleware allows user possessing permission.
     */
    public function test_permission_middleware_allows_user_with_permission(): void
    {
        $token = auth('api')->login($this->biblioUser);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/test-permission-livres');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /**
     * Test permission middleware forbids user lacking specific permission.
     */
    public function test_permission_middleware_forbids_user_without_permission(): void
    {
        $token = auth('api')->login($this->biblioUser);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/test-permission-delete');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }
}
