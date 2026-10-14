<?php

namespace App\Services\Document;

use App\Models\Livre;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LivreService
{
    /**
     * Liste paginée des livres d'un site avec filtres, recherche et comptage dynamique des exemplaires.
     */
    public function getAllPaginated(string $siteId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);

        $query = Livre::with(['categorieLivre'])
            ->withCount('exemplaires')
            ->where('site_id', $siteId);

        // Recherche multi-champs (code, titre, auteur, éditeur, ISBN)
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('code', 'LIKE', $search)
                  ->orWhere('titre', 'LIKE', $search)
                  ->orWhere('auteur', 'LIKE', $search)
                  ->orWhere('editeur', 'LIKE', $search)
                  ->orWhere('isbn', 'LIKE', $search);
            });
        }

        // Filtre par catégorie de livre
        if (!empty($filters['categorie_livre_id'])) {
            $query->where('categorie_livre_id', $filters['categorie_livre_id']);
        }

        // Filtre par statut (ACTIF, INACTIF, ARCHIVE)
        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        // Filtre par visibilité en ligne
        if (isset($filters['visible_en_ligne']) && $filters['visible_en_ligne'] !== '') {
            $visible = filter_var($filters['visible_en_ligne'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($visible !== null) {
                $query->where('visible_en_ligne', $visible);
            }
        }

        // Filtre par auteur
        if (!empty($filters['auteur'])) {
            $query->where('auteur', 'LIKE', '%' . $filters['auteur'] . '%');
        }

        // Filtre par année de publication
        if (!empty($filters['annee_publication'])) {
            $query->where('annee_publication', (int) $filters['annee_publication']);
        }

        // Tri sécurisé
        $allowedSorts = ['id', 'code', 'titre', 'auteur', 'annee_publication', 'statut', 'created_at', 'exemplaires_count'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts, true) ? $filters['sort_by'] : 'created_at';
        $sortDirection = strtolower($filters['sort_direction'] ?? '') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortDirection)->paginate($perPage);
    }

    /**
     * Trouver un livre par ID avec étanchéité multi-tenant stricte.
     */
    public function findById(string $siteId, string $id, bool $withTrashed = false): ?Livre
    {
        $query = $withTrashed ? Livre::withTrashed() : Livre::query();

        return $query->with(['categorieLivre'])
            ->withCount('exemplaires')
            ->where('site_id', $siteId)
            ->where('id', $id)
            ->first();
    }

    /**
     * Créer un nouveau livre avec stockage éventuel de l'image de couverture et du fichier numérique.
     */
    public function create(string $siteId, array $data, ?UploadedFile $image = null, ?UploadedFile $fichier = null): Livre
    {
        $data['site_id'] = $siteId;

        // Stockage de l'image de couverture
        if ($image) {
            $path = $image->store('livres/images/' . $siteId, 'public');
            $data['image_principale'] = $path;
        }

        // Stockage du fichier numérique (PDF/EPUB)
        if ($fichier) {
            $path = $fichier->store('livres/fichiers/' . $siteId, 'public');
            $data['fichier_numerique'] = $path;
        }

        return DB::transaction(function () use ($data) {
            $livre = Livre::create($data);
            return $livre->load('categorieLivre')->loadCount('exemplaires');
        });
    }

    /**
     * Mettre à jour un livre et actualiser les fichiers si fournis.
     */
    public function update(Livre $livre, array $data, ?UploadedFile $image = null, ?UploadedFile $fichier = null): Livre
    {
        // Remplacement de l'image de couverture
        if ($image) {
            if ($livre->image_principale && Storage::disk('public')->exists($livre->image_principale)) {
                Storage::disk('public')->delete($livre->image_principale);
            }
            $path = $image->store('livres/images/' . $livre->site_id, 'public');
            $data['image_principale'] = $path;
        }

        // Remplacement du fichier numérique
        if ($fichier) {
            if ($livre->fichier_numerique && Storage::disk('public')->exists($livre->fichier_numerique)) {
                Storage::disk('public')->delete($livre->fichier_numerique);
            }
            $path = $fichier->store('livres/fichiers/' . $livre->site_id, 'public');
            $data['fichier_numerique'] = $path;
        }

        return DB::transaction(function () use ($livre, $data) {
            $livre->update($data);
            return $livre->fresh(['categorieLivre'])->loadCount('exemplaires');
        });
    }

    /**
     * Suppression logique d'un livre (Soft Delete avec audit).
     */
    public function delete(Livre $livre, ?string $userId = null): bool
    {
        if ($userId) {
            $livre->deleted_by = $userId;
            $livre->save();
        }

        return (bool) $livre->delete();
    }

    /**
     * Restaurer un livre supprimé.
     */
    public function restore(string $siteId, string $id): ?Livre
    {
        $livre = Livre::onlyTrashed()
            ->where('site_id', $siteId)
            ->where('id', $id)
            ->first();

        if ($livre) {
            $livre->restore();
            $livre->deleted_by = null;
            $livre->save();
            return $livre->load('categorieLivre')->loadCount('exemplaires');
        }

        return null;
    }
}
