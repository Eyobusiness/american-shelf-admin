<?php

namespace App\Services\SuperAdmin;

use App\Models\Configuration;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Utilisateur;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SuperAdminSiteService
{
    /**
     * Standard list of permissions to initialize for any new site.
     */
    protected array $standardPermissions = [
        'DASHBOARD_READ' => 'Consulter le tableau de bord et les statistiques',
        'SITE_READ' => 'Lire les sites',
        'SITE_CREATE' => 'Créer un site',
        'SITE_UPDATE' => 'Modifier un site',
        'SITE_DELETE' => 'Supprimer un site',
        'USER_READ' => 'Consulter les utilisateurs administratifs',
        'USER_CREATE' => 'Créer un utilisateur administratif',
        'USER_UPDATE' => 'Modifier un utilisateur administratif',
        'USER_DELETE' => 'Supprimer un utilisateur administratif',
        'ROLE_READ' => 'Consulter les rôles',
        'ROLE_CREATE' => 'Créer un rôle',
        'ROLE_UPDATE' => 'Modifier un rôle',
        'ROLE_DELETE' => 'Supprimer un rôle',
        'PERMISSION_READ' => 'Consulter les permissions',
        'PERMISSION_CREATE' => 'Créer une permission',
        'PERMISSION_UPDATE' => 'Modifier une permission',
        'PERMISSION_DELETE' => 'Supprimer une permission',
        'CONFIG_READ' => 'Consulter la configuration',
        'CONFIG_UPDATE' => 'Modifier la configuration',
        'MENU_READ' => 'Consulter les menus',
        'MENU_CREATE' => 'Créer un menu',
        'MENU_UPDATE' => 'Modifier un menu',
        'MENU_DELETE' => 'Supprimer un menu',
        'SOUS_MENU_READ' => 'Consulter les sous-menus',
        'SOUS_MENU_CREATE' => 'Créer un sous-menu',
        'SOUS_MENU_UPDATE' => 'Modifier un sous-menu',
        'SOUS_MENU_DELETE' => 'Supprimer un sous-menu',
        'CATEGORIE_USAGER_READ' => 'Consulter les catégories d\'usagers',
        'CATEGORIE_USAGER_CREATE' => 'Créer une catégorie d\'usager',
        'CATEGORIE_USAGER_UPDATE' => 'Modifier une catégorie d\'usager',
        'CATEGORIE_USAGER_DELETE' => 'Supprimer une catégorie d\'usager',
        'CATEGORIE_LIVRE_READ' => 'Consulter les catégories de livres',
        'CATEGORIE_LIVRE_CREATE' => 'Créer une catégorie de livre',
        'CATEGORIE_LIVRE_UPDATE' => 'Modifier une catégorie de livre',
        'CATEGORIE_LIVRE_DELETE' => 'Supprimer une catégorie de livre',
        'LIVRE_READ' => 'Consulter le catalogue des livres',
        'LIVRE_CREATE' => 'Créer un livre',
        'LIVRE_UPDATE' => 'Modifier un livre',
        'LIVRE_DELETE' => 'Supprimer un livre',
        'EXEMPLAIRE_READ' => 'Consulter les exemplaires',
        'EXEMPLAIRE_CREATE' => 'Créer un exemplaire',
        'EXEMPLAIRE_UPDATE' => 'Modifier un exemplaire',
        'EXEMPLAIRE_DELETE' => 'Supprimer un exemplaire',
        'ACQUISITION_READ' => 'Consulter les acquisitions',
        'ACQUISITION_CREATE' => 'Créer une acquisition',
        'ACQUISITION_UPDATE' => 'Modifier une acquisition',
        'ACQUISITION_DELETE' => 'Supprimer une acquisition',
        'USAGER_READ' => 'Consulter les usagers',
        'USAGER_CREATE' => 'Créer un usager',
        'USAGER_UPDATE' => 'Modifier un usager',
        'USAGER_DELETE' => 'Supprimer un usager',
        'USAGERSITE_READ' => 'Consulter les adhésions par site',
        'USAGERSITE_CREATE' => 'Créer une adhésion usager',
        'USAGERSITE_UPDATE' => 'Modifier une adhésion usager',
        'USAGERSITE_DELETE' => 'Supprimer une adhésion usager',
        'PRESENCE_READ' => 'Consulter les présences',
        'PRESENCE_CREATE' => 'Enregistrer une présence',
        'PRESENCE_UPDATE' => 'Modifier une présence',
        'PRESENCE_DELETE' => 'Supprimer une présence',
        'OPERATION_READ' => 'Consulter les opérations',
        'OPERATION_CREATE' => 'Créer une opération (prêt/consultation)',
        'OPERATION_UPDATE' => 'Modifier ou retourner une opération',
        'OPERATION_DELETE' => 'Supprimer une opération',
        'RESERVATION_READ' => 'Consulter les réservations',
        'RESERVATION_CREATE' => 'Créer une réservation',
        'RESERVATION_UPDATE' => 'Modifier ou annuler une réservation',
        'RESERVATION_DELETE' => 'Supprimer une réservation',
        'NOTIFICATION_READ' => 'Consulter les notifications',
        'NOTIFICATION_CREATE' => 'Envoyer une notification',
        'NOTIFICATION_DELETE' => 'Supprimer une notification',
        'JOURNAL_READ' => 'Consulter le journal d\'activité',
        'SAUVEGARDE_READ' => 'Consulter les sauvegardes',
        'SAUVEGARDE_DELETE' => 'Supprimer une sauvegarde',
        'RAPPORT_READ' => 'Consulter les rapports et statistiques avancées',
        'RAPPORT_EXPORT' => 'Télécharger les rapports au format Excel et PDF',
        'INVENTAIRE_READ' => 'Consulter les états d\'inventaire et historiques',
        'INVENTAIRE_EXECUTE' => 'Déclencher un inventaire ou un snapshot de stock',
    ];

    /**
     * Liste paginée de tous les sites pour le Super Admin avec filtres et comptages.
     */
    public function getAllSites(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);

        $query = Site::query()
            ->with(['configuration', 'utilisateurs' => function ($q) {
                $q->with('role');
            }])
            ->withCount(['livres', 'exemplaires', 'usagerSites', 'utilisateurs']);

        // Recherche par mot-clé
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'LIKE', $search)
                  ->orWhere('code', 'LIKE', $search)
                  ->orWhere('email', 'LIKE', $search)
                  ->orWhere('telephone', 'LIKE', $search);
            });
        }

        // Filtre par statut
        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        // Tri
        $allowedSorts = ['id', 'code', 'nom', 'statut', 'created_at', 'date_creation'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts, true) ? $filters['sort_by'] : 'created_at';
        $sortDirection = strtolower($filters['sort_direction'] ?? '') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortDirection)->paginate($perPage);
    }

    /**
     * Trouver un site par son ID avec toutes ses relations administratives.
     */
    public function getSiteById(string $id, bool $withTrashed = false): ?Site
    {
        $query = $withTrashed ? Site::withTrashed() : Site::query();

        return $query->with(['configuration', 'utilisateurs' => function ($q) {
                $q->with('role');
            }, 'roles'])
            ->withCount(['livres', 'exemplaires', 'usagerSites', 'utilisateurs'])
            ->find($id);
    }

    /**
     * Création complète et atomique d'un site avec son premier administrateur et sa configuration.
     */
    public function createSiteWithAdmin(array $siteData, array $adminData, ?array $configData = null): array
    {
        return DB::transaction(function () use ($siteData, $adminData, $configData) {
            // 1. Gestion du logo du site
            if (isset($siteData['logo']) && $siteData['logo'] instanceof UploadedFile) {
                $siteData['logo'] = $siteData['logo']->store('logos', 'public');
            }

            if (empty($siteData['statut'])) {
                $siteData['statut'] = 'ACTIF';
            }

            // 2. Création du site
            $site = Site::create($siteData);

            // 3. Création de la configuration par défaut
            $configuration = Configuration::create([
                'site_id' => $site->id,
                'duree_emprunt' => $configData['duree_emprunt'] ?? 14,
                'nombre_jours_grace' => $configData['nombre_jours_grace'] ?? 3,
                'duree_consultation' => $configData['duree_consultation'] ?? 2,
                'quota_emprunt' => $configData['quota_emprunt'] ?? 3,
                'duree_reservation' => $configData['duree_reservation'] ?? 3,
            ]);

            // 4. Initialisation des rôles fondamentaux du site
            $roleAdmin = Role::create([
                'site_id' => $site->id,
                'code' => 'ADMIN',
                'nom' => 'Administrateur',
                'description' => 'Accès complet au système pour le site',
                'statut' => 'ACTIF',
            ]);

            $roleBiblio = Role::create([
                'site_id' => $site->id,
                'code' => 'BIBLIO',
                'nom' => 'Bibliothécaire',
                'description' => 'Gestion documentaire et usagers',
                'statut' => 'ACTIF',
            ]);

            // 5. Initialisation des permissions du site et liaison aux rôles
            $permissionIds = [];
            foreach ($this->standardPermissions as $code => $libelle) {
                $perm = Permission::firstOrCreate(
                    ['site_id' => $site->id, 'code' => $code],
                    ['libelle' => $libelle, 'action' => 'MANAGE']
                );
                $permissionIds[] = $perm->id;
            }
            $roleAdmin->permissions()->sync($permissionIds);

            // 6. Création du premier administrateur du site
            $adminUser = Utilisateur::create([
                'site_id' => $site->id,
                'role_id' => $roleAdmin->id,
                'nom' => $adminData['nom'],
                'prenom' => $adminData['prenom'],
                'email' => $adminData['email'],
                'telephone' => $adminData['telephone'] ?? null,
                'mot_de_passe' => Hash::make($adminData['mot_de_passe']),
                'statut' => 'ACTIF',
            ]);

            $site->load(['configuration', 'utilisateurs' => function ($q) {
                $q->with('role');
            }]);

            return [
                'site' => $site,
                'admin' => $adminUser,
                'configuration' => $configuration,
            ];
        });
    }

    /**
     * Mettre à jour les informations générales d'un site.
     */
    public function updateSite(Site $site, array $data): Site
    {
        return DB::transaction(function () use ($site, $data) {
            if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
                if ($site->logo && !str_starts_with($site->logo, 'http') && Storage::disk('public')->exists($site->logo)) {
                    Storage::disk('public')->delete($site->logo);
                }
                $data['logo'] = $data['logo']->store('logos', 'public');
            }

            $site->update($data);

            return $site->fresh(['configuration', 'utilisateurs.role']);
        });
    }

    /**
     * Mettre à jour le statut d'un site (ACTIF, INACTIF, SUSPENDU).
     */
    public function updateSiteStatus(Site $site, string $statut): Site
    {
        $site->statut = $statut;
        $site->save();

        return $site->fresh(['configuration', 'utilisateurs.role']);
    }

    /**
     * Créer ou affecter un nouvel administrateur à un site existant.
     */
    public function assignSiteAdmin(Site $site, array $adminData): Utilisateur
    {
        return DB::transaction(function () use ($site, $adminData) {
            // Recherche ou création du rôle ADMIN pour ce site
            $roleAdmin = Role::firstOrCreate(
                ['site_id' => $site->id, 'code' => 'ADMIN'],
                ['nom' => 'Administrateur', 'description' => 'Accès complet au système', 'statut' => 'ACTIF']
            );

            return Utilisateur::create([
                'site_id' => $site->id,
                'role_id' => $roleAdmin->id,
                'nom' => $adminData['nom'],
                'prenom' => $adminData['prenom'],
                'email' => $adminData['email'],
                'telephone' => $adminData['telephone'] ?? null,
                'mot_de_passe' => Hash::make($adminData['mot_de_passe']),
                'statut' => $adminData['statut'] ?? 'ACTIF',
            ]);
        });
    }

    /**
     * Supprimer logiquement un site (Soft Delete).
     */
    public function deleteSite(Site $site, ?string $userId = null): bool
    {
        if ($userId) {
            $site->deleted_by = $userId;
            $site->save();
        }

        return (bool) $site->delete();
    }

    /**
     * Restaurer un site supprimé.
     */
    public function restoreSite(string $id): ?Site
    {
        $site = Site::onlyTrashed()->find($id);
        if ($site) {
            $site->restore();
            $site->deleted_by = null;
            $site->save();

            return $this->getSiteById($site->id);
        }

        return null;
    }
}
