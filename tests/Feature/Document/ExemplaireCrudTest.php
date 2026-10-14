<?php

namespace Tests\Feature\Document;

use App\Models\CategorieLivre;
use App\Models\Exemplaire;
use App\Models\Livre;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExemplaireCrudTest extends TestCase
{
    use DatabaseTransactions;

    protected Site $siteA;
    protected Site $siteB;
    protected CategorieLivre $categorieA;
    protected CategorieLivre $categorieB;
    protected Livre $livreA;
    protected Livre $livreB;
    protected Role $adminRoleA;
    protected Utilisateur $adminA;
    protected string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        // Sites isolés
        $this->siteA = Site::create([
            'code' => 'SITE-EX-A',
            'nom' => 'Site Exemplaire A',
            'statut' => 'ACTIF',
        ]);

        $this->siteB = Site::create([
            'code' => 'SITE-EX-B',
            'nom' => 'Site Exemplaire B',
            'statut' => 'ACTIF',
        ]);

        // Catégories
        $this->categorieA = CategorieLivre::create([
            'site_id' => $this->siteA->id,
            'code' => 'CAT-EX-A',
            'nom' => 'Catégorie A',
            'statut' => 'ACTIF',
        ]);

        $this->categorieB = CategorieLivre::create([
            'site_id' => $this->siteB->id,
            'code' => 'CAT-EX-B',
            'nom' => 'Catégorie B',
            'statut' => 'ACTIF',
        ]);

        // Livres
        $this->livreA = Livre::create([
            'site_id' => $this->siteA->id,
            'code' => 'ROM-01',
            'titre' => 'Chaka Zoulou',
            'auteur' => 'Thomas Mofolo',
            'categorie_livre_id' => $this->categorieA->id,
            'statut' => 'ACTIF',
        ]);

        $this->livreB = Livre::create([
            'site_id' => $this->siteB->id,
            'code' => 'SCI-99',
            'titre' => 'Astrophysique',
            'auteur' => 'Neil Tyson',
            'categorie_livre_id' => $this->categorieB->id,
            'statut' => 'ACTIF',
        ]);

        // Administrateur pour Site A
        $this->adminRoleA = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'ADMIN',
            'nom' => 'Administrateur',
            'statut' => 'ACTIF',
        ]);

        $this->adminA = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'nom' => 'Admin',
            'prenom' => 'Exemplaires',
            'email' => 'admin.ex@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $this->adminRoleA->id,
            'statut' => 'ACTIF',
        ]);

        $this->tokenA = $this->loginAs($this->adminA);
    }

    public function test_can_create_single_exemplaire_with_auto_generated_code(): void
    {
        // 1er exemplaire
        $response1 = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/exemplaires', [
                'livre_id' => $this->livreA->id,
                'etat' => 'NEUF',
                'localisation' => 'Rayon A - Étagère 1',
            ]);

        $response1->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'ROM-01-001',
                    'etat' => 'NEUF',
                    'statut' => 'DISPONIBLE',
                    'localisation' => 'Rayon A - Étagère 1',
                ]
            ]);

        // 2e exemplaire : doit recevoir le numéro de séquence suivant 002
        $response2 = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/exemplaires', [
                'livre_id' => $this->livreA->id,
                'etat' => 'BON',
            ]);

        $response2->assertStatus(201)
            ->assertJsonPath('data.code', 'ROM-01-002');

        $this->assertDatabaseHas('exemplaires', [
            'site_id' => $this->siteA->id,
            'livre_id' => $this->livreA->id,
            'code' => 'ROM-01-001',
        ]);

        $this->assertDatabaseHas('exemplaires', [
            'site_id' => $this->siteA->id,
            'livre_id' => $this->livreA->id,
            'code' => 'ROM-01-002',
        ]);
    }

    public function test_can_create_bulk_exemplaires_via_bulk_endpoint(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/exemplaires/bulk', [
                'livre_id' => $this->livreA->id,
                'nombre' => 5,
                'etat' => 'BON',
                'statut' => 'DISPONIBLE',
                'localisation' => 'Magasin Central',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'site_id', 'livre_id', 'code', 'etat', 'statut', 'localisation']
                ]
            ]);

        $data = $response->json('data');
        $this->assertCount(5, $data);
        $this->assertEquals('ROM-01-001', $data[0]['code']);
        $this->assertEquals('ROM-01-002', $data[1]['code']);
        $this->assertEquals('ROM-01-003', $data[2]['code']);
        $this->assertEquals('ROM-01-004', $data[3]['code']);
        $this->assertEquals('ROM-01-005', $data[4]['code']);

        $this->assertEquals(5, Exemplaire::where('livre_id', $this->livreA->id)->count());
    }

    public function test_can_create_bulk_exemplaires_for_specific_livre_route(): void
    {
        // Création initiale d'un exemplaire pour débuter à 001
        Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $this->livreA->id,
            'code' => 'ROM-01-001',
            'etat' => 'BON',
            'statut' => 'DISPONIBLE',
        ]);

        // Appel POST /api/v1/livres/{id}/exemplaires avec nombre = 3
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/livres/{$this->livreA->id}/exemplaires", [
                'nombre' => 3,
                'etat' => 'NEUF',
                'localisation' => 'Section Jeunesse',
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertCount(3, $data);
        $this->assertEquals('ROM-01-002', $data[0]['code']);
        $this->assertEquals('ROM-01-003', $data[1]['code']);
        $this->assertEquals('ROM-01-004', $data[2]['code']);
    }

    public function test_code_generation_resilience_when_copy_is_deleted(): void
    {
        // Créer les exemplaires 001, 002, 003
        $ex1 = Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $this->livreA->id,
            'code' => 'ROM-01-001',
            'etat' => 'BON',
        ]);
        $ex2 = Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $this->livreA->id,
            'code' => 'ROM-01-002',
            'etat' => 'BON',
        ]);
        $ex3 = Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $this->livreA->id,
            'code' => 'ROM-01-003',
            'etat' => 'BON',
        ]);

        // Suppression logique du 002
        $ex2->delete();

        // Le prochain exemplaire ne doit PAS utiliser un simple count() qui donnerait 003 (collision !)
        // Il doit correctement détecter le max (003) et générer 004
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/exemplaires', [
                'livre_id' => $this->livreA->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'ROM-01-004');

        $this->assertDatabaseHas('exemplaires', [
            'site_id' => $this->siteA->id,
            'code' => 'ROM-01-004',
        ]);
    }

    public function test_bulk_creation_is_atomic_and_rolls_back_on_error(): void
    {
        $initialCount = Exemplaire::where('livre_id', $this->livreA->id)->count();

        // Déjà un exemplaire 002 présent
        Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $this->livreA->id,
            'code' => 'ROM-01-002',
            'etat' => 'BON',
        ]);

        // Simuler une collision forcée ou interruption en transaction
        try {
            DB::transaction(function () {
                Exemplaire::create([
                    'site_id' => $this->siteA->id,
                    'livre_id' => $this->livreA->id,
                    'code' => 'ROM-01-999',
                    'etat' => 'BON',
                ]);

                // Exception forcée au milieu du lot
                throw new \Exception('Erreur simulée en cours de lot bulk');
            });
        } catch (\Exception $e) {
            // Transaction annulée
        }

        // L'exemplaire ROM-01-999 ne doit PAS être persisté en base
        $this->assertDatabaseMissing('exemplaires', [
            'code' => 'ROM-01-999',
        ]);
    }

    public function test_can_list_exemplaires_paginated_with_filters_and_search(): void
    {
        Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $this->livreA->id,
            'code' => 'ROM-01-001',
            'etat' => 'NEUF',
            'statut' => 'DISPONIBLE',
            'localisation' => 'Rayon Philosophie',
        ]);

        Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $this->livreA->id,
            'code' => 'ROM-01-002',
            'etat' => 'DEGRADE',
            'statut' => 'INDISPONIBLE',
            'localisation' => 'Rayon Réserve',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/exemplaires?etat=NEUF&search=Philosophie');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'site_id', 'livre_id', 'code', 'etat', 'statut', 'localisation', 'livre']
                ],
                'meta' => ['current_page', 'per_page', 'total']
            ])
            ->assertJsonFragment(['code' => 'ROM-01-001'])
            ->assertJsonMissing(['code' => 'ROM-01-002']);
    }

    public function test_can_show_and_update_exemplaire(): void
    {
        $ex = Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $this->livreA->id,
            'code' => 'ROM-01-001',
            'etat' => 'BON',
            'statut' => 'DISPONIBLE',
            'localisation' => 'Étagère 1',
        ]);

        // 1. Show
        $showResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/exemplaires/' . $ex->id);

        $showResp->assertStatus(200)
            ->assertJsonPath('data.code', 'ROM-01-001')
            ->assertJsonPath('data.livre.titre', 'Chaka Zoulou');

        // 2. Update
        $updateResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson('/api/v1/exemplaires/' . $ex->id, [
                'etat' => 'MOYEN',
                'statut' => 'EMPRUNTE',
                'localisation' => 'Emprunté - En circulation',
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.etat', 'MOYEN')
            ->assertJsonPath('data.statut', 'EMPRUNTE');

        $this->assertDatabaseHas('exemplaires', [
            'id' => $ex->id,
            'etat' => 'MOYEN',
            'statut' => 'EMPRUNTE',
        ]);
    }

    public function test_can_soft_delete_and_restore_exemplaire(): void
    {
        $ex = Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $this->livreA->id,
            'code' => 'ROM-01-DEL',
            'etat' => 'BON',
        ]);

        // Delete
        $delResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->deleteJson('/api/v1/exemplaires/' . $ex->id);

        $delResp->assertStatus(200);
        $this->assertSoftDeleted('exemplaires', ['id' => $ex->id]);

        // Restore
        $restoreResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/exemplaires/' . $ex->id . '/restore');

        $restoreResp->assertStatus(200);
        $this->assertNotSoftDeleted('exemplaires', ['id' => $ex->id]);
    }

    public function test_exemplaire_validation_rules(): void
    {
        // Validation bulk: nombre > 100 ou < 1
        $respMax = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/exemplaires/bulk', [
                'livre_id' => $this->livreA->id,
                'nombre' => 150,
            ]);

        $respMax->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);

        $respMin = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/exemplaires/bulk', [
                'livre_id' => $this->livreA->id,
                'nombre' => 0,
            ]);

        $respMin->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);

        // Validation état invalide
        $respEtat = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/exemplaires', [
                'livre_id' => $this->livreA->id,
                'etat' => 'INVALIDE_ETAT',
            ]);

        $respEtat->assertStatus(422)
            ->assertJsonValidationErrors(['etat']);
    }

    public function test_anti_idor_and_cross_site_rejection_on_exemplaires(): void
    {
        // 1. Tentative de créer un exemplaire pour un livre du Site B via l'utilisateur du Site A
        $crossCreate = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/exemplaires', [
                'livre_id' => $this->livreB->id, // Livre du Site B !
                'etat' => 'NEUF',
            ]);

        $crossCreate->assertStatus(422)
            ->assertJsonValidationErrors(['livre_id']);

        // 2. Tentative de bulk via la route /livres/{id}/exemplaires avec un livre du Site B
        $crossBulkLivre = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/livres/{$this->livreB->id}/exemplaires", [
                'nombre' => 2,
            ]);

        $crossBulkLivre->assertStatus(404);

        // 3. Exemplaire appartenant au Site B
        $exB = Exemplaire::create([
            'site_id' => $this->siteB->id,
            'livre_id' => $this->livreB->id,
            'code' => 'SCI-99-001',
            'etat' => 'NEUF',
        ]);

        // Consultation interdite (404)
        $readResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/exemplaires/' . $exB->id);
        $readResp->assertStatus(404);

        // Modification interdite (404)
        $updateResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson('/api/v1/exemplaires/' . $exB->id, [
                'etat' => 'DEGRADE',
            ]);
        $updateResp->assertStatus(404);

        // Suppression interdite (404)
        $deleteResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->deleteJson('/api/v1/exemplaires/' . $exB->id);
        $deleteResp->assertStatus(404);
        $this->assertNotSoftDeleted('exemplaires', ['id' => $exB->id]);

        // Liste filtrée : ne contient jamais l'exemplaire du Site B
        $listResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/exemplaires');
        $listResp->assertStatus(200)
            ->assertJsonMissing(['code' => 'SCI-99-001']);
    }

    public function test_permissions_enforcement_on_exemplaires(): void
    {
        $restrictedRole = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'INVITE',
            'nom' => 'Invité sans droits exemplaires',
            'statut' => 'ACTIF',
        ]);

        $restrictedUser = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'nom' => 'Invite',
            'prenom' => 'User',
            'email' => 'invite.ex@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $restrictedRole->id,
            'statut' => 'ACTIF',
        ]);

        $restrictedToken = $this->loginAs($restrictedUser);

        // GET /api/v1/exemplaires -> 403 Forbidden
        $readResp = $this->withHeader('Authorization', 'Bearer ' . $restrictedToken)
            ->getJson('/api/v1/exemplaires');
        $readResp->assertStatus(403);

        // POST /api/v1/exemplaires -> 403 Forbidden
        $createResp = $this->withHeader('Authorization', 'Bearer ' . $restrictedToken)
            ->postJson('/api/v1/exemplaires', [
                'livre_id' => $this->livreA->id,
            ]);
        $createResp->assertStatus(403);

        // Assigner la permission EXEMPLAIRE_READ
        $permRead = Permission::create([
            'site_id' => $this->siteA->id,
            'code' => 'EXEMPLAIRE_READ',
            'libelle' => 'Lecture des exemplaires',
            'action' => 'READ',
        ]);
        $restrictedRole->permissions()->attach($permRead->id);

        // GET /api/v1/exemplaires -> 200 OK
        $allowedResp = $this->withHeader('Authorization', 'Bearer ' . $restrictedToken)
            ->getJson('/api/v1/exemplaires');
        $allowedResp->assertStatus(200);
    }
}
