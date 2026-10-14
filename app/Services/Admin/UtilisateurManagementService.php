<?php

namespace App\Services\Admin;

use App\Models\Role;
use App\Models\Utilisateur;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class UtilisateurManagementService
{
    public function getAllPaginated(string $siteId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);
        $query = Utilisateur::with(['role', 'site'])->where('site_id', $siteId);

        if (!empty($filters['role_id'])) {
            $query->where('role_id', $filters['role_id']);
        }

        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'LIKE', $search)
                  ->orWhere('prenom', 'LIKE', $search)
                  ->orWhere('email', 'LIKE', $search)
                  ->orWhere('telephone', 'LIKE', $search);
            });
        }

        $allowedSorts = ['id', 'nom', 'prenom', 'email', 'statut', 'created_at', 'derniere_connexion'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts, true) ? $filters['sort_by'] : 'id';
        $sortDirection = strtolower($filters['sort_direction'] ?? '') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortBy, $sortDirection)->paginate($perPage);
    }

    public function findById(string $siteId, string $id, bool $withTrashed = false): ?Utilisateur
    {
        $query = $withTrashed ? Utilisateur::withTrashed() : Utilisateur::query();
        return $query->with(['role', 'site'])->where('site_id', $siteId)->where('id', $id)->first();
    }

    public function create(string $siteId, array $data): Utilisateur
    {
        // Validation stricte : le rôle doit appartenir au même site
        $role = Role::where('site_id', $siteId)->where('id', $data['role_id'])->first();
        if (!$role) {
            throw new UnprocessableEntityHttpException('Le rôle sélectionné n\'appartient pas à ce site.');
        }

        $data['site_id'] = $siteId;
        // Hashage automatique du mot de passe
        $data['mot_de_passe'] = Hash::make($data['mot_de_passe']);

        return DB::transaction(function () use ($data) {
            return Utilisateur::create($data);
        });
    }

    public function update(Utilisateur $user, array $data): Utilisateur
    {
        // Validation si le rôle est mis à jour
        if (!empty($data['role_id']) && (string) $data['role_id'] !== (string) $user->role_id) {
            $role = Role::where('site_id', $user->site_id)->where('id', $data['role_id'])->first();
            if (!$role) {
                throw new UnprocessableEntityHttpException('Le rôle sélectionné n\'appartient pas à ce site.');
            }
        }

        // Hashage du mot de passe s'il est fourni
        if (!empty($data['mot_de_passe'])) {
            $data['mot_de_passe'] = Hash::make($data['mot_de_passe']);
        } else {
            unset($data['mot_de_passe']);
        }

        return DB::transaction(function () use ($user, $data) {
            $user->update($data);
            return $user->fresh(['role', 'site']);
        });
    }

    public function delete(Utilisateur $user, ?string $deletedById = null): bool
    {
        if ($deletedById) {
            $user->deleted_by = $deletedById;
            $user->save();
        }
        return (bool) $user->delete();
    }

    public function restore(string $siteId, string $id): ?Utilisateur
    {
        $user = Utilisateur::onlyTrashed()
            ->where('site_id', $siteId)
            ->where('id', $id)
            ->first();

        if ($user) {
            $user->restore();
            $user->deleted_by = null;
            $user->save();
            return $user->fresh(['role', 'site']);
        }

        return null;
    }
}
