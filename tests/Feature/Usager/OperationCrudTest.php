<?php

namespace Tests\Feature\Usager;

use App\Models\CategorieLivre;
use App\Models\CategorieUsager;
use App\Models\Configuration;
use App\Models\Exemplaire;
use App\Models\Livre;
use App\Models\Operation;
use App\Models\Role;
use App\Models\Site;
use App\Models\Usager;
use App\Models\UsagerSite;
use App\Models\Utilisateur;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OperationCrudTest extends TestCase
{
    use DatabaseTransactions;

    protected Site $siteA;
    protected Site $siteB;
    protected Utilisateur $adminA;
    protected Utilisateur $adminB;
    protected string $tokenA;
    protected string $tokenB;
    protected Usager $usagerA;
    protected Usager $usagerB;
    protected Livre $livreA;
    protected Exemplaire $exemplaireA1;
    protected Exemplaire $exemplaireA2;
    protected Exemplaire $exemplaireB;
    protected Configuration $configA;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Sites
        $this->siteA = Site::create([
            'code' => 'SITE-OP-A',
            'nom' => 'Site Opération A',
            'statut' => 'ACTIF',
        ]);

        $this->siteB = Site::create([
            'code' => 'SITE-OP-B',
            'nom' => 'Site Opération B',
            'statut' => 'ACTIF',
        ]);

        // 2. Roles & Admins
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
            'email' => 'admin.op.a@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $roleA->id,
            'statut' => 'ACTIF',
        ]);

        $this->tokenA = auth('api')->login($this->adminA);

        // 3. Configurations
        $this->configA = Configuration::create([
            'site_id' => $this->siteA->id,
            'duree_emprunt' => 14,
            'nombre_jours_grace' => 3,
            'duree_consultation' => 2,
            'quota_emprunt' => 2,
            'duree_reservation' => 2,
        ]);

        // 4. Catégories & Usagers
        $catUsager = CategorieUsager::create([
            'site_id' => $this->siteA->id,
            'code' => 'ETUD',
            'nom' => 'Étudiant',
        ]);

        $this->usagerA = Usager::create([
            'numero' => 'USG-OP-A01',
            'nom' => 'Touré',
            'prenom' => 'Aïcha',
            'email' => 'aicha.t@example.com',
        ]);

        UsagerSite::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'categorie_usager_id' => $catUsager->id,
            'type' => 'ADHERENT',
            'statut' => 'ACTIF',
            'date_inscription' => now()->toDateString(),
        ]);

        $this->usagerB = Usager::create([
            'numero' => 'USG-OP-B01',
            'nom' => 'Koffi',
            'prenom' => 'Jean',
            'email' => 'jean.k@example.com',
        ]);

        UsagerSite::create([
            'site_id' => $this->siteB->id,
            'usager_id' => $this->usagerB->id,
            'type' => 'ADHERENT',
            'statut' => 'ACTIF',
            'date_inscription' => now()->toDateString(),
        ]);

        // 5. Livres & Exemplaires
        $catLivre = CategorieLivre::create([
            'site_id' => $this->siteA->id,
            'code' => 'ROMAN',
            'nom' => 'Roman Littéraire',
        ]);

        $this->livreA = Livre::create([
            'site_id' => $this->siteA->id,
            'categorie_livre_id' => $catLivre->id,
            'code' => 'LIV-OP-01',
            'titre' => 'Les Soleils des Indépendances',
            'auteur' => 'Ahmadou Kourouma',
            'isbn' => '978-2020005722',
        ]);

        $this->exemplaireA1 = Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $this->livreA->id,
            'code' => 'EXP-A1-001',
            'etat' => 'BON',
            'statut' => 'DISPONIBLE',
            'localisation' => 'Rayon A1',
        ]);

        $this->exemplaireA2 = Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $this->livreA->id,
            'code' => 'EXP-A1-002',
            'etat' => 'NEUF',
            'statut' => 'DISPONIBLE',
            'localisation' => 'Rayon A2',
        ]);

        $catLivreB = CategorieLivre::create([
            'site_id' => $this->siteB->id,
            'code' => 'ROMAN-B',
            'nom' => 'Roman Littéraire B',
        ]);

        $livreB = Livre::create([
            'site_id' => $this->siteB->id,
            'categorie_livre_id' => $catLivreB->id,
            'code' => 'LIV-OP-B01',
            'titre' => 'Livre Site B',
            'auteur' => 'Auteur B',
        ]);

        $this->exemplaireB = Exemplaire::create([
            'site_id' => $this->siteB->id,
            'livre_id' => $livreB->id,
            'code' => 'EXP-B1-001',
            'etat' => 'BON',
            'statut' => 'DISPONIBLE',
            'localisation' => 'Rayon B1',
        ]);
    }

    public function test_can_create_consultation_and_exemplaire_becomes_en_consultation(): void
    {
        $payload = [
            'usager_id' => $this->usagerA->id,
            'exemplaire_id' => $this->exemplaireA1->id,
            'type' => 'CONSULTATION',
            'observations' => 'Consultation en salle de lecture',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/operations', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'type' => 'CONSULTATION',
                    'statut' => 'EN_COURS',
                    'observations' => 'Consultation en salle de lecture',
                ],
            ]);

        $this->assertDatabaseHas('operations', [
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'exemplaire_id' => $this->exemplaireA1->id,
            'type' => 'CONSULTATION',
            'statut' => 'EN_COURS',
        ]);

        $this->assertEquals('EN_CONSULTATION', $this->exemplaireA1->fresh()->statut);
    }

    public function test_can_create_emprunt_with_calculated_date_retour_prevue_and_frozen_configuration(): void
    {
        $payload = [
            'usager_id' => $this->usagerA->id,
            'exemplaire_id' => $this->exemplaireA1->id,
            'type' => 'EMPRUNT',
            'date_operation' => '2026-09-16 10:00:00',
            'observations' => 'Emprunt à domicile pour 14 jours',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/operations', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'type' => 'EMPRUNT',
                    'statut' => 'EN_COURS',
                    'configuration_id' => $this->configA->id,
                    'observations' => 'Emprunt à domicile pour 14 jours',
                ],
            ]);

        // Date de retour prévue doit être date_operation + 14 jours = 2026-09-30
        $data = $response->json('data');
        $this->assertStringStartsWith('2026-09-30', $data['date_retour_prevue']);
        $this->assertEquals('EMPRUNTE', $this->exemplaireA1->fresh()->statut);
    }

    public function test_cannot_loan_when_quota_is_exceeded(): void
    {
        // Quota = 2 pour configA
        // Crée 2 emprunts en cours
        Operation::create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $this->usagerA->id,
            'exemplaire_id' => $this->exemplaireA1->id,
            'type' => 'EMPRUNT',
            'date_operation' => now(),
            'date_retour_prevue' => now()->addDays(14),
            'statut' => 'EN_COURS',
        ]);

        $this->exemplaireA1->update(['statut' => 'EMPRUNTE']);

        Operation::create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $this->usagerA->id,
            'exemplaire_id' => $this->exemplaireA2->id,
            'type' => 'EMPRUNT',
            'date_operation' => now(),
            'date_retour_prevue' => now()->addDays(14),
            'statut' => 'EN_COURS',
        ]);

        $this->exemplaireA2->update(['statut' => 'EMPRUNTE']);

        // Créer un 3ème exemplaire disponible
        $exemplaireA3 = Exemplaire::create([
            'site_id' => $this->siteA->id,
            'livre_id' => $this->livreA->id,
            'code' => 'EXP-A1-003',
            'etat' => 'BON',
            'statut' => 'DISPONIBLE',
        ]);

        // Tenter un 3ème emprunt
        $payload = [
            'usager_id' => $this->usagerA->id,
            'exemplaire_id' => $exemplaireA3->id,
            'type' => 'EMPRUNT',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/operations', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['usager_id']);
    }

    public function test_cannot_create_operation_if_exemplaire_is_not_disponible(): void
    {
        $this->exemplaireA1->update(['statut' => 'EMPRUNTE']);

        $payload = [
            'usager_id' => $this->usagerA->id,
            'exemplaire_id' => $this->exemplaireA1->id,
            'type' => 'CONSULTATION',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/operations', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['exemplaire_id']);
    }

    public function test_cannot_create_operation_if_usager_is_not_active_member(): void
    {
        // Usager B n'a pas d'adhésion sur le Site A
        $payload = [
            'usager_id' => $this->usagerB->id,
            'exemplaire_id' => $this->exemplaireA1->id,
            'type' => 'CONSULTATION',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/operations', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['usager_id']);
    }

    public function test_cannot_create_operation_with_exemplaire_from_another_site(): void
    {
        // Exemplaire B appartient au Site B
        $payload = [
            'usager_id' => $this->usagerA->id,
            'exemplaire_id' => $this->exemplaireB->id,
            'type' => 'EMPRUNT',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/operations', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['exemplaire_id']);
    }

    public function test_can_return_operation_on_time_and_exemplaire_becomes_disponible(): void
    {
        $operation = Operation::create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $this->usagerA->id,
            'exemplaire_id' => $this->exemplaireA1->id,
            'type' => 'EMPRUNT',
            'date_operation' => '2026-09-01 10:00:00',
            'date_retour_prevue' => '2026-09-15 10:00:00',
            'statut' => 'EN_COURS',
        ]);
        $this->exemplaireA1->update(['statut' => 'EMPRUNTE']);

        // Retour dans les délais (10 septembre)
        $payload = [
            'date_retour_effective' => '2026-09-10 14:00:00',
            'etat_exemplaire' => 'BON',
            'observations' => 'Retourné en parfait état',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/operations/{$operation->id}/retour", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $operation->id,
                    'statut' => 'RETOURNE',
                    'est_en_retard' => false,
                    'jours_retard' => 0,
                ],
            ]);

        $this->assertEquals('DISPONIBLE', $this->exemplaireA1->fresh()->statut);
    }

    public function test_return_operation_with_delay_sets_statut_en_retard(): void
    {
        $operation = Operation::create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $this->usagerA->id,
            'exemplaire_id' => $this->exemplaireA1->id,
            'type' => 'EMPRUNT',
            'date_operation' => '2026-09-01 10:00:00',
            'date_retour_prevue' => '2026-09-15 10:00:00', // Grace days = 3 -> limite = 18 sept
            'statut' => 'EN_COURS',
        ]);
        $this->exemplaireA1->update(['statut' => 'EMPRUNTE']);

        // Retour le 25 septembre (retard avéré après date limite du 18 sept)
        $payload = [
            'date_retour_effective' => '2026-09-25 10:00:00',
            'etat_exemplaire' => 'DEGRADE',
            'observations' => 'Livre rendu avec des pages froissées',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/operations/{$operation->id}/retour", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $operation->id,
                    'statut' => 'EN_RETARD',
                    'est_en_retard' => true,
                ],
            ]);

        $this->assertEquals('DISPONIBLE', $this->exemplaireA1->fresh()->statut);
        $this->assertEquals('DEGRADE', $this->exemplaireA1->fresh()->etat);
    }

    public function test_return_operation_with_lost_exemplaire(): void
    {
        $operation = Operation::create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $this->usagerA->id,
            'exemplaire_id' => $this->exemplaireA1->id,
            'type' => 'EMPRUNT',
            'date_operation' => '2026-09-01 10:00:00',
            'date_retour_prevue' => '2026-09-15 10:00:00',
            'statut' => 'EN_COURS',
        ]);
        $this->exemplaireA1->update(['statut' => 'EMPRUNTE']);

        $payload = [
            'etat_exemplaire' => 'PERDU',
            'observations' => 'Usager déclare avoir égaré le livre dans les transports',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/operations/{$operation->id}/retour", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $operation->id,
                    'statut' => 'PERDU',
                ],
            ]);

        $this->assertEquals('INDISPONIBLE', $this->exemplaireA1->fresh()->statut);
        $this->assertEquals('PERDU', $this->exemplaireA1->fresh()->etat);
    }

    public function test_cannot_return_already_closed_operation(): void
    {
        $operation = Operation::create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $this->usagerA->id,
            'exemplaire_id' => $this->exemplaireA1->id,
            'type' => 'EMPRUNT',
            'date_operation' => now(),
            'date_retour_prevue' => now()->addDays(14),
            'statut' => 'RETOURNE',
        ]);

        $payload = [
            'date_retour_effective' => now()->toDateTimeString(),
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/operations/{$operation->id}/retour", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['statut']);
    }

    public function test_anti_idor_cross_tenant_access_returns_404(): void
    {
        // Créer une opération sur le Site B
        $configB = Configuration::create([
            'site_id' => $this->siteB->id,
            'duree_emprunt' => 14,
            'nombre_jours_grace' => 3,
            'duree_consultation' => 2,
            'quota_emprunt' => 3,
            'duree_reservation' => 2,
        ]);

        $operationB = Operation::create([
            'site_id' => $this->siteB->id,
            'configuration_id' => $configB->id,
            'usager_id' => $this->usagerB->id,
            'exemplaire_id' => $this->exemplaireB->id,
            'type' => 'CONSULTATION',
            'date_operation' => now(),
            'statut' => 'EN_COURS',
        ]);

        // Admin A tente d'accéder à l'opération du Site B
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/v1/operations/{$operationB->id}");

        $response->assertStatus(404);

        // Admin A tente de clôturer l'opération du Site B
        $retourResponse = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/operations/{$operationB->id}/retour", []);

        $retourResponse->assertStatus(404);
    }

    public function test_can_delete_and_restore_operation(): void
    {
        $operation = Operation::create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $this->usagerA->id,
            'exemplaire_id' => $this->exemplaireA1->id,
            'type' => 'EMPRUNT',
            'date_operation' => now(),
            'statut' => 'EN_COURS',
        ]);
        $this->exemplaireA1->update(['statut' => 'EMPRUNTE']);

        // Suppression de l'opération en cours : doit réinitialiser l'exemplaire en DISPONIBLE
        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->deleteJson("/api/v1/operations/{$operation->id}");

        $deleteResponse->assertStatus(200);
        $this->assertSoftDeleted('operations', ['id' => $operation->id]);
        $this->assertEquals('DISPONIBLE', $this->exemplaireA1->fresh()->statut);

        // Restauration
        $restoreResponse = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/operations/{$operation->id}/restore");

        $restoreResponse->assertStatus(200);
        $this->assertNotSoftDeleted('operations', ['id' => $operation->id]);
    }

    public function test_can_list_operations_with_filters_and_search(): void
    {
        Operation::create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $this->usagerA->id,
            'exemplaire_id' => $this->exemplaireA1->id,
            'type' => 'EMPRUNT',
            'date_operation' => '2026-09-01 10:00:00',
            'statut' => 'EN_COURS',
            'observations' => 'Emprunt exceptionnel été',
        ]);

        Operation::create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $this->usagerA->id,
            'exemplaire_id' => $this->exemplaireA2->id,
            'type' => 'CONSULTATION',
            'date_operation' => '2026-09-05 10:00:00',
            'statut' => 'RETOURNE',
            'observations' => 'Consultation sur place courte',
        ]);

        // Filtrer par type EMPRUNT
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/operations?type=EMPRUNT');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('EMPRUNT', $response->json('data.0.type'));

        // Filtrer par recherche textuelle
        $searchResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/operations?search=exceptionnel');
        $searchResp->assertStatus(200);
        $this->assertCount(1, $searchResp->json('data'));
    }

    public function test_can_update_operation(): void
    {
        $operation = Operation::create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $this->usagerA->id,
            'exemplaire_id' => $this->exemplaireA1->id,
            'type' => 'EMPRUNT',
            'date_operation' => '2026-09-01 10:00:00',
            'date_retour_prevue' => '2026-09-15 10:00:00',
            'statut' => 'EN_COURS',
        ]);

        $payload = [
            'date_retour_prevue' => '2026-09-22 10:00:00',
            'observations' => 'Prolongation de 7 jours accordée',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson("/api/v1/operations/{$operation->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $operation->id,
                    'observations' => 'Prolongation de 7 jours accordée',
                ],
            ]);

        $this->assertStringStartsWith('2026-09-22', $response->json('data.date_retour_prevue'));
    }

    public function test_permissions_enforcement_on_operations(): void
    {
        $restrictedRole = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'STAGIAIRE_OP',
            'nom' => 'Stagiaire Opérations',
            'statut' => 'ACTIF',
        ]);

        $restrictedUser = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'nom' => 'Stagiaire',
            'prenom' => 'Op',
            'email' => 'stagiaire.op@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $restrictedRole->id,
            'statut' => 'ACTIF',
        ]);

        $restrictedToken = auth('api')->login($restrictedUser);

        // Tentative de lecture sans OPERATION_READ -> 403 Forbidden
        $readResp = $this->withHeader('Authorization', 'Bearer ' . $restrictedToken)
            ->getJson('/api/v1/operations');
        $readResp->assertStatus(403);

        // Tentative de création sans OPERATION_CREATE -> 403 Forbidden
        $createResp = $this->withHeader('Authorization', 'Bearer ' . $restrictedToken)
            ->postJson('/api/v1/operations', [
                'usager_id' => $this->usagerA->id,
                'exemplaire_id' => $this->exemplaireA1->id,
                'type' => 'EMPRUNT',
            ]);
        $createResp->assertStatus(403);

        // Attribuer OPERATION_READ au rôle
        $permRead = \App\Models\Permission::create([
            'site_id' => $this->siteA->id,
            'code' => 'OPERATION_READ',
            'libelle' => 'Lecture des opérations',
            'action' => 'READ',
        ]);
        $restrictedRole->permissions()->attach($permRead->id);

        // La lecture devient autorisée (200 OK)
        $allowedReadResp = $this->withHeader('Authorization', 'Bearer ' . $restrictedToken)
            ->getJson('/api/v1/operations');
        $allowedReadResp->assertStatus(200);
    }

    public function test_operations_pagination(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $exp = Exemplaire::create([
                'site_id' => $this->siteA->id,
                'livre_id' => $this->livreA->id,
                'code' => "EXP-PAGE-$i",
                'etat' => 'BON',
                'statut' => 'DISPONIBLE',
            ]);

            Operation::create([
                'site_id' => $this->siteA->id,
                'configuration_id' => $this->configA->id,
                'usager_id' => $this->usagerA->id,
                'exemplaire_id' => $exp->id,
                'type' => 'CONSULTATION',
                'date_operation' => now(),
                'statut' => 'EN_COURS',
            ]);
        }

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/operations?per_page=3&page=1');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 3)
            ->assertJsonPath('meta.current_page', 1);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_filter_operations_en_retard(): void
    {
        // 1. Opération dans les délais
        Operation::create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $this->usagerA->id,
            'exemplaire_id' => $this->exemplaireA1->id,
            'type' => 'EMPRUNT',
            'date_operation' => now(),
            'date_retour_prevue' => now()->addDays(10),
            'statut' => 'EN_COURS',
            'observations' => 'Emprunt récent',
        ]);

        // 2. Opération échue en retard
        Operation::create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $this->usagerA->id,
            'exemplaire_id' => $this->exemplaireA2->id,
            'type' => 'EMPRUNT',
            'date_operation' => now()->subDays(30),
            'date_retour_prevue' => now()->subDays(16),
            'statut' => 'EN_RETARD',
            'observations' => 'Emprunt très en retard',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/operations?en_retard=true');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Emprunt très en retard', $data[0]['observations']);
    }

    public function test_concurrency_pessimistic_locking_prevents_double_loan(): void
    {
        // Créer un second usager sur le Site A
        $secondUsager = Usager::create([
            'numero' => 'USG-OP-CONC',
            'nom' => 'Diallo',
            'prenom' => 'Mamadou',
            'email' => 'mamadou.d@example.com',
        ]);
        UsagerSite::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $secondUsager->id,
            'type' => 'ADHERENT',
            'statut' => 'ACTIF',
            'date_inscription' => now()->toDateString(),
        ]);

        // Première requête : emprunte l'exemplaire A1
        $resp1 = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/operations', [
                'usager_id' => $this->usagerA->id,
                'exemplaire_id' => $this->exemplaireA1->id,
                'type' => 'EMPRUNT',
            ]);
        $resp1->assertStatus(201);
        $this->assertEquals('EMPRUNTE', $this->exemplaireA1->fresh()->statut);

        // Deuxième requête simultanée / concurrente : tente d'emprunter le même exemplaire
        $resp2 = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/operations', [
                'usager_id' => $secondUsager->id,
                'exemplaire_id' => $this->exemplaireA1->id,
                'type' => 'EMPRUNT',
            ]);

        // Rejet strict car l'exemplaire n'est plus DISPONIBLE
        $resp2->assertStatus(422)
            ->assertJsonValidationErrors(['exemplaire_id']);
    }

    public function test_operation_factory(): void
    {
        $operation = Operation::factory()->create([
            'site_id' => $this->siteA->id,
            'configuration_id' => $this->configA->id,
            'usager_id' => $this->usagerA->id,
            'exemplaire_id' => $this->exemplaireA1->id,
            'type' => 'EMPRUNT',
            'statut' => 'EN_COURS',
        ]);

        $this->assertDatabaseHas('operations', [
            'id' => $operation->id,
            'site_id' => $this->siteA->id,
            'type' => 'EMPRUNT',
            'statut' => 'EN_COURS',
        ]);
    }
}
