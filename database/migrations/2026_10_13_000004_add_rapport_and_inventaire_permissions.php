<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $newPermissions = [
            'RAPPORT_READ' => 'Consulter les rapports et statistiques avancées',
            'RAPPORT_EXPORT' => 'Télécharger les rapports au format Excel et PDF',
            'INVENTAIRE_READ' => 'Consulter les états d\'inventaire et historiques',
            'INVENTAIRE_EXECUTE' => 'Déclencher un inventaire ou un snapshot de stock',
        ];

        $sites = Site::all();

        foreach ($sites as $site) {
            $createdPermIds = [];

            foreach ($newPermissions as $code => $libelle) {
                $permission = Permission::firstOrCreate(
                    [
                        'site_id' => $site->id,
                        'code' => $code,
                    ],
                    [
                        'libelle' => $libelle,
                        'action' => 'MANAGE',
                    ]
                );
                $createdPermIds[$code] = $permission->id;
            }

            // Associer aux rôles existants pour ce site
            $adminRole = Role::where('site_id', $site->id)->where('code', 'ADMIN')->first();
            if ($adminRole) {
                $adminRole->permissions()->syncWithoutDetaching(array_values($createdPermIds));
            }

            $biblioRole = Role::where('site_id', $site->id)->where('code', 'BIBLIO')->first();
            if ($biblioRole) {
                $biblioPerms = array_intersect_key($createdPermIds, array_flip(['RAPPORT_READ', 'RAPPORT_EXPORT', 'INVENTAIRE_READ']));
                $biblioRole->permissions()->syncWithoutDetaching(array_values($biblioPerms));
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::whereIn('code', [
            'RAPPORT_READ',
            'RAPPORT_EXPORT',
            'INVENTAIRE_READ',
            'INVENTAIRE_EXECUTE',
        ])->delete();
    }
};
