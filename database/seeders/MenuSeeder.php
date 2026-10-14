<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Site;
use App\Models\SousMenu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Détacher les références de menus dans la table permissions si existantes
        DB::table('permissions')->update([
            'menu_id' => null,
            'sous_menu_id' => null,
        ]);

        // 2. Vider tous les sous-menus et menus existants (suppression définitive)
        SousMenu::query()->forceDelete();
        Menu::query()->forceDelete();

        // 3. Définition exacte des menus et sous-menus demandés
        $menuTree = [
            [
                'order' => 1,
                'code' => '100',
                'libelle' => 'Tableau de bord',
                'icon' => 'bi bi-speedometer2',
                'path' => '/dashboard',
                'permission' => '1,2,3,4',
                'reference' => 'dashboard',
                'statut' => 'ACTIF',
                'sousMenus' => [],
            ],
            [
                'order' => 2,
                'code' => '200',
                'libelle' => 'Documents',
                'icon' => 'bi bi-book',
                'path' => '#',
                'permission' => '1,2,3,4',
                'reference' => 'main_documents',
                'statut' => 'ACTIF',
                'sousMenus' => [
                    [
                        'order' => 1,
                        'code' => '201',
                        'libelle' => 'Livres',
                        'icon' => 'bi bi-book',
                        'path' => '/documents/livres',
                        'permission' => null,
                        'reference' => 'livres',
                        'statut' => 'ACTIF',
                    ],
                    [
                        'order' => 2,
                        'code' => '202',
                        'libelle' => 'Exemplaires',
                        'icon' => 'bi bi-files',
                        'path' => '/documents/exemplaires',
                        'permission' => null,
                        'reference' => 'exemplaires',
                        'statut' => 'ACTIF',
                    ],
                    [
                        'order' => 3,
                        'code' => '203',
                        'libelle' => 'Acquisitions',
                        'icon' => 'bi bi-box-seam',
                        'path' => '/documents/acquisitions',
                        'permission' => null,
                        'reference' => 'acquisitions',
                        'statut' => 'ACTIF',
                    ],
                ],
            ],
            [
                'order' => 3,
                'code' => '300',
                'libelle' => 'Usagers',
                'icon' => 'bi bi-people',
                'path' => '#',
                'permission' => '1,2,3,4',
                'reference' => 'main_usagers',
                'statut' => 'ACTIF',
                'sousMenus' => [
                    [
                        'order' => 1,
                        'code' => '301',
                        'libelle' => 'Usagers',
                        'icon' => 'bi bi-person-lines-fill',
                        'path' => '/usagers/liste',
                        'permission' => null,
                        'reference' => 'usagers',
                        'statut' => 'ACTIF',
                    ],
                    [
                        'order' => 2,
                        'code' => '302',
                        'libelle' => 'Catégories usagers',
                        'icon' => 'bi bi-person-badge',
                        'path' => '/usagers/categories',
                        'permission' => null,
                        'reference' => 'categories_usagers',
                        'statut' => 'ACTIF',
                    ],
                    [
                        'order' => 3,
                        'code' => '303',
                        'libelle' => 'Présences',
                        'icon' => 'bi bi-calendar-check',
                        'path' => '/usagers/presences',
                        'permission' => null,
                        'reference' => 'presences',
                        'statut' => 'ACTIF',
                    ],
                ],
            ],
            [
                'order' => 4,
                'code' => '400',
                'libelle' => 'Activités',
                'icon' => 'bi bi-activity',
                'path' => '#',
                'permission' => '1,2,3,4',
                'reference' => 'main_activites',
                'statut' => 'ACTIF',
                'sousMenus' => [
                    [
                        'order' => 1,
                        'code' => '401',
                        'libelle' => 'Emprunts',
                        'icon' => 'bi bi-arrow-left-right',
                        'path' => '/activites/emprunts',
                        'permission' => null,
                        'reference' => 'emprunts',
                        'statut' => 'ACTIF',
                    ],
                    [
                        'order' => 2,
                        'code' => '402',
                        'libelle' => 'Consultations',
                        'icon' => 'bi bi-eye',
                        'path' => '/activites/consultations',
                        'permission' => null,
                        'reference' => 'consultations',
                        'statut' => 'ACTIF',
                    ],
                    [
                        'order' => 3,
                        'code' => '403',
                        'libelle' => 'Réservations',
                        'icon' => 'bi bi-bookmark-check',
                        'path' => '/activites/reservations',
                        'permission' => null,
                        'reference' => 'reservations',
                        'statut' => 'ACTIF',
                    ],
                ],
            ],
            [
                'order' => 5,
                'code' => '500',
                'libelle' => 'Administration',
                'icon' => 'bi bi-shield-lock',
                'path' => '#',
                'permission' => '1,2,3,4',
                'reference' => 'main_administration',
                'statut' => 'ACTIF',
                'sousMenus' => [
                    [
                        'order' => 1,
                        'code' => '501',
                        'libelle' => 'Utilisateurs',
                        'icon' => 'bi bi-person-gear',
                        'path' => '/admin/utilisateurs',
                        'permission' => null,
                        'reference' => 'utilisateurs',
                        'statut' => 'ACTIF',
                    ],
                    [
                        'order' => 2,
                        'code' => '502',
                        'libelle' => 'Rôles & permissions',
                        'icon' => 'bi bi-shield-check',
                        'path' => '/admin/roles-permissions',
                        'permission' => null,
                        'reference' => 'roles_permissions',
                        'statut' => 'ACTIF',
                    ],
                    [
                        'order' => 3,
                        'code' => '503',
                        'libelle' => 'Menus',
                        'icon' => 'bi bi-list-ul',
                        'path' => '/admin/menus',
                        'permission' => null,
                        'reference' => 'menus',
                        'statut' => 'ACTIF',
                    ],
                    [
                        'order' => 4,
                        'code' => '504',
                        'libelle' => 'Configuration',
                        'icon' => 'bi bi-gear',
                        'path' => '/admin/configuration',
                        'permission' => null,
                        'reference' => 'configuration',
                        'statut' => 'ACTIF',
                    ],
                ],
            ],
            [
                'order' => 6,
                'code' => '600',
                'libelle' => 'Système',
                'icon' => 'bi bi-sliders',
                'path' => '#',
                'permission' => '1,2,3,4',
                'reference' => 'main_systeme',
                'statut' => 'ACTIF',
                'sousMenus' => [
                    [
                        'order' => 1,
                        'code' => '601',
                        'libelle' => 'Notifications',
                        'icon' => 'bi bi-bell',
                        'path' => '/systeme/notifications',
                        'permission' => null,
                        'reference' => 'notifications',
                        'statut' => 'ACTIF',
                    ],
                    [
                        'order' => 2,
                        'code' => '602',
                        'libelle' => 'Journal d\'activité',
                        'icon' => 'bi bi-journal-text',
                        'path' => '/systeme/journal',
                        'permission' => null,
                        'reference' => 'journal_activite',
                        'statut' => 'ACTIF',
                    ],
                    [
                        'order' => 3,
                        'code' => '603',
                        'libelle' => 'Sauvegardes',
                        'icon' => 'bi bi-database-check',
                        'path' => '/systeme/sauvegardes',
                        'permission' => null,
                        'reference' => 'sauvegardes',
                        'statut' => 'ACTIF',
                    ],
                ],
            ],
        ];

        // 4. Peuplement pour chaque site existant
        $sites = Site::all();
        if ($sites->isEmpty()) {
            $sites = collect([
                Site::firstOrCreate(
                    ['code' => 'ASA'],
                    ['nom' => 'American Shelf Abobo', 'statut' => 'ACTIF']
                ),
            ]);
        }

        foreach ($sites as $site) {
            foreach ($menuTree as $menuItem) {
                $subMenus = $menuItem['sousMenus'] ?? [];
                unset($menuItem['sousMenus']);

                $menuItem['site_id'] = $site->id;
                $menu = Menu::create($menuItem);

                foreach ($subMenus as $subMenuItem) {
                    $subMenuItem['site_id'] = $site->id;
                    $subMenuItem['menu_id'] = $menu->id;
                    SousMenu::create($subMenuItem);
                }
            }
        }
    }
}
