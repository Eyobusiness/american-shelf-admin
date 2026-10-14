<?php

namespace Database\Seeders;

use App\Models\Acquisition;
use App\Models\CategorieLivre;
use App\Models\CategorieUsager;
use App\Models\Configuration;
use App\Models\Exemplaire;
use App\Models\JournalActivite;
use App\Models\Livre;
use App\Models\Menu;
use App\Models\Notification;
use App\Models\Operation;
use App\Models\Permission;
use App\Models\Presence;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Sauvegarde;
use App\Models\Site;
use App\Models\SousMenu;
use App\Models\Usager;
use App\Models\UsagerSite;
use App\Models\Utilisateur;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Exécution du seeder principal complet pour initialiser un environnement de développement réaliste.
     */
    public function run(): void
    {
        // =========================================================================
        // 1. SITES (TENANTS)
        // =========================================================================
        $siteAbobo = Site::firstOrCreate(
            ['code' => 'ASA-ABOBO'],
            [
                'nom' => 'American Shelf Abobo',
                'logo' => 'logos/asa_abobo_logo.png',
                'description' => 'Bibliothèque et espace culturel American Shelf - Université Nangui Abrogoua, Abobo',
                'adresse' => 'Campus Universitaire d\'Abobo-Adjamé, Bâtiment Bibliothèque',
                'statut' => 'ACTIF',
            ]
        );

        $siteCocody = Site::firstOrCreate(
            ['code' => 'ACC-COCODY'],
            [
                'nom' => 'American Corner Cocody',
                'logo' => 'logos/acc_cocody_logo.png',
                'description' => 'Espace documentaire et d\'apprentissage American Corner Cocody',
                'adresse' => 'Campus Université Félix Houphouët-Boigny, Cocody',
                'statut' => 'ACTIF',
            ]
        );

        // =========================================================================
        // 2. CONFIGURATIONS
        // =========================================================================
        Configuration::updateOrCreate(
            ['site_id' => $siteAbobo->id],
            [
                'duree_emprunt' => 14,
                'nombre_jours_grace' => 3,
                'duree_consultation' => 2,
                'quota_emprunt' => 3,
                'duree_reservation' => 3,
            ]
        );

        Configuration::updateOrCreate(
            ['site_id' => $siteCocody->id],
            [
                'duree_emprunt' => 14,
                'nombre_jours_grace' => 2,
                'duree_consultation' => 2,
                'quota_emprunt' => 2,
                'duree_reservation' => 2,
            ]
        );

        // =========================================================================
        // 3. CATÉGORIES USAGERS
        // =========================================================================
        $catEtudiant = CategorieUsager::firstOrCreate(
            ['site_id' => $siteAbobo->id, 'code' => 'ETUD'],
            ['nom' => 'Étudiant', 'description' => 'Étudiant inscrit à l\'université']
        );
        $catChercheur = CategorieUsager::firstOrCreate(
            ['site_id' => $siteAbobo->id, 'code' => 'CHERC'],
            ['nom' => 'Enseignant-Chercheur', 'description' => 'Personnel académique et chercheurs']
        );
        $catPublic = CategorieUsager::firstOrCreate(
            ['site_id' => $siteAbobo->id, 'code' => 'PUBLIC'],
            ['nom' => 'Grand Public', 'description' => 'Visiteurs et lecteurs externes']
        );

        // Site Cocody
        CategorieUsager::firstOrCreate(
            ['site_id' => $siteCocody->id, 'code' => 'ETUD'],
            ['nom' => 'Étudiant Cocody', 'description' => 'Étudiant inscrit']
        );

        // =========================================================================
        // 4. CATÉGORIES LIVRES
        // =========================================================================
        $catLit = CategorieLivre::firstOrCreate(
            ['site_id' => $siteAbobo->id, 'code' => 'LIT-US'],
            ['nom' => 'Littérature Américaine & Fiction', 'statut' => 'ACTIF']
        );
        $catSci = CategorieLivre::firstOrCreate(
            ['site_id' => $siteAbobo->id, 'code' => 'SCI-TECH'],
            ['nom' => 'Sciences & Informatique', 'statut' => 'ACTIF']
        );
        $catHist = CategorieLivre::firstOrCreate(
            ['site_id' => $siteAbobo->id, 'code' => 'HIST-CIV'],
            ['nom' => 'Histoire & Civilisation', 'statut' => 'ACTIF']
        );
        $catAnglais = CategorieLivre::firstOrCreate(
            ['site_id' => $siteAbobo->id, 'code' => 'LANG-METH'],
            ['nom' => 'Apprentissage de l\'Anglais & TOEFL', 'statut' => 'ACTIF']
        );

        // Site Cocody
        CategorieLivre::firstOrCreate(
            ['site_id' => $siteCocody->id, 'code' => 'LIT-US'],
            ['nom' => 'Littérature Américaine', 'statut' => 'ACTIF']
        );

        // =========================================================================
        // 5. MENUS ET SOUS-MENUS
        // =========================================================================
        $this->call(MenuSeeder::class);

        // =========================================================================
        // 6. PERMISSIONS SYSTÈME
        // =========================================================================
        $permissionCodes = [
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

        $createdPermissions = [];
        foreach ($permissionCodes as $code => $libelle) {
            $perm = Permission::firstOrCreate(
                ['site_id' => $siteAbobo->id, 'code' => $code],
                ['libelle' => $libelle, 'action' => 'MANAGE']
            );
            $createdPermissions[] = $perm->id;
        }

        // =========================================================================
        // 7. RÔLES
        // =========================================================================
        $roleSuperAdmin = Role::firstOrCreate(
            ['site_id' => $siteAbobo->id, 'code' => 'SUPERADMIN'],
            ['nom' => 'Super Administrateur', 'description' => 'Accès global multi-sites et administration de la plateforme', 'statut' => 'ACTIF']
        );
        $roleSuperAdmin->permissions()->sync($createdPermissions);

        $roleAdmin = Role::firstOrCreate(
            ['site_id' => $siteAbobo->id, 'code' => 'ADMIN'],
            ['nom' => 'Administrateur', 'description' => 'Accès complet au système', 'statut' => 'ACTIF']
        );
        $roleAdmin->permissions()->sync($createdPermissions);

        $roleBiblio = Role::firstOrCreate(
            ['site_id' => $siteAbobo->id, 'code' => 'BIBLIO'],
            ['nom' => 'Bibliothécaire', 'description' => 'Gestion des livres, prêts et usagers', 'statut' => 'ACTIF']
        );

        $roleBiblioPerms = Permission::where('site_id', $siteAbobo->id)
            ->where(function ($q) {
                $q->where('code', 'LIKE', 'LIVRE_%')
                  ->orWhere('code', 'LIKE', 'EXEMPLAIRE_%')
                  ->orWhere('code', 'LIKE', 'ACQUISITION_%')
                  ->orWhere('code', 'LIKE', 'USAGER_%')
                  ->orWhere('code', 'LIKE', 'USAGERSITE_%')
                  ->orWhere('code', 'LIKE', 'OPERATION_%')
                  ->orWhere('code', 'LIKE', 'RESERVATION_%')
                  ->orWhere('code', 'LIKE', 'PRESENCE_%')
                  ->orWhere('code', 'LIKE', 'NOTIFICATION_%')
                  ->orWhere('code', 'LIKE', 'RAPPORT_%')
                  ->orWhere('code', 'INVENTAIRE_READ')
                  ->orWhere('code', 'DASHBOARD_READ');
            })
            ->pluck('id');
        $roleBiblio->permissions()->sync($roleBiblioPerms);

        // Site Cocody
        $roleAdminCocody = Role::firstOrCreate(
            ['site_id' => $siteCocody->id, 'code' => 'ADMIN'],
            ['nom' => 'Administrateur Cocody', 'description' => 'Admin Cocody', 'statut' => 'ACTIF']
        );

        // =========================================================================
        // 8. UTILISATEURS DU PERSONNEL (ADMINISTRATION)
        // =========================================================================
        $superAdminUser = Utilisateur::updateOrCreate(
            ['email' => 'superadmin@americanshelf.ci'],
            [
                'site_id' => $siteAbobo->id,
                'role_id' => $roleSuperAdmin->id,
                'nom' => 'Super',
                'prenom' => 'Administrateur',
                'telephone' => '+225 0707070700',
                'mot_de_passe' => Hash::make('Password123!'),
                'statut' => 'ACTIF',
                'derniere_connexion' => now(),
            ]
        );

        $adminUser = Utilisateur::updateOrCreate(
            ['email' => 'admin@americanshelf.ci'],
            [
                'site_id' => $siteAbobo->id,
                'role_id' => $roleAdmin->id,
                'nom' => 'Kouassi',
                'prenom' => 'Jean-Marc',
                'telephone' => '+225 0707070701',
                'mot_de_passe' => Hash::make('Password123!'),
                'statut' => 'ACTIF',
                'derniere_connexion' => now(),
            ]
        );

        $biblioUser = Utilisateur::updateOrCreate(
            ['email' => 'biblio@americanshelf.ci'],
            [
                'site_id' => $siteAbobo->id,
                'role_id' => $roleBiblio->id,
                'nom' => 'Bamba',
                'prenom' => 'Aminata',
                'telephone' => '+225 0707070702',
                'mot_de_passe' => Hash::make('Password123!'),
                'statut' => 'ACTIF',
                'derniere_connexion' => now()->subHours(2),
            ]
        );

        // Admin Cocody
        Utilisateur::updateOrCreate(
            ['email' => 'admin.cocody@americanshelf.ci'],
            [
                'site_id' => $siteCocody->id,
                'role_id' => $roleAdminCocody->id,
                'nom' => 'Koffi',
                'prenom' => 'Serge',
                'telephone' => '+225 0505050501',
                'mot_de_passe' => Hash::make('Password123!'),
                'statut' => 'ACTIF',
            ]
        );

        // =========================================================================
        // 9. USAGERS ET ADHÉSIONS
        // =========================================================================
        $usagersData = [
            ['numero' => 'USG-2026-001', 'nom' => 'Konan', 'prenom' => 'Aya Marie', 'email' => 'aya.konan@americanshelf.ci', 'contact' => '+225 0101010101', 'cat' => $catEtudiant],
            ['numero' => 'USG-2026-002', 'nom' => 'Ouattara', 'prenom' => 'Ibrahim', 'email' => 'ibrahim.ouattara@americanshelf.ci', 'contact' => '+225 0101010102', 'cat' => $catEtudiant],
            ['numero' => 'USG-2026-003', 'nom' => 'Diallo', 'prenom' => 'Fatoumata', 'email' => 'fatou.diallo@americanshelf.ci', 'contact' => '+225 0101010103', 'cat' => $catChercheur],
            ['numero' => 'USG-2026-004', 'nom' => 'Kouamé', 'prenom' => 'Yao Franck', 'email' => 'franck.kouame@americanshelf.ci', 'contact' => '+225 0101010104', 'cat' => $catEtudiant],
            ['numero' => 'USG-2026-005', 'nom' => 'Traoré', 'prenom' => 'Seydou', 'email' => 'seydou.traore@americanshelf.ci', 'contact' => '+225 0101010105', 'cat' => $catPublic],
            ['numero' => 'USG-2026-006', 'nom' => 'N\'Guessan', 'prenom' => 'Affoué Estelle', 'email' => 'estelle.nguessan@americanshelf.ci', 'contact' => '+225 0101010106', 'cat' => $catEtudiant],
            ['numero' => 'USG-2026-007', 'nom' => 'Bakayoko', 'prenom' => 'Souleymane', 'email' => 'souley.bakayoko@americanshelf.ci', 'contact' => '+225 0101010107', 'cat' => $catChercheur],
            ['numero' => 'USG-2026-008', 'nom' => 'Yapi', 'prenom' => 'Christophe', 'email' => 'christophe.yapi@americanshelf.ci', 'contact' => '+225 0101010108', 'cat' => $catPublic],
        ];

        $seededUsagers = [];
        foreach ($usagersData as $uData) {
            $usager = Usager::updateOrCreate(
                ['email' => $uData['email']],
                [
                    'numero' => $uData['numero'],
                    'nom' => $uData['nom'],
                    'prenom' => $uData['prenom'],
                    'contact' => $uData['contact'],
                ]
            );

            UsagerSite::firstOrCreate(
                ['site_id' => $siteAbobo->id, 'usager_id' => $usager->id],
                [
                    'categorie_usager_id' => $uData['cat']->id,
                    'type' => 'ADHERENT',
                    'date_inscription' => now()->subDays(rand(10, 60))->toDateString(),
                    'statut' => 'ACTIF',
                ]
            );

            $seededUsagers[] = $usager;
        }

        // =========================================================================
        // 10. ACQUISITIONS
        // =========================================================================
        $acq1 = Acquisition::firstOrCreate(
            ['site_id' => $siteAbobo->id, 'reference_facture' => 'FAC-2026-001'],
            [
                'type' => 'ACHAT',
                'provenance' => 'Librairie de France Abidjan',
                'date_acquisition' => now()->subMonths(2)->toDateString(),
                'cout' => 350000.00,
                'quantite' => 10,
                'etat' => 'RECU',
                'observations' => 'Dotation initiale en ouvrages de référence',
            ]
        );

        $acq2 = Acquisition::firstOrCreate(
            ['site_id' => $siteAbobo->id, 'reference_facture' => 'FAC-2026-002'],
            [
                'type' => 'DON',
                'donateur' => 'Ambassade des États-Unis - Section Culturelle',
                'date_acquisition' => now()->subMonth()->toDateString(),
                'cout' => 600000.00,
                'quantite' => 15,
                'etat' => 'RECU',
                'observations' => 'Don d\'ouvrages et méthodes d\'apprentissage',
            ]
        );

        // =========================================================================
        // 11. LIVRES ET EXEMPLAIRES
        // =========================================================================
        $livresData = [
            [
                'code' => 'LIV-LIT-001',
                'titre' => 'To Kill a Mockingbird',
                'auteur' => 'Harper Lee',
                'editeur' => 'J. B. Lippincott & Co.',
                'annee_edition' => 1960,
                'isbn' => '978-0061120084',
                'categorie' => $catLit,
                'acquisition' => $acq2,
                'nbr_exemplaires' => 3,
            ],
            [
                'code' => 'LIV-LIT-002',
                'titre' => 'The Great Gatsby',
                'auteur' => 'F. Scott Fitzgerald',
                'editeur' => 'Charles Scribner\'s Sons',
                'annee_edition' => 1925,
                'isbn' => '978-0743273565',
                'categorie' => $catLit,
                'acquisition' => $acq2,
                'nbr_exemplaires' => 2,
            ],
            [
                'code' => 'LIV-SCI-001',
                'titre' => 'Clean Code: A Handbook of Agile Software Craftsmanship',
                'auteur' => 'Robert C. Martin',
                'editeur' => 'Prentice Hall',
                'annee_edition' => 2008,
                'isbn' => '978-0132350884',
                'categorie' => $catSci,
                'acquisition' => $acq1,
                'nbr_exemplaires' => 2,
            ],
            [
                'code' => 'LIV-SCI-002',
                'titre' => 'Python Crash Course: A Hands-On Project-Based Introduction',
                'auteur' => 'Eric Matthes',
                'editeur' => 'No Starch Press',
                'annee_edition' => 2023,
                'isbn' => '978-1718502703',
                'categorie' => $catSci,
                'acquisition' => $acq1,
                'nbr_exemplaires' => 2,
            ],
            [
                'code' => 'LIV-HIST-001',
                'titre' => 'A People\'s History of the United States',
                'auteur' => 'Howard Zinn',
                'editeur' => 'Harper & Row',
                'annee_edition' => 1980,
                'isbn' => '978-0060838652',
                'categorie' => $catHist,
                'acquisition' => $acq2,
                'nbr_exemplaires' => 2,
            ],
            [
                'code' => 'LIV-ANG-001',
                'titre' => 'Official Guide to the TOEFL iBT Test',
                'auteur' => 'Educational Testing Service (ETS)',
                'editeur' => 'McGraw-Hill Education',
                'annee_edition' => 2021,
                'isbn' => '978-1260470352',
                'categorie' => $catAnglais,
                'acquisition' => $acq1,
                'nbr_exemplaires' => 3,
            ],
        ];

        $seededLivres = [];
        $seededExemplaires = [];

        foreach ($livresData as $lData) {
            $livre = Livre::firstOrCreate(
                ['site_id' => $siteAbobo->id, 'code' => $lData['code']],
                [
                    'categorie_livre_id' => $lData['categorie']->id,
                    'titre' => $lData['titre'],
                    'auteur' => $lData['auteur'],
                    'editeur' => $lData['editeur'],
                    'annee_publication' => $lData['annee_edition'],
                    'isbn' => $lData['isbn'],
                    'statut' => 'ACTIF',
                ]
            );

            $seededLivres[] = $livre;

            for ($i = 1; $i <= $lData['nbr_exemplaires']; $i++) {
                $codeEx = $livre->code . '-EX' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                $exemplaire = Exemplaire::firstOrCreate(
                    ['site_id' => $siteAbobo->id, 'code' => $codeEx],
                    [
                        'livre_id' => $livre->id,
                        'etat' => 'BON',
                        'statut' => 'DISPONIBLE',
                        'localisation' => 'Rayon A1',
                    ]
                );
                $seededExemplaires[] = $exemplaire;
            }
        }

        // =========================================================================
        // 12. PRÉSENCES
        // =========================================================================
        Presence::firstOrCreate(
            ['site_id' => $siteAbobo->id, 'usager_id' => $seededUsagers[0]->id, 'date_presence' => today()->toDateString()],
            [
                'heure_arrivee' => '09:15:00',
                'heure_depart' => '11:45:00',
                'motif' => 'Lecture & Recherche',
            ]
        );

        Presence::firstOrCreate(
            ['site_id' => $siteAbobo->id, 'usager_id' => $seededUsagers[1]->id, 'date_presence' => today()->toDateString()],
            [
                'heure_arrivee' => '10:00:00',
                'heure_depart' => null,
                'motif' => 'Préparation TOEFL',
            ]
        );

        Presence::firstOrCreate(
            ['site_id' => $siteAbobo->id, 'usager_id' => null, 'date_presence' => today()->subDay()->toDateString()],
            [
                'heure_arrivee' => '14:00:00',
                'heure_depart' => '16:30:00',
                'motif' => 'Visite culturelle',
                'observations' => 'Visiteur externe : Kone Mamadou (+225 0708091011)',
            ]
        );

        // =========================================================================
        // 13. OPÉRATIONS (EMPRUNTS ET CONSULTATIONS)
        // =========================================================================
        $configAbobo = Configuration::where('site_id', $siteAbobo->id)->first();

        // Emprunt 1 : en cours
        if (isset($seededExemplaires[0])) {
            $ex1 = $seededExemplaires[0];
            $ex1->update(['statut' => 'EMPRUNTE']);

            Operation::firstOrCreate(
                ['site_id' => $siteAbobo->id, 'usager_id' => $seededUsagers[0]->id, 'exemplaire_id' => $ex1->id, 'type' => 'EMPRUNT', 'statut' => 'EN_COURS'],
                [
                    'configuration_id' => $configAbobo->id,
                    'date_operation' => now()->subDays(4),
                    'date_retour_prevue' => now()->addDays(10),
                    'statut' => 'EN_COURS',
                ]
            );
        }

        // Emprunt 2 : terminé (retourné)
        if (isset($seededExemplaires[1])) {
            $ex2 = $seededExemplaires[1];

            Operation::firstOrCreate(
                ['site_id' => $siteAbobo->id, 'usager_id' => $seededUsagers[2]->id, 'exemplaire_id' => $ex2->id, 'type' => 'EMPRUNT', 'statut' => 'TERMINE'],
                [
                    'configuration_id' => $configAbobo->id,
                    'date_operation' => now()->subDays(20),
                    'date_retour_prevue' => now()->subDays(6),
                    'date_retour_effective' => now()->subDays(7),
                    'statut' => 'TERMINE',
                ]
            );
        }

        // Emprunt 3 : en retard
        if (isset($seededExemplaires[2])) {
            $ex3 = $seededExemplaires[2];
            $ex3->update(['statut' => 'EMPRUNTE']);

            Operation::firstOrCreate(
                ['site_id' => $siteAbobo->id, 'usager_id' => $seededUsagers[3]->id, 'exemplaire_id' => $ex3->id, 'type' => 'EMPRUNT', 'statut' => 'EN_RETARD'],
                [
                    'configuration_id' => $configAbobo->id,
                    'date_operation' => now()->subDays(25),
                    'date_retour_prevue' => now()->subDays(11),
                    'statut' => 'EN_RETARD',
                ]
            );
        }

        // Consultation 1 : terminée
        if (isset($seededExemplaires[3])) {
            $ex4 = $seededExemplaires[3];

            Operation::firstOrCreate(
                ['site_id' => $siteAbobo->id, 'usager_id' => $seededUsagers[4]->id, 'exemplaire_id' => $ex4->id, 'type' => 'CONSULTATION', 'statut' => 'TERMINE'],
                [
                    'configuration_id' => $configAbobo->id,
                    'date_operation' => now()->subDays(1),
                    'date_retour_effective' => now()->subDays(1)->addHours(2),
                    'statut' => 'TERMINE',
                ]
            );
        }

        // =========================================================================
        // 14. RÉSERVATIONS
        // =========================================================================
        Reservation::firstOrCreate(
            ['site_id' => $siteAbobo->id, 'usager_id' => $seededUsagers[1]->id, 'livre_id' => $seededLivres[0]->id, 'statut' => 'EN_ATTENTE'],
            [
                'date_reservation' => now(),
                'date_expiration' => now()->addDays(3),
                'statut' => 'EN_ATTENTE',
                'observations' => 'Réservé pour préparation d\'examen',
            ]
        );

        Reservation::firstOrCreate(
            ['site_id' => $siteAbobo->id, 'usager_id' => $seededUsagers[5]->id, 'livre_id' => $seededLivres[2]->id, 'statut' => 'EXPIREE'],
            [
                'date_reservation' => now()->subDays(10),
                'date_expiration' => now()->subDays(7),
                'statut' => 'EXPIREE',
                'observations' => 'Expirée non retirée',
            ]
        );

        // =========================================================================
        // 15. NOTIFICATIONS
        // =========================================================================
        Notification::firstOrCreate(
            ['site_id' => $siteAbobo->id, 'email_destinataire' => 'aya.konan@americanshelf.ci', 'type' => Notification::TYPE_RESERVATION_CREEE],
            [
                'usager_id' => $seededUsagers[0]->id,
                'objet' => 'Confirmation de réservation : To Kill a Mockingbird',
                'message' => 'Votre réservation pour l\'ouvrage a été enregistrée avec succès.',
                'date_envoi' => now()->subDays(1),
                'statut' => Notification::STATUT_ENVOYE,
            ]
        );

        Notification::firstOrCreate(
            ['site_id' => $siteAbobo->id, 'email_destinataire' => 'franck.kouame@americanshelf.ci', 'type' => Notification::TYPE_EMPRUNT_EN_RETARD],
            [
                'usager_id' => $seededUsagers[3]->id,
                'objet' => 'Rappel : Retard sur le retour de votre emprunt',
                'message' => 'Votre emprunt est arrivé à échéance. Merci de le rapporter à la bibliothèque.',
                'date_envoi' => now()->subDays(2),
                'statut' => Notification::STATUT_ENVOYE,
            ]
        );

        // =========================================================================
        // 16. JOURNAL D'ACTIVITÉ
        // =========================================================================
        JournalActivite::firstOrCreate(
            ['site_id' => $siteAbobo->id, 'action' => 'INITIALISATION', 'module' => 'SYSTEM'],
            [
                'utilisateur_id' => $adminUser->id,
                'reference' => 'SEED-2026',
                'description' => 'Initialisation réussie des données de démonstration du système.',
                'adresse_ip' => '127.0.0.1',
                'created_at' => now(),
            ]
        );

        // =========================================================================
        // 17. SAUVEGARDES
        // =========================================================================
        Sauvegarde::firstOrCreate(
            ['site_id' => $siteAbobo->id, 'nom_fichier' => 'backup_systeme_initial_2026_10_01.sql'],
            [
                'chemin_fichier' => '/storage/backups/backup_systeme_initial_2026_10_01.sql',
                'taille' => 524288,
                'date_sauvegarde' => now()->subDays(5),
                'statut' => 'TERMINE',
                'created_at' => now()->subDays(5),
            ]
        );
    }
}
