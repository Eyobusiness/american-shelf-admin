<?php

namespace Tests\Feature\Usager;

use App\Models\Permission;
use App\Models\Presence;
use App\Models\Role;
use App\Models\Site;
use App\Models\Usager;
use App\Models\UsagerSite;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PresenceCrudTest extends TestCase
{
    use DatabaseTransactions;

    protected Site $siteA;
    protected Site $siteB;
    protected Usager $usagerA;
    protected Role $adminRoleA;
    protected Utilisateur $adminA;
    protected string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siteA = Site::create([
            'code' => 'SITE-PRES-A',
            'nom' => 'Site Présence A',
            'statut' => 'ACTIF',
        ]);

        $this->siteB = Site::create([
            'code' => 'SITE-PRES-B',
            'nom' => 'Site Présence B',
            'statut' => 'ACTIF',
        ]);

        $this->usagerA = Usager::create([
            'numero' => 'USG-PRES-01',
            'nom' => 'Kone',
            'prenom' => 'Bakary',
            'email' => 'kone.b@example.com',
        ]);

        UsagerSite::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'type' => 'ADHERENT',
            'statut' => 'ACTIF',
            'date_inscription' => now()->toDateString(),
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
            'prenom' => 'Presence',
            'email' => 'admin.pres@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $this->adminRoleA->id,
            'statut' => 'ACTIF',
        ]);

        $this->tokenA = auth('api')->login($this->adminA);
    }

    public function test_can_create_presence_with_identified_usager(): void
    {
        $payload = [
            'usager_id' => $this->usagerA->id,
            'date_presence' => '2026-09-16',
            'heure_arrivee' => '09:30',
            'heure_depart' => '11:45',
            'motif' => 'Travaux de recherche documentaire',
            'observations' => 'Entrée salle de lecture principale',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/presences', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'site_id' => $this->siteA->id,
                    'usager_id' => $this->usagerA->id,
                    'est_anonyme' => false,
                    'motif' => 'Travaux de recherche documentaire',
                ]
            ]);

        $this->assertDatabaseHas('presences', [
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'date_presence' => '2026-09-16',
            'motif' => 'Travaux de recherche documentaire',
        ]);
    }

    public function test_can_create_anonymous_presence(): void
    {
        // Visiteur sans compte usager
        $payload = [
            'heure_arrivee' => '14:00',
            'heure_depart' => '16:00',
            'motif' => 'Visite libre et consultation sur place',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/presences', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'site_id' => $this->siteA->id,
                    'usager_id' => null,
                    'est_anonyme' => true,
                    'motif' => 'Visite libre et consultation sur place',
                ]
            ]);

        $this->assertDatabaseHas('presences', [
            'site_id' => $this->siteA->id,
            'usager_id' => null,
            'motif' => 'Visite libre et consultation sur place',
        ]);
    }

    public function test_presence_time_validation_rejects_depart_before_arrivee(): void
    {
        $payload = [
            'heure_arrivee' => '15:00',
            'heure_depart' => '13:00', // Heure de départ antérieure à l'arrivée !
            'motif' => 'Test incohérence horaire',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/presences', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['heure_depart']);
    }

    public function test_can_show_and_update_presence(): void
    {
        $presence = Presence::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'date_presence' => '2026-09-16',
            'heure_arrivee' => '10:00:00',
            'motif' => 'Lecture',
        ]);

        // 1. Show
        $showResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/presences/' . $presence->id);

        $showResp->assertStatus(200)
            ->assertJsonPath('data.motif', 'Lecture')
            ->assertJsonPath('data.usager.email', 'kone.b@example.com');

        // 2. Update (renseigner l'heure de départ)
        $updateResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson('/api/v1/presences/' . $presence->id, [
                'heure_depart' => '12:30:00',
                'motif' => 'Lecture approfondie',
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.motif', 'Lecture approfondie');

        $this->assertDatabaseHas('presences', [
            'id' => $presence->id,
            'heure_depart' => '12:30:00',
            'motif' => 'Lecture approfondie',
        ]);
    }

    public function test_can_soft_delete_and_restore_presence(): void
    {
        $presence = Presence::create([
            'site_id' => $this->siteA->id,
            'date_presence' => '2026-09-16',
            'heure_arrivee' => '08:00:00',
            'motif' => 'Présence à supprimer',
        ]);

        // Delete
        $delResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->deleteJson('/api/v1/presences/' . $presence->id);

        $delResp->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('presences', ['id' => $presence->id]);

        // Restore
        $restoreResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/presences/' . $presence->id . '/restore');

        $restoreResp->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertNotSoftDeleted('presences', ['id' => $presence->id]);
    }

    public function test_can_list_presences_with_filters_and_search(): void
    {
        // 1. Présence identifiée
        Presence::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'date_presence' => '2026-09-15',
            'heure_arrivee' => '09:00:00',
            'motif' => 'Révision examen',
        ]);

        // 2. Présence anonyme
        Presence::create([
            'site_id' => $this->siteA->id,
            'usager_id' => null,
            'date_presence' => '2026-09-16',
            'heure_arrivee' => '14:00:00',
            'motif' => 'Consultation presse quotidienne',
        ]);

        // Liste filtrée sur les présences anonymes
        $respAnonyme = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/presences?anonyme=true');

        $respAnonyme->assertStatus(200)
            ->assertJsonFragment(['motif' => 'Consultation presse quotidienne'])
            ->assertJsonMissing(['motif' => 'Révision examen']);

        // Liste avec recherche textuelle sur le nom de l'usager
        $respSearch = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/presences?search=Bakary');

        $respSearch->assertStatus(200)
            ->assertJsonFragment(['motif' => 'Révision examen'])
            ->assertJsonMissing(['motif' => 'Consultation presse quotidienne']);
    }

    public function test_anti_idor_and_tenant_isolation_on_presences(): void
    {
        // Présence sur Site B
        $presenceB = Presence::create([
            'site_id' => $this->siteB->id,
            'date_presence' => '2026-09-16',
            'heure_arrivee' => '10:00:00',
            'motif' => 'Présence confidentielle Site B',
        ]);

        // 1. Lecture directe depuis Site A -> 404
        $readResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/presences/' . $presenceB->id);
        $readResp->assertStatus(404);

        // 2. Modification depuis Site A -> 404
        $updateResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson('/api/v1/presences/' . $presenceB->id, ['motif' => 'Piratage']);
        $updateResp->assertStatus(404);

        // 3. Suppression depuis Site A -> 404
        $delResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->deleteJson('/api/v1/presences/' . $presenceB->id);
        $delResp->assertStatus(404);
        $this->assertNotSoftDeleted('presences', ['id' => $presenceB->id]);

        // 4. Liste Site A ne contient jamais Site B
        $listResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/presences');
        $listResp->assertStatus(200)
            ->assertJsonMissing(['motif' => 'Présence confidentielle Site B']);
    }

    public function test_permissions_enforcement_on_presences(): void
    {
        $restrictedRole = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'ACCUEIL_SANS_DROITS',
            'nom' => 'Accueil restreint',
            'statut' => 'ACTIF',
        ]);

        $restrictedUser = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'nom' => 'Agent',
            'prenom' => 'Accueil',
            'email' => 'accueil.pres@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $restrictedRole->id,
            'statut' => 'ACTIF',
        ]);

        $restrictedToken = auth('api')->login($restrictedUser);

        // 1. Lecture sans permission -> 403 Forbidden
        $readResp = $this->withHeader('Authorization', 'Bearer ' . $restrictedToken)
            ->getJson('/api/v1/presences');
        $readResp->assertStatus(403);

        // 2. Création sans permission -> 403 Forbidden
        $createResp = $this->withHeader('Authorization', 'Bearer ' . $restrictedToken)
            ->postJson('/api/v1/presences', [
                'heure_arrivee' => '10:00',
            ]);
        $createResp->assertStatus(403);

        // 3. Assigner PRESENCE_READ au rôle
        $permRead = Permission::create([
            'site_id' => $this->siteA->id,
            'code' => 'PRESENCE_READ',
            'libelle' => 'Lecture des présences',
            'action' => 'READ',
        ]);
        $restrictedRole->permissions()->attach($permRead->id);

        // La lecture devient autorisée (200 OK)
        $allowedResp = $this->withHeader('Authorization', 'Bearer ' . $restrictedToken)
            ->getJson('/api/v1/presences');
        $allowedResp->assertStatus(200);
    }

    public function test_presence_pagination(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            Presence::create([
                'site_id' => $this->siteA->id,
                'date_presence' => '2026-09-16',
                'heure_arrivee' => sprintf('%02d:00:00', $i + 8),
                'motif' => "Visite $i",
            ]);
        }

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/presences?per_page=4&page=1');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 4)
            ->assertJsonPath('meta.current_page', 1);
        $this->assertCount(4, $response->json('data'));
    }

    public function test_presence_update_rejects_depart_before_arrivee(): void
    {
        $presence = Presence::create([
            'site_id' => $this->siteA->id,
            'date_presence' => '2026-09-16',
            'heure_arrivee' => '10:00:00',
            'motif' => 'Lecture',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson('/api/v1/presences/' . $presence->id, [
                'heure_depart' => '09:00:00', // Antérieur à 10:00:00
            ]);

        $response->assertStatus(422);
    }

    public function test_presence_factory(): void
    {
        $presence = Presence::factory()->create([
            'site_id' => $this->siteA->id,
            'motif' => 'Factory Motif Test',
        ]);

        $this->assertDatabaseHas('presences', [
            'id' => $presence->id,
            'site_id' => $this->siteA->id,
            'motif' => 'Factory Motif Test',
        ]);
    }

    public function test_prevents_duplicate_active_presence_for_same_usager_on_same_day(): void
    {
        $today = now()->toDateString();

        // Premiere arrivee (sans heure de depart)
        $resp1 = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/presences', [
                'usager_id' => $this->usagerA->id,
                'date_presence' => $today,
                'heure_arrivee' => '09:00',
            ]);
        $resp1->assertStatus(201);

        // Deuxieme arrivee tentee pour le meme usager sans que la premiere ne soit sortie -> 422
        $resp2 = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/presences', [
                'usager_id' => $this->usagerA->id,
                'date_presence' => $today,
                'heure_arrivee' => '10:00',
            ]);
        $resp2->assertStatus(422);
    }

    public function test_allows_subsequent_presence_after_departure_on_same_day(): void
    {
        $today = now()->toDateString();

        // 1. Premiere visite du matin avec sortie
        $visit1 = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/presences', [
                'usager_id' => $this->usagerA->id,
                'date_presence' => $today,
                'heure_arrivee' => '08:30',
                'heure_depart' => '11:00',
            ]);
        $visit1->assertStatus(201);

        // 2. Visite legitime de l'apres-midi pour le meme usager le meme jour -> Autorise (201)
        $visit2 = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/presences', [
                'usager_id' => $this->usagerA->id,
                'date_presence' => $today,
                'heure_arrivee' => '14:00',
            ]);
        $visit2->assertStatus(201);
    }

    public function test_can_record_sortie_via_dedicated_endpoint(): void
    {
        $presence = Presence::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'date_presence' => '2026-09-18',
            'heure_arrivee' => '10:00:00',
            'motif' => 'Travail en groupe',
        ]);

        $sortieResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/presences/' . $presence->id . '/sortie', [
                'heure_depart' => '12:30',
                'observations' => 'Session terminee',
            ]);

        $sortieResp->assertStatus(200)
            ->assertJsonPath('data.statut', 'TERMINE')
            ->assertJsonPath('data.est_en_cours', false)
            ->assertJsonPath('data.duree_minutes', 150)
            ->assertJsonPath('data.duree_formatee', '2 h 30 min');
    }

    public function test_rejects_second_sortie_if_already_checked_out(): void
    {
        $presence = Presence::create([
            'site_id' => $this->siteA->id,
            'date_presence' => '2026-09-18',
            'heure_arrivee' => '10:00:00',
            'heure_depart' => '11:30:00',
        ]);

        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/presences/' . $presence->id . '/sortie', [
                'heure_depart' => '12:00',
            ]);

        $resp->assertStatus(422);
    }

    public function test_can_fetch_presences_statistics(): void
    {
        Presence::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'date_presence' => '2026-09-18',
            'heure_arrivee' => '09:00:00',
            'heure_depart' => '10:30:00',
        ]);

        Presence::create([
            'site_id' => $this->siteA->id,
            'usager_id' => null,
            'date_presence' => '2026-09-18',
            'heure_arrivee' => '14:00:00',
        ]);

        $statsResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/presences/statistiques?annee=2026&mois=9');

        $statsResp->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'annee_selectionnee',
                    'mois_selectionne',
                    'mois_nom',
                    'bibliotheque' => [
                        'total_passages',
                        'passages_annee',
                        'passages_mois',
                    ],
                    'frequentation_mensuelle',
                ],
            ]);
    }
}