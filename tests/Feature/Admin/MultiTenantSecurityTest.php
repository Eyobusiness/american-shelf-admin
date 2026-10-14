<?php

namespace Tests\Feature\Admin;

use App\Models\CategorieLivre;
use App\Models\CategorieUsager;
use App\Models\Menu;
use App\Models\Role;
use App\Models\Site;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MultiTenantSecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected Site $siteA;
    protected Site $siteB;
    protected Role $roleA;
    protected Role $roleB;
    protected Utilisateur $userA;
    protected Utilisateur $userB;
    protected string $tokenA;
    protected string $tokenB;

    protected CategorieLivre $catLivreB;
    protected CategorieUsager $catUsagerB;
    protected Menu $menuB;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Site A et Utilisateur A
        $this->siteA = Site::create([
            'code' => 'TENANT-SITE-A',
            'nom' => 'Site A (Abobo)',
            'statut' => 'ACTIF',
        ]);

        $this->roleA = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'ADMIN',
            'nom' => 'Admin Site A',
            'statut' => 'ACTIF',
        ]);

        $this->userA = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'nom' => 'Admin',
            'prenom' => 'SiteA',
            'email' => 'admin.a@tenanta.ci',
            'mot_de_passe' => Hash::make('Secret123!'),
            'role_id' => $this->roleA->id,
            'statut' => 'ACTIF',
        ]);

        $this->tokenA = auth('api')->login($this->userA);

        // 2. Site B et Utilisateur B
        $this->siteB = Site::create([
            'code' => 'TENANT-SITE-B',
            'nom' => 'Site B (Cocody)',
            'statut' => 'ACTIF',
        ]);

        $this->roleB = Role::create([
            'site_id' => $this->siteB->id,
            'code' => 'ADMIN',
            'nom' => 'Admin Site B',
            'statut' => 'ACTIF',
        ]);

        $this->userB = Utilisateur::create([
            'site_id' => $this->siteB->id,
            'nom' => 'Admin',
            'prenom' => 'SiteB',
            'email' => 'admin.b@tenantb.ci',
            'mot_de_passe' => Hash::make('Secret123!'),
            'role_id' => $this->roleB->id,
            'statut' => 'ACTIF',
        ]);

        // On ne pré-authentifie pas userB dans setUp pour ne pas écraser l'instance active de login() en mémoire du runner

        // 3. Ressources créées sur le Site B
        $this->catLivreB = CategorieLivre::create([
            'site_id' => $this->siteB->id,
            'code' => 'CONFIDENTIEL-B',
            'nom' => 'Documents B',
            'statut' => 'ACTIF',
        ]);

        $this->catUsagerB = CategorieUsager::create([
            'site_id' => $this->siteB->id,
            'code' => 'USAGERS-B',
            'nom' => 'Usagers B',
            'statut' => 'ACTIF',
        ]);

        $this->menuB = Menu::create([
            'site_id' => $this->siteB->id,
            'code' => 'MENU-B',
            'libelle' => 'Menu Interne B',
            'statut' => 'ACTIF',
        ]);
    }

    public function test_user_a_cannot_read_category_from_site_b_by_direct_id(): void
    {
        // Utilisateur A tente d'accéder à la ressource du Site B via son ID exact
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/categories-livres/' . $this->catLivreB->id);

        // Doit retourner 404 (isolation stricte par site, la ressource n'existe pas dans le tenant A)
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_user_a_cannot_update_resource_of_site_b(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson('/api/v1/categories-livres/' . $this->catLivreB->id, [
                'nom' => 'Piratage par Utilisateur A',
            ]);

        $response->assertStatus(404);
        $this->assertDatabaseMissing('categories_livres', [
            'id' => $this->catLivreB->id,
            'nom' => 'Piratage par Utilisateur A',
        ]);
    }

    public function test_user_a_cannot_delete_resource_of_site_b(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->deleteJson('/api/v1/categories-livres/' . $this->catLivreB->id);

        $response->assertStatus(404);
        $this->assertNotSoftDeleted('categories_livres', ['id' => $this->catLivreB->id]);
    }

    public function test_user_a_cannot_see_site_b_resources_in_lists(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/categories-livres');

        $response->assertStatus(200);
        $data = $response->json('data');

        foreach ($data as $item) {
            $this->assertNotEquals($this->catLivreB->id, $item['id']);
            $this->assertNotEquals('CONFIDENTIEL-B', $item['code']);
        }
    }
}
