<?php

namespace Tests\Feature\Usager;

use App\Models\CategorieUsager;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Usager;
use App\Models\UsagerSite;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsagerCrudTest extends TestCase
{
    use DatabaseTransactions;

    protected Site $siteA;
    protected Site $siteB;
    protected CategorieUsager $catUsagerA;
    protected CategorieUsager $catUsagerB;
    protected Role $adminRoleA;
    protected Utilisateur $adminA;
    protected string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siteA = Site::create([
            'code' => 'SITE-USG-A',
            'nom' => 'Site Usager A',
            'statut' => 'ACTIF',
        ]);

        $this->siteB = Site::create([
            'code' => 'SITE-USG-B',
            'nom' => 'Site Usager B',
            'statut' => 'ACTIF',
        ]);

        $this->catUsagerA = CategorieUsager::create([
            'site_id' => $this->siteA->id,
            'code' => 'ETUDIANT-A',
            'nom' => 'Étudiant Site A',
            'statut' => 'ACTIF',
        ]);

        $this->catUsagerB = CategorieUsager::create([
            'site_id' => $this->siteB->id,
            'code' => 'CHERCHEUR-B',
            'nom' => 'Chercheur Site B',
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
            'prenom' => 'Usager',
            'email' => 'admin.usg@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $this->adminRoleA->id,
            'statut' => 'ACTIF',
        ]);

        $this->tokenA = $this->loginAs($this->adminA);
    }

    public function test_can_create_new_usager_with_site_membership(): void
    {
        $payload = [
            'nom' => 'Koffi',
            'prenom' => 'Serge',
            'email' => 'koffi.serge@example.com',
            'contact' => '+22501020304',
            'mot_de_passe' => 'Secret12345!',
            'type' => 'ADHERENT',
            'categorie_usager_id' => $this->catUsagerA->id,
            'numero_local' => 'ABO-2026-001',
            'statut' => 'ACTIF',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/usagers', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'numero',
                    'nom',
                    'prenom',
                    'email',
                    'contact',
                    'usager_site' => [
                        'site_id',
                        'type',
                        'numero',
                        'statut',
                        'categorie',
                    ]
                ]
            ])
            ->assertJsonMissing(['mot_de_passe']);

        $this->assertDatabaseHas('usagers', [
            'email' => 'koffi.serge@example.com',
            'nom' => 'Koffi',
        ]);

        $createdUsager = Usager::where('email', 'koffi.serge@example.com')->first();
        $this->assertDatabaseHas('usager_sites', [
            'site_id' => $this->siteA->id,
            'usager_id' => $createdUsager->id,
            'type' => 'ADHERENT',
            'numero' => 'ABO-2026-001',
        ]);
    }

    public function test_can_link_existing_usager_to_new_site_without_duplicating_person(): void
    {
        // 1. Usager existant sur Site A
        $usager = Usager::create([
            'numero' => 'USG-GLOBAL-01',
            'nom' => 'Traoré',
            'prenom' => 'Ibrahim',
            'email' => 'traore.ib@example.com',
            'contact' => '07070707',
        ]);

        UsagerSite::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $usager->id,
            'type' => 'USAGER',
            'statut' => 'ACTIF',
            'date_inscription' => now()->toDateString(),
        ]);

        $initialUsagersCount = Usager::count();

        // 2. Créer un administrateur pour Site B
        $adminRoleB = Role::create([
            'site_id' => $this->siteB->id,
            'code' => 'ADMIN',
            'nom' => 'Admin B',
            'statut' => 'ACTIF',
        ]);
        $adminB = Utilisateur::create([
            'site_id' => $this->siteB->id,
            'nom' => 'Admin',
            'prenom' => 'B',
            'email' => 'admin.b@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $adminRoleB->id,
            'statut' => 'ACTIF',
        ]);
        $tokenB = $this->loginAs($adminB);

        // 3. Rattacher la personne existante au Site B via /api/v1/usager-sites
        $attachResponse = $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->postJson('/api/v1/usager-sites', [
                'usager_id' => $usager->id,
                'type' => 'ADHERENT',
                'categorie_usager_id' => $this->catUsagerB->id,
                'numero' => 'SITE-B-ADH-001',
            ]);

        $attachResponse->assertStatus(201)
            ->assertJsonPath('data.site_id', $this->siteB->id)
            ->assertJsonPath('data.type', 'ADHERENT');

        // Vérification : la personne globale n'a pas été dupliquée !
        $this->assertEquals($initialUsagersCount, Usager::count());
        $this->assertEquals(2, UsagerSite::where('usager_id', $usager->id)->count());
    }

    public function test_can_list_usagers_scoped_to_current_site_with_filters_and_search(): void
    {
        $u1 = Usager::create([
            'numero' => 'USG-001',
            'nom' => 'Diallo',
            'prenom' => 'Amadou',
            'email' => 'diallo@example.com',
        ]);
        UsagerSite::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $u1->id,
            'type' => 'ADHERENT',
            'statut' => 'ACTIF',
            'date_inscription' => now()->toDateString(),
        ]);

        $u2 = Usager::create([
            'numero' => 'USG-002',
            'nom' => 'Konan',
            'prenom' => 'Aya',
            'email' => 'aya.konan@example.com',
        ]);
        UsagerSite::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $u2->id,
            'type' => 'USAGER',
            'statut' => 'ACTIF',
            'date_inscription' => now()->toDateString(),
        ]);

        // Usager appartenant UNIQUEMENT au Site B
        $uSecretB = Usager::create([
            'numero' => 'USG-SECRET-B',
            'nom' => 'SecretUser',
            'prenom' => 'SiteB',
            'email' => 'secret.b@example.com',
        ]);
        UsagerSite::create([
            'site_id' => $this->siteB->id,
            'usager_id' => $uSecretB->id,
            'type' => 'ADHERENT',
            'statut' => 'ACTIF',
            'date_inscription' => now()->toDateString(),
        ]);

        // Liste avec recherche et filtre type=ADHERENT sur Site A
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/usagers?type=ADHERENT&search=Diallo');

        $response->assertStatus(200)
            ->assertJsonFragment(['email' => 'diallo@example.com'])
            ->assertJsonMissing(['email' => 'aya.konan@example.com'])
            ->assertJsonMissing(['email' => 'secret.b@example.com']);
    }

    public function test_can_show_and_update_usager_within_tenant(): void
    {
        $u = Usager::create([
            'numero' => 'USG-SHOW-01',
            'nom' => 'Bamba',
            'prenom' => 'Moussa',
            'email' => 'bamba@example.com',
        ]);
        UsagerSite::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $u->id,
            'type' => 'USAGER',
            'statut' => 'ACTIF',
            'date_inscription' => now()->toDateString(),
        ]);

        // 1. Show
        $showResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/usagers/' . $u->id);

        $showResp->assertStatus(200)
            ->assertJsonPath('data.email', 'bamba@example.com');

        // 2. Update
        $updateResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson('/api/v1/usagers/' . $u->id, [
                'nom' => 'Bamba Modifié',
                'type' => 'ADHERENT',
                'statut' => 'ACTIF',
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.nom', 'Bamba Modifié')
            ->assertJsonPath('data.usager_site.type', 'ADHERENT');

        $this->assertDatabaseHas('usagers', [
            'id' => $u->id,
            'nom' => 'Bamba Modifié',
        ]);
        $this->assertDatabaseHas('usager_sites', [
            'site_id' => $this->siteA->id,
            'usager_id' => $u->id,
            'type' => 'ADHERENT',
        ]);
    }

    public function test_can_soft_delete_and_restore_usager_site_membership(): void
    {
        $u = Usager::create([
            'numero' => 'USG-DEL-01',
            'nom' => 'Touré',
            'prenom' => 'Fatou',
            'email' => 'toure.fatou@example.com',
        ]);
        $us = UsagerSite::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $u->id,
            'type' => 'USAGER',
            'statut' => 'ACTIF',
            'date_inscription' => now()->toDateString(),
        ]);

        // 1. Suppression logique de l'adhésion au site
        $delResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->deleteJson('/api/v1/usagers/' . $u->id);

        $delResp->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('usager_sites', ['id' => $us->id]);
        $this->assertNotSoftDeleted('usagers', ['id' => $u->id]); // La personne globale n'est pas détruite

        // 2. Restauration de l'adhésion
        $restoreResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/usagers/' . $u->id . '/restore');

        $restoreResp->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertNotSoftDeleted('usager_sites', ['id' => $us->id]);
    }

    public function test_cross_site_category_rejection_on_usager_creation(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/usagers', [
                'nom' => 'Test',
                'prenom' => 'Cross',
                'categorie_usager_id' => $this->catUsagerB->id, // Catégorie du Site B !
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['categorie_usager_id']);
    }

    public function test_anti_idor_and_tenant_isolation_on_usagers_and_usager_sites(): void
    {
        // Usager appartenant UNIQUEMENT au Site B
        $uB = Usager::create([
            'numero' => 'USG-EXCLUSIVE-B',
            'nom' => 'Exclusive',
            'prenom' => 'SiteB',
            'email' => 'exclusive.b@example.com',
        ]);
        $usB = UsagerSite::create([
            'site_id' => $this->siteB->id,
            'usager_id' => $uB->id,
            'type' => 'ADHERENT',
            'statut' => 'ACTIF',
            'date_inscription' => now()->toDateString(),
        ]);

        // 1. Accès direct à l'usager du Site B depuis le Site A -> 404
        $readUsagerResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/usagers/' . $uB->id);
        $readUsagerResp->assertStatus(404);

        // 2. Modification de l'usager du Site B depuis le Site A -> 404
        $updateUsagerResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson('/api/v1/usagers/' . $uB->id, ['nom' => 'Hacked']);
        $updateUsagerResp->assertStatus(404);

        // 3. Suppression de l'usager du Site B depuis le Site A -> 404
        $deleteUsagerResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->deleteJson('/api/v1/usagers/' . $uB->id);
        $deleteUsagerResp->assertStatus(404);

        // 4. Accès direct à l'UsagerSite du Site B depuis le Site A -> 404
        $readUsSiteResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/usager-sites/' . $usB->id);
        $readUsSiteResp->assertStatus(404);

        // 5. Modification de l'UsagerSite du Site B depuis le Site A -> 404
        $updateUsSiteResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson('/api/v1/usager-sites/' . $usB->id, ['type' => 'USAGER']);
        $updateUsSiteResp->assertStatus(404);

        // 6. Suppression de l'UsagerSite du Site B depuis le Site A -> 404
        $delUsSiteResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->deleteJson('/api/v1/usager-sites/' . $usB->id);
        $delUsSiteResp->assertStatus(404);
        $this->assertNotSoftDeleted('usager_sites', ['id' => $usB->id]);
    }

    public function test_permissions_enforcement_on_usagers_and_usager_sites(): void
    {
        $restrictedRole = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'STAGIAIRE_SANS_DROITS',
            'nom' => 'Stagiaire',
            'statut' => 'ACTIF',
        ]);

        $restrictedUser = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'nom' => 'Stagiaire',
            'prenom' => 'User',
            'email' => 'stagiaire@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $restrictedRole->id,
            'statut' => 'ACTIF',
        ]);

        $restrictedToken = $this->loginAs($restrictedUser);

        // 1. Tentative de lecture usagers sans USAGER_READ -> 403 Forbidden
        $readUsagerResp = $this->withHeader('Authorization', 'Bearer ' . $restrictedToken)
            ->getJson('/api/v1/usagers');
        $readUsagerResp->assertStatus(403);

        // 2. Tentative de création usagers sans USAGER_CREATE -> 403 Forbidden
        $createUsagerResp = $this->withHeader('Authorization', 'Bearer ' . $restrictedToken)
            ->postJson('/api/v1/usagers', [
                'nom' => 'Test',
                'prenom' => 'Forbidden',
            ]);
        $createUsagerResp->assertStatus(403);

        // 3. Attribuer USAGER_READ au rôle
        $permRead = Permission::create([
            'site_id' => $this->siteA->id,
            'code' => 'USAGER_READ',
            'libelle' => 'Lecture des usagers',
            'action' => 'READ',
        ]);
        $restrictedRole->permissions()->attach($permRead->id);

        // La lecture devient autorisée (200 OK)
        $allowedReadResp = $this->withHeader('Authorization', 'Bearer ' . $restrictedToken)
            ->getJson('/api/v1/usagers');
        $allowedReadResp->assertStatus(200);
    }

    public function test_usagers_and_usager_sites_pagination(): void
    {
        // Créer 15 usagers sur Site A
        for ($i = 1; $i <= 15; $i++) {
            $u = Usager::create([
                'numero' => "USG-PAGE-$i",
                'nom' => "Nom $i",
                'prenom' => "Prenom $i",
                'email' => "user.page.$i@example.com",
            ]);
            UsagerSite::create([
                'site_id' => $this->siteA->id,
                'usager_id' => $u->id,
                'type' => 'USAGER',
                'statut' => 'ACTIF',
                'date_inscription' => now()->toDateString(),
            ]);
        }

        // Test pagination usagers
        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/usagers?per_page=5&page=1');

        $resp->assertStatus(200)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.current_page', 1);
        $this->assertCount(5, $resp->json('data'));

        // Test pagination usager-sites
        $respSites = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/usager-sites?per_page=5&page=1');

        $respSites->assertStatus(200)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.current_page', 1);
        $this->assertCount(5, $respSites->json('data'));
    }

    public function test_reattaching_trashed_membership_restores_and_updates_cleanly(): void
    {
        $u = Usager::create([
            'numero' => 'USG-RESTORE-REATTACH',
            'nom' => 'Kone',
            'prenom' => 'Bakary',
            'email' => 'kone.bakary@example.com',
        ]);
        $us = UsagerSite::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $u->id,
            'type' => 'USAGER',
            'statut' => 'ACTIF',
            'date_inscription' => now()->toDateString(),
        ]);

        // Soft delete membership
        $us->delete();
        $this->assertSoftDeleted('usager_sites', ['id' => $us->id]);

        // Re-attach via POST /api/v1/usager-sites
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/usager-sites', [
                'usager_id' => $u->id,
                'type' => 'ADHERENT',
                'statut' => 'ACTIF',
            ]);

        $response->assertStatus(201);
        $this->assertNotSoftDeleted('usager_sites', ['id' => $us->id]);
        $this->assertEquals('ADHERENT', $us->fresh()->type);
    }

    public function test_usager_and_usager_site_factories(): void
    {
        $usager = Usager::factory()->create([
            'nom' => 'Kouadio',
            'prenom' => 'Ferdinand',
        ]);
        $this->assertDatabaseHas('usagers', [
            'id' => $usager->id,
            'nom' => 'Kouadio',
            'prenom' => 'Ferdinand',
        ]);

        $usagerSite = UsagerSite::factory()->create([
            'site_id' => $this->siteA->id,
            'usager_id' => $usager->id,
            'type' => 'ADHERENT',
        ]);
        $this->assertDatabaseHas('usager_sites', [
            'id' => $usagerSite->id,
            'site_id' => $this->siteA->id,
            'usager_id' => $usager->id,
            'type' => 'ADHERENT',
        ]);
    }
}

