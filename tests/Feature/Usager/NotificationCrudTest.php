<?php

namespace Tests\Feature\Usager;

use App\Models\CategorieLivre;
use App\Models\CategorieUsager;
use App\Models\Configuration;
use App\Models\Livre;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Site;
use App\Models\Usager;
use App\Models\UsagerSite;
use App\Models\Utilisateur;
use App\Services\Usager\ReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationCrudTest extends TestCase
{
    use DatabaseTransactions;

    protected Site $siteA;
    protected Site $siteB;
    protected Utilisateur $adminA;
    protected Utilisateur $adminB;
    protected string $tokenA;
    protected Usager $usagerA;
    protected Usager $usagerB;
    protected Livre $livreA;
    protected Configuration $configA;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        // 1. Sites
        $this->siteA = Site::create([
            'code' => 'SITE-NOTIF-A',
            'nom' => 'Site Notification A',
            'statut' => 'ACTIF',
        ]);

        $this->siteB = Site::create([
            'code' => 'SITE-NOTIF-B',
            'nom' => 'Site Notification B',
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
            'email' => 'admin.notif.a@example.com',
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
            'email' => 'admin.notif.b@example.com',
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
            'duree_reservation' => 3,
        ]);

        // 4. Usagers
        $catUsagerA = CategorieUsager::create([
            'site_id' => $this->siteA->id,
            'code' => 'ETUD-NOTIF-A',
            'nom' => 'Étudiant Site A',
        ]);

        $this->usagerA = Usager::create([
            'numero' => 'USG-NOTIF-A01',
            'nom' => 'Kone',
            'prenom' => 'Fatou',
            'email' => 'fatou.kone@example.com',
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
            'numero' => 'USG-NOTIF-B01',
            'nom' => 'Traore',
            'prenom' => 'Salif',
            'email' => 'salif.traore@example.com',
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
            'code' => 'ROM-NOTIF-A',
            'nom' => 'Roman Littéraire A',
        ]);

        $this->livreA = Livre::create([
            'site_id' => $this->siteA->id,
            'categorie_livre_id' => $catLivreA->id,
            'code' => 'LIV-NOTIF-A01',
            'titre' => 'Le Petit Prince',
            'auteur' => 'Antoine de Saint-Exupéry',
        ]);
    }

    public function test_can_create_manual_notification(): void
    {
        $payload = [
            'usager_id' => $this->usagerA->id,
            'type' => Notification::TYPE_EMAIL,
            'objet' => 'Information générale',
            'message' => 'La bibliothèque sera exceptionnellement fermée ce samedi.',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/notifications', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Notification créée et envoyée avec succès.',
                'data' => [
                    'site_id' => $this->siteA->id,
                    'usager_id' => $this->usagerA->id,
                    'email_destinataire' => 'fatou.kone@example.com',
                    'objet' => 'Information générale',
                    'statut' => Notification::STATUT_ENVOYE,
                ],
            ]);

        $this->assertDatabaseHas('notifications', [
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'email_destinataire' => 'fatou.kone@example.com',
            'statut' => Notification::STATUT_ENVOYE,
        ]);
    }

    public function test_can_list_notifications_with_filters_and_pagination(): void
    {
        // Créer 3 notifications avec statuts et types variés
        Notification::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'type' => Notification::TYPE_RESERVATION_CREEE,
            'objet' => 'Réservation confirmée 1',
            'message' => 'Message 1',
            'email_destinataire' => 'fatou.kone@example.com',
            'date_envoi' => now(),
            'statut' => Notification::STATUT_ENVOYE,
        ]);

        Notification::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'type' => Notification::TYPE_RESERVATION_ANNULEE,
            'objet' => 'Réservation annulée 2',
            'message' => 'Message 2',
            'email_destinataire' => 'fatou.kone@example.com',
            'date_envoi' => now(),
            'statut' => Notification::STATUT_ENVOYE,
        ]);

        Notification::create([
            'site_id' => $this->siteA->id,
            'usager_id' => null,
            'type' => Notification::TYPE_EMAIL,
            'objet' => 'Message en échec 3',
            'message' => 'Message 3',
            'email_destinataire' => 'externe@example.com',
            'date_envoi' => null,
            'statut' => Notification::STATUT_ECHEC,
        ]);

        // 1. Liste globale
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/notifications?per_page=10');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
        $this->assertCount(3, $response->json('data'));

        // 2. Filtre par type
        $typeResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/notifications?type=' . Notification::TYPE_RESERVATION_CREEE);
        $typeResp->assertStatus(200);
        $this->assertCount(1, $typeResp->json('data'));

        // 3. Filtre par statut
        $statutResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/notifications?statut=' . Notification::STATUT_ECHEC);
        $statutResp->assertStatus(200);
        $this->assertCount(1, $statutResp->json('data'));

        // 4. Recherche textuelle
        $searchResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/notifications?search=annulée');
        $searchResp->assertStatus(200);
        $this->assertCount(1, $searchResp->json('data'));
    }

    public function test_can_show_notification_detail(): void
    {
        $notification = Notification::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'type' => Notification::TYPE_RESERVATION_CREEE,
            'objet' => 'Détail test',
            'message' => 'Contenu du message',
            'email_destinataire' => 'fatou.kone@example.com',
            'date_envoi' => now(),
            'statut' => Notification::STATUT_ENVOYE,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/v1/notifications/{$notification->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $notification->id,
                    'objet' => 'Détail test',
                    'usager' => [
                        'id' => $this->usagerA->id,
                        'email' => 'fatou.kone@example.com',
                    ],
                ],
            ]);
    }

    public function test_can_soft_delete_and_restore_notification(): void
    {
        $notification = Notification::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'type' => Notification::TYPE_EMAIL,
            'objet' => 'A supprimer',
            'message' => 'Contenu',
            'email_destinataire' => 'fatou.kone@example.com',
            'date_envoi' => now(),
            'statut' => Notification::STATUT_ENVOYE,
        ]);

        // Suppression logique
        $delResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->deleteJson("/api/v1/notifications/{$notification->id}");

        $delResp->assertStatus(200);
        $this->assertSoftDeleted('notifications', [
            'id' => $notification->id,
            'deleted_by' => $this->adminA->id,
        ]);

        // La ressource n'est plus accessible normalement
        $getResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/v1/notifications/{$notification->id}");
        $getResp->assertStatus(404);

        // Restauration
        $restoreResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/notifications/{$notification->id}/restore");

        $restoreResp->assertStatus(200);
        $this->assertNotSoftDeleted('notifications', ['id' => $notification->id]);
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'deleted_by' => null,
        ]);
    }

    public function test_reservation_creation_triggers_notification(): void
    {
        // Créer une réservation via l'endpoint API
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/reservations', [
                'usager_id' => $this->usagerA->id,
                'livre_id' => $this->livreA->id,
            ]);

        $response->assertStatus(201);
        $reservationId = $response->json('data.id');

        // Vérifier qu'une notification de type RESERVATION_CREEE a été créée dans la BD
        $this->assertDatabaseHas('notifications', [
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'type' => Notification::TYPE_RESERVATION_CREEE,
            'email_destinataire' => 'fatou.kone@example.com',
            'statut' => Notification::STATUT_ENVOYE,
        ]);
    }

    public function test_reservation_cancellation_triggers_notification(): void
    {
        // 1. Créer une réservation
        $res = Reservation::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'livre_id' => $this->livreA->id,
            'date_reservation' => now(),
            'date_expiration' => now()->addDays(3),
            'statut' => 'EN_ATTENTE',
        ]);

        // 2. Annuler via l'API
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/reservations/{$res->id}/annuler", [
                'observations' => 'Annulé par le lecteur',
            ]);

        $response->assertStatus(200);

        // 3. Vérifier la notification de type RESERVATION_ANNULEE
        $this->assertDatabaseHas('notifications', [
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'type' => Notification::TYPE_RESERVATION_ANNULEE,
            'email_destinataire' => 'fatou.kone@example.com',
            'statut' => Notification::STATUT_ENVOYE,
        ]);
    }

    public function test_reservation_expiration_triggers_notification(): void
    {
        // 1. Créer une réservation expirée dans le passé
        $res = Reservation::create([
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'livre_id' => $this->livreA->id,
            'date_reservation' => now()->subDays(5),
            'date_expiration' => now()->subDays(2),
            'statut' => 'EN_ATTENTE',
        ]);

        // 2. Vérifier / mettre à jour via le service
        /** @var ReservationService $reservationService */
        $reservationService = app(ReservationService::class);
        $updatedRes = $reservationService->findById($this->siteA->id, $res->id);

        $this->assertEquals('EXPIREE', $updatedRes->statut);

        // 3. Vérifier la notification de type RESERVATION_EXPIREE
        $this->assertDatabaseHas('notifications', [
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
            'type' => Notification::TYPE_RESERVATION_EXPIREE,
            'email_destinataire' => 'fatou.kone@example.com',
            'statut' => Notification::STATUT_ENVOYE,
        ]);
    }

    public function test_cross_tenant_isolation_and_anti_idor_on_notifications(): void
    {
        // Créer une notification sur Site B
        $notifB = Notification::create([
            'site_id' => $this->siteB->id,
            'usager_id' => $this->usagerB->id,
            'type' => Notification::TYPE_EMAIL,
            'objet' => 'Notification Confidentielle Site B',
            'message' => 'Confidentiel',
            'email_destinataire' => 'salif.traore@example.com',
            'date_envoi' => now(),
            'statut' => Notification::STATUT_ENVOYE,
        ]);

        // 1. Lecture directe depuis Site A -> 404
        $readResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/v1/notifications/{$notifB->id}");
        $readResp->assertStatus(404);

        // 2. Suppression depuis Site A -> 404
        $delResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->deleteJson("/api/v1/notifications/{$notifB->id}");
        $delResp->assertStatus(404);
        $this->assertNotSoftDeleted('notifications', ['id' => $notifB->id]);

        // 3. Non visible dans la liste du Site A
        $listResp = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/notifications');
        $listResp->assertStatus(200);
        $this->assertEmpty(collect($listResp->json('data'))->where('id', $notifB->id));
    }

    public function test_permissions_enforcement_on_notifications(): void
    {
        $restrictedRole = Role::create([
            'site_id' => $this->siteA->id,
            'code' => 'STAGIAIRE_NOTIF',
            'nom' => 'Stagiaire Notifications',
            'statut' => 'ACTIF',
        ]);

        $restrictedUser = Utilisateur::create([
            'site_id' => $this->siteA->id,
            'nom' => 'Stagiaire',
            'prenom' => 'Notif',
            'email' => 'stagiaire.notif@example.com',
            'mot_de_passe' => Hash::make('Password123!'),
            'role_id' => $restrictedRole->id,
            'statut' => 'ACTIF',
        ]);

        $restrictedToken = auth('api')->login($restrictedUser);

        // Lecture sans NOTIFICATION_READ -> 403
        $readResp = $this->withHeader('Authorization', 'Bearer ' . $restrictedToken)
            ->getJson('/api/v1/notifications');
        $readResp->assertStatus(403);

        // Création sans NOTIFICATION_CREATE -> 403
        $createResp = $this->withHeader('Authorization', 'Bearer ' . $restrictedToken)
            ->postJson('/api/v1/notifications', [
                'usager_id' => $this->usagerA->id,
                'objet' => 'Test permission',
                'message' => 'Test',
            ]);
        $createResp->assertStatus(403);

        // Assigner la permission NOTIFICATION_READ
        $permRead = Permission::create([
            'site_id' => $this->siteA->id,
            'code' => 'NOTIFICATION_READ',
            'libelle' => 'Lecture des notifications',
            'action' => 'READ',
        ]);
        $restrictedRole->permissions()->attach($permRead->id);

        // Lecture maintenant autorisée (200)
        $allowedRead = $this->withHeader('Authorization', 'Bearer ' . $restrictedToken)
            ->getJson('/api/v1/notifications');
        $allowedRead->assertStatus(200);
    }

    public function test_notification_factory(): void
    {
        $notif = Notification::factory()->create([
            'site_id' => $this->siteA->id,
            'usager_id' => $this->usagerA->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $notif->id,
            'site_id' => $this->siteA->id,
        ]);
    }
}
