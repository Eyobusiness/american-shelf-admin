<?php

namespace Tests\Feature\Document;

use App\Models\Acquisition;
use App\Models\Role;
use App\Models\Site;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AcquisitionCrudTest extends TestCase
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

        Storage::fake('public');

        $this->siteA = Site::create([
            'code' => 'SITE-ACQ-A',
            'nom' => 'Site Acquisition A',
            'statut' => 'ACTIF',
        ]);

        $this->siteB = Site::create([
            'code' => 'SITE-ACQ-B',
            'nom' => 'Site Acquisition B',
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
            'prenom' => 'Acquisition',
            'email' => 'admin.acq@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $this->adminRoleA->id,
            'statut' => 'ACTIF',
        ]);

        $this->tokenA = auth('api')->login($this->adminA);
    }

    public function test_can_list_acquisitions_paginated_with_filters(): void
    {
        Acquisition::create([
            'site_id' => $this->siteA->id,
            'type' => 'ACHAT',
            'date_acquisition' => '2026-09-01',
            'quantite' => 15,
            'cout' => 150000.00,
            'reference_facture' => 'FAC-2026-001',
            'etat' => 'RECU',
        ]);

        Acquisition::create([
            'site_id' => $this->siteA->id,
            'type' => 'DON',
            'date_acquisition' => '2026-09-02',
            'quantite' => 50,
            'donateur' => 'Fondation Culturelle',
            'nature_don' => 'Ouvrages Littéraires',
            'etat' => 'RECU',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/acquisitions?type=ACHAT&search=FAC-2026');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'site_id', 'type', 'date_acquisition', 'quantite', 'cout', 'reference_facture']
                ],
                'meta' => ['current_page', 'per_page', 'total']
            ])
            ->assertJsonFragment(['reference_facture' => 'FAC-2026-001']);
    }

    public function test_can_create_acquisition_with_file_upload(): void
    {
        $file = UploadedFile::fake()->create('bon_livraison.pdf', 500, 'application/pdf');

        $payload = [
            'type' => 'ACHAT',
            'date_acquisition' => '2026-09-10',
            'provenance' => 'Librairie de France Abidjan',
            'quantite' => 30,
            'cout' => 250000.00,
            'reference_facture' => 'FACT-ABJ-992',
            'destination' => 'Fonds Général',
            'etat' => 'RECU',
            'observations' => 'Livraison conforme au devis',
            'piece_jointe' => $file,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/acquisitions', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'type' => 'ACHAT',
                    'quantite' => 30,
                    'reference_facture' => 'FACT-ABJ-992',
                ]
            ]);

        $this->assertDatabaseHas('acquisitions', [
            'site_id' => $this->siteA->id,
            'reference_facture' => 'FACT-ABJ-992',
            'quantite' => 30,
        ]);

        $created = Acquisition::where('reference_facture', 'FACT-ABJ-992')->first();
        $this->assertNotNull($created->piece_jointe);
        Storage::disk('public')->assertExists($created->piece_jointe);
    }

    public function test_acquisition_validation_rules(): void
    {
        $payload = [
            'type' => '', // Obligatoire
            'date_acquisition' => 'date-invalide',
            'quantite' => 0, // Min: 1
            'cout' => -50, // Min: 0
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/acquisitions', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'date_acquisition', 'quantite', 'cout']);
    }

    public function test_can_show_and_update_acquisition(): void
    {
        $acq = Acquisition::create([
            'site_id' => $this->siteA->id,
            'type' => 'DON',
            'date_acquisition' => '2026-09-05',
            'quantite' => 10,
            'donateur' => 'Partenaire Local',
            'etat' => 'EN_TRAITEMENT',
        ]);

        // 1. Show
        $showResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/acquisitions/' . $acq->id);

        $showResp->assertStatus(200)
            ->assertJsonPath('data.id', $acq->id)
            ->assertJsonPath('data.donateur', 'Partenaire Local');

        // 2. Update
        $updateResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson('/api/v1/acquisitions/' . $acq->id, [
                'etat' => 'TRAITE',
                'quantite' => 12,
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.etat', 'TRAITE')
            ->assertJsonPath('data.quantite', 12);

        $this->assertDatabaseHas('acquisitions', [
            'id' => $acq->id,
            'etat' => 'TRAITE',
            'quantite' => 12,
        ]);
    }

    public function test_can_soft_delete_and_restore_acquisition(): void
    {
        $acq = Acquisition::create([
            'site_id' => $this->siteA->id,
            'type' => 'ACHAT',
            'date_acquisition' => '2026-09-08',
            'quantite' => 5,
            'etat' => 'RECU',
        ]);

        // Delete
        $delResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->deleteJson('/api/v1/acquisitions/' . $acq->id);

        $delResp->assertStatus(200);
        $this->assertSoftDeleted('acquisitions', ['id' => $acq->id]);

        // Restore
        $restoreResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/acquisitions/' . $acq->id . '/restore');

        $restoreResp->assertStatus(200);
        $this->assertNotSoftDeleted('acquisitions', ['id' => $acq->id]);
    }

    public function test_anti_idor_and_multi_tenant_isolation_on_acquisitions(): void
    {
        // Acquisition appartenant au Site B
        $acqB = Acquisition::create([
            'site_id' => $this->siteB->id,
            'type' => 'DON',
            'date_acquisition' => '2026-09-12',
            'quantite' => 100,
            'donateur' => 'Donateur Secret B',
            'etat' => 'CONFIDENTIEL',
        ]);

        // Utilisateur du Site A tente de lire l'acquisition du Site B
        $readResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/acquisitions/' . $acqB->id);

        $readResp->assertStatus(404);

        // Utilisateur du Site A tente de modifier l'acquisition du Site B
        $updateResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson('/api/v1/acquisitions/' . $acqB->id, [
                'quantite' => 1,
            ]);

        $updateResp->assertStatus(404);

        // Utilisateur du Site A tente de supprimer l'acquisition du Site B
        $deleteResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->deleteJson('/api/v1/acquisitions/' . $acqB->id);

        $deleteResp->assertStatus(404);
        $this->assertNotSoftDeleted('acquisitions', ['id' => $acqB->id]);
    }
}
