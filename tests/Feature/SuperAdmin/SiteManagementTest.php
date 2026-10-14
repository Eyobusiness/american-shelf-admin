<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Configuration;
use App\Models\Role;
use App\Models\Site;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SiteManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Site $rootSite;
    protected Role $superAdminRole;
    protected Role $adminRole;
    protected Utilisateur $superAdminUser;
    protected Utilisateur $siteAdminUser;

    protected string $superAdminToken;
    protected string $siteAdminToken;

    /**
     * @return \PHPOpenSourceSaver\JWTAuth\JWTGuard
     */
    protected function jwtGuard()
    {
        /** @var \PHPOpenSourceSaver\JWTAuth\JWTGuard $guard */
        $guard = auth('api');
        return $guard;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->jwtGuard()->logout();

        // Site racine hébergeant l'administration centrale
        $this->rootSite = Site::create([
            'code' => 'SITE-CENTRAL-ROOT',
            'nom' => 'Siège Central American Shelf',
            'statut' => 'ACTIF',
        ]);

        $this->superAdminRole = Role::create([
            'site_id' => $this->rootSite->id,
            'code' => 'SUPERADMIN',
            'nom' => 'Super Administrateur',
            'statut' => 'ACTIF',
        ]);

        $this->adminRole = Role::create([
            'site_id' => $this->rootSite->id,
            'code' => 'ADMIN',
            'nom' => 'Administrateur Local',
            'statut' => 'ACTIF',
        ]);

        $this->superAdminUser = Utilisateur::create([
            'site_id' => $this->rootSite->id,
            'nom' => 'Super',
            'prenom' => 'Admin',
            'email' => 'superadmin.sites@americanshelf.ci',
            'mot_de_passe' => Hash::make('Secret123!'),
            'role_id' => $this->superAdminRole->id,
            'statut' => 'ACTIF',
        ]);

        $this->siteAdminUser = Utilisateur::create([
            'site_id' => $this->rootSite->id,
            'nom' => 'Admin',
            'prenom' => 'Local',
            'email' => 'localadmin.sites@americanshelf.ci',
            'mot_de_passe' => Hash::make('Secret123!'),
            'role_id' => $this->adminRole->id,
            'statut' => 'ACTIF',
        ]);
    }

    protected function tokenFor(Utilisateur $user): string
    {
        $this->jwtGuard()->logout();
        return $this->jwtGuard()->login($user);
    }

    /**
     * Le Super Admin peut lister les sites avec pagination, recherche et filtres.
     */
    public function test_super_admin_can_list_sites_with_pagination_and_filters(): void
    {
        $token = $this->tokenFor($this->superAdminUser);

        // Création de 2 sites de test
        Site::create([
            'code' => 'TEST-LIST-1',
            'nom' => 'Bibliothèque Yopougon',
            'statut' => 'ACTIF',
        ]);
        Site::create([
            'code' => 'TEST-LIST-2',
            'nom' => 'Bibliothèque Bouaké',
            'statut' => 'INACTIF',
        ]);

        // 1. Liste complète
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/super-admin/sites');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'nom',
                        'statut',
                        'statistiques_rapides' => [
                            'livres_count',
                            'exemplaires_count',
                            'usagers_count',
                            'utilisateurs_count',
                        ],
                    ],
                ],
                'meta' => ['current_page', 'total'],
            ]);

        // 2. Filtre par statut ACTIF
        $filterResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/super-admin/sites?statut=ACTIF');

        $filterResponse->assertStatus(200);
        foreach ($filterResponse->json('data') as $item) {
            $this->assertEquals('ACTIF', $item['statut']);
        }

        // 3. Recherche par nom
        $searchResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/super-admin/sites?search=Yopougon');

        $searchResponse->assertStatus(200);
        $this->assertTrue(collect($searchResponse->json('data'))->contains('code', 'TEST-LIST-1'));
    }

    /**
     * Création atomique d'un site avec son premier administrateur, ses rôles et sa configuration.
     */
    public function test_super_admin_can_create_site_with_admin_and_configuration_atomically(): void
    {
        $token = $this->tokenFor($this->superAdminUser);

        $payload = [
            'code' => 'ASA-YOP',
            'nom' => 'American Shelf Yopougon',
            'description' => 'Nouvelle antenne communale',
            'adresse' => 'Yopougon Selmer',
            'telephone' => '+225 0102030405',
            'email' => 'contact@yopougon-shelf.ci',
            'statut' => 'ACTIF',
            'admin' => [
                'nom' => 'Touré',
                'prenom' => 'Ibrahim',
                'email' => 'admin.yop@americanshelf.ci',
                'mot_de_passe' => 'MotDePasseSecurise123!',
                'telephone' => '+225 0708091011',
            ],
            'configuration' => [
                'duree_emprunt' => 21,
                'nombre_jours_grace' => 5,
                'duree_consultation' => 3,
                'quota_emprunt' => 4,
                'duree_reservation' => 2,
            ],
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/super-admin/sites', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Site, configuration et premier administrateur créés avec succès.',
            ]);

        $siteId = $response->json('data.id');
        $this->assertNotNull($siteId);

        // 1. Vérification du site en base
        $this->assertDatabaseHas('sites', [
            'id' => $siteId,
            'code' => 'ASA-YOP',
            'nom' => 'American Shelf Yopougon',
            'statut' => 'ACTIF',
        ]);

        // 2. Vérification de la configuration rattachée
        $this->assertDatabaseHas('configurations', [
            'site_id' => $siteId,
            'duree_emprunt' => 21,
            'nombre_jours_grace' => 5,
            'quota_emprunt' => 4,
        ]);

        // 3. Vérification des rôles initialisés
        $this->assertDatabaseHas('roles', [
            'site_id' => $siteId,
            'code' => 'ADMIN',
        ]);
        $this->assertDatabaseHas('roles', [
            'site_id' => $siteId,
            'code' => 'BIBLIO',
        ]);

        // 4. Vérification de l'administrateur rattaché et actif
        $admin = Utilisateur::where('email', 'admin.yop@americanshelf.ci')->first();
        $this->assertNotNull($admin);
        $this->assertEquals($siteId, $admin->site_id);
        $this->assertEquals('Touré', $admin->nom);
        $this->assertEquals('ACTIF', $admin->statut);
        $this->assertTrue(Hash::check('MotDePasseSecurise123!', $admin->mot_de_passe));
        $this->assertEquals('ADMIN', $admin->role->code);
    }

    /**
     * La transaction est annulée si les données de l'administrateur sont invalides.
     */
    public function test_creation_rolls_back_atomically_if_admin_data_is_invalid(): void
    {
        $token = $this->tokenFor($this->superAdminUser);

        // Utilisateur existant avec cet email
        Utilisateur::create([
            'site_id' => $this->rootSite->id,
            'nom' => 'Existant',
            'prenom' => 'Compte',
            'email' => 'conflit.email@americanshelf.ci',
            'mot_de_passe' => Hash::make('Pass123!'),
            'role_id' => $this->adminRole->id,
            'statut' => 'ACTIF',
        ]);

        $payload = [
            'code' => 'ASA-FAIL-SITE',
            'nom' => 'Site Qui Doit Échouer',
            'admin' => [
                'nom' => 'Nouveau',
                'prenom' => 'Admin',
                'email' => 'conflit.email@americanshelf.ci', // Déjà utilisé -> Erreur 422
                'mot_de_passe' => 'MotDePasseSecurise123!',
            ],
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/super-admin/sites', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['admin.email']);

        // Le site ne doit PAS avoir été créé en base
        $this->assertDatabaseMissing('sites', ['code' => 'ASA-FAIL-SITE']);
    }

    /**
     * Le Super Admin peut consulter les détails d'un site avec son administrateur.
     */
    public function test_super_admin_can_view_site_details(): void
    {
        $token = $this->tokenFor($this->superAdminUser);

        $site = Site::create([
            'code' => 'ASA-DETAILS',
            'nom' => 'Site Pour Détails',
            'statut' => 'ACTIF',
        ]);

        Configuration::create([
            'site_id' => $site->id,
            'duree_emprunt' => 14,
            'nombre_jours_grace' => 2,
            'duree_consultation' => 2,
            'quota_emprunt' => 3,
            'duree_reservation' => 3,
        ]);

        $adminRole = Role::create([
            'site_id' => $site->id,
            'code' => 'ADMIN',
            'nom' => 'Administrateur',
            'statut' => 'ACTIF',
        ]);

        Utilisateur::create([
            'site_id' => $site->id,
            'role_id' => $adminRole->id,
            'nom' => 'AdminDétail',
            'prenom' => 'Marc',
            'email' => 'detail.admin@americanshelf.ci',
            'mot_de_passe' => Hash::make('Pass123!'),
            'statut' => 'ACTIF',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/super-admin/sites/' . $site->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $site->id,
                    'code' => 'ASA-DETAILS',
                    'admin' => [
                        'email' => 'detail.admin@americanshelf.ci',
                        'nom' => 'AdminDétail',
                    ],
                    'configuration' => [
                        'duree_emprunt' => 14,
                    ],
                ],
            ]);
    }

    /**
     * Le Super Admin peut modifier les informations générales d'un site.
     */
    public function test_super_admin_can_update_site_information(): void
    {
        $token = $this->tokenFor($this->superAdminUser);

        $site = Site::create([
            'code' => 'ASA-ORIGINAL',
            'nom' => 'Nom Original',
            'statut' => 'ACTIF',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/super-admin/sites/' . $site->id, [
                'nom' => 'Nouveau Nom Modifié',
                'adresse' => 'Nouvelle Adresse 123',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'nom' => 'Nouveau Nom Modifié',
                    'adresse' => 'Nouvelle Adresse 123',
                ],
            ]);

        $this->assertDatabaseHas('sites', [
            'id' => $site->id,
            'nom' => 'Nouveau Nom Modifié',
            'adresse' => 'Nouvelle Adresse 123',
        ]);
    }

    /**
     * Le Super Admin peut modifier le statut d'un site (SUSPENDU, INACTIF, ACTIF).
     */
    public function test_super_admin_can_change_site_status(): void
    {
        $token = $this->tokenFor($this->superAdminUser);

        $site = Site::create([
            'code' => 'ASA-STATUS-TEST',
            'nom' => 'Site Pour Statut',
            'statut' => 'ACTIF',
        ]);

        // Suspension du site
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/super-admin/sites/' . $site->id . '/status', [
                'statut' => 'SUSPENDU',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'statut' => 'SUSPENDU',
                ],
            ]);

        $this->assertDatabaseHas('sites', [
            'id' => $site->id,
            'statut' => 'SUSPENDU',
        ]);
    }

    /**
     * Le Super Admin peut créer ou affecter un administrateur sur un site existant.
     */
    public function test_super_admin_can_assign_new_admin_to_site(): void
    {
        $token = $this->tokenFor($this->superAdminUser);

        $site = Site::create([
            'code' => 'ASA-ORPHAN',
            'nom' => 'Site Sans Admin Initial',
            'statut' => 'ACTIF',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/super-admin/sites/' . $site->id . '/admin', [
                'nom' => 'Konan',
                'prenom' => 'Serge',
                'email' => 'serge.konan@americanshelf.ci',
                'mot_de_passe' => 'MotDePasseFort123!',
                'telephone' => '+225 0505050505',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Administrateur assigné au site avec succès.',
            ]);

        $this->assertDatabaseHas('utilisateurs', [
            'site_id' => $site->id,
            'email' => 'serge.konan@americanshelf.ci',
            'nom' => 'Konan',
        ]);

        $assignedUser = Utilisateur::where('email', 'serge.konan@americanshelf.ci')->first();
        $this->assertEquals('ADMIN', $assignedUser->role->code);
    }

    /**
     * Le Super Admin peut supprimer logiquement et restaurer un site.
     */
    public function test_super_admin_can_soft_delete_and_restore_site(): void
    {
        $token = $this->tokenFor($this->superAdminUser);

        $site = Site::create([
            'code' => 'ASA-TO-DELETE',
            'nom' => 'Site À Supprimer',
            'statut' => 'ACTIF',
        ]);

        // 1. Suppression logique
        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/super-admin/sites/' . $site->id);

        $deleteResponse->assertStatus(200);
        $this->assertSoftDeleted('sites', ['id' => $site->id]);

        // 2. Restauration
        $restoreResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/super-admin/sites/' . $site->id . '/restore');

        $restoreResponse->assertStatus(200);
        $this->assertNotSoftDeleted('sites', ['id' => $site->id]);
    }

    /**
     * Un administrateur de site ou un utilisateur ordinaire est rejeté (403 Forbidden).
     */
    public function test_site_admin_is_forbidden_from_super_admin_site_management(): void
    {
        $localToken = $this->tokenFor($this->siteAdminUser);

        // Tentative de listing
        $listResponse = $this->withHeader('Authorization', 'Bearer ' . $localToken)
            ->getJson('/api/v1/super-admin/sites');

        $listResponse->assertStatus(403);

        // Tentative de création
        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $localToken)
            ->postJson('/api/v1/super-admin/sites', [
                'code' => 'PIRATE-SITE',
                'nom' => 'Site Non Autorisé',
                'admin' => [
                    'nom' => 'Pirate',
                    'prenom' => 'User',
                    'email' => 'pirate@example.com',
                    'mot_de_passe' => 'Pass123456!',
                ],
            ]);

        $createResponse->assertStatus(403);
    }
}
