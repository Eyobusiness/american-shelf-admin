<?php

namespace App\Services\Usager;

use App\Models\Livre;
use App\Models\Reservation;
use App\Models\UsagerSite;
use App\Services\Admin\ConfigurationService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function __construct(
        protected ConfigurationService $configurationService,
        protected NotificationService $notificationService
    ) {}

    /**
     * Liste paginée des réservations avec filtres multi-critères et recherche.
     */
    public function getAllPaginated(string $siteId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);

        // Mettre à jour automatiquement les réservations expirées du site
        $this->updateExpiredReservations($siteId);

        $query = Reservation::with(['usager', 'livre.categorieLivre'])
            ->where('site_id', $siteId);

        // Recherche textuelle
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('observations', 'LIKE', $search)
                  ->orWhereHas('usager', function ($uQuery) use ($search) {
                      $uQuery->where('nom', 'LIKE', $search)
                             ->orWhere('prenom', 'LIKE', $search)
                             ->orWhere('numero', 'LIKE', $search)
                             ->orWhere('email', 'LIKE', $search);
                  })
                  ->orWhereHas('livre', function ($lQuery) use ($search) {
                      $lQuery->where('titre', 'LIKE', $search)
                             ->orWhere('auteur', 'LIKE', $search)
                             ->orWhere('code', 'LIKE', $search)
                             ->orWhere('isbn', 'LIKE', $search);
                  });
            });
        }

        // Filtre par statut
        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        // Filtre par usager
        if (!empty($filters['usager_id'])) {
            $query->where('usager_id', $filters['usager_id']);
        }

        // Filtre par livre
        if (!empty($filters['livre_id'])) {
            $query->where('livre_id', $filters['livre_id']);
        }

        // Filtre réservations actives (EN_ATTENTE, DISPONIBLE)
        if (!empty($filters['actives'])) {
            $query->whereIn('statut', ['EN_ATTENTE', 'DISPONIBLE']);
        }

        // Filtre par période de réservation
        if (!empty($filters['date_debut'])) {
            $query->whereDate('date_reservation', '>=', $filters['date_debut']);
        }
        if (!empty($filters['date_fin'])) {
            $query->whereDate('date_reservation', '<=', $filters['date_fin']);
        }

        // Tri sécurisé
        $allowedSorts = ['id', 'date_reservation', 'date_expiration', 'statut', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts, true) ? $filters['sort_by'] : 'date_reservation';
        $order = in_array(strtolower($filters['sort_direction'] ?? $filters['order'] ?? ''), ['asc', 'desc'], true)
            ? strtolower($filters['sort_direction'] ?? $filters['order'] ?? '')
            : 'desc';

        return $query->orderBy($sortBy, $order)->paginate($perPage);
    }

    /**
     * Trouver une réservation par ID au sein du site courant.
     */
    public function findById(string $siteId, string $id, bool $withTrashed = false): ?Reservation
    {
        $query = Reservation::with(['usager', 'livre.categorieLivre'])
            ->where('site_id', $siteId);

        if ($withTrashed) {
            $query->withTrashed();
        }

        $reservation = $query->find($id);

        if ($reservation) {
            $this->checkAndUpdateExpired($reservation);
        }

        return $reservation;
    }

    /**
     * Création transactionnelle d'une réservation pour un livre.
     */
    public function create(string $siteId, array $data): Reservation
    {
        return DB::transaction(function () use ($siteId, $data) {
            // 1. Vérifier l'adhésion active de l'usager sur ce site
            $usagerSite = UsagerSite::where('site_id', $siteId)
                ->where('usager_id', $data['usager_id'])
                ->where('statut', 'ACTIF')
                ->first();

            if (!$usagerSite) {
                throw ValidationException::withMessages([
                    'usager_id' => ["L'usager sélectionné n'a pas d'adhésion active sur ce site."],
                ]);
            }

            // 2. Vérifier que le livre appartient au site courant et n'est pas supprimé
            $livre = Livre::where('site_id', $siteId)
                ->where('id', $data['livre_id'])
                ->first();

            if (!$livre) {
                throw ValidationException::withMessages([
                    'livre_id' => ["Le livre demandé n'appartient pas à ce site ou n'existe pas."],
                ]);
            }

            // 3. Empêcher les doublons de réservation active pour le même usager et livre
            $existingActive = Reservation::where('site_id', $siteId)
                ->where('usager_id', $data['usager_id'])
                ->where('livre_id', $data['livre_id'])
                ->whereIn('statut', ['EN_ATTENTE', 'DISPONIBLE'])
                ->exists();

            if ($existingActive) {
                throw ValidationException::withMessages([
                    'livre_id' => ["Une réservation active existe déjà pour cet usager sur ce livre."],
                ]);
            }

            // 4. Calcul de la date d'expiration via la configuration du site
            $config = $this->configurationService->getForSite($siteId);
            $dureeReservation = (int) ($config->duree_reservation ?: 2);

            $dateReservation = !empty($data['date_reservation'])
                ? Carbon::parse($data['date_reservation'])
                : now();

            $dateExpiration = $dateReservation->copy()->addDays($dureeReservation);

            // 5. Création de la réservation
            $reservation = Reservation::create([
                'site_id' => $siteId,
                'usager_id' => $data['usager_id'],
                'livre_id' => $data['livre_id'],
                'date_reservation' => $dateReservation,
                'date_expiration' => $dateExpiration,
                'statut' => 'EN_ATTENTE',
                'observations' => $data['observations'] ?? null,
            ]);

            // Notification usager
            $this->notificationService->notifyReservationCreee($reservation);

            return $reservation->load(['usager', 'livre.categorieLivre']);
        });
    }

    /**
     * Annulation d'une réservation.
     */
    public function annuler(string $siteId, string $id, array $data = []): Reservation
    {
        return DB::transaction(function () use ($siteId, $id, $data) {
            $reservation = Reservation::where('site_id', $siteId)
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($reservation->statut, ['HONOREE', 'ANNULEE', 'EXPIREE'], true)) {
                throw ValidationException::withMessages([
                    'statut' => ["Cette réservation ne peut plus être annulée car son statut est {$reservation->statut}."],
                ]);
            }

            $observations = $reservation->observations;
            if (!empty($data['observations'])) {
                $observations = $observations
                    ? $observations . ' | Annulation: ' . $data['observations']
                    : 'Annulation: ' . $data['observations'];
            }

            $reservation->update([
                'statut' => 'ANNULEE',
                'observations' => $observations,
            ]);

            // Notification usager
            $this->notificationService->notifyReservationAnnulee($reservation, $data['observations'] ?? null);

            return $reservation->fresh(['usager', 'livre.categorieLivre']);
        });
    }

    /**
     * Mise à jour des informations modifiables d'une réservation.
     */
    public function update(Reservation $reservation, array $data): Reservation
    {
        return DB::transaction(function () use ($reservation, $data) {
            $updateData = [];

            if (array_key_exists('observations', $data)) {
                $updateData['observations'] = $data['observations'];
            }

            if (!empty($data['date_expiration'])) {
                $updateData['date_expiration'] = Carbon::parse($data['date_expiration']);
            }

            if (!empty($data['statut'])) {
                $updateData['statut'] = $data['statut'];
            }

            if (!empty($updateData)) {
                $reservation->update($updateData);
            }

            return $reservation->fresh(['usager', 'livre.categorieLivre']);
        });
    }

    /**
     * Suppression logique d'une réservation (Soft Delete).
     */
    public function delete(Reservation $reservation, ?string $userId = null): bool
    {
        return DB::transaction(function () use ($reservation, $userId) {
            if ($userId) {
                $reservation->deleted_by = $userId;
                $reservation->save();
            }

            return (bool) $reservation->delete();
        });
    }

    /**
     * Restauration d'une réservation supprimée logiquement.
     */
    public function restore(string $siteId, string $id): ?Reservation
    {
        $reservation = Reservation::onlyTrashed()
            ->where('site_id', $siteId)
            ->find($id);

        if (!$reservation) {
            return null;
        }

        $reservation->restore();
        $reservation->deleted_by = null;
        $reservation->save();

        return $reservation->load(['usager', 'livre.categorieLivre']);
    }

    /**
     * Vérifier si une réservation a dépassé sa date d'expiration et mettre à jour le statut.
     */
    public function checkAndUpdateExpired(Reservation $reservation): Reservation
    {
        if (in_array($reservation->statut, ['EN_ATTENTE', 'DISPONIBLE'], true)
            && $reservation->date_expiration
            && now()->isAfter($reservation->date_expiration)
        ) {
            $reservation->update(['statut' => 'EXPIREE']);
            $this->notificationService->notifyReservationExpiree($reservation);
        }

        return $reservation;
    }

    /**
     * Mettre à jour en masse les réservations expirées pour un site.
     */
    public function updateExpiredReservations(string $siteId): int
    {
        $expired = Reservation::with(['usager', 'livre'])
            ->where('site_id', $siteId)
            ->whereIn('statut', ['EN_ATTENTE', 'DISPONIBLE'])
            ->where('date_expiration', '<', now())
            ->get();

        foreach ($expired as $res) {
            $res->update(['statut' => 'EXPIREE']);
            $this->notificationService->notifyReservationExpiree($res);
        }

        return $expired->count();
    }
}
