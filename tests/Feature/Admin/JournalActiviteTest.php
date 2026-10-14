<?php

namespace Tests\Feature\Admin;

use App\Models\JournalActivite;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JournalActiviteTest extends TestCase
{
    use DatabaseTransactions;

    protected Site $siteA;
    protected Site $siteB;
    protected Utilisateur $adminA;
    protected string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siteA = Site::create([
            'code' => 'SITE-JRNL-A',
            'nom' => 'Site Journal A',
            'statut' => 'ACTIF',
        ]);

        $this->siteB = Site::create([
            'code' => 'SITE-JRNL-B',
            'nom' => 'Site Journal B',
            'statut' => 'ACTIF',
        ]);

        $roleA = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'ADMIN',
            'nom' => 'Admin A',
            'statut' => 'ACTIF',
        ]);

        $this->adminA = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'nom' => 'Admin',
            'prenom' => 'Journal',
            'email' => 'admin.journal@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $roleA->id,
            'statut' => 'ACTIF',
        ]);

        $this->tokenA = auth('api')->login($this->adminA);
    }

    public function test_can_list_activity_logs_with_filters_and_pagination(): void
    {
        JournalActivite::create([
            'site_id' => $this->siteA->id,
            'utilisateur_id' => $this->adminA->id,
            'action' => 'CREATE_LIVRE',
            'module' => 'LIVRE',
            'reference' => 'LIV-001',
            'description' => 'Création d\'un nouveau livre',
            'created_at' => now(),
        ]);

        JournalActivite::create([
            'site_id' => $this->siteA->id,
            'utilisateur_id' => $this->adminA->id,
            'action' => 'LOGIN',
            'module' => 'AUTH',
            'reference' => null,
            'description' => 'Connexion utilisateur',
            'created_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/journal-activites');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
        $this->assertCount(2, $response->json('data'));

        // Filtre par module
        $filterResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/journal-activites?module=LIVRE');
        $filterResp->assertStatus(200);
        $this->assertCount(1, $filterResp->json('data'));
    }

    public function test_can_show_activity_log_detail(): void
    {
        $log = JournalActivite::create([
            'site_id' => $this->siteA->id,
            'utilisateur_id' => $this->adminA->id,
            'action' => 'UPDATE_CONFIG',
            'module' => 'CONFIGURATION',
            'reference' => 'CFG-01',
            'description' => 'Mise à jour des durées de prêt',
            'created_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/v1/journal-activites/{$log->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $log->id,
                    'action' => 'UPDATE_CONFIG',
                    'module' => 'CONFIGURATION',
                    'reference' => 'CFG-01',
                ],
            ]);
    }

    public function test_cross_tenant_isolation_on_activity_logs(): void
    {
        $logB = JournalActivite::create([
            'site_id' => $this->siteB->id,
            'utilisateur_id' => null,
            'action' => 'CONFIDENTIAL',
            'module' => 'SECRET',
            'reference' => 'B-01',
            'description' => 'Log Site B',
            'created_at' => now(),
        ]);

        // Tentative d'accès depuis Site A -> 404
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/v1/journal-activites/{$logB->id}");
        $response->assertStatus(404);

        // Non présent dans la liste du Site A
        $listResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/journal-activites');
        $this->assertEmpty(collect($listResp->json('data'))->where('id', $logB->id));
    }

    public function test_permissions_enforcement_on_activity_logs(): void
    {
        $restrictedRole = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'STAGIAIRE_JRNL',
            'nom' => 'Stagiaire Journal',
            'statut' => 'ACTIF',
        ]);

        $restrictedUser = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'nom' => 'Stagiaire',
            'prenom' => 'Journal',
            'email' => 'stagiaire.journal@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $restrictedRole->id,
            'statut' => 'ACTIF',
        ]);

        $tokenRestricted = auth('api')->login($restrictedUser);

        // Sans permission JOURNAL_READ -> 403
        $resp = $this->withHeader('Authorization', 'Bearer ' . $tokenRestricted)
            ->getJson('/api/v1/journal-activites');
        $resp->assertStatus(403);

        // Avec permission JOURNAL_READ -> 200
        $perm = Permission::create([
            'site_id' => $this->siteA->id,
            'code' => 'JOURNAL_READ',
            'libelle' => 'Lecture journal',
            'action' => 'READ',
        ]);
        $restrictedRole->permissions()->attach($perm->id);

        $allowedResp = $this->withHeader('Authorization', 'Bearer ' . $tokenRestricted)
            ->getJson('/api/v1/journal-activites');
        $allowedResp->assertStatus(200);
    }

    public function test_journal_activite_factory(): void
    {
        $log = JournalActivite::factory()->create([
            'site_id' => $this->siteA->id,
        ]);

        $this->assertDatabaseHas('journaux_activites', [
            'id' => $log->id,
            'site_id' => $this->siteA->id,
        ]);
    }
}
