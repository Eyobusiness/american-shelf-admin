<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RolePermissionCrudTest extends TestCase
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
            'code' => 'SITE-ROLE-A',
            'nom' => 'Site Role A',
            'statut' => 'ACTIF',
        ]);

        $this->siteB = Site::create([
            'code' => 'SITE-ROLE-B',
            'nom' => 'Site Role B',
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
            'email' => 'admin.role.a@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $this->adminRoleA->id,
            'statut' => 'ACTIF',
        ]);

        $this->tokenA = auth('api')->login($this->adminA);
    }

    public function test_can_create_role_and_sync_permissions_within_same_site(): void
    {
        // 1. Créer permissions sur Site A
        $perm1 = Permission::create([
            'site_id' => $this->siteA->id,
            'action' => 'READ',
            'code' => 'TEST_DOC_READ',
            'libelle' => 'Lire doc',
        ]);

        $perm2 = Permission::create([
            'site_id' => $this->siteA->id,
            'action' => 'CREATE',
            'code' => 'TEST_DOC_CREATE',
            'libelle' => 'Créer doc',
        ]);

        // 2. Créer rôle sur Site A
        $roleResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/roles', [
                'code' => 'GESTIONNAIRE',
                'nom' => 'Gestionnaire Documentaire',
                'statut' => 'ACTIF',
            ]);

        $roleResp->assertStatus(201);
        $roleId = $roleResp->json('data.id');

        // 3. Synchroniser les permissions du même site
        $syncResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson('/api/v1/roles/' . $roleId . '/permissions', [
                'permission_ids' => [$perm1->id, $perm2->id]
            ]);

        $syncResp->assertStatus(200);
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $roleId,
            'permission_id' => $perm1->id,
        ]);
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $roleId,
            'permission_id' => $perm2->id,
        ]);
    }

    public function test_cannot_sync_permissions_from_different_site(): void
    {
        // Permission appartenant au Site B
        $permB = Permission::create([
            'site_id' => $this->siteB->id,
            'action' => 'DELETE',
            'code' => 'TEST_CROSS_DELETE',
            'libelle' => 'Supprimer B',
        ]);

        $roleA = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'AGENT-A',
            'nom' => 'Agent A',
            'statut' => 'ACTIF',
        ]);

        // Tentative d'attacher la permission du Site B au rôle du Site A
        $syncResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson('/api/v1/roles/' . $roleA->id . '/permissions', [
                'permission_ids' => [$permB->id]
            ]);

        // Doit échouer avec un code 422 Unprocessable Entity
        $syncResp->assertStatus(422);
        $this->assertDatabaseMissing('role_permissions', [
            'role_id' => $roleA->id,
            'permission_id' => $permB->id,
        ]);
    }
}
