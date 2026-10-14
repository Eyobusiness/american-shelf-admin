<?php

namespace Tests\Feature\Admin;

use App\Models\CategorieLivre;
use App\Models\CategorieUsager;
use App\Models\Configuration;
use App\Models\Exemplaire;
use App\Models\Livre;
use App\Models\Operation;
use App\Models\Permission;
use App\Models\Presence;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Site;
use App\Models\Usager;
use App\Models\UsagerSite;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use DatabaseTransactions;

    protected Site $siteA;
    protected Site $siteB;
    protected Utilisateur $adminA;
    protected string $tokenA;
    protected Configuration $configA;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Sites
        $this->siteA = Site::create([
            'code' => 'SITE-DASH-A',
            'nom' => 'Site Dashboard A',
            'statut' => 'ACTIF',
        ]);

        $this->siteB = Site::create([
            'code' => 'SITE-DASH-B',
            'nom' => 'Site Dashboard B',
            'statut' => 'ACTIF',
        ]);

        // 2. Roles & Admin
        $roleA = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'ADMIN',
            'nom' => 'Admin A',
            'statut' => 'ACTIF',
        ]);

        $this->adminA = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'nom' => 'Admin',
            'prenom' => 'Site A',
            'email' => 'admin.dash.a@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $roleA->id,
            'statut' => 'ACTIF',
        ]);

        $this->tokenA = $this->loginAs($this->adminA);

        // 3. Configuration
        $this->configA = Configuration::create([
            'site_id' => $this->siteA->id,
            'duree_emprunt' => 14,
            'nombre_jours_grace' => 3,
            'duree_consultation' => 2,
            'quota_emprunt' => 3,
            'duree_reservation' => 3,
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

    public function test_can_access_dashboard_with_admin_role(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Statistiques du tableau de bord récupérées avec succès.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'periode' => ['date_debut', 'date_fin'],
                    'documents' => [
                        'nombre_livres',
                        'nombre_exemplaires',
                        'nombre_exemplaires_disponibles',
                        'nombre_exemplaires_empruntes',
                        'nombre_exemplaires_en_consultation',
                        'nombre_exemplaires_indisponibles',
                        'nombre_exemplaires_perdus',
                    ],
                    'usagers' => [
                        'nombre_total_usagers',
                        'nombre_adhesions_actives',
                        'nombre_adhesions_suspendues',
                        'nombre_adhesions_expirees',
                        'nouveaux_usagers_periode',
                    ],
                    'activites' => [
                        'emprunts' => ['du_jour', 'periode', 'en_cours', 'retournes', 'en_retard'],
                        'consultations' => ['du_jour', 'periode', 'en_cours', 'terminees'],
                        'reservations' => ['actives', 'en_attente', 'expirees', 'annulees', 'honorees'],
                    ],
                    'presences' => ['du_jour', 'periode'],
                    'top_livres',
                    'categories' => ['livres_par_categorie', 'emprunts_par_categorie'],
                ],
            ]);
    }

    public function test_cannot_access_dashboard_without_permission(): void
    {
        $restrictedRole = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'STAGIAIRE_DASH',
            'nom' => 'Stagiaire Dashboard',
            'statut' => 'ACTIF',
        ]);

        $restrictedUser = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'nom' => 'Stagiaire',
            'prenom' => 'Dash',
            'email' => 'stagiaire.dash@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $restrictedRole->id,
            'statut' => 'ACTIF',
        ]);

        $tokenRestricted = $this->loginAs($restrictedUser);

        // Sans permission DASHBOARD_READ -> 403
        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenRestricted)
            ->getJson('/api/v1/dashboard');
        $response->assertStatus(403);

        // Assigner la permission
        $perm = Permission::create([
            'site_id' => $this->siteA->id,
            'code' => 'DASHBOARD_READ',
            'libelle' => 'Lecture du dashboard',
            'action' => 'READ',
        ]);
        $restrictedRole->permissions()->attach($perm->id);

        // Maintenant autorisé
        $allowed = $this->withHeader('Authorization', 'Bearer ' . $tokenRestricted)
            ->getJson('/api/v1/dashboard');
        $allowed->assertStatus(200);
    }

    public function test_dashboard_period_filtering_and_validation(): void
    {
        // 1. Période valide
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/dashboard?date_debut=2026-10-01&date_fin=2026-10-31');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'periode' => [
                        'date_debut' => '2026-10-01',
                        'date_fin' => '2026-10-31',
                    ],
                ],
            ]);

        // 2. Date fin antérieure à date début -> 422
        $invalidResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/dashboard?date_debut=2026-10-31&date_fin=2026-10-01');

        $invalidResp->assertStatus(422)
            ->assertJsonValidationErrors(['date_fin']);
    }

    public function test_dashboard_multi_tenant_isolation(): void
    {
        // Créer un livre et des exemplaires pour Site A
        $catA = CategorieLivre::create([
            'site_id' => $this->siteA->id,
            'code' => 'CAT-A',
            'nom' => 'Catégorie A',
        ]);

        $livreA = Livre::create([
            'site_id' => $this->siteA->id,
            'categorie_livre_id' => $catA->id,
            'code' => 'LIV-DASH-A',
            'titre' => 'Livre Site A',
            'auteur' => 'Auteur A',
        ]);

        Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $livreA->id,
            'code' => 'EXP-DASH-A1',
            'statut' => 'DISPONIBLE',
        ]);

        // Créer des ressources pour Site B
        $catB = CategorieLivre::create([
            'site_id' => $this->siteB->id,
            'code' => 'CAT-B',
            'nom' => 'Catégorie B',
        ]);

        $livreB = Livre::create([
            'site_id' => $this->siteB->id,
            'categorie_livre_id' => $catB->id,
            'code' => 'LIV-DASH-B',
            'titre' => 'Livre Site B',
            'auteur' => 'Auteur B',
        ]);

        Exemplaire::create([
            'site_id' => $this->siteB->id,
            'livre_id' => $livreB->id,
            'code' => 'EXP-DASH-B1',
            'statut' => 'DISPONIBLE',
        ]);
        Exemplaire::create([
            'site_id' => $this->siteB->id,
            'livre_id' => $livreB->id,
            'code' => 'EXP-DASH-B2',
            'statut' => 'DISPONIBLE',
        ]);

        // Vérifier que le dashboard de Site A ne compte que ses ressources
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.documents.nombre_livres'));
        $this->assertEquals(1, $response->json('data.documents.nombre_exemplaires'));
    }

    public function test_dashboard_accurately_calculates_operations_and_top_books(): void
    {
        $cat = CategorieLivre::create([
            'site_id' => $this->siteA->id,
            'code' => 'LIT-TEST',
            'nom' => 'Littérature Test',
        ]);

        $livre = Livre::create([
            'site_id' => $this->siteA->id,
            'categorie_livre_id' => $cat->id,
            'code' => 'LIV-OP-TEST',
            'titre' => 'Roman Test Prêt',
            'auteur' => 'Écrivain',
        ]);

        $ex = Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $livre->id,
            'code' => 'EXP-OP-TEST',
            'statut' => 'EMPRUNTE',
        ]);

        $usager = Usager::create([
            'numero' => 'USG-OP-01',
            'nom' => 'Testeur',
            'prenom' => 'Pierre',
            'email' => 'pierre.t@example.com',
        ]);

        UsagerSite::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $usager->id,
            'type' => 'ADHERENT',
            'statut' => 'ACTIF',
            'date_inscription' => now()->toDateString(),
        ]);

        // Créer une opération d'emprunt en cours
        Operation::create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $usager->id,
            'exemplaire_id' => $ex->id,
            'type' => 'EMPRUNT',
            'date_operation' => now(),
            'date_retour_prevue' => now()->addDays(14),
            'statut' => 'EN_COURS',
        ]);

        // Créer une présence
        Presence::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $usager->id,
            'date_presence' => today()->toDateString(),
            'heure_arrivee' => '10:00:00',
        ]);

        // Créer une réservation
        Reservation::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $usager->id,
            'livre_id' => $livre->id,
            'date_reservation' => now(),
            'date_expiration' => now()->addDays(3),
            'statut' => 'EN_ATTENTE',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.activites.emprunts.en_cours'));
        $this->assertEquals(1, $response->json('data.activites.reservations.actives'));
        $this->assertEquals(1, $response->json('data.presences.du_jour'));

        // Vérifier top livres
        $top = $response->json('data.top_livres');
        $this->assertNotEmpty($top);
        $this->assertEquals('Roman Test Prêt', $top[0]['titre']);
        $this->assertEquals(1, $top[0]['nombre_emprunts']);
    }

    public function test_dashboard_calculates_returns_strictly_within_period(): void
    {
        $cat = CategorieLivre::create([
            'site_id' => $this->siteA->id,
            'code' => 'CAT-RET',
            'nom' => 'Catégorie Retours',
        ]);

        $livre = Livre::create([
            'site_id' => $this->siteA->id,
            'categorie_livre_id' => $cat->id,
            'code' => 'LIV-RET',
            'titre' => 'Livre Retours',
            'auteur' => 'Auteur Retours',
        ]);

        $ex1 = Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $livre->id,
            'code' => 'EX-RET-1',
            'statut' => 'DISPONIBLE',
        ]);
        $ex2 = Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $livre->id,
            'code' => 'EX-RET-2',
            'statut' => 'DISPONIBLE',
        ]);

        $usager = Usager::create([
            'numero' => 'USG-RET-01',
            'nom' => 'Retourneur',
            'prenom' => 'Jean',
            'email' => 'jean.retour@example.com',
        ]);

        // Emprunt 1 : contracté avant la période (août), retourné pendant la période (septembre)
        Operation::create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $usager->id,
            'exemplaire_id' => $ex1->id,
            'type' => 'EMPRUNT',
            'date_operation' => '2026-08-15 10:00:00',
            'date_retour_prevue' => '2026-08-29 10:00:00',
            'date_retour_effective' => '2026-09-05 14:00:00',
            'statut' => 'RETOURNE',
        ]);

        // Emprunt 2 : contracté en septembre, mais PAS ENCORE retourné (en cours)
        Operation::create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $usager->id,
            'exemplaire_id' => $ex2->id,
            'type' => 'EMPRUNT',
            'date_operation' => '2026-09-10 09:00:00',
            'date_retour_prevue' => '2026-09-24 09:00:00',
            'date_retour_effective' => null,
            'statut' => 'EN_COURS',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/dashboard?date_debut=2026-09-01&date_fin=2026-09-30');

        $response->assertStatus(200);
        // Emprunts de la période (septembre) = 1 (Emprunt 2)
        $this->assertEquals(1, $response->json('data.activites.emprunts.periode'));
        // Retours effectifs de la période (septembre) = 1 (Emprunt 1, qui a date_retour_effective en septembre)
        $this->assertEquals(1, $response->json('data.activites.emprunts.retournes'));
        $this->assertEquals(1, $response->json('data.activites.emprunts.retours'));
        // Emprunts en cours = 1
        $this->assertEquals(1, $response->json('data.activites.emprunts.en_cours'));
    }

    public function test_dashboard_calculates_lost_exemplaires_by_etat_physique(): void
    {
        $cat = CategorieLivre::create([
            'site_id' => $this->siteA->id,
            'code' => 'CAT-PERDU',
            'nom' => 'Catégorie Perte',
        ]);

        $livre = Livre::create([
            'site_id' => $this->siteA->id,
            'categorie_livre_id' => $cat->id,
            'code' => 'LIV-PERDU',
            'titre' => 'Livre Perdu Test',
            'auteur' => 'Auteur Perdu',
        ]);

        // Exemplaire perdu : état 'PERDU', statut opérationnel 'INDISPONIBLE'
        Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $livre->id,
            'code' => 'EX-PERDU-01',
            'etat' => 'PERDU',
            'statut' => 'INDISPONIBLE',
        ]);

        // Exemplaire dégradé mais pas perdu : état 'DEGRADE', statut 'DISPONIBLE'
        Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $livre->id,
            'code' => 'EX-DEGRADE-01',
            'etat' => 'DEGRADE',
            'statut' => 'DISPONIBLE',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200);
        // Doit compter 1 exemplaire perdu (basé sur etat = 'PERDU')
        $this->assertEquals(1, $response->json('data.documents.exemplaires_perdus'));
        $this->assertEquals(1, $response->json('data.documents.nombre_exemplaires_perdus'));
    }

    public function test_dashboard_top_books_respects_period_and_ignores_out_of_period_loans(): void
    {
        $cat = CategorieLivre::create([
            'site_id' => $this->siteA->id,
            'code' => 'CAT-TOP',
            'nom' => 'Catégorie Top',
        ]);

        $livreAncien = Livre::create([
            'site_id' => $this->siteA->id,
            'categorie_livre_id' => $cat->id,
            'code' => 'LIV-ANCIEN',
            'titre' => 'Livre Ancien Très Emprunté En 2025',
            'auteur' => 'Auteur Ancien',
        ]);

        $livreRecent = Livre::create([
            'site_id' => $this->siteA->id,
            'categorie_livre_id' => $cat->id,
            'code' => 'LIV-RECENT',
            'titre' => 'Livre Récent Populaire En Novembre 2026',
            'auteur' => 'Auteur Récent',
        ]);

        $exAncien = Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $livreAncien->id,
            'code' => 'EX-ANC-01',
            'statut' => 'DISPONIBLE',
        ]);

        $exRecent = Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $livreRecent->id,
            'code' => 'EX-REC-01',
            'statut' => 'DISPONIBLE',
        ]);

        $usager = Usager::create([
            'numero' => 'USG-TOP-01',
            'nom' => 'Lecteur',
            'prenom' => 'Assidu',
            'email' => 'lecteur.top@example.com',
        ]);

        // 3 emprunts en 2025 pour le livre ancien
        for ($i = 0; $i < 3; $i++) {
            Operation::create([
                'site_id' => $this->siteA->id,
                'configuration_id' => $this->configA->id,
                'usager_id' => $usager->id,
                'exemplaire_id' => $exAncien->id,
                'type' => 'EMPRUNT',
                'date_operation' => '2025-01-10 10:00:00',
                'date_retour_prevue' => '2025-01-24 10:00:00',
                'statut' => 'RETOURNE',
            ]);
        }

        // 1 emprunt en novembre 2026 pour le livre récent
        Operation::create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $usager->id,
            'exemplaire_id' => $exRecent->id,
            'type' => 'EMPRUNT',
            'date_operation' => '2026-11-05 10:00:00',
            'date_retour_prevue' => '2026-11-19 10:00:00',
            'statut' => 'RETOURNE',
        ]);

        // Filtre pour novembre 2026 : seul le livre récent doit apparaître dans top_livres !
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/dashboard?date_debut=2026-11-01&date_fin=2026-11-30&top_limit=5');

        $response->assertStatus(200);
        $top = $response->json('data.top_livres');
        $this->assertCount(1, $top);
        $this->assertEquals('LIV-RECENT', $top[0]['code']);
        $this->assertEquals(1, $top[0]['nombre_emprunts']);
    }

    public function test_dashboard_handles_empty_period_gracefully(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/dashboard?date_debut=2020-01-01&date_fin=2020-01-31');

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('data.activites.emprunts.periode'));
        $this->assertEquals(0, $response->json('data.activites.emprunts.retournes'));
        $this->assertEquals(0, $response->json('data.presences.periode'));
        $this->assertEquals(0, $response->json('data.acquisitions.total_acquisitions_periode'));
        $this->assertEmpty($response->json('data.top_livres'));
    }
}
