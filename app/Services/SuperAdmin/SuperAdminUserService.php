<?php

namespace App\Services\SuperAdmin;

use App\Models\Role;
use App\Models\Site;
use App\Models\Utilisateur;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SuperAdminUserService
{
    /**
     * Liste paginée des comptes Super Administrateurs.
     */
    public function getAllSuperAdmins(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);

        $query = Utilisateur::with(['role', 'site'])
            ->whereHas('role', function ($q) {
                $q->where('code', 'SUPERADMIN');
            });

        // Filtre par statut
        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        // Recherche par mot-clé
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
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts, true) ? $filters['sort_by'] : 'created_at';
        $sortDirection = strtolower($filters['sort_direction'] ?? '') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortDirection)->paginate($perPage);
    }

    /**
     * Trouver un Super Admin par son ID.
     */
    public function getSuperAdminById(string $id, bool $withTrashed = false): ?Utilisateur
    {
        $query = $withTrashed ? Utilisateur::withTrashed() : Utilisateur::query();

        return $query->with(['role', 'site'])
            ->whereHas('role', function ($q) {
                $q->where('code', 'SUPERADMIN');
            })
            ->find($id);
    }

    /**
     * Créer un nouveau compte Super Administrateur.
     */
    public function createSuperAdmin(array $data): Utilisateur
    {
        return DB::transaction(function () use ($data) {
            $siteId = $data['site_id'] ?? null;

            if (!$siteId) {
                $defaultSite = Site::where('code', 'ASA-ABOBO')->first()
                    ?? Site::whereNull('deleted_at')->first();

                if (!$defaultSite) {
                    throw new UnprocessableEntityHttpException('Aucun site actif n\'existe pour héberger le compte Super Administrateur.');
                }
                $siteId = $defaultSite->id;
            }

            // Récupère ou crée le rôle SUPERADMIN rattaché au site d'ancrage
            $roleSuperAdmin = Role::firstOrCreate(
                ['site_id' => $siteId, 'code' => 'SUPERADMIN'],
                [
                    'nom' => 'Super Administrateur',
                    'description' => 'Super Administrateur Plateforme globale',
                    'statut' => 'ACTIF',
                ]
            );

            return Utilisateur::create([
                'site_id' => $siteId,
                'role_id' => $roleSuperAdmin->id,
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'email' => $data['email'],
                'telephone' => $data['telephone'] ?? null,
                'mot_de_passe' => Hash::make($data['mot_de_passe']),
                'statut' => $data['statut'] ?? 'ACTIF',
            ]);
        });
    }

    /**
     * Modifier les informations d'un compte Super Administrateur.
     */
    public function updateSuperAdmin(Utilisateur $user, array $data, string $currentUserId): Utilisateur
    {
        return DB::transaction(function () use ($user, $data, $currentUserId) {
            if (isset($data['statut']) && $data['statut'] !== $user->statut) {
                $this->guardStatusChange($user, $data['statut'], $currentUserId);
            }

            if (!empty($data['mot_de_passe'])) {
                $data['mot_de_passe'] = Hash::make($data['mot_de_passe']);
            } else {
                unset($data['mot_de_passe']);
            }

            $user->update($data);

            return $user->fresh(['role', 'site']);
        });
    }

    /**
     * Modifier le statut d'un compte Super Administrateur (ACTIF, INACTIF, BLOQUE).
     */
    public function updateStatus(Utilisateur $user, string $statut, string $currentUserId): Utilisateur
    {
        $this->guardStatusChange($user, $statut, $currentUserId);

        $user->statut = $statut;
        $user->save();

        return $user->fresh(['role', 'site']);
    }

    /**
     * Supprimer logiquement un compte Super Administrateur.
     */
    public function deleteSuperAdmin(Utilisateur $user, string $currentUserId): bool
    {
        if ((string) $user->id === (string) $currentUserId) {
            throw new UnprocessableEntityHttpException('Vous ne pouvez pas supprimer votre propre compte Super Administrateur.');
        }

        $activeRemaining = Utilisateur::whereHas('role', function ($q) {
            $q->where('code', 'SUPERADMIN');
        })
        ->where('statut', 'ACTIF')
        ->where('id', '!=', $user->id)
        ->count();

        if ($activeRemaining === 0) {
            throw new UnprocessableEntityHttpException('Impossible de supprimer le dernier Super Administrateur actif de la plateforme.');
        }

        $user->deleted_by = $currentUserId;
        $user->save();

        return (bool) $user->delete();
    }

    /**
     * Garde-fous de sécurité sur la désactivation d'un Super Admin.
     */
    protected function guardStatusChange(Utilisateur $user, string $newStatut, string $currentUserId): void
    {
        if ((string) $user->id === (string) $currentUserId && $newStatut !== 'ACTIF') {
            throw new UnprocessableEntityHttpException('Vous ne pouvez pas désactiver ou bloquer votre propre compte Super Administrateur.');
        }

        if ($user->statut === 'ACTIF' && $newStatut !== 'ACTIF') {
            $activeRemaining = Utilisateur::whereHas('role', function ($q) {
                $q->where('code', 'SUPERADMIN');
            })
            ->where('statut', 'ACTIF')
            ->where('id', '!=', $user->id)
            ->count();

            if ($activeRemaining === 0) {
                throw new UnprocessableEntityHttpException('Impossible de désactiver le dernier Super Administrateur actif de la plateforme.');
            }
        }
    }
}
