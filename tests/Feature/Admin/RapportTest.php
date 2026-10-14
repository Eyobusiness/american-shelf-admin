<?php

namespace Tests\Feature\Admin;

use App\Models\Acquisition;
use App\Models\CategorieLivre;
use App\Models\CategorieUsager;
use App\Models\Configuration;
use App\Models\Exemplaire;
use App\Models\ExemplaireMouvement;
use App\Models\InventaireSnapshot;
use App\Models\Livre;
use App\Models\Operation;
use App\Models\Permission;
use App\Models\Presence;
use App\Models\Role;
use App\Models\Site;
use App\Models\Usager;
use App\Models\UsagerSite;
use App\Models\Utilisateur;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RapportTest extends TestCase
{
    use DatabaseTransactions;

    protected Site $siteA;
    protected Site $siteB;
    protected Utilisateur $adminA;
    protected string $tokenA;
    protected Configuration $configA;
    protected Role $roleA;
    protected CategorieLivre $defaultCategorieA;
    protected CategorieLivre $defaultCategorieB;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Sites
        $this->siteA = Site::create([
            'code' => 'SITE-RAP-A',
            'nom' => 'Site Rapport A',
            'statut' => 'ACTIF',
        ]);

        $this->siteB = Site::create([
            'code' => 'SITE-RAP-B',
            'nom' => 'Site Rapport B',
            'statut' => 'ACTIF',
        ]);

        // 2. Roles & Permissions
        $this->roleA = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'RESPONSABLE_RAPPORTS',
            'nom' => 'Responsable Rapports',
            'statut' => 'ACTIF',
        ]);

        // Attribuer toutes les permissions de rapports et d'inventaire
        $permissions = Permission::whereIn('code', [
            'RAPPORT_READ',
            'RAPPORT_EXPORT',
            'INVENTAIRE_READ',
            'INVENTAIRE_EXECUTE',
        ])->get();

        $this->roleA->permissions()->sync($permissions->pluck('id'));

        $this->adminA = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'nom' => 'Rapporteur',
            'prenom' => 'Chef',
            'email' => 'rapporteur@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $this->roleA->id,
            'statut' => 'ACTIF',
        ]);

        $this->tokenA = $this->loginAs($this->adminA);

        // 3. Configuration
        $this->configA = Configuration::create([
            'site_id' => $this->siteA->id,
            'duree_emprunt' => 14,
            'nombre_jours_grace' => 2,
            'duree_consultation' => 2,
            'quota_emprunt' => 5,
            'duree_reservation' => 3,
        ]);

        // 4. Catégories par défaut
        $this->defaultCategorieA = CategorieLivre::create([
            'site_id' => $this->siteA->id,
            'code' => 'CAT-DEF-A',
            'nom' => 'Générale A',
        ]);

        $this->defaultCategorieB = CategorieLivre::create([
            'site_id' => $this->siteB->id,
            'code' => 'CAT-DEF-B',
            'nom' => 'Générale B',
        ]);
    }

    /**
     * Génère un jeton JWT valide pour un utilisateur donné avec typage JWTGuard.
     */
    protected function loginAs(Utilisateur $user): string
    {
        /** @var \PHPOpenSourceSaver\JWTAuth\JWTGuard $guard */
        $guard = auth('api');
        return (string) $guard->login($user);
    }

    public function test_fonds_documentaire_report_accurately_counts_stock_and_separates_lost(): void
    {
        $cat = CategorieLivre::create([
            'site_id' => $this->siteA->id,
            'code' => 'INFO-RAP',
            'nom' => 'Informatique & IA',
        ]);

        $livre1 = Livre::create([
            'site_id' => $this->siteA->id,
            'categorie_livre_id' => $cat->id,
            'code' => 'LIV-RAP-01',
            'titre' => 'Architecture Laravel 12',
            'auteur' => 'Taylor O.',
            'annee_publication' => 2025,
            'statut' => 'ACTIF',
        ]);

        // Exemplaire disponible neuf
        Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $livre1->id,
            'code' => 'EX-RAP-01',
            'etat' => 'NEUF',
            'statut' => 'DISPONIBLE',
        ]);

        // Exemplaire emprunté bon
        Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $livre1->id,
            'code' => 'EX-RAP-02',
            'etat' => 'BON',
            'statut' => 'EMPRUNTE',
        ]);

        // Exemplaire perdu (physique: PERDU, opérationnel: INDISPONIBLE)
        Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $livre1->id,
            'code' => 'EX-RAP-03',
            'etat' => 'PERDU',
            'statut' => 'INDISPONIBLE',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/rapports/fonds-documentaire');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'synthese' => [
                        'total_references_bibliographiques' => 1,
                        'total_exemplaires' => 3,
                        'exemplaires_disponibles' => 1,
                        'exemplaires_empruntes' => 1,
                        'exemplaires_perdus' => 1,
                        'exemplaires_indisponibles' => 0, // Ne double-compte pas le perdu
                    ],
                    'repartition_par_etat' => [
                        'NEUF' => 1,
                        'BON' => 1,
                        'PERDU' => 1,
                    ],
                    'repartition_par_statut' => [
                        'DISPONIBLE' => 1,
                        'EMPRUNTE' => 1,
                        'INDISPONIBLE' => 1,
                    ],
                ],
            ]);
    }

    public function test_emprunts_report_calculates_returns_strictly_within_period_and_tracks_retards(): void
    {
        $livre = Livre::create([
            'site_id' => $this->siteA->id,
            'categorie_livre_id' => $this->defaultCategorieA->id,
            'code' => 'LIV-EMP-01',
            'titre' => 'Le Guide du Développeur',
            'auteur' => 'Martin Fowler',
            'statut' => 'ACTIF',
        ]);

        $ex1 = Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $livre->id,
            'code' => 'EX-EMP-01',
            'etat' => 'BON',
            'statut' => 'DISPONIBLE',
        ]);

        $ex2 = Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $livre->id,
            'code' => 'EX-EMP-02',
            'etat' => 'BON',
            'statut' => 'EMPRUNTE',
        ]);

        $catUsager = CategorieUsager::create([
            'site_id' => $this->siteA->id,
            'code' => 'ETUDIANTS',
            'nom' => 'Étudiants',
            'quota_emprunts' => 3,
        ]);

        $usager = Usager::create([
            'nom' => 'Kouame',
            'prenom' => 'Boris',
            'email' => 'boris@example.com',
            'numero' => 'USG-RAP-01',
        ]);

        UsagerSite::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $usager->id,
            'categorie_usager_id' => $catUsager->id,
            'statut' => 'ACTIF',
            'date_inscription' => now(),
        ]);

        // 1. Emprunt commencé avant mai 2026, retourné en mai 2026
        Operation::create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $usager->id,
            'exemplaire_id' => $ex1->id,
            'type' => 'EMPRUNT',
            'date_operation' => '2026-04-10 10:00:00',
            'date_retour_prevue' => '2026-04-24 10:00:00',
            'date_retour_effective' => '2026-05-05 14:00:00',
            'statut' => 'RETOURNE',
        ]);

        // 2. Emprunt commencé en mai 2026, non retourné
        Operation::create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $usager->id,
            'exemplaire_id' => $ex2->id,
            'type' => 'EMPRUNT',
            'date_operation' => '2026-05-15 09:00:00',
            'date_retour_prevue' => '2026-05-29 09:00:00',
            'date_retour_effective' => null,
            'statut' => 'EN_COURS',
        ]);

        // 3. Consultation sur place en mai 2026
        Operation::create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $usager->id,
            'exemplaire_id' => $ex1->id,
            'type' => 'CONSULTATION',
            'date_operation' => '2026-05-20 15:00:00',
            'date_retour_prevue' => '2026-05-20 17:00:00',
            'date_retour_effective' => '2026-05-20 16:30:00',
            'statut' => 'RETOURNE',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/rapports/emprunts?date_debut=2026-05-01&date_fin=2026-05-31');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'synthese' => [
                        'emprunts_realises' => 1, // Opération 2 seulement (commencée en mai)
                        'retours_effectues' => 1, // Opération 1 retournée en mai
                        'consultations_sur_place' => 1, // Opération 3 (consultation)
                    ],
                ],
            ]);
    }

    public function test_top_livres_report_respects_period_and_limit(): void
    {
        $livreTop = Livre::create([
            'site_id' => $this->siteA->id,
            'categorie_livre_id' => $this->defaultCategorieA->id,
            'code' => 'LIV-TOP-1',
            'titre' => 'Top Livre En Juin',
            'auteur' => 'Auteur Top',
            'statut' => 'ACTIF',
        ]);

        $livreHorsPeriode = Livre::create([
            'site_id' => $this->siteA->id,
            'categorie_livre_id' => $this->defaultCategorieA->id,
            'code' => 'LIV-TOP-2',
            'titre' => 'Livre Populaire Hors Juin',
            'auteur' => 'Auteur Hors',
            'statut' => 'ACTIF',
        ]);

        $exTop = Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $livreTop->id,
            'code' => 'EX-TOP-1',
            'statut' => 'DISPONIBLE',
        ]);

        $exHors = Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $livreHorsPeriode->id,
            'code' => 'EX-TOP-2',
            'statut' => 'DISPONIBLE',
        ]);

        $usager = Usager::create([
            'nom' => 'Koffi',
            'prenom' => 'Jean',
            'numero' => 'USG-TOP-01',
        ]);

        // 2 emprunts pour livreTop en juin 2026
        for ($i = 0; $i < 2; $i++) {
            Operation::create([
                'site_id' => $this->siteA->id,
                'configuration_id' => $this->configA->id,
                'usager_id' => $usager->id,
                'exemplaire_id' => $exTop->id,
                'type' => 'EMPRUNT',
                'date_operation' => '2026-06-10 10:00:00',
                'date_retour_prevue' => '2026-06-24 10:00:00',
                'statut' => 'RETOURNE',
                'date_retour_effective' => '2026-06-20 10:00:00',
            ]);
        }

        // 5 emprunts pour livreHorsPeriode en janvier 2026
        for ($i = 0; $i < 5; $i++) {
            Operation::create([
                'site_id' => $this->siteA->id,
                'configuration_id' => $this->configA->id,
                'usager_id' => $usager->id,
                'exemplaire_id' => $exHors->id,
                'type' => 'EMPRUNT',
                'date_operation' => '2026-01-10 10:00:00',
                'date_retour_prevue' => '2026-01-24 10:00:00',
                'statut' => 'RETOURNE',
                'date_retour_effective' => '2026-01-20 10:00:00',
            ]);
        }

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/rapports/livres-plus-empruntes?date_debut=2026-06-01&date_fin=2026-06-30&limit=5');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_livres_classes' => 1,
                    'classement' => [
                        [
                            'rang' => 1,
                            'livre_id' => $livreTop->id,
                            'nombre_emprunts' => 2,
                        ],
                    ],
                ],
            ]);
    }

    public function test_retards_report_categorizes_by_brackets_without_overlap(): void
    {
        $livre = Livre::create([
            'site_id' => $this->siteA->id,
            'categorie_livre_id' => $this->defaultCategorieA->id,
            'code' => 'LIV-RET-01',
            'titre' => 'Le Livre en Retard',
            'auteur' => 'Auteur Retard',
            'statut' => 'ACTIF',
        ]);

        $ex1 = Exemplaire::create(['site_id' => $this->siteA->id, 'livre_id' => $livre->id, 'code' => 'EX-RET-1', 'statut' => 'EMPRUNTE']);
        $ex2 = Exemplaire::create(['site_id' => $this->siteA->id, 'livre_id' => $livre->id, 'code' => 'EX-RET-2', 'statut' => 'EMPRUNTE']);
        $ex3 = Exemplaire::create(['site_id' => $this->siteA->id, 'livre_id' => $livre->id, 'code' => 'EX-RET-3', 'statut' => 'EMPRUNTE']);

        $usager = Usager::create(['nom' => 'Yao', 'prenom' => 'David', 'numero' => 'USG-RET-01']);

        // 1. Retard de 5 jours (< 7 jours)
        Operation::create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $usager->id,
            'exemplaire_id' => $ex1->id,
            'type' => 'EMPRUNT',
            'date_operation' => now()->subDays(19),
            'date_retour_prevue' => now()->subDays(5),
            'date_retour_effective' => null,
            'statut' => 'EN_RETARD',
        ]);

        // 2. Retard de 15 jours (7 à 30 jours)
        Operation::create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $usager->id,
            'exemplaire_id' => $ex2->id,
            'type' => 'EMPRUNT',
            'date_operation' => now()->subDays(29),
            'date_retour_prevue' => now()->subDays(15),
            'date_retour_effective' => null,
            'statut' => 'EN_RETARD',
        ]);

        // 3. Retard de 45 jours (> 30 jours)
        Operation::create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $usager->id,
            'exemplaire_id' => $ex3->id,
            'type' => 'EMPRUNT',
            'date_operation' => now()->subDays(59),
            'date_retour_prevue' => now()->subDays(45),
            'date_retour_effective' => null,
            'statut' => 'EN_RETARD',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/rapports/retards');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'synthese_tranches' => [
                        'total_retards_actifs' => 3,
                        'moins_de_7_jours' => 1,
                        'de_7_a_30_jours' => 1,
                        'plus_de_30_jours' => 1,
                    ],
                ],
            ]);
    }

    public function test_acquisitions_report_separates_achats_and_dons(): void
    {
        // 1. Achat
        Acquisition::create([
            'site_id' => $this->siteA->id,
            'type' => 'ACHAT',
            'date_acquisition' => '2026-07-15',
            'provenance' => 'Librairie de France',
            'quantite' => 10,
            'cout' => 150000.00,
            'etat' => 'NEUF',
        ]);

        // 2. Don (pas de coût / cout = 0)
        Acquisition::create([
            'site_id' => $this->siteA->id,
            'type' => 'DON',
            'date_acquisition' => '2026-07-20',
            'donateur' => 'Ambassade des USA',
            'nature_don' => 'Ouvrages bilingues',
            'quantite' => 25,
            'cout' => 0.00,
            'etat' => 'BON',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/rapports/acquisitions?date_debut=2026-07-01&date_fin=2026-07-31');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'synthese' => [
                        'total_operations' => 2,
                        'quantite_totale_acquise' => 35,
                        'achats' => [
                            'nombre_operations' => 1,
                            'quantite_acquise' => 10,
                            'montant_total' => 150000.0,
                        ],
                        'dons' => [
                            'nombre_operations' => 1,
                            'quantite_acquise' => 25,
                        ],
                    ],
                ],
            ]);
    }

    public function test_frequentation_report_integrates_presence_service_and_counts_uniques(): void
    {
        $usager1 = Usager::create(['nom' => 'Diallo', 'prenom' => 'Amadou', 'numero' => 'USG-PR-01']);
        $usager2 = Usager::create(['nom' => 'Konan', 'prenom' => 'Aimé', 'numero' => 'USG-PR-02']);

        // 2 passages pour usager 1 aujourd'hui
        Presence::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $usager1->id,
            'date_presence' => now()->toDateString(),
            'heure_arrivee' => '09:00',
            'heure_depart' => '11:00',
            'motif' => 'Lecture',
        ]);
        Presence::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $usager1->id,
            'date_presence' => now()->toDateString(),
            'heure_arrivee' => '14:00',
            'heure_depart' => '16:00',
            'motif' => 'Recherche',
        ]);

        // 1 passage pour usager 2 aujourd'hui
        Presence::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $usager2->id,
            'date_presence' => now()->toDateString(),
            'heure_arrivee' => '10:00',
            'heure_depart' => '12:00',
            'motif' => 'Internet',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/rapports/frequentation?date_debut=' . now()->toDateString() . '&date_fin=' . now()->toDateString());

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'bibliotheque' => [
                        'total_passages' => 3,
                        'visiteurs_uniques_periode' => 2,
                    ],
                ],
            ]);
    }

    public function test_declencher_snapshot_and_historique_inventory_lifecycle(): void
    {
        $livre = Livre::create([
            'site_id' => $this->siteA->id,
            'categorie_livre_id' => $this->defaultCategorieA->id,
            'code' => 'LIV-INV-01',
            'titre' => 'Inventaire An 1',
            'auteur' => 'Auteur Inv',
            'statut' => 'ACTIF',
        ]);

        Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $livre->id,
            'code' => 'EX-INV-01',
            'etat' => 'NEUF',
            'statut' => 'DISPONIBLE',
        ]);

        // 1. Déclencher un snapshot via endpoint
        $postResponse = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/rapports/inventaire/snapshot', [
                'date' => '2026-08-01',
            ]);

        $postResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'site_id' => $this->siteA->id,
                    'annee' => 2026,
                    'mois' => 8,
                    'total_livres' => 1,
                    'total_exemplaires' => 1,
                    'total_disponibles' => 1,
                ],
            ]);

        // 2. Consulter l'historique
        $getResponse = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/rapports/inventaire/historique?date_debut=2026-08-01&date_fin=2026-08-31');

        $getResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'snapshots_disponibles' => 1,
                    'snapshots' => [
                        [
                            'date_snapshot' => '2026-08-01',
                            'total_livres' => 1,
                            'total_exemplaires' => 1,
                        ],
                    ],
                ],
            ]);
    }

    public function test_artisan_snapshot_command_is_idempotent(): void
    {
        $exitCode1 = Artisan::call('inventaire:snapshot', [
            '--site' => $this->siteA->id,
            '--date' => '2026-09-01',
        ]);
        $this->assertEquals(0, $exitCode1);

        $this->assertDatabaseHas('inventaire_snapshots', [
            'site_id' => $this->siteA->id,
            'date_snapshot' => '2026-09-01',
        ]);

        $countInitial = InventaireSnapshot::where('site_id', $this->siteA->id)
            ->where('date_snapshot', '2026-09-01')
            ->count();
        $this->assertEquals(1, $countInitial);

        // Exécuter une deuxième fois pour tester l'idempotence
        $exitCode2 = Artisan::call('inventaire:snapshot', [
            '--site' => $this->siteA->id,
            '--date' => '2026-09-01',
        ]);
        $this->assertEquals(0, $exitCode2);

        $countSecond = InventaireSnapshot::where('site_id', $this->siteA->id)
            ->where('date_snapshot', '2026-09-01')
            ->count();
        $this->assertEquals(1, $countSecond, "La commande doit être idempotente et ne pas créer de doublon.");
    }

    public function test_export_endpoints_generate_valid_files(): void
    {
        // 1. Export Excel Fonds documentaire
        $xlsxResponse = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->get('/api/v1/rapports/fonds-documentaire/export?format=xlsx');

        $xlsxResponse->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml.sheet', (string) $xlsxResponse->headers->get('content-type'));

        // 2. Export PDF Emprunts
        $pdfResponse = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->get('/api/v1/rapports/emprunts/export?format=pdf');

        $pdfResponse->assertStatus(200);
        $this->assertStringContainsString('application/pdf', (string) $pdfResponse->headers->get('content-type'));
    }

    public function test_tenant_isolation_prevents_seeing_other_site_data(): void
    {
        // Créer un livre sur site B
        Livre::create([
            'site_id' => $this->siteB->id,
            'categorie_livre_id' => $this->defaultCategorieB->id,
            'code' => 'LIV-SITE-B',
            'titre' => 'Livre Exclusif Site B',
            'auteur' => 'Auteur Site B',
            'statut' => 'ACTIF',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/rapports/fonds-documentaire');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'synthese' => [
                        'total_references_bibliographiques' => 0,
                        'total_exemplaires' => 0,
                    ],
                ],
            ]);
    }

    public function test_reports_endpoints_require_permissions(): void
    {
        // Créer un utilisateur sans permission
        $roleSansPerm = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'SANS_PERM',
            'nom' => 'Sans Permissions',
            'statut' => 'ACTIF',
        ]);

        $userSansPerm = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'nom' => 'Lambda',
            'prenom' => 'User',
            'email' => 'lambda@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $roleSansPerm->id,
            'statut' => 'ACTIF',
        ]);

        $tokenSansPerm = $this->loginAs($userSansPerm);

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenSansPerm)
            ->getJson('/api/v1/rapports/fonds-documentaire');

        $response->assertStatus(403);
    }
}
