<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Sauvegarde;
use App\Models\Site;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SauvegardeTest extends TestCase
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
            'code' => 'SITE-SVG-A',
            'nom' => 'Site Sauvegarde A',
            'statut' => 'ACTIF',
        ]);

        $this->siteB = Site::create([
            'code' => 'SITE-SVG-B',
            'nom' => 'Site Sauvegarde B',
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
            'prenom' => 'SVG',
            'email' => 'admin.svg@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $roleA->id,
            'statut' => 'ACTIF',
        ]);

        $this->tokenA = auth('api')->login($this->adminA);
    }

    public function test_can_list_sauvegardes(): void
    {
        Sauvegarde::create([
            'site_id' => $this->siteA->id,
            'nom_fichier' => 'backup_1.sql',
            'chemin_fichier' => '/backups/backup_1.sql',
            'taille' => 10240,
            'date_sauvegarde' => now(),
            'statut' => 'TERMINE',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/sauvegardes');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_show_sauvegarde_detail(): void
    {
        $svg = Sauvegarde::create([
            'site_id' => $this->siteA->id,
            'nom_fichier' => 'backup_detail.sql',
            'chemin_fichier' => '/backups/backup_detail.sql',
            'taille' => 20480,
            'date_sauvegarde' => now(),
            'statut' => 'TERMINE',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/v1/sauvegardes/{$svg->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $svg->id,
                    'nom_fichier' => 'backup_detail.sql',
                    'taille' => 20480,
                ],
            ]);
    }

    public function test_can_soft_delete_and_restore_sauvegarde(): void
    {
        $svg = Sauvegarde::create([
            'site_id' => $this->siteA->id,
            'nom_fichier' => 'backup_delete.sql',
            'chemin_fichier' => '/backups/backup_delete.sql',
            'taille' => 5120,
            'date_sauvegarde' => now(),
            'statut' => 'TERMINE',
        ]);

        // Suppression logique
        $delResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->deleteJson("/api/v1/sauvegardes/{$svg->id}");
        $delResp->assertStatus(200);

        $this->assertSoftDeleted('sauvegardes', ['id' => $svg->id]);

        // Restauration
        $restoreResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/sauvegardes/{$svg->id}/restore");
        $restoreResp->assertStatus(200);

        $this->assertNotSoftDeleted('sauvegardes', ['id' => $svg->id]);
    }

    public function test_cross_tenant_isolation_on_sauvegardes(): void
    {
        $svgB = Sauvegarde::create([
            'site_id' => $this->siteB->id,
            'nom_fichier' => 'backup_site_b.sql',
            'chemin_fichier' => '/backups/backup_b.sql',
            'taille' => 4096,
            'date_sauvegarde' => now(),
            'statut' => 'TERMINE',
        ]);

        // Lecture depuis Site A -> 404
        $readResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/v1/sauvegardes/{$svgB->id}");
        $readResp->assertStatus(404);

        // Suppression depuis Site A -> 404
        $delResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->deleteJson("/api/v1/sauvegardes/{$svgB->id}");
        $delResp->assertStatus(404);
        $this->assertNotSoftDeleted('sauvegardes', ['id' => $svgB->id]);
    }

    public function test_permissions_enforcement_on_sauvegardes(): void
    {
        $restrictedRole = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'STAGIAIRE_SVG',
            'nom' => 'Stagiaire SVG',
            'statut' => 'ACTIF',
        ]);

        $restrictedUser = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'nom' => 'Stagiaire',
            'prenom' => 'Svg',
            'email' => 'stagiaire.svg@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $restrictedRole->id,
            'statut' => 'ACTIF',
        ]);

        $tokenRestricted = auth('api')->login($restrictedUser);

        // Sans permission SAUVEGARDE_READ -> 403
        $resp = $this->withHeader('Authorization', 'Bearer ' . $tokenRestricted)
            ->getJson('/api/v1/sauvegardes');
        $resp->assertStatus(403);

        // Assigner la permission
        $perm = Permission::create([
            'site_id' => $this->siteA->id,
            'code' => 'SAUVEGARDE_READ',
            'libelle' => 'Lecture sauvegardes',
            'action' => 'READ',
        ]);
        $restrictedRole->permissions()->attach($perm->id);

        $allowedResp = $this->withHeader('Authorization', 'Bearer ' . $tokenRestricted)
            ->getJson('/api/v1/sauvegardes');
        $allowedResp->assertStatus(200);
    }

    public function test_sauvegarde_factory(): void
    {
        $svg = Sauvegarde::factory()->create([
            'site_id' => $this->siteA->id,
        ]);

        $this->assertDatabaseHas('sauvegardes', [
            'id' => $svg->id,
            'site_id' => $this->siteA->id,
        ]);
    }
}
