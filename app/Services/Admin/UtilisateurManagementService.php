<?php

namespace App\Services\Admin;

use App\Models\Role;
use App\Models\Utilisateur;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class UtilisateurManagementService
{
    public function getAllPaginated(string $siteId, array $filters = [], int $perPage = 15, ?Utilisateur $currentUser = null): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);
        $query = Utilisateur::with(['role', 'site'])->where('site_id', $siteId);

        // Si l'utilisateur n'est pas SuperAdmin, masquer les comptes Super Administrateurs
        if (!$currentUser || !$currentUser->isSuperAdmin()) {
            $query->whereDoesntHave('role', function ($q) {
                $q->where('code', 'SUPERADMIN');
            });
        }

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

    public function findById(string $siteId, string $id, bool $withTrashed = false, ?Utilisateur $currentUser = null): ?Utilisateur
    {
        $query = $withTrashed ? Utilisateur::withTrashed() : Utilisateur::query();
        $user = $query->with(['role', 'site'])->where('site_id', $siteId)->where('id', $id)->first();

        // Les administrateurs ordinaires ne peuvent pas consulter le profil Super Admin
        if ($user && $user->isSuperAdmin() && (!$currentUser || !$currentUser->isSuperAdmin())) {
            return null;
        }

        return $user;
    }

    public function create(string $siteId, array $data, ?Utilisateur $currentUser = null): Utilisateur
    {
        // Validation stricte : le rôle doit appartenir au même site
        $role = Role::where('site_id', $siteId)->where('id', $data['role_id'])->first();
        if (!$role) {
            throw new UnprocessableEntityHttpException('Le rôle sélectionné n\'appartient pas à ce site.');
        }

        // Interdiction d'attribuer le rôle Super Admin pour les administrateurs ordinaires
        if ($role->code === 'SUPERADMIN' && (!$currentUser || !$currentUser->isSuperAdmin())) {
            throw new AccessDeniedHttpException('Attribution du rôle Super Administrateur non autorisée.');
        }

        $data['site_id'] = $siteId;
        // Hashage automatique du mot de passe
        $data['mot_de_passe'] = Hash::make($data['mot_de_passe']);

        return DB::transaction(function () use ($data) {
            return Utilisateur::create($data);
        });
    }

    public function update(Utilisateur $user, array $data, ?Utilisateur $currentUser = null): Utilisateur
    {
        // Interdiction pour les administrateurs ordinaires de modifier un Super Admin
        if ($user->isSuperAdmin() && (!$currentUser || !$currentUser->isSuperAdmin())) {
            throw new AccessDeniedHttpException('Action non autorisée sur un compte Super Administrateur.');
        }

        // Validation si le rôle est mis à jour
        if (!empty($data['role_id']) && (string) $data['role_id'] !== (string) $user->role_id) {
            $role = Role::where('site_id', $user->site_id)->where('id', $data['role_id'])->first();
            if (!$role) {
                throw new UnprocessableEntityHttpException('Le rôle sélectionné n\'appartient pas à ce site.');
            }
            if ($role->code === 'SUPERADMIN' && (!$currentUser || !$currentUser->isSuperAdmin())) {
                throw new AccessDeniedHttpException('Attribution du rôle Super Administrateur non autorisée.');
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

    public function delete(Utilisateur $user, ?string $deletedById = null, ?Utilisateur $currentUser = null): bool
    {
        if ($user->isSuperAdmin() && (!$currentUser || !$currentUser->isSuperAdmin())) {
            throw new AccessDeniedHttpException('Suppression du compte Super Administrateur non autorisée.');
        }

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
