<?php

namespace App\Services\Document;

use App\Models\Acquisition;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AcquisitionService
{
    /**
     * Liste paginée des acquisitions d'un site avec filtres et recherche.
     */
    public function getAllPaginated(string $siteId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);
        $query = Acquisition::where('site_id', $siteId);

        // Recherche textuelle multi-colonnes
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('type', 'LIKE', $search)
                  ->orWhere('provenance', 'LIKE', $search)
                  ->orWhere('reference_facture', 'LIKE', $search)
                  ->orWhere('donateur', 'LIKE', $search)
                  ->orWhere('nature_don', 'LIKE', $search)
                  ->orWhere('observations', 'LIKE', $search);
            });
        }

        // Filtre par type (ACHAT, DON, etc.)
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Filtre par état
        if (!empty($filters['etat'])) {
            $query->where('etat', $filters['etat']);
        }

        // Filtre par date
        if (!empty($filters['date_acquisition'])) {
            $query->whereDate('date_acquisition', $filters['date_acquisition']);
        }

        // Tri sécurisé
        $allowedSorts = ['id', 'type', 'date_acquisition', 'quantite', 'cout', 'etat', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts, true) ? $filters['sort_by'] : 'date_acquisition';
        $sortDirection = strtolower($filters['sort_direction'] ?? '') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortDirection)->paginate($perPage);
    }

    /**
     * Trouver une acquisition par ID au sein du site.
     */
    public function findById(string $siteId, string $id, bool $withTrashed = false): ?Acquisition
    {
        $query = $withTrashed ? Acquisition::withTrashed() : Acquisition::query();
        return $query->where('site_id', $siteId)->where('id', $id)->first();
    }

    /**
     * Créer une acquisition.
     */
    public function create(string $siteId, array $data, ?UploadedFile $file = null): Acquisition
    {
        $data['site_id'] = $siteId;

        if ($file) {
            $path = $file->store('acquisitions/' . $siteId, 'public');
            $data['piece_jointe'] = $path;
        }

        return DB::transaction(function () use ($data) {
            return Acquisition::create($data);
        });
    }

    /**
     * Mettre à jour une acquisition.
     */
    public function update(Acquisition $acquisition, array $data, ?UploadedFile $file = null): Acquisition
    {
        if ($file) {
            // Supprimer l'ancien fichier si existant
            if ($acquisition->piece_jointe && Storage::disk('public')->exists($acquisition->piece_jointe)) {
                Storage::disk('public')->delete($acquisition->piece_jointe);
            }
            $path = $file->store('acquisitions/' . $acquisition->site_id, 'public');
            $data['piece_jointe'] = $path;
        }

        return DB::transaction(function () use ($acquisition, $data) {
            $acquisition->update($data);
            return $acquisition->fresh();
        });
    }

    /**
     * Suppression logique d'une acquisition.
     */
    public function delete(Acquisition $acquisition, ?string $userId = null): bool
    {
        if ($userId) {
            $acquisition->deleted_by = $userId;
            $acquisition->save();
        }
        return (bool) $acquisition->delete();
    }

    /**
     * Restaurer une acquisition supprimée.
     */
    public function restore(string $siteId, string $id): ?Acquisition
    {
        $acquisition = Acquisition::onlyTrashed()
            ->where('site_id', $siteId)
            ->where('id', $id)
            ->first();

        if ($acquisition) {
            $acquisition->restore();
            $acquisition->deleted_by = null;
            $acquisition->save();
            return $acquisition;
        }

        return null;
    }
}
