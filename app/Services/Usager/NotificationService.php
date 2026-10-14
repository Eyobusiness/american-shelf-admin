<?php

namespace App\Services\Usager;

use App\Mail\LibraryNotificationMail;
use App\Models\Notification;
use App\Models\Operation;
use App\Models\Reservation;
use App\Models\Usager;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class NotificationService
{
    /**
     * Liste paginée des notifications avec filtres multi-critères.
     */
    public function getAllPaginated(string $siteId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);

        $query = Notification::with(['usager', 'utilisateur'])
            ->where('site_id', $siteId);

        // Filtre par type de destinataire (ADMIN / USAGER)
        if (!empty($filters['destinataire_type'])) {
            $query->where('destinataire_type', $filters['destinataire_type']);
        }

        // Filtre par non lues (in-app)
        if (!empty($filters['non_lues']) && filter_var($filters['non_lues'], FILTER_VALIDATE_BOOLEAN)) {
            $query->whereNull('date_lecture');
        }

        // Filtre par utilisateur spécifique
        if (!empty($filters['utilisateur_id'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereNull('utilisateur_id')
                  ->orWhere('utilisateur_id', $filters['utilisateur_id']);
            });
        }

        // Recherche textuelle
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('objet', 'LIKE', $search)
                  ->orWhere('message', 'LIKE', $search)
                  ->orWhere('email_destinataire', 'LIKE', $search)
                  ->orWhereHas('usager', function ($uQuery) use ($search) {
                      $uQuery->where('nom', 'LIKE', $search)
                             ->orWhere('prenom', 'LIKE', $search)
                             ->orWhere('numero', 'LIKE', $search)
                             ->orWhere('email', 'LIKE', $search);
                  });
            });
        }

        // Filtre par statut d'envoi
        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        // Filtre par type d'événement
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Filtre par usager
        if (!empty($filters['usager_id'])) {
            $query->where('usager_id', $filters['usager_id']);
        }

        // Filtre par entité
        if (!empty($filters['entite_type'])) {
            $query->where('entite_type', $filters['entite_type']);
        }
        if (!empty($filters['entite_id'])) {
            $query->where('entite_id', $filters['entite_id']);
        }

        // Filtre par date exacte
        if (!empty($filters['date'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereDate('date_envoi', $filters['date'])
                  ->orWhereDate('created_at', $filters['date']);
            });
        }

        // Filtre par période
        if (!empty($filters['date_debut'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereDate('date_envoi', '>=', $filters['date_debut'])
                  ->orWhereDate('created_at', '>=', $filters['date_debut']);
            });
        }
        if (!empty($filters['date_fin'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereDate('date_envoi', '<=', $filters['date_fin'])
                  ->orWhereDate('created_at', '<=', $filters['date_fin']);
            });
        }

        // Tri sécurisé
        $allowedSorts = ['id', 'date_envoi', 'created_at', 'statut', 'type', 'objet', 'date_lecture'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts, true) ? $filters['sort_by'] : 'created_at';
        $order = in_array(strtolower($filters['sort_direction'] ?? $filters['order'] ?? ''), ['asc', 'desc'], true)
            ? strtolower($filters['sort_direction'] ?? $filters['order'] ?? '')
            : 'desc';

        return $query->orderBy($sortBy, $order)->paginate($perPage);
    }

    /**
     * Trouver une notification par son ID au sein du tenant courant.
     */
    public function findById(string $siteId, string $id, bool $withTrashed = false): ?Notification
    {
        $query = Notification::with(['usager', 'utilisateur'])
            ->where('site_id', $siteId);

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->find($id);
    }

    /**
     * Obtenir le nombre de notifications non lues (in-app).
     */
    public function getUnreadCount(string $siteId, ?string $userId = null, string $destinataireType = Notification::DESTINATAIRE_ADMIN): int
    {
        return Notification::where('site_id', $siteId)
            ->where('destinataire_type', $destinataireType)
            ->whereNull('date_lecture')
            ->when($userId, function ($q) use ($userId) {
                $q->where(function ($sub) use ($userId) {
                    $sub->whereNull('utilisateur_id')
                        ->orWhere('utilisateur_id', $userId);
                });
            })
            ->count();
    }

    /**
     * Marquer une notification comme lue.
     */
    public function markAsRead(string $siteId, string $id, ?string $userId = null): Notification
    {
        $query = Notification::where('site_id', $siteId);
        if ($userId) {
            $query->where(function ($q) use ($userId) {
                $q->whereNull('utilisateur_id')
                  ->orWhere('utilisateur_id', $userId);
            });
        }

        $notification = $query->findOrFail($id);

        if (is_null($notification->date_lecture)) {
            $notification->update([
                'date_lecture' => now(),
            ]);
        }

        return $notification->fresh(['usager', 'utilisateur']);
    }

    /**
     * Marquer toutes les notifications du destinataire comme lues.
     */
    public function markAllAsRead(string $siteId, ?string $userId = null, string $destinataireType = Notification::DESTINATAIRE_ADMIN): int
    {
        return Notification::where('site_id', $siteId)
            ->where('destinataire_type', $destinataireType)
            ->whereNull('date_lecture')
            ->when($userId, function ($q) use ($userId) {
                $q->where(function ($sub) use ($userId) {
                    $sub->whereNull('utilisateur_id')
                        ->orWhere('utilisateur_id', $userId);
                });
            })
            ->update([
                'date_lecture' => now(),
            ]);
    }

    /**
     * Création et envoi sécurisé d'une notification.
     */
    public function create(string $siteId, array $data): Notification
    {
        return DB::transaction(function () use ($siteId, $data) {
            $usager = null;
            if (!empty($data['usager_id'])) {
                $usager = Usager::find($data['usager_id']);
            }

            $destinataireType = $data['destinataire_type'] ?? (
                !empty($data['email_destinataire']) || !empty($data['usager_id'])
                    ? Notification::DESTINATAIRE_USAGER
                    : Notification::DESTINATAIRE_ADMIN
            );

            $emailDestinataire = $data['email_destinataire'] ?? ($destinataireType === Notification::DESTINATAIRE_USAGER ? $usager?->email : null);

            $initialStatut = $data['statut'] ?? (
                !empty($emailDestinataire)
                    ? Notification::STATUT_EN_ATTENTE
                    : ($destinataireType === Notification::DESTINATAIRE_ADMIN ? Notification::STATUT_ENVOYE : Notification::STATUT_ECHEC)
            );

            $notification = Notification::create([
                'site_id' => $siteId,
                'usager_id' => $data['usager_id'] ?? null,
                'utilisateur_id' => $data['utilisateur_id'] ?? null,
                'destinataire_type' => $destinataireType,
                'type' => $data['type'] ?? Notification::TYPE_EMAIL,
                'objet' => $data['objet'],
                'message' => $data['message'],
                'email_destinataire' => $emailDestinataire,
                'statut' => $initialStatut,
                'date_envoi' => null,
                'date_lecture' => $data['date_lecture'] ?? null,
                'entite_type' => $data['entite_type'] ?? null,
                'entite_id' => $data['entite_id'] ?? null,
                'action_url' => $data['action_url'] ?? null,
            ]);

            // Tentative d'envoi d'e-mail si une adresse est définie
            if (!empty($emailDestinataire)) {
                $this->sendEmail($notification, $data['badge_label'] ?? null, $data['details'] ?? []);
            }

            return $notification->fresh(['usager', 'utilisateur']);
        });
    }

    /**
     * Déclenchement de la notification de réservation créée.
     * Envoie l'e-mail automatique à l'usager ET génère l'alerte in-app pour l'administration.
     */
    public function notifyReservationCreee(Reservation $reservation): array
    {
        $reservation->loadMissing(['usager', 'livre']);
        $usager = $reservation->usager;
        $livre = $reservation->livre;

        $dateRes = $reservation->date_reservation ? Carbon::parse($reservation->date_reservation)->format('d/m/Y') : now()->format('d/m/Y');
        $dateExp = $reservation->date_expiration ? Carbon::parse($reservation->date_expiration)->format('d/m/Y') : 'N/A';
        $titreLivre = $livre ? $livre->titre : 'Ouvrage réservé';
        $nomUsager = $usager ? trim("{$usager->prenom} {$usager->nom}") : 'Lecteur';

        $notifications = [
            'usager_notification' => null,
            'admin_notification' => null,
        ];

        // 1. Notification par email à l'usager
        if ($usager && !empty($usager->email)) {
            $objetUsager = "Confirmation de réservation : {$titreLivre}";
            $messageUsager = "Votre réservation pour l'ouvrage \"{$titreLivre}\" a été confirmée avec succès.\n\n" .
                "Merci de retirer l'ouvrage auprès de notre équipe avant la date d'expiration.";

            $notifications['usager_notification'] = $this->create($reservation->site_id, [
                'usager_id' => $usager->id,
                'destinataire_type' => Notification::DESTINATAIRE_USAGER,
                'type' => Notification::TYPE_RESERVATION_CREEE,
                'objet' => $objetUsager,
                'message' => $messageUsager,
                'email_destinataire' => $usager->email,
                'entite_type' => 'Reservation',
                'entite_id' => $reservation->id,
                'action_url' => '/activites/reservations',
                'badge_label' => 'Réservation confirmée',
                'details' => [
                    'Ouvrage' => $titreLivre,
                    'Date de réservation' => $dateRes,
                    'Date limite de retrait' => $dateExp,
                    'Statut' => $reservation->statut,
                    'Lieu de retrait' => 'American Shelf Abobo (Abidjan)',
                ],
            ]);
        } else {
            Log::info("Aucune adresse email valide trouvée pour l'usager lors de la réservation {$reservation->id}");
        }

        // 2. Notification In-App pour l'équipe administrative (Header Bell)
        $objetAdmin = "Nouvelle réservation : {$titreLivre}";
        $messageAdmin = "L'usager {$nomUsager} a réservé l'ouvrage \"{$titreLivre}\" (Retrait attendu avant le {$dateExp}).";

        $notifications['admin_notification'] = $this->create($reservation->site_id, [
            'usager_id' => $usager?->id,
            'destinataire_type' => Notification::DESTINATAIRE_ADMIN,
            'type' => Notification::TYPE_RESERVATION_CREEE,
            'objet' => $objetAdmin,
            'message' => $messageAdmin,
            'entite_type' => 'Reservation',
            'entite_id' => $reservation->id,
            'action_url' => '/activites/reservations',
        ]);

        return $notifications;
    }

    /**
     * Déclenchement de la notification d'annulation de réservation.
     */
    public function notifyReservationAnnulee(Reservation $reservation, ?string $motif = null): array
    {
        $reservation->loadMissing(['usager', 'livre']);
        $usager = $reservation->usager;
        $livre = $reservation->livre;

        $titreLivre = $livre ? $livre->titre : 'Ouvrage réservé';
        $nomUsager = $usager ? trim("{$usager->prenom} {$usager->nom}") : 'Lecteur';

        $notifications = [
            'usager_notification' => null,
            'admin_notification' => null,
        ];

        // 1. E-mail à l'usager
        if ($usager && !empty($usager->email)) {
            $objet = "Annulation de réservation : {$titreLivre}";
            $message = "Votre réservation pour l'ouvrage \"{$titreLivre}\" a bien été annulée.";
            if (!empty($motif)) {
                $message .= "\nMotif : {$motif}";
            }

            $notifications['usager_notification'] = $this->create($reservation->site_id, [
                'usager_id' => $usager->id,
                'destinataire_type' => Notification::DESTINATAIRE_USAGER,
                'type' => Notification::TYPE_RESERVATION_ANNULEE,
                'objet' => $objet,
                'message' => $message,
                'email_destinataire' => $usager->email,
                'entite_type' => 'Reservation',
                'entite_id' => $reservation->id,
                'action_url' => '/activites/reservations',
                'badge_label' => 'Réservation annulée',
                'details' => [
                    'Ouvrage' => $titreLivre,
                    'Motif' => $motif ?? 'Annulation demandée',
                ],
            ]);
        }

        // 2. Alerte In-App pour l'administration
        $notifications['admin_notification'] = $this->create($reservation->site_id, [
            'usager_id' => $usager?->id,
            'destinataire_type' => Notification::DESTINATAIRE_ADMIN,
            'type' => Notification::TYPE_RESERVATION_ANNULEE,
            'objet' => "Réservation annulée : {$titreLivre}",
            'message' => "La réservation de l'ouvrage \"{$titreLivre}\" par {$nomUsager} a été annulée." . ($motif ? " (Motif : {$motif})" : ''),
            'entite_type' => 'Reservation',
            'entite_id' => $reservation->id,
            'action_url' => '/activites/reservations',
        ]);

        return $notifications;
    }

    /**
     * Déclenchement de la notification d'expiration de réservation.
     */
    public function notifyReservationExpiree(Reservation $reservation): array
    {
        $reservation->loadMissing(['usager', 'livre']);
        $usager = $reservation->usager;
        $livre = $reservation->livre;

        $titreLivre = $livre ? $livre->titre : 'Ouvrage réservé';
        $nomUsager = $usager ? trim("{$usager->prenom} {$usager->nom}") : 'Lecteur';

        $notifications = [
            'usager_notification' => null,
            'admin_notification' => null,
        ];

        // 1. E-mail à l'usager
        if ($usager && !empty($usager->email)) {
            $objet = "Expiration de votre réservation : {$titreLivre}";
            $message = "Votre réservation pour l'ouvrage \"{$titreLivre}\" est arrivée à expiration.\n" .
                "Le livre a été remis à la disposition des autres lecteurs de la bibliothèque.";

            $notifications['usager_notification'] = $this->create($reservation->site_id, [
                'usager_id' => $usager->id,
                'destinataire_type' => Notification::DESTINATAIRE_USAGER,
                'type' => Notification::TYPE_RESERVATION_EXPIREE,
                'objet' => $objet,
                'message' => $message,
                'email_destinataire' => $usager->email,
                'entite_type' => 'Reservation',
                'entite_id' => $reservation->id,
                'action_url' => '/activites/reservations',
                'badge_label' => 'Réservation expirée',
                'details' => [
                    'Ouvrage' => $titreLivre,
                    'Statut' => 'EXPIREE',
                ],
            ]);
        }

        // 2. Alerte In-App pour l'administration
        $notifications['admin_notification'] = $this->create($reservation->site_id, [
            'usager_id' => $usager?->id,
            'destinataire_type' => Notification::DESTINATAIRE_ADMIN,
            'type' => Notification::TYPE_RESERVATION_EXPIREE,
            'objet' => "Réservation expirée : {$titreLivre}",
            'message' => "La réservation de \"{$titreLivre}\" pour {$nomUsager} a expiré et le livre a été remis en circulation.",
            'entite_type' => 'Reservation',
            'entite_id' => $reservation->id,
            'action_url' => '/activites/reservations',
        ]);

        return $notifications;
    }

    /**
     * Déclenchement de l'alerte pour emprunt en retard avec prévention stricte des doublons.
     */
    public function notifyEmpruntEnRetard(Operation $operation): ?array
    {
        $operation->loadMissing(['usager', 'exemplaire.livre']);
        $usager = $operation->usager;
        $livre = $operation->exemplaire?->livre;

        $titreLivre = $livre?->titre ?? 'Ouvrage emprunté';
        $dateEcheance = $operation->date_retour_prevue
            ? Carbon::parse($operation->date_retour_prevue)->format('d/m/Y')
            : 'Échéance dépassée';
        $nomUsager = $usager ? trim("{$usager->prenom} {$usager->nom}") : 'Emprunteur';

        // Anti-doublon : Vérifier si une notification active a déjà été créée pour cet emprunt
        // au cours des 3 derniers jours.
        $alreadyNotified = Notification::where('site_id', $operation->site_id)
            ->where('entite_type', 'Operation')
            ->where('entite_id', $operation->id)
            ->where('type', Notification::TYPE_EMPRUNT_EN_RETARD)
            ->where('created_at', '>=', now()->subDays(3))
            ->exists();

        if ($alreadyNotified) {
            return null;
        }

        $result = [
            'usager_notification' => null,
            'admin_notification' => null,
        ];

        // 1. E-mail de relance à l'usager
        if ($usager && !empty($usager->email)) {
            $objetUsager = "Rappel urgent : Retard sur la restitution de \"{$titreLivre}\"";
            $messageUsager = "La date d'échéance de votre emprunt pour l'ouvrage \"{$titreLivre}\" était fixée au {$dateEcheance}.\n\n" .
                "Merci de bien vouloir rapporter cet ouvrage à la bibliothèque dans les meilleurs délais.";

            $result['usager_notification'] = $this->create($operation->site_id, [
                'usager_id' => $usager->id,
                'destinataire_type' => Notification::DESTINATAIRE_USAGER,
                'type' => Notification::TYPE_EMPRUNT_EN_RETARD,
                'objet' => $objetUsager,
                'message' => $messageUsager,
                'email_destinataire' => $usager->email,
                'entite_type' => 'Operation',
                'entite_id' => $operation->id,
                'action_url' => '/activites/emprunts',
                'badge_label' => 'Emprunt en retard',
                'details' => [
                    'Ouvrage' => $titreLivre,
                    'Date d\'échéance' => $dateEcheance,
                    'Statut' => 'EN RETARD',
                    'Consigne' => 'Restitution requise au guichet de prêt',
                ],
            ]);
        }

        // 2. Notification In-App pour l'équipe administrative (Header Bell)
        $objetAdmin = "Emprunt en retard : {$titreLivre}";
        $messageAdmin = "L'emprunt de \"{$titreLivre}\" par {$nomUsager} est en retard (Échéance : {$dateEcheance}).";

        $result['admin_notification'] = $this->create($operation->site_id, [
            'usager_id' => $usager?->id,
            'destinataire_type' => Notification::DESTINATAIRE_ADMIN,
            'type' => Notification::TYPE_EMPRUNT_EN_RETARD,
            'objet' => $objetAdmin,
            'message' => $messageAdmin,
            'entite_type' => 'Operation',
            'entite_id' => $operation->id,
            'action_url' => '/activites/emprunts',
        ]);

        return $result;
    }

    /**
     * Déclenchement de la notification de retour d'emprunt effectué.
     */
    public function notifyRetourEffectue(Operation $operation): ?array
    {
        $operation->loadMissing(['usager', 'exemplaire.livre']);
        $usager = $operation->usager;
        $livre = $operation->exemplaire?->livre;

        $titreLivre = $livre?->titre ?? 'Ouvrage';
        $nomUsager = $usager ? trim("{$usager->prenom} {$usager->nom}") : 'Lecteur';

        $result = [
            'usager_notification' => null,
            'admin_notification' => null,
        ];

        // 1. E-mail de confirmation à l'usager
        if ($usager && !empty($usager->email)) {
            $result['usager_notification'] = $this->create($operation->site_id, [
                'usager_id' => $usager->id,
                'destinataire_type' => Notification::DESTINATAIRE_USAGER,
                'type' => Notification::TYPE_RETOUR_EFFECTUE,
                'objet' => "Confirmation de retour : {$titreLivre}",
                'message' => "Le retour de votre emprunt pour l'ouvrage \"{$titreLivre}\" a été enregistré avec succès.\nMerci pour votre fidélité !",
                'email_destinataire' => $usager->email,
                'entite_type' => 'Operation',
                'entite_id' => $operation->id,
                'action_url' => '/activites/emprunts',
                'badge_label' => 'Retour effectué',
                'details' => [
                    'Ouvrage' => $titreLivre,
                    'Date de restitution' => now()->format('d/m/Y H:i'),
                ],
            ]);
        }

        // 2. Alerte In-App pour l'administration
        $result['admin_notification'] = $this->create($operation->site_id, [
            'usager_id' => $usager?->id,
            'destinataire_type' => Notification::DESTINATAIRE_ADMIN,
            'type' => Notification::TYPE_RETOUR_EFFECTUE,
            'objet' => "Retour effectué : {$titreLivre}",
            'message' => "L'exemplaire de \"{$titreLivre}\" a été restitué par {$nomUsager}.",
            'entite_type' => 'Operation',
            'entite_id' => $operation->id,
            'action_url' => '/activites/emprunts',
        ]);

        return $result;
    }

    /**
     * Envoi d'email via Mailable avec gestion des erreurs et mise à jour du statut.
     */
    public function sendEmail(Notification $notification, ?string $badgeLabel = null, array $details = []): void
    {
        try {
            $recipientName = $notification->usager
                ? "{$notification->usager->prenom} {$notification->usager->nom}"
                : null;

            Mail::to($notification->email_destinataire)->send(
                new LibraryNotificationMail(
                    subjectText: $notification->objet,
                    bodyContent: $notification->message,
                    recipientName: $recipientName,
                    typeBadge: $badgeLabel,
                    details: $details
                )
            );

            $notification->update([
                'statut' => Notification::STATUT_ENVOYE,
                'date_envoi' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Échec de l\'envoi de la notification par email', [
                'notification_id' => $notification->id,
                'destinataire' => $notification->email_destinataire,
                'error' => $e->getMessage(),
            ]);

            $notification->update([
                'statut' => Notification::STATUT_ECHEC,
            ]);
        }
    }

    /**
     * Suppression logique (Soft Delete).
     */
    public function delete(Notification $notification, ?string $userId = null): bool
    {
        return DB::transaction(function () use ($notification, $userId) {
            if ($userId) {
                $notification->deleted_by = $userId;
                $notification->save();
            }

            return (bool) $notification->delete();
        });
    }

    /**
     * Restauration d'une notification supprimée logiquement.
     */
    public function restore(string $siteId, string $id): ?Notification
    {
        $notification = Notification::onlyTrashed()
            ->where('site_id', $siteId)
            ->find($id);

        if (!$notification) {
            return null;
        }

        $notification->restore();
        $notification->deleted_by = null;
        $notification->save();

        return $notification->load(['usager', 'utilisateur']);
    }
}