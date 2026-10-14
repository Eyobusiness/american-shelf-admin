<?php

namespace Tests\Feature\Admin;

use App\Models\Menu;
use App\Models\Role;
use App\Models\Site;
use App\Models\SousMenu;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MenuHierarchyTest extends TestCase
{
    use DatabaseTransactions;

    protected Site $siteA;
    protected Site $siteB;
    protected Role $adminRoleA;
    protected Utilisateur $adminA;
    protected string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siteA = Site::create([
            'code' => 'SITE-MENU-A',
            'nom' => 'Site Menu A',
            'statut' => 'ACTIF',
        ]);

        $this->siteB = Site::create([
            'code' => 'SITE-MENU-B',
            'nom' => 'Site Menu B',
            'statut' => 'ACTIF',
        ]);

        $this->adminRoleA = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'ADMIN',
            'nom' => 'Administrateur',
            'statut' => 'ACTIF',
        ]);

        $this->adminA = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'nom' => 'Admin',
            'prenom' => 'A',
            'email' => 'admin.menu.a@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $this->adminRoleA->id,
            'statut' => 'ACTIF',
        ]);

        $this->tokenA = auth('api')->login($this->adminA);
    }

    public function test_menu_and_submenu_creation_within_same_site(): void
    {
        // 1. Création Menu Site A
        $menuResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/menus', [
                'code' => 'CATALOGUE',
                'libelle' => 'Gestion du Catalogue',
                'icon' => 'book',
                'order' => 1,
                'statut' => 'ACTIF',
            ]);

        $menuResp->assertStatus(201);
        $menuId = $menuResp->json('data.id');

        // 2. Création Sous-Menu lié au Menu du Site A
        $sousMenuResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/sous-menus', [
                'menu_id' => $menuId,
                'code' => 'LIVRES',
                'libelle' => 'Liste des Livres',
                'path' => '/catalogue/livres',
                'order' => 1,
                'statut' => 'ACTIF',
            ]);

        $sousMenuResp->assertStatus(201)
            ->assertJsonPath('data.code', 'LIVRES')
            ->assertJsonPath('data.menu_id', $menuId);
    }

    public function test_forbidden_to_link_submenu_to_menu_from_different_site(): void
    {
        // Créer un Menu appartenant au Site B
        $menuB = Menu::create([
            'site_id' => $this->siteB->id,
            'code' => 'MENU-SITE-B',
            'libelle' => 'Menu Exclusif B',
            'statut' => 'ACTIF',
        ]);

        // L'administrateur du Site A tente de créer un sous-menu rattaché au Menu B
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/sous-menus', [
                'menu_id' => $menuB->id,
                'code' => 'INTRUSION-SUBMENU',
                'libelle' => 'Tentative Intrusion',
            ]);

        // Doit être rejeté (422) par la validation FormRequest scoped par site
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['menu_id']);
    }

    public function test_menu_seeder_and_tree_endpoint(): void
    {
        $this->seed(\Database\Seeders\MenuSeeder::class);

        // Tester l'arborescence complète via l'API
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/menus/tree');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $menus = $response->json('data');
        $this->assertCount(6, $menus);

        // 1. Tableau de bord
        $this->assertEquals('100', $menus[0]['code']);
        $this->assertEquals('Tableau de bord', $menus[0]['libelle']);
        $this->assertEquals('1,2,3,4', $menus[0]['permission']);
        $this->assertEquals('dashboard', $menus[0]['reference']);
        $this->assertEmpty($menus[0]['sous_menus']);

        // 2. Documents
        $this->assertEquals('200', $menus[1]['code']);
        $this->assertEquals('Documents', $menus[1]['libelle']);
        $this->assertEquals('main_documents', $menus[1]['reference']);
        $this->assertCount(3, $menus[1]['sous_menus']);
        $this->assertEquals('201', $menus[1]['sous_menus'][0]['code']);
        $this->assertEquals('Livres', $menus[1]['sous_menus'][0]['libelle']);
        $this->assertEquals('202', $menus[1]['sous_menus'][1]['code']);
        $this->assertEquals('Exemplaires', $menus[1]['sous_menus'][1]['libelle']);
        $this->assertEquals('203', $menus[1]['sous_menus'][2]['code']);
        $this->assertEquals('Acquisitions', $menus[1]['sous_menus'][2]['libelle']);

        // 3. Usagers
        $this->assertEquals('300', $menus[2]['code']);
        $this->assertEquals('Usagers', $menus[2]['libelle']);
        $this->assertEquals('main_usagers', $menus[2]['reference']);
        $this->assertCount(3, $menus[2]['sous_menus']);
        $this->assertEquals('301', $menus[2]['sous_menus'][0]['code']);
        $this->assertEquals('302', $menus[2]['sous_menus'][1]['code']);
        $this->assertEquals('303', $menus[2]['sous_menus'][2]['code']);

        // 4. Activités
        $this->assertEquals('400', $menus[3]['code']);
        $this->assertEquals('Activités', $menus[3]['libelle']);
        $this->assertEquals('main_activites', $menus[3]['reference']);
        $this->assertCount(3, $menus[3]['sous_menus']);
        $this->assertEquals('401', $menus[3]['sous_menus'][0]['code']);
        $this->assertEquals('402', $menus[3]['sous_menus'][1]['code']);
        $this->assertEquals('403', $menus[3]['sous_menus'][2]['code']);

        // 5. Administration
        $this->assertEquals('500', $menus[4]['code']);
        $this->assertEquals('Administration', $menus[4]['libelle']);
        $this->assertEquals('main_administration', $menus[4]['reference']);
        $this->assertCount(4, $menus[4]['sous_menus']);
        $this->assertEquals('501', $menus[4]['sous_menus'][0]['code']);
        $this->assertEquals('502', $menus[4]['sous_menus'][1]['code']);
        $this->assertEquals('503', $menus[4]['sous_menus'][2]['code']);
        $this->assertEquals('504', $menus[4]['sous_menus'][3]['code']);

        // 6. Système
        $this->assertEquals('600', $menus[5]['code']);
        $this->assertEquals('Système', $menus[5]['libelle']);
        $this->assertEquals('main_systeme', $menus[5]['reference']);
        $this->assertCount(3, $menus[5]['sous_menus']);
        $this->assertEquals('601', $menus[5]['sous_menus'][0]['code']);
        $this->assertEquals('602', $menus[5]['sous_menus'][1]['code']);
        $this->assertEquals('603', $menus[5]['sous_menus'][2]['code']);
    }
}
