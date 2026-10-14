<?php

namespace Tests\Feature\Usager;

use App\Models\CategorieLivre;
use App\Models\CategorieUsager;
use App\Models\Configuration;
use App\Models\Livre;
use App\Models\Permission;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Site;
use App\Models\Usager;
use App\Models\UsagerSite;
use App\Models\Utilisateur;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReservationCrudTest extends TestCase
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
    protected Livre $livreB;
    protected Configuration $configA;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Sites
        $this->siteA = Site::create([
            'code' => 'SITE-RES-A',
            'nom' => 'Site Réservation A',
            'statut' => 'ACTIF',
        ]);

        $this->siteB = Site::create([
            'code' => 'SITE-RES-B',
            'nom' => 'Site Réservation B',
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
            'email' => 'admin.res.a@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $roleA->id,
            'statut' => 'ACTIF',
        ]);

        $this->tokenA = auth('api')->login($this->adminA);

        $roleB = Role::create([
            'site_id' => $this->siteB->id,
            'code' => 'ADMIN',
            'nom' => 'Admin B',
            'statut' => 'ACTIF',
        ]);

        $this->adminB = Utilisateur::create([
            'site_id' => $this->siteB->id,
            'nom' => 'Admin',
            'prenom' => 'Site B',
            'email' => 'admin.res.b@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $roleB->id,
            'statut' => 'ACTIF',
        ]);

        // 3. Configurations
        $this->configA = Configuration::create([
            'site_id' => $this->siteA->id,
            'duree_emprunt' => 14,
            'nombre_jours_grace' => 3,
            'duree_consultation' => 2,
            'quota_emprunt' => 3,
            'duree_reservation' => 3, // 3 jours pour Site A
        ]);

        // 4. Usagers
        $catUsagerA = CategorieUsager::create([
            'site_id' => $this->siteA->id,
            'code' => 'ETUD-A',
            'nom' => 'Étudiant Site A',
        ]);

        $this->usagerA = Usager::create([
            'numero' => 'USG-RES-A01',
            'nom' => 'Diallo',
            'prenom' => 'Kadiatou',
            'email' => 'kadiatou.d@example.com',
        ]);

        UsagerSite::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'categorie_usager_id' => $catUsagerA->id,
            'type' => 'ADHERENT',
            'statut' => 'ACTIF',
            'date_inscription' => now()->toDateString(),
        ]);

        $this->usagerB = Usager::create([
            'numero' => 'USG-RES-B01',
            'nom' => 'Konan',
            'prenom' => 'Yves',
            'email' => 'yves.k@example.com',
        ]);

        UsagerSite::create([
            'site_id' => $this->siteB->id,
            'usager_id' => $this->usagerB->id,
            'type' => 'ADHERENT',
            'statut' => 'ACTIF',
            'date_inscription' => now()->toDateString(),
        ]);

        // 5. Livres
        $catLivreA = CategorieLivre::create([
            'site_id' => $this->siteA->id,
            'code' => 'ROMAN-A',
            'nom' => 'Roman Littéraire A',
        ]);

        $this->livreA = Livre::create([
            'site_id' => $this->siteA->id,
            'categorie_livre_id' => $catLivreA->id,
            'code' => 'LIV-RES-A01',
            'titre' => 'L\'Étranger',
            'auteur' => 'Albert Camus',
        ]);

        $catLivreB = CategorieLivre::create([
            'site_id' => $this->siteB->id,
            'code' => 'ROMAN-B',
            'nom' => 'Roman Littéraire B',
        ]);

        $this->livreB = Livre::create([
            'site_id' => $this->siteB->id,
            'categorie_livre_id' => $catLivreB->id,
            'code' => 'LIV-RES-B01',
            'titre' => 'Livre Site B',
            'auteur' => 'Auteur B',
        ]);
    }

    public function test_can_create_reservation_with_calculated_date_expiration(): void
    {
        $payload = [
            'usager_id' => $this->usagerA->id,
            'livre_id' => $this->livreA->id,
            'date_reservation' => '2026-10-01 10:00:00',
            'observations' => 'Réservation prioritaire',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/reservations', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'site_id' => $this->siteA->id,
                    'usager_id' => $this->usagerA->id,
                    'livre_id' => $this->livreA->id,
                    'statut' => 'EN_ATTENTE',
                    'observations' => 'Réservation prioritaire',
                ],
            ]);

        // Configurée avec duree_reservation = 3 jours -> date_expiration = 2026-10-04
        $data = $response->json('data');
        $this->assertStringStartsWith('2026-10-04', $data['date_expiration']);

        $this->assertDatabaseHas('reservations', [
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'livre_id' => $this->livreA->id,
            'statut' => 'EN_ATTENTE',
        ]);
    }

    public function test_cannot_create_duplicate_active_reservation_for_same_user_and_book(): void
    {
        // 1ère réservation
        Reservation::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'livre_id' => $this->livreA->id,
            'date_reservation' => now(),
            'date_expiration' => now()->addDays(3),
            'statut' => 'EN_ATTENTE',
        ]);

        // Tentative de 2ème réservation active sur le même livre
        $payload = [
            'usager_id' => $this->usagerA->id,
            'livre_id' => $this->livreA->id,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/reservations', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['livre_id']);
    }

    public function test_cannot_create_reservation_if_user_not_active_in_site(): void
    {
        // Usager B n'appartient pas au Site A
        $payload = [
            'usager_id' => $this->usagerB->id,
            'livre_id' => $this->livreA->id,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/reservations', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['usager_id']);
    }

    public function test_cannot_create_reservation_with_book_from_another_site(): void
    {
        // Livre B appartient au Site B
        $payload = [
            'usager_id' => $this->usagerA->id,
            'livre_id' => $this->livreB->id,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/reservations', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['livre_id']);
    }

    public function test_can_cancel_active_reservation(): void
    {
        $reservation = Reservation::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'livre_id' => $this->livreA->id,
            'date_reservation' => now(),
            'date_expiration' => now()->addDays(3),
            'statut' => 'EN_ATTENTE',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/reservations/{$reservation->id}/annuler", [
                'observations' => 'Usager a trouvé un autre exemplaire',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $reservation->id,
                    'statut' => 'ANNULEE',
                ],
            ]);

        $this->assertEquals('ANNULEE', $reservation->fresh()->statut);
    }

    public function test_cannot_cancel_already_cancelled_or_honored_reservation(): void
    {
        $reservation = Reservation::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'livre_id' => $this->livreA->id,
            'date_reservation' => now(),
            'date_expiration' => now()->addDays(3),
            'statut' => 'ANNULEE',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/reservations/{$reservation->id}/annuler");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['statut']);
    }

    public function test_reservation_automatically_marked_expired_when_past_date_expiration(): void
    {
        $reservation = Reservation::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'livre_id' => $this->livreA->id,
            'date_reservation' => now()->subDays(5),
            'date_expiration' => now()->subDays(2),
            'statut' => 'EN_ATTENTE',
        ]);

        // Appel show : le service déclenche checkAndUpdateExpired
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/v1/reservations/{$reservation->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $reservation->id,
                    'statut' => 'EXPIREE',
                    'est_expiree' => true,
                ],
            ]);

        $this->assertEquals('EXPIREE', $reservation->fresh()->statut);
    }

    public function test_can_list_reservations_with_filters_and_pagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $livre = Livre::create([
                'site_id' => $this->siteA->id,
                'categorie_livre_id' => $this->livreA->categorie_livre_id,
                'code' => "LIV-PAG-$i",
                'titre' => "Titre $i",
                'auteur' => "Auteur $i",
            ]);

            Reservation::create([
                'site_id' => $this->siteA->id,
                'usager_id' => $this->usagerA->id,
                'livre_id' => $livre->id,
                'date_reservation' => now(),
                'date_expiration' => now()->addDays(3),
                'statut' => $i === 1 ? 'ANNULEE' : 'EN_ATTENTE',
            ]);
        }

        // 1. Pagination
        $respPag = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/reservations?per_page=2&page=1');

        $respPag->assertStatus(200)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.current_page', 1);
        $this->assertCount(2, $respPag->json('data'));

        // 2. Filtre statut
        $respStatut = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/reservations?statut=ANNULEE');
        $respStatut->assertStatus(200);
        $this->assertCount(1, $respStatut->json('data'));

        // 3. Filtre actives
        $respActives = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/reservations?actives=true');
        $respActives->assertStatus(200);
        $this->assertCount(4, $respActives->json('data'));
    }

    public function test_can_show_and_update_reservation(): void
    {
        $reservation = Reservation::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'livre_id' => $this->livreA->id,
            'date_reservation' => now(),
            'date_expiration' => now()->addDays(3),
            'statut' => 'EN_ATTENTE',
            'observations' => 'Note initiale',
        ]);

        // Show
        $showResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/v1/reservations/{$reservation->id}");
        $showResp->assertStatus(200)
            ->assertJsonPath('data.observations', 'Note initiale')
            ->assertJsonPath('data.usager.email', 'kadiatou.d@example.com')
            ->assertJsonPath('data.livre.titre', 'L\'Étranger');

        // Update
        $newExp = now()->addDays(5)->toDateTimeString();
        $updResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson("/api/v1/reservations/{$reservation->id}", [
                'observations' => 'Prolongation manuelle accordée',
                'date_expiration' => $newExp,
            ]);

        $updResp->assertStatus(200)
            ->assertJsonPath('data.observations', 'Prolongation manuelle accordée');

        $this->assertEquals('Prolongation manuelle accordée', $reservation->fresh()->observations);
    }

    public function test_can_soft_delete_and_restore_reservation(): void
    {
        $reservation = Reservation::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'livre_id' => $this->livreA->id,
            'date_reservation' => now(),
            'date_expiration' => now()->addDays(3),
            'statut' => 'EN_ATTENTE',
        ]);

        // Delete
        $delResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->deleteJson("/api/v1/reservations/{$reservation->id}");

        $delResp->assertStatus(200);
        $this->assertSoftDeleted('reservations', ['id' => $reservation->id]);

        // Restore
        $resResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/reservations/{$reservation->id}/restore");

        $resResp->assertStatus(200);
        $this->assertNotSoftDeleted('reservations', ['id' => $reservation->id]);
    }

    public function test_anti_idor_and_tenant_isolation_on_reservations(): void
    {
        // Réservation sur le Site B
        $resB = Reservation::create([
            'site_id' => $this->siteB->id,
            'usager_id' => $this->usagerB->id,
            'livre_id' => $this->livreB->id,
            'date_reservation' => now(),
            'date_expiration' => now()->addDays(2),
            'statut' => 'EN_ATTENTE',
        ]);

        // 1. Accès direct depuis Site A -> 404
        $readResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/v1/reservations/{$resB->id}");
        $readResp->assertStatus(404);

        // 2. Annulation depuis Site A -> 404
        $annulerResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/reservations/{$resB->id}/annuler");
        $annulerResp->assertStatus(404);

        // 3. Modification depuis Site A -> 404
        $updResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson("/api/v1/reservations/{$resB->id}", ['observations' => 'Hack']);
        $updResp->assertStatus(404);

        // 4. Suppression depuis Site A -> 404
        $delResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->deleteJson("/api/v1/reservations/{$resB->id}");
        $delResp->assertStatus(404);
        $this->assertNotSoftDeleted('reservations', ['id' => $resB->id]);

        // 5. Non visible dans la liste du Site A
        $listResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/reservations');
        $listResp->assertStatus(200);
        $this->assertEmpty(collect($listResp->json('data'))->where('id', $resB->id));
    }

    public function test_permissions_enforcement_on_reservations(): void
    {
        $restrictedRole = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'STAGIAIRE_RES',
            'nom' => 'Stagiaire Réservations',
            'statut' => 'ACTIF',
        ]);

        $restrictedUser = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'nom' => 'Stagiaire',
            'prenom' => 'Res',
            'email' => 'stagiaire.res@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $restrictedRole->id,
            'statut' => 'ACTIF',
        ]);

        $restrictedToken = auth('api')->login($restrictedUser);

        // Lecture sans RESERVATION_READ -> 403 Forbidden
        $readResp = $this->withHeader('Authorization', 'Bearer ' . $restrictedToken)
            ->getJson('/api/v1/reservations');
        $readResp->assertStatus(403);

        // Création sans RESERVATION_CREATE -> 403 Forbidden
        $createResp = $this->withHeader('Authorization', 'Bearer ' . $restrictedToken)
            ->postJson('/api/v1/reservations', [
                'usager_id' => $this->usagerA->id,
                'livre_id' => $this->livreA->id,
            ]);
        $createResp->assertStatus(403);

        // Assigner RESERVATION_READ au rôle
        $permRead = Permission::create([
            'site_id' => $this->siteA->id,
            'code' => 'RESERVATION_READ',
            'libelle' => 'Lecture des réservations',
            'action' => 'READ',
        ]);
        $restrictedRole->permissions()->attach($permRead->id);

        // La lecture devient autorisée (200 OK)
        $allowedReadResp = $this->withHeader('Authorization', 'Bearer ' . $restrictedToken)
            ->getJson('/api/v1/reservations');
        $allowedReadResp->assertStatus(200);
    }

    public function test_reservation_factory(): void
    {
        $reservation = Reservation::factory()->create([
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'livre_id' => $this->livreA->id,
            'statut' => 'EN_ATTENTE',
        ]);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'site_id' => $this->siteA->id,
            'statut' => 'EN_ATTENTE',
        ]);
    }
}
