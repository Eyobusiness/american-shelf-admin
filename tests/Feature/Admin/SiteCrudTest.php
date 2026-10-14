<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Site;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SiteCrudTest extends TestCase
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
            'code' => 'SITE-TEST-ADMIN',
            'nom' => 'Site Principal Test',
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
            'nom' => 'Super',
            'prenom' => 'Admin',
            'email' => 'admin.sites@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $this->adminRole->id,
            'statut' => 'ACTIF',
        ]);

        $this->token = auth('api')->login($this->admin);
    }

    public function test_can_list_sites_paginated(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/sites');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['id', 'code', 'nom', 'statut']
                ],
                'meta' => ['current_page', 'per_page', 'total']
            ]);
    }

    public function test_can_create_new_site(): void
    {
        $payload = [
            'code' => 'SITE-NOUVEAU',
            'nom' => 'Nouveau Site de Test',
            'adresse' => 'Abobo Gare',
            'telephone' => '+22501020304',
            'email' => 'contact@nouveau-site.ci',
            'statut' => 'ACTIF',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/sites', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'SITE-NOUVEAU',
                    'nom' => 'Nouveau Site de Test',
                ]
            ]);

        $this->assertDatabaseHas('sites', ['code' => 'SITE-NOUVEAU']);
    }

    public function test_cannot_create_site_with_duplicate_code(): void
    {
        $payload = [
            'code' => 'SITE-TEST-ADMIN',
            'nom' => 'Doublon Site',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/sites', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_can_show_site_detail(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/sites/' . $this->site->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->site->id,
                    'code' => 'SITE-TEST-ADMIN',
                ]
            ]);
    }

    public function test_can_update_site(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/sites/' . $this->site->id, [
                'nom' => 'Site Principal Modifié',
                'statut' => 'INACTIF',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'nom' => 'Site Principal Modifié',
                    'statut' => 'INACTIF',
                ]
            ]);
    }

    public function test_can_soft_delete_and_restore_site(): void
    {
        // 1. Suppression
        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/sites/' . $this->site->id);

        $deleteResponse->assertStatus(200);
        $this->assertSoftDeleted('sites', ['id' => $this->site->id]);

        // 2. Restauration
        $restoreResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/sites/' . $this->site->id . '/restore');

        $restoreResponse->assertStatus(200);
        $this->assertNotSoftDeleted('sites', ['id' => $this->site->id]);
    }

    public function test_can_upload_site_logo_via_dedicated_endpoint(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $file = \Illuminate\Http\UploadedFile::fake()->create('asa_logo.png', 400, 'image/png');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/sites/' . $this->site->id . '/logo', [
                'logo' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logo du site mis à jour avec succès.',
            ]);

        $site = $this->site->fresh();
        $this->assertNotNull($site->logo);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($site->logo);
    }

    public function test_reuploading_site_logo_deletes_previous_file(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $firstFile = \Illuminate\Http\UploadedFile::fake()->create('logo1.png', 200, 'image/png');
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/sites/' . $this->site->id . '/logo', ['logo' => $firstFile]);

        $firstPath = $this->site->fresh()->logo;
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($firstPath);

        $secondFile = \Illuminate\Http\UploadedFile::fake()->create('logo2.png', 300, 'image/png');
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/sites/' . $this->site->id . '/logo', ['logo' => $secondFile]);

        $secondPath = $this->site->fresh()->logo;
        $this->assertNotEquals($firstPath, $secondPath);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($firstPath);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($secondPath);
    }

    public function test_upload_site_logo_validation_rejects_non_image(): void
    {
        $file = \Illuminate\Http\UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/sites/' . $this->site->id . '/logo', [
                'logo' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['logo']);
    }
}
