<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Site;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CategoryCrudTest extends TestCase
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
            'code' => 'SITE-CAT-TEST',
            'nom' => 'Site Catégorie Test',
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
            'prenom' => 'Cat',
            'email' => 'admin.cat@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $this->adminRole->id,
            'statut' => 'ACTIF',
        ]);

        $this->token = auth('api')->login($this->admin);
    }

    public function test_categories_usagers_crud_lifecycle(): void
    {
        // 1. Création
        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/categories-usagers', [
                'code' => 'ETUDIANT',
                'nom' => 'Étudiant Universitaire',
                'description' => 'Étudiants inscrits',
                'statut' => 'ACTIF',
            ]);

        $createResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'ETUDIANT',
                    'nom' => 'Étudiant Universitaire',
                ]
            ]);

        $catId = $createResponse->json('data.id');

        // 2. Consultation & Recherche
        $listResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/categories-usagers?search=Etudiant');

        $listResponse->assertStatus(200)
            ->assertJsonFragment(['code' => 'ETUDIANT']);

        // 3. Mise à jour
        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/categories-usagers/' . $catId, [
                'nom' => 'Étudiant & Chercheur',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.nom', 'Étudiant & Chercheur');

        // 4. Suppression logique
        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/categories-usagers/' . $catId);

        $deleteResponse->assertStatus(200);
        $this->assertSoftDeleted('categories_usagers', ['id' => $catId]);

        // 5. Restauration
        $restoreResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/categories-usagers/' . $catId . '/restore');

        $restoreResponse->assertStatus(200);
        $this->assertNotSoftDeleted('categories_usagers', ['id' => $catId]);
    }

    public function test_categories_livres_crud_lifecycle(): void
    {
        // 1. Création
        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/categories-livres', [
                'code' => 'ROMAN',
                'nom' => 'Romans & Littérature',
                'description' => 'Romans classiques et contemporains',
                'statut' => 'ACTIF',
            ]);

        $createResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'ROMAN',
                    'nom' => 'Romans & Littérature',
                ]
            ]);

        $catId = $createResponse->json('data.id');

        // 2. Unicité du code par site
        $duplicateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/categories-livres', [
                'code' => 'ROMAN',
                'nom' => 'Un autre roman',
            ]);

        $duplicateResponse->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        // 3. Suppression
        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/categories-livres/' . $catId);

        $deleteResponse->assertStatus(200);
        $this->assertSoftDeleted('categories_livres', ['id' => $catId]);
    }
}
