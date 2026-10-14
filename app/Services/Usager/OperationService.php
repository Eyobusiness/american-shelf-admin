<?php

namespace App\Services\Usager;

use App\Models\Configuration;
use App\Models\Exemplaire;
use App\Models\Operation;
use App\Models\UsagerSite;
use App\Services\Admin\ConfigurationService;
use App\Services\Document\ExemplaireMouvementService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationService
{
    public function __construct(
        protected ConfigurationService $configurationService,
        protected NotificationService $notificationService,
        protected ?ExemplaireMouvementService $mouvementService = null
    ) {
        $this->mouvementService = $mouvementService ?? new ExemplaireMouvementService();
    }

    /**
     * Liste paginée des opérations avec filtres multi-critères et recherche.
     */
    public function getAllPaginated(string $siteId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);

        $query = Operation::with(['usager', 'exemplaire.livre', 'configuration'])
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
                  ->orWhereHas('exemplaire', function ($eQuery) use ($search) {
                      $eQuery->where('code', 'LIKE', $search)
                             ->orWhere('localisation', 'LIKE', $search)
                             ->orWhereHas('livre', function ($lQuery) use ($search) {
                                 $lQuery->where('titre', 'LIKE', $search)
                                        ->orWhere('auteur', 'LIKE', $search)
                                        ->orWhere('code', 'LIKE', $search)
                                        ->orWhere('isbn', 'LIKE', $search);
                             });
                  });
            });
        }

        // Filtre par type (CONSULTATION, EMPRUNT)
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Filtre par statut (EN_COURS, RETOURNE, EN_RETARD, PERDU)
        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        // Filtre par usager
        if (!empty($filters['usager_id'])) {
            $query->where('usager_id', $filters['usager_id']);
        }

        // Filtre par exemplaire
        if (!empty($filters['exemplaire_id'])) {
            $query->where('exemplaire_id', $filters['exemplaire_id']);
        }

        // Filtre par date_operation début / fin
        if (!empty($filters['date_operation_debut'])) {
            $query->whereDate('date_operation', '>=', $filters['date_operation_debut']);
        }
        if (!empty($filters['date_operation_fin'])) {
            $query->whereDate('date_operation', '<=', $filters['date_operation_fin']);
        }

        // Filtre opérations en retard
        if (!empty($filters['en_retard'])) {
            $query->where(function ($q) {
                $q->where('statut', 'EN_RETARD')
                  ->orWhere(function ($sub) {
                      $sub->where('statut', 'EN_COURS')
                          ->whereNotNull('date_retour_prevue')
                          ->where('date_retour_prevue', '<', now());
                  });
            });
        }

        // Tri sécurisé
        $allowedSorts = ['id', 'type', 'statut', 'date_operation', 'date_retour_prevue', 'date_retour_effective', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts, true) ? $filters['sort_by'] : 'date_operation';
        $order = in_array(strtolower($filters['order'] ?? ''), ['asc', 'desc'], true) ? strtolower($filters['order']) : 'desc';

        return $query->orderBy($sortBy, $order)->paginate($perPage);
    }

    /**
     * Récupère une opération par son ID et son site.
     */
    public function findById(string $siteId, string $id, bool $withTrashed = false): ?Operation
    {
        $query = Operation::with(['usager', 'exemplaire.livre', 'configuration'])
            ->where('site_id', $siteId);

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->find($id);
    }

    /**
     * Crée une nouvelle opération (EMPRUNT ou CONSULTATION) de manière transactionnelle.
     */
    public function create(string $siteId, array $data): Operation
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

            // 2. Verrouillage pessimiste de l'exemplaire sur ce site
            $exemplaire = Exemplaire::where('site_id', $siteId)
                ->where('id', $data['exemplaire_id'])
                ->lockForUpdate()
                ->first();

            if (!$exemplaire) {
                throw ValidationException::withMessages([
                    'exemplaire_id' => ["L'exemplaire demandé n'appartient pas à ce site ou n'existe pas."],
                ]);
            }

            if ($exemplaire->statut !== 'DISPONIBLE') {
                throw ValidationException::withMessages([
                    'exemplaire_id' => ["L'exemplaire n'est pas disponible pour cette opération (statut actuel : {$exemplaire->statut})."],
                ]);
            }

            // 3. Récupérer et figer la configuration active du site
            $config = $this->configurationService->getForSite($siteId);

            $dateOperation = !empty($data['date_operation'])
                ? Carbon::parse($data['date_operation'])
                : now();

            if ($data['type'] === 'EMPRUNT') {
                // 4. Contrôle du quota maximal d'emprunts en cours
                $activeLoans = Operation::where('site_id', $siteId)
                    ->where('usager_id', $data['usager_id'])
                    ->where('type', 'EMPRUNT')
                    ->where('statut', 'EN_COURS')
                    ->count();

                if ($activeLoans >= $config->quota_emprunt) {
                    throw ValidationException::withMessages([
                        'usager_id' => ["Le quota maximal d'emprunts simultanés ({$config->quota_emprunt}) est atteint pour cet usager."],
                    ]);
                }

                // Calcul date de retour prévue pour emprunt
                $duree = $config->duree_emprunt ?: 14;
                $dateRetourPrevue = $dateOperation->copy()->addDays($duree);

                $ancienStatut = $exemplaire->statut;
                // Mise à jour de l'état de l'exemplaire
                $exemplaire->update(['statut' => 'EMPRUNTE']);
            } else {
                // CONSULTATION
                $dureeHeures = $config->duree_consultation ?: 2;
                $dateRetourPrevue = $dateOperation->copy()->addHours($dureeHeures);

                $ancienStatut = $exemplaire->statut;
                // Mise à jour de l'état de l'exemplaire
                $exemplaire->update(['statut' => 'EN_CONSULTATION']);
            }

            // 5. Création de l'opération
            $operation = Operation::create([
                'site_id' => $siteId,
                'configuration_id' => $config->id,
                'usager_id' => $data['usager_id'],
                'exemplaire_id' => $data['exemplaire_id'],
                'type' => $data['type'],
                'date_operation' => $dateOperation,
                'date_retour_prevue' => $dateRetourPrevue,
                'date_retour_effective' => null,
                'statut' => 'EN_COURS',
                'observations' => $data['observations'] ?? null,
            ]);

            // Traçabilité du mouvement d'inventaire
            $this->mouvementService->log(
                $exemplaire,
                $exemplaire->statut,
                $exemplaire->etat,
                $ancienStatut,
                $exemplaire->etat,
                $data['type'] === 'EMPRUNT' ? 'Prêt de l\'exemplaire' : 'Consultation sur place',
                $operation->id
            );

            return $operation->load(['usager', 'exemplaire.livre', 'configuration']);
        });
    }

    /**
     * Clôture / Retour d'une opération de manière transactionnelle avec verrouillage.
     */
    public function retour(string $siteId, string $id, array $data): Operation
    {
        return DB::transaction(function () use ($siteId, $id, $data) {
            // 1. Verrouillage de l'opération
            $operation = Operation::with('configuration')
                ->where('site_id', $siteId)
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($operation->statut !== 'EN_COURS') {
                throw ValidationException::withMessages([
                    'statut' => ["Cette opération est déjà clôturée avec le statut {$operation->statut}."],
                ]);
            }

            // 2. Verrouillage de l'exemplaire associé
            $exemplaire = Exemplaire::where('site_id', $siteId)
                ->where('id', $operation->exemplaire_id)
                ->lockForUpdate()
                ->firstOrFail();

            $dateRetourEffective = !empty($data['date_retour_effective'])
                ? Carbon::parse($data['date_retour_effective'])
                : now();

            // 3. Calcul de retard avec jours de grâce
            $graceDays = (int) ($operation->configuration?->nombre_jours_grace ?? 0);
            $dateLimite = $operation->date_retour_prevue
                ? ($operation->type === 'EMPRUNT'
                    ? $operation->date_retour_prevue->copy()->addDays($graceDays)
                    : $operation->date_retour_prevue->copy())
                : null;

            $isLate = $dateLimite ? $dateRetourEffective->isAfter($dateLimite) : false;
            $etatExemplaire = $data['etat_exemplaire'] ?? null;
            $ancienEtatExemplaire = $exemplaire->etat;
            $ancienStatutExemplaire = $exemplaire->statut;

            // 4. Détermination du statut final et mise à jour exemplaire
            if ($etatExemplaire === 'PERDU') {
                $finalStatut = 'PERDU';
                $exemplaire->update([
                    'etat' => 'PERDU',
                    'statut' => 'INDISPONIBLE',
                ]);

                // Traçabilité de l'inventaire
                $this->mouvementService->log(
                    $exemplaire,
                    'INDISPONIBLE',
                    'PERDU',
                    $ancienStatutExemplaire,
                    $ancienEtatExemplaire,
                    'Retour avec déclaration de perte',
                    $operation->id
                );
            } else {
                $finalStatut = $isLate ? 'EN_RETARD' : 'RETOURNE';
                $exemplaireUpdates = ['statut' => 'DISPONIBLE'];
                if (!empty($etatExemplaire)) {
                    $exemplaireUpdates['etat'] = $etatExemplaire;
                }
                $exemplaire->update($exemplaireUpdates);

                // Traçabilité de l'inventaire
                $this->mouvementService->log(
                    $exemplaire,
                    'DISPONIBLE',
                    $exemplaire->etat,
                    $ancienStatutExemplaire,
                    $ancienEtatExemplaire,
                    $isLate ? 'Retour effectif avec retard' : 'Retour effectif dans les délais',
                    $operation->id
                );
            }

            // 5. Mise à jour de l'opération
            $observations = $operation->observations;
            if (!empty($data['observations'])) {
                $observations = $observations
                    ? $observations . ' | Clôture: ' . $data['observations']
                    : $data['observations'];
            }

            $operation->update([
                'date_retour_effective' => $dateRetourEffective,
                'statut' => $finalStatut,
                'observations' => $observations,
            ]);

            // Notification de retour
            $this->notificationService->notifyRetourEffectue($operation);

            return $operation->fresh(['usager', 'exemplaire.livre', 'configuration']);
        });
    }

    /**
     * Met à jour les informations modifiables d'une opération.
     */
    public function update(Operation $operation, array $data): Operation
    {
        return DB::transaction(function () use ($operation, $data) {
            $operation->update($data);
            return $operation->fresh(['usager', 'exemplaire.livre', 'configuration']);
        });
    }

    /**
     * Supprime une opération (Soft Delete).
     * Si l'opération était en cours, remet l'exemplaire en DISPONIBLE.
     */
    public function delete(Operation $operation, ?string $userId = null): bool
    {
        return DB::transaction(function () use ($operation, $userId) {
            if ($operation->statut === 'EN_COURS') {
                $exemplaire = Exemplaire::where('site_id', $operation->site_id)
                    ->where('id', $operation->exemplaire_id)
                    ->first();

                if ($exemplaire && in_array($exemplaire->statut, ['EMPRUNTE', 'EN_CONSULTATION'], true)) {
                    $ancStatut = $exemplaire->statut;
                    $exemplaire->update(['statut' => 'DISPONIBLE']);
                    $this->mouvementService->log(
                        $exemplaire,
                        'DISPONIBLE',
                        $exemplaire->etat,
                        $ancStatut,
                        $exemplaire->etat,
                        'Annulation/suppression de l\'opération en cours',
                        $operation->id,
                        $userId
                    );
                }
            }

            return (bool) $operation->deleteWithAuditor($userId);
        });
    }

    /**
     * Restaure une opération supprimée.
     */
    public function restore(string $siteId, string $id): ?Operation
    {
        $operation = Operation::onlyTrashed()
            ->where('site_id', $siteId)
            ->find($id);

        if (!$operation) {
            return null;
        }

        $operation->restore();

        return $operation->load(['usager', 'exemplaire.livre', 'configuration']);
    }
}