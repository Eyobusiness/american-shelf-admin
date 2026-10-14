<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\CategorieLivre;
use App\Models\Configuration;
use App\Models\Exemplaire;
use App\Models\Livre;
use App\Models\Operation;
use App\Models\Presence;
use App\Models\Role;
use App\Models\Site;
use App\Models\Usager;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GlobalDashboardTest extends TestCase
{
    use DatabaseTransactions;

    protected Site $siteA;
    protected Site $siteB;
    protected Site $siteC;

    protected Role $superAdminRole;
    protected Role $adminRole;

    protected Configuration $configA;
    protected Configuration $configB;

    protected Utilisateur $superAdminUser;
    protected Utilisateur $siteAdminUser;

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

        // 1. Création de 3 sites distincts avec statuts différents
        $this->siteA = Site::create([
            'code' => 'GDASH-SITE-A',
            'nom' => 'Site Actif A',
            'statut' => 'ACTIF',
        ]);

        $this->siteB = Site::create([
            'code' => 'GDASH-SITE-B',
            'nom' => 'Site Inactif B',
            'statut' => 'INACTIF',
        ]);

        $this->siteC = Site::create([
            'code' => 'GDASH-SITE-C',
            'nom' => 'Site Suspendu C',
            'statut' => 'SUSPENDU',
        ]);

        // Configurations
        $this->configA = Configuration::create([
            'site_id' => $this->siteA->id,
            'duree_emprunt' => 14,
            'nombre_jours_grace' => 2,
            'duree_consultation' => 2,
            'quota_emprunt' => 3,
            'duree_reservation' => 3,
        ]);

        $this->configB = Configuration::create([
            'site_id' => $this->siteB->id,
            'duree_emprunt' => 14,
            'nombre_jours_grace' => 2,
            'duree_consultation' => 2,
            'quota_emprunt' => 3,
            'duree_reservation' => 3,
        ]);

        Configuration::create([
            'site_id' => $this->siteC->id,
            'duree_emprunt' => 14,
            'nombre_jours_grace' => 2,
            'duree_consultation' => 2,
            'quota_emprunt' => 3,
            'duree_reservation' => 3,
        ]);

        // Rôles
        $this->superAdminRole = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'SUPERADMIN',
            'nom' => 'Super Administrateur',
            'statut' => 'ACTIF',
        ]);

        $this->adminRole = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'ADMIN',
            'nom' => 'Administrateur Local',
            'statut' => 'ACTIF',
        ]);

        // Utilisateurs
        $this->superAdminUser = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'role_id' => $this->superAdminRole->id,
            'nom' => 'Super',
            'prenom' => 'Admin',
            'email' => 'superadmin.gdash@americanshelf.ci',
            'mot_de_passe' => Hash::make('Secret123!'),
            'statut' => 'ACTIF',
        ]);

        $this->siteAdminUser = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'role_id' => $this->adminRole->id,
            'nom' => 'Local',
            'prenom' => 'Admin',
            'email' => 'localadmin.gdash@americanshelf.ci',
            'mot_de_passe' => Hash::make('Secret123!'),
            'statut' => 'ACTIF',
        ]);
    }

    protected function tokenFor(Utilisateur $user): string
    {
        $this->jwtGuard()->logout();
        return $this->jwtGuard()->login($user);
    }

    /**
     * Le Super Admin récupère les statistiques globales consolidées de l'ensemble des sites.
     */
    public function test_super_admin_can_fetch_global_dashboard_data(): void
    {
        $token = $this->tokenFor($this->superAdminUser);

        // 1. Données documentaires Site A
        $catA = CategorieLivre::create([
            'site_id' => $this->siteA->id,
            'code' => 'LIT-GEN',
            'nom' => 'Littérature Générale',
            'statut' => 'ACTIF',
        ]);

        $livreA = Livre::create([
            'site_id' => $this->siteA->id,
            'code' => 'LIV-GDASH-A',
            'titre' => 'Roman Africain',
            'auteur' => 'Auteur A',
            'langue' => 'Français',
            'categorie_livre_id' => $catA->id,
            'nb_exemplaires_total' => 2,
            'nb_exemplaires_disponibles' => 1,
        ]);

        $exA1 = Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $livreA->id,
            'code' => 'EX-GD-A1',
            'code_barre' => 'BARRE-GD-A1',
            'etat' => 'BON',
            'statut' => 'DISPONIBLE',
        ]);

        $exA2 = Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $livreA->id,
            'code' => 'EX-GD-A2',
            'code_barre' => 'BARRE-GD-A2',
            'etat' => 'NEUF',
            'statut' => 'EMPRUNTE',
        ]);

        // 2. Données documentaires Site B
        $catB = CategorieLivre::create([
            'site_id' => $this->siteB->id,
            'code' => 'SCI-TECH',
            'nom' => 'Sciences et Tech',
            'statut' => 'ACTIF',
        ]);

        $livreB = Livre::create([
            'site_id' => $this->siteB->id,
            'code' => 'LIV-GDASH-B',
            'titre' => 'Informatique Quantique',
            'auteur' => 'Auteur B',
            'langue' => 'Anglais',
            'categorie_livre_id' => $catB->id,
            'nb_exemplaires_total' => 1,
            'nb_exemplaires_disponibles' => 0,
        ]);

        $exB1 = Exemplaire::create([
            'site_id' => $this->siteB->id,
            'livre_id' => $livreB->id,
            'code' => 'EX-GD-B1',
            'code_barre' => 'BARRE-GD-B1',
            'etat' => 'DEGRADE',
            'statut' => 'EN_CONSULTATION',
        ]);

        // 3. Usager pour les opérations
        $usager = Usager::create([
            'numero' => 'USG-GD-001',
            'nom' => 'Lecteur',
            'prenom' => 'Test',
            'email' => 'lecteur.gd@example.com',
            'statut' => 'ACTIF',
        ]);

        // 4. Opérations
        // Emprunt en cours sur Site A
        Operation::create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $usager->id,
            'exemplaire_id' => $exA2->id,
            'type' => 'EMPRUNT',
            'date_operation' => now(),
            'date_retour_prevue' => now()->addDays(14),
            'statut' => 'EN_COURS',
        ]);

        // Consultation en cours sur Site B
        Operation::create([
            'site_id' => $this->siteB->id,
            'configuration_id' => $this->configB->id,
            'usager_id' => $usager->id,
            'exemplaire_id' => $exB1->id,
            'type' => 'CONSULTATION',
            'date_operation' => now(),
            'statut' => 'EN_COURS',
        ]);

        // 5. Présences
        Presence::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $usager->id,
            'date_presence' => now()->toDateString(),
            'heure_arrivee' => '09:00:00',
        ]);

        Presence::create([
            'site_id' => $this->siteB->id,
            'usager_id' => null, // Visiteur anonyme
            'visiteur_anonyme_id' => 'ANON-12345',
            'date_presence' => now()->toDateString(),
            'heure_arrivee' => '10:30:00',
        ]);

        // Appel de l'endpoint
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/super-admin/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'periode' => ['date_debut', 'date_fin'],
                    'sites' => ['total', 'actifs', 'inactifs', 'suspendus'],
                    'fonds_documentaire' => [
                        'total_livres',
                        'total_exemplaires',
                        'total_categories',
                        'categories_uniques',
                        'exemplaires_par_etat',
                        'exemplaires_par_statut',
                    ],
                    'emprunts' => [
                        'total_periode',
                        'en_cours',
                        'termines',
                        'en_retard',
                        'total_historique',
                    ],
                    'consultations' => [
                        'total_periode',
                        'en_cours',
                        'terminees',
                        'total_historique',
                    ],
                    'presences' => [
                        'total_periode',
                        'du_jour',
                        'du_mois',
                        'usagers_adherents',
                        'visiteurs_anonymes',
                    ],
                    'comparaison_sites' => [
                        '*' => [
                            'site_id',
                            'code',
                            'nom',
                            'statut',
                            'total_livres',
                            'total_exemplaires',
                            'emprunts_en_cours',
                            'consultations_en_cours',
                            'presences_periode',
                        ],
                    ],
                    'evolution_activites' => [
                        '*' => [
                            'mois',
                            'label',
                            'emprunts',
                            'consultations',
                            'presences',
                        ],
                    ],
                ],
            ]);

        $data = $response->json('data');

        // Vérification des indicateurs consolidés
        $this->assertGreaterThanOrEqual(3, $data['sites']['total']);
        $this->assertGreaterThanOrEqual(1, $data['sites']['actifs']);
        $this->assertGreaterThanOrEqual(1, $data['sites']['inactifs']);
        $this->assertGreaterThanOrEqual(1, $data['sites']['suspendus']);

        $this->assertGreaterThanOrEqual(2, $data['fonds_documentaire']['total_livres']);
        $this->assertGreaterThanOrEqual(3, $data['fonds_documentaire']['total_exemplaires']);
        $this->assertArrayHasKey('BON', $data['fonds_documentaire']['exemplaires_par_etat']);
        $this->assertArrayHasKey('NEUF', $data['fonds_documentaire']['exemplaires_par_etat']);

        $this->assertGreaterThanOrEqual(1, $data['emprunts']['en_cours']);
        $this->assertGreaterThanOrEqual(1, $data['consultations']['en_cours']);
        $this->assertGreaterThanOrEqual(2, $data['presences']['total_periode']);
        $this->assertGreaterThanOrEqual(1, $data['presences']['usagers_adherents']);
        $this->assertGreaterThanOrEqual(1, $data['presences']['visiteurs_anonymes']);

        // Vérification de la présence des sites dans le comparatif
        $codesComparaison = collect($data['comparaison_sites'])->pluck('code')->all();
        $this->assertContains('GDASH-SITE-A', $codesComparaison);
        $this->assertContains('GDASH-SITE-B', $codesComparaison);
        $this->assertContains('GDASH-SITE-C', $codesComparaison);
    }

    /**
     * Le filtre par plage de dates personnalisée fonctionne correctement.
     */
    public function test_global_dashboard_supports_custom_date_filter(): void
    {
        $token = $this->tokenFor($this->superAdminUser);

        $dateDebut = now()->subDays(10)->toDateString();
        $dateFin = now()->toDateString();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/v1/super-admin/dashboard?date_debut={$dateDebut}&date_fin={$dateFin}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'periode' => [
                        'date_debut' => $dateDebut,
                        'date_fin' => $dateFin,
                    ],
                ],
            ]);
    }

    /**
     * Un administrateur de site local reçoit 403 Forbidden sur le dashboard global.
     */
    public function test_site_admin_is_forbidden_from_global_dashboard(): void
    {
        $token = $this->tokenFor($this->siteAdminUser);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/super-admin/dashboard');

        $response->assertStatus(403);
    }

    /**
     * Une requête non authentifiée reçoit 401 Unauthorized.
     */
    public function test_unauthenticated_request_is_unauthorized(): void
    {
        $this->jwtGuard()->logout();

        $response = $this->getJson('/api/v1/super-admin/dashboard');

        $response->assertStatus(401);
    }
}
