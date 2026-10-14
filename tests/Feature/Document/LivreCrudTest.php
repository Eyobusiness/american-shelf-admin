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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LivreCrudTest extends TestCase
{
    use DatabaseTransactions;

    protected Site $siteA;
    protected Site $siteB;
    protected CategorieLivre $categorieA;
    protected CategorieLivre $categorieB;
    protected Role $adminRoleA;
    protected Utilisateur $adminA;
    protected string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Création des sites isolés
        $this->siteA = Site::create([
            'code' => 'SITE-LIVRE-A',
            'nom' => 'Site Documentaire A',
            'statut' => 'ACTIF',
        ]);

        $this->siteB = Site::create([
            'code' => 'SITE-LIVRE-B',
            'nom' => 'Site Documentaire B',
            'statut' => 'ACTIF',
        ]);

        // Catégories de livres par site
        $this->categorieA = CategorieLivre::create([
            'site_id' => $this->siteA->id,
            'code' => 'CAT-ROMAN',
            'nom' => 'Romans et Fiction',
            'statut' => 'ACTIF',
        ]);

        $this->categorieB = CategorieLivre::create([
            'site_id' => $this->siteB->id,
            'code' => 'CAT-SCIENCES',
            'nom' => 'Sciences et Techniques',
            'statut' => 'ACTIF',
        ]);

        // Rôle administrateur pour Site A
        $this->adminRoleA = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'ADMIN',
            'nom' => 'Administrateur Site A',
            'statut' => 'ACTIF',
        ]);

        $this->adminA = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'nom' => 'Kouassi',
            'prenom' => 'Jean',
            'email' => 'admin.livre@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $this->adminRoleA->id,
            'statut' => 'ACTIF',
        ]);

        $this->tokenA = auth('api')->login($this->adminA);
    }

    public function test_can_list_livres_paginated_with_filters_and_dynamic_exemplaires_count(): void
    {
        $livre1 = Livre::create([
            'site_id' => $this->siteA->id,
            'code' => 'LIV-001',
            'titre' => 'Les Soleils des Indépendances',
            'auteur' => 'Ahmadou Kourouma',
            'editeur' => 'Seuil',
            'annee_publication' => 1968,
            'isbn' => '978-202000001',
            'categorie_livre_id' => $this->categorieA->id,
            'visible_en_ligne' => true,
            'statut' => 'ACTIF',
        ]);

        $livre2 = Livre::create([
            'site_id' => $this->siteA->id,
            'code' => 'LIV-002',
            'titre' => 'Le Monde s\'effondre',
            'auteur' => 'Chinua Achebe',
            'editeur' => 'Presence Africaine',
            'annee_publication' => 1958,
            'isbn' => '978-202000002',
            'categorie_livre_id' => $this->categorieA->id,
            'visible_en_ligne' => false,
            'statut' => 'ACTIF',
        ]);

        // Créer 2 exemplaires pour le livre 1
        Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $livre1->id,
            'code' => 'LIV-001-001',
            'etat' => 'BON',
            'statut' => 'DISPONIBLE',
            'localisation' => 'Rayon A1',
        ]);

        Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $livre1->id,
            'code' => 'LIV-001-002',
            'etat' => 'NEUF',
            'statut' => 'DISPONIBLE',
            'localisation' => 'Rayon A1',
        ]);

        // Requête avec recherche et filtre
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/livres?search=Kourouma&visible_en_ligne=true');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'site_id',
                        'code',
                        'titre',
                        'auteur',
                        'editeur',
                        'annee_publication',
                        'isbn',
                        'categorie_livre_id',
                        'visible_en_ligne',
                        'statut',
                        'nombre_exemplaires',
                    ]
                ],
                'meta' => ['current_page', 'per_page', 'total']
            ])
            ->assertJsonFragment(['code' => 'LIV-001'])
            ->assertJsonMissing(['code' => 'LIV-002']);

        // Vérifier que le nombre d'exemplaires est calculé dynamiquement
        $data = $response->json('data');
        $this->assertEquals(2, $data[0]['nombre_exemplaires']);
    }

    public function test_can_create_livre_with_cover_image_and_digital_file(): void
    {
        $image = UploadedFile::fake()->create('couverture.jpg', 200, 'image/jpeg');
        $fichier = UploadedFile::fake()->create('livre_numerique.pdf', 1500, 'application/pdf');

        $payload = [
            'code' => 'LIV-NEW-100',
            'titre' => 'L\'Étranger',
            'auteur' => 'Albert Camus',
            'editeur' => 'Gallimard',
            'annee_publication' => 1942,
            'isbn' => '978-2070360024',
            'categorie_livre_id' => $this->categorieA->id,
            'visible_en_ligne' => true,
            'statut' => 'ACTIF',
            'image_principale' => $image,
            'fichier_numerique' => $fichier,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/livres', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'LIV-NEW-100',
                    'titre' => 'L\'Étranger',
                    'auteur' => 'Albert Camus',
                    'visible_en_ligne' => true,
                    'nombre_exemplaires' => 0,
                ]
            ]);

        $this->assertDatabaseHas('livres', [
            'site_id' => $this->siteA->id,
            'code' => 'LIV-NEW-100',
            'titre' => 'L\'Étranger',
        ]);

        $created = Livre::where('code', 'LIV-NEW-100')->first();
        $this->assertNotNull($created->image_principale);
        $this->assertNotNull($created->fichier_numerique);
        Storage::disk('public')->assertExists($created->image_principale);
        Storage::disk('public')->assertExists($created->fichier_numerique);
    }

    public function test_livre_validation_rules_and_cross_site_category_rejection(): void
    {
        // 1. Champs obligatoires manquants
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/livres', [
                'code' => '',
                'titre' => '',
                'auteur' => '',
                'categorie_livre_id' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'titre', 'auteur', 'categorie_livre_id']);

        // 2. Unicité du code au sein du site
        Livre::create([
            'site_id' => $this->siteA->id,
            'code' => 'LIV-EXISTING',
            'titre' => 'Livre Existant',
            'auteur' => 'Auteur Connu',
            'categorie_livre_id' => $this->categorieA->id,
        ]);

        $dupResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/livres', [
                'code' => 'LIV-EXISTING',
                'titre' => 'Autre Titre',
                'auteur' => 'Autre Auteur',
                'categorie_livre_id' => $this->categorieA->id,
            ]);

        $dupResp->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        // 3. Rejet strict d'une catégorie appartenant à un autre site (Cross-site rejection)
        $crossSiteResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/livres', [
                'code' => 'LIV-CROSS-01',
                'titre' => 'Tentative Cross-Site',
                'auteur' => 'Hacker',
                'categorie_livre_id' => $this->categorieB->id, // Catégorie appartenant au Site B !
            ]);

        $crossSiteResp->assertStatus(422)
            ->assertJsonValidationErrors(['categorie_livre_id']);
    }

    public function test_can_show_and_update_livre_including_file_replacement(): void
    {
        $oldImage = UploadedFile::fake()->create('ancienne_couverture.jpg', 200, 'image/jpeg');
        $livre = Livre::create([
            'site_id' => $this->siteA->id,
            'code' => 'LIV-TO-UPDATE',
            'titre' => 'Titre Initial',
            'auteur' => 'Auteur Initial',
            'categorie_livre_id' => $this->categorieA->id,
            'image_principale' => $oldImage->store('livres/images/' . $this->siteA->id, 'public'),
        ]);

        // 1. Consultation détail (Show)
        $showResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/livres/' . $livre->id);

        $showResp->assertStatus(200)
            ->assertJsonPath('data.code', 'LIV-TO-UPDATE')
            ->assertJsonPath('data.titre', 'Titre Initial');

        // 2. Mise à jour avec remplacement de la couverture
        $newImage = UploadedFile::fake()->create('nouvelle_couverture.png', 200, 'image/png');

        $updateResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson('/api/v1/livres/' . $livre->id, [
                'titre' => 'Titre Modifié et Enrichi',
                'image_principale' => $newImage,
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.titre', 'Titre Modifié et Enrichi');

        $this->assertDatabaseHas('livres', [
            'id' => $livre->id,
            'titre' => 'Titre Modifié et Enrichi',
        ]);

        $livre->refresh();
        Storage::disk('public')->assertExists($livre->image_principale);
    }

    public function test_can_soft_delete_and_restore_livre(): void
    {
        $livre = Livre::create([
            'site_id' => $this->siteA->id,
            'code' => 'LIV-TO-DELETE',
            'titre' => 'Livre à supprimer',
            'auteur' => 'Auteur Test',
            'categorie_livre_id' => $this->categorieA->id,
        ]);

        // 1. Suppression logique (Soft Delete)
        $delResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->deleteJson('/api/v1/livres/' . $livre->id);

        $delResp->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('livres', ['id' => $livre->id]);

        // 2. Restauration
        $restoreResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/livres/' . $livre->id . '/restore');

        $restoreResp->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertNotSoftDeleted('livres', ['id' => $livre->id]);
    }

    public function test_anti_idor_and_multi_tenant_isolation_on_livres(): void
    {
        // Livre appartenant au Site B
        $livreB = Livre::create([
            'site_id' => $this->siteB->id,
            'code' => 'LIV-SITE-B',
            'titre' => 'Livre Secret Site B',
            'auteur' => 'Auteur B',
            'categorie_livre_id' => $this->categorieB->id,
        ]);

        // 1. L'utilisateur du Site A tente de consulter le livre du Site B -> 404
        $readResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/livres/' . $livreB->id);

        $readResp->assertStatus(404);

        // 2. L'utilisateur du Site A tente de modifier le livre du Site B -> 404
        $updateResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson('/api/v1/livres/' . $livreB->id, [
                'titre' => 'Piratage Titre',
            ]);

        $updateResp->assertStatus(404);

        // 3. L'utilisateur du Site A tente de supprimer le livre du Site B -> 404
        $deleteResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->deleteJson('/api/v1/livres/' . $livreB->id);

        $deleteResp->assertStatus(404);
        $this->assertNotSoftDeleted('livres', ['id' => $livreB->id]);

        // 4. Liste globale filtrée : le livre du Site B n'apparaît jamais
        $listResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/livres');

        $listResp->assertStatus(200)
            ->assertJsonMissing(['code' => 'LIV-SITE-B']);
    }

    public function test_permissions_enforcement_on_livres(): void
    {
        // Créer un rôle sans aucune permission documentaire
        $restrictedRole = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'LECTEUR_SIMPLE',
            'nom' => 'Lecteur sans droits de gestion',
            'statut' => 'ACTIF',
        ]);

        $restrictedUser = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'nom' => 'Restreint',
            'prenom' => 'User',
            'email' => 'restricted@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $restrictedRole->id,
            'statut' => 'ACTIF',
        ]);

        $restrictedToken = auth('api')->login($restrictedUser);

        // Tentative de consultation sans permission LIVRE_READ -> 403 Forbidden
        $readResp = $this->withHeader('Authorization', 'Bearer ' . $restrictedToken)
            ->getJson('/api/v1/livres');

        $readResp->assertStatus(403);

        // Tentative de création sans permission LIVRE_CREATE -> 403 Forbidden
        $createResp = $this->withHeader('Authorization', 'Bearer ' . $restrictedToken)
            ->postJson('/api/v1/livres', [
                'code' => 'LIV-FORBIDDEN',
                'titre' => 'Forbidden Book',
                'auteur' => 'Anonymous',
                'categorie_livre_id' => $this->categorieA->id,
            ]);

        $createResp->assertStatus(403);

        // Assigner la permission LIVRE_READ au rôle
        $permRead = Permission::create([
            'site_id' => $this->siteA->id,
            'code' => 'LIVRE_READ',
            'libelle' => 'Lecture du catalogue des livres',
            'action' => 'READ',
        ]);
        $restrictedRole->permissions()->attach($permRead->id);

        // La consultation devient autorisée
        $allowedReadResp = $this->withHeader('Authorization', 'Bearer ' . $restrictedToken)
            ->getJson('/api/v1/livres');

        $allowedReadResp->assertStatus(200);
    }
}
