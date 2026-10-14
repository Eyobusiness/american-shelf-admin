<?php

use App\Http\Controllers\Api\V1\Admin\CategorieLivreController;
use App\Http\Controllers\Api\V1\Admin\CategorieUsagerController;
use App\Http\Controllers\Api\V1\Admin\ConfigurationController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\JournalActiviteController;
use App\Http\Controllers\Api\V1\Admin\MenuController;
use App\Http\Controllers\Api\V1\Admin\PermissionController;
use App\Http\Controllers\Api\V1\Admin\RapportController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\SauvegardeController;
use App\Http\Controllers\Api\V1\Admin\SiteController;
use App\Http\Controllers\Api\V1\Admin\SousMenuController;
use App\Http\Controllers\Api\V1\Admin\UtilisateurController;
use App\Http\Controllers\Api\V1\Auth\AdminAuthController;
use App\Http\Controllers\Api\V1\Auth\UsagerAuthController;
use App\Http\Controllers\Api\V1\Document\AcquisitionController;
use App\Http\Controllers\Api\V1\Document\ExemplaireController;
use App\Http\Controllers\Api\V1\Document\LivreController;
use App\Http\Controllers\Api\V1\HealthCheckController;
use App\Http\Controllers\Api\V1\SuperAdmin\GlobalDashboardController;
use App\Http\Controllers\Api\V1\SuperAdmin\SiteManagementController;
use App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminManagementController;
use App\Http\Controllers\Api\V1\Usager\NotificationController;
use App\Http\Controllers\Api\V1\Usager\OperationController;
use App\Http\Controllers\Api\V1\Usager\PresenceController;
use App\Http\Controllers\Api\V1\Usager\ReservationController;
use App\Http\Controllers\Api\V1\Usager\UsagerController;
use App\Http\Controllers\Api\V1\Usager\UsagerSiteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - American Shelf Abobo
|--------------------------------------------------------------------------
| Toutes les routes de l'API sont versionnÃƒÂ©es sous /api/v1.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // Diagnostic et santÃƒÂ© API
    Route::get('/health', [HealthCheckController::class, 'index'])->name('health');
    Route::get('/site-public', [SiteController::class, 'publicSite'])->name('site.public');

    // ==========================================
    // 1. ESPACE AUTHENTIFICATION ADMINISTRATION
    // ==========================================
    Route::prefix('auth/admin')->name('auth.admin.')->group(function () {
        // Routes publiques
        Route::post('/login', [AdminAuthController::class, 'login'])->name('login');

        // Routes protÃƒÂ©gÃƒÂ©es par JWT (guard: api)
        Route::middleware('auth.jwt:api')->group(function () {
            Route::get('/me', [AdminAuthController::class, 'me'])->name('me');
            Route::put('/me', [AdminAuthController::class, 'updateProfile'])->name('me.update');
            Route::put('/password', [AdminAuthController::class, 'updatePassword'])->name('password.update');
            Route::post('/refresh', [AdminAuthController::class, 'refresh'])->name('refresh');
            Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        });
    });

    // ==========================================
    // 2. ESPACE AUTHENTIFICATION USAGER (PUBLIC)
    // ==========================================
    Route::prefix('auth/usager')->name('auth.usager.')->group(function () {
        // Routes publiques
        Route::post('/login', [UsagerAuthController::class, 'login'])->name('login');

        // Routes protÃƒÂ©gÃƒÂ©es par JWT (guard: usagers)
        Route::middleware('auth.jwt:usagers')->group(function () {
            Route::get('/me', [UsagerAuthController::class, 'me'])->name('me');
            Route::post('/refresh', [UsagerAuthController::class, 'refresh'])->name('refresh');
            Route::post('/logout', [UsagerAuthController::class, 'logout'])->name('logout');
        });
    });

    // =========================================================================
    // 3. MODULES D'ADMINISTRATION & CONFIGURATION (Ãƒâ€°tape 4)
    // =========================================================================
    Route::middleware(['auth.jwt:api', 'tenant'])->group(function () {

        // 3.1 MODULE SITES
        Route::prefix('sites')->name('sites.')->group(function () {
            Route::get('/', [SiteController::class, 'index'])->middleware('permission:SITE_READ')->name('index');
            Route::post('/', [SiteController::class, 'store'])->middleware('permission:SITE_CREATE')->name('store');
            Route::get('/{id}', [SiteController::class, 'show'])->middleware('permission:SITE_READ')->name('show');
            Route::put('/{id}', [SiteController::class, 'update'])->middleware('permission:SITE_UPDATE')->name('update');
            Route::patch('/{id}', [SiteController::class, 'update'])->middleware('permission:SITE_UPDATE')->name('patch');
            Route::delete('/{id}', [SiteController::class, 'destroy'])->middleware('permission:SITE_DELETE')->name('destroy');
            Route::post('/{id}/restore', [SiteController::class, 'restore'])->middleware('permission:SITE_UPDATE')->name('restore');
            Route::post('/{id}/logo', [SiteController::class, 'uploadLogo'])->middleware('permission:SITE_UPDATE')->name('logo.upload');
        });

        // 3.2 MODULE CATEGORIES USAGERS
        Route::prefix('categories-usagers')->name('categories-usagers.')->group(function () {
            Route::get('/', [CategorieUsagerController::class, 'index'])->middleware('permission:CATEGORIE_USAGER_READ')->name('index');
            Route::post('/', [CategorieUsagerController::class, 'store'])->middleware('permission:CATEGORIE_USAGER_CREATE')->name('store');
            Route::get('/{id}', [CategorieUsagerController::class, 'show'])->middleware('permission:CATEGORIE_USAGER_READ')->name('show');
            Route::put('/{id}', [CategorieUsagerController::class, 'update'])->middleware('permission:CATEGORIE_USAGER_UPDATE')->name('update');
            Route::patch('/{id}', [CategorieUsagerController::class, 'update'])->middleware('permission:CATEGORIE_USAGER_UPDATE')->name('patch');
            Route::delete('/{id}', [CategorieUsagerController::class, 'destroy'])->middleware('permission:CATEGORIE_USAGER_DELETE')->name('destroy');
            Route::post('/{id}/restore', [CategorieUsagerController::class, 'restore'])->middleware('permission:CATEGORIE_USAGER_UPDATE')->name('restore');
        });

        // 3.3 MODULE CATEGORIES LIVRES
        Route::prefix('categories-livres')->name('categories-livres.')->group(function () {
            Route::get('/', [CategorieLivreController::class, 'index'])->middleware('permission:CATEGORIE_LIVRE_READ')->name('index');
            Route::post('/', [CategorieLivreController::class, 'store'])->middleware('permission:CATEGORIE_LIVRE_CREATE')->name('store');
            Route::get('/{id}', [CategorieLivreController::class, 'show'])->middleware('permission:CATEGORIE_LIVRE_READ')->name('show');
            Route::put('/{id}', [CategorieLivreController::class, 'update'])->middleware('permission:CATEGORIE_LIVRE_UPDATE')->name('update');
            Route::patch('/{id}', [CategorieLivreController::class, 'update'])->middleware('permission:CATEGORIE_LIVRE_UPDATE')->name('patch');
            Route::delete('/{id}', [CategorieLivreController::class, 'destroy'])->middleware('permission:CATEGORIE_LIVRE_DELETE')->name('destroy');
            Route::post('/{id}/restore', [CategorieLivreController::class, 'restore'])->middleware('permission:CATEGORIE_LIVRE_UPDATE')->name('restore');
        });

        // 3.4 MODULE CONFIGURATION
        Route::prefix('configuration')->name('configuration.')->group(function () {
            Route::get('/', [ConfigurationController::class, 'show'])->middleware('permission:CONFIGURATION_READ')->name('show');
            Route::put('/', [ConfigurationController::class, 'save'])->middleware('permission:CONFIGURATION_UPDATE')->name('save');
            Route::post('/', [ConfigurationController::class, 'save'])->middleware('permission:CONFIGURATION_UPDATE')->name('store');
        });

        // 3.5 MODULE MENUS
        Route::prefix('menus')->name('menus.')->group(function () {
            Route::get('/', [MenuController::class, 'index'])->middleware('permission:MENU_READ')->name('index');
            Route::get('/tree', [MenuController::class, 'tree'])->middleware('permission:MENU_READ')->name('tree');
            Route::post('/', [MenuController::class, 'store'])->middleware('permission:MENU_CREATE')->name('store');
            Route::get('/{id}', [MenuController::class, 'show'])->middleware('permission:MENU_READ')->name('show');
            Route::put('/{id}', [MenuController::class, 'update'])->middleware('permission:MENU_UPDATE')->name('update');
            Route::patch('/{id}', [MenuController::class, 'update'])->middleware('permission:MENU_UPDATE')->name('patch');
            Route::delete('/{id}', [MenuController::class, 'destroy'])->middleware('permission:MENU_DELETE')->name('destroy');
            Route::post('/{id}/restore', [MenuController::class, 'restore'])->middleware('permission:MENU_UPDATE')->name('restore');
        });

        // 3.6 MODULE SOUS-MENUS
        Route::prefix('sous-menus')->name('sous-menus.')->group(function () {
            Route::get('/', [SousMenuController::class, 'index'])->middleware('permission:SOUS_MENU_READ')->name('index');
            Route::post('/', [SousMenuController::class, 'store'])->middleware('permission:SOUS_MENU_CREATE')->name('store');
            Route::get('/{id}', [SousMenuController::class, 'show'])->middleware('permission:SOUS_MENU_READ')->name('show');
            Route::put('/{id}', [SousMenuController::class, 'update'])->middleware('permission:SOUS_MENU_UPDATE')->name('update');
            Route::patch('/{id}', [SousMenuController::class, 'update'])->middleware('permission:SOUS_MENU_UPDATE')->name('patch');
            Route::delete('/{id}', [SousMenuController::class, 'destroy'])->middleware('permission:SOUS_MENU_DELETE')->name('destroy');
            Route::post('/{id}/restore', [SousMenuController::class, 'restore'])->middleware('permission:SOUS_MENU_UPDATE')->name('restore');
        });

        // 3.7 MODULE PERMISSIONS
        Route::prefix('permissions')->name('permissions.')->group(function () {
            Route::get('/', [PermissionController::class, 'index'])->middleware('permission:PERMISSION_READ')->name('index');
            Route::post('/', [PermissionController::class, 'store'])->middleware('permission:PERMISSION_CREATE')->name('store');
            Route::get('/{id}', [PermissionController::class, 'show'])->middleware('permission:PERMISSION_READ')->name('show');
            Route::put('/{id}', [PermissionController::class, 'update'])->middleware('permission:PERMISSION_UPDATE')->name('update');
            Route::patch('/{id}', [PermissionController::class, 'update'])->middleware('permission:PERMISSION_UPDATE')->name('patch');
            Route::delete('/{id}', [PermissionController::class, 'destroy'])->middleware('permission:PERMISSION_DELETE')->name('destroy');
        });

        // 3.8 MODULE ROLES & ROLE_PERMISSIONS
        Route::prefix('roles')->name('roles.')->group(function () {
            Route::get('/', [RoleController::class, 'index'])->middleware('permission:ROLE_READ')->name('index');
            Route::post('/', [RoleController::class, 'store'])->middleware('permission:ROLE_CREATE')->name('store');
            Route::get('/{id}', [RoleController::class, 'show'])->middleware('permission:ROLE_READ')->name('show');
            Route::put('/{id}', [RoleController::class, 'update'])->middleware('permission:ROLE_UPDATE')->name('update');
            Route::patch('/{id}', [RoleController::class, 'update'])->middleware('permission:ROLE_UPDATE')->name('patch');
            Route::delete('/{id}', [RoleController::class, 'destroy'])->middleware('permission:ROLE_DELETE')->name('destroy');
            Route::post('/{id}/restore', [RoleController::class, 'restore'])->middleware('permission:ROLE_UPDATE')->name('restore');

            // Synchronisation des permissions d'un rÃƒÂ´le
            Route::put('/{id}/permissions', [RoleController::class, 'syncPermissions'])->middleware('permission:ROLE_UPDATE')->name('permissions.sync');
        });

        // 3.9 MODULE UTILISATEURS ADMINISTRATIFS
        Route::prefix('utilisateurs')->name('utilisateurs.')->group(function () {
            Route::get('/', [UtilisateurController::class, 'index'])->middleware('permission:UTILISATEUR_READ')->name('index');
            Route::post('/', [UtilisateurController::class, 'store'])->middleware('permission:UTILISATEUR_CREATE')->name('store');
            Route::get('/{id}', [UtilisateurController::class, 'show'])->middleware('permission:UTILISATEUR_READ')->name('show');
            Route::put('/{id}', [UtilisateurController::class, 'update'])->middleware('permission:UTILISATEUR_UPDATE')->name('update');
            Route::patch('/{id}', [UtilisateurController::class, 'update'])->middleware('permission:UTILISATEUR_UPDATE')->name('patch');
            Route::delete('/{id}', [UtilisateurController::class, 'destroy'])->middleware('permission:UTILISATEUR_DELETE')->name('destroy');
            Route::post('/{id}/restore', [UtilisateurController::class, 'restore'])->middleware('permission:UTILISATEUR_UPDATE')->name('restore');
        });

        // =========================================================================
        // 4. MODULES DE GESTION DOCUMENTAIRE (Ãƒâ€°tape 5)
        // =========================================================================

        // 4.1 MODULE ACQUISITIONS
        Route::prefix('acquisitions')->name('acquisitions.')->group(function () {
            Route::get('/', [AcquisitionController::class, 'index'])->middleware('permission:ACQUISITION_READ')->name('index');
            Route::post('/', [AcquisitionController::class, 'store'])->middleware('permission:ACQUISITION_CREATE')->name('store');
            Route::get('/{id}', [AcquisitionController::class, 'show'])->middleware('permission:ACQUISITION_READ')->name('show');
            Route::put('/{id}', [AcquisitionController::class, 'update'])->middleware('permission:ACQUISITION_UPDATE')->name('update');
            Route::patch('/{id}', [AcquisitionController::class, 'update'])->middleware('permission:ACQUISITION_UPDATE')->name('patch');
            Route::delete('/{id}', [AcquisitionController::class, 'destroy'])->middleware('permission:ACQUISITION_DELETE')->name('destroy');
            Route::post('/{id}/restore', [AcquisitionController::class, 'restore'])->middleware('permission:ACQUISITION_UPDATE')->name('restore');
        });

        // 4.2 MODULE LIVRES
        Route::prefix('livres')->name('livres.')->group(function () {
            Route::get('/', [LivreController::class, 'index'])->middleware('permission:LIVRE_READ')->name('index');
            Route::post('/', [LivreController::class, 'store'])->middleware('permission:LIVRE_CREATE')->name('store');
            Route::get('/{id}', [LivreController::class, 'show'])->middleware('permission:LIVRE_READ')->name('show');
            Route::put('/{id}', [LivreController::class, 'update'])->middleware('permission:LIVRE_UPDATE')->name('update');
            Route::patch('/{id}', [LivreController::class, 'update'])->middleware('permission:LIVRE_UPDATE')->name('patch');
            Route::delete('/{id}', [LivreController::class, 'destroy'])->middleware('permission:LIVRE_DELETE')->name('destroy');
            Route::post('/{id}/restore', [LivreController::class, 'restore'])->middleware('permission:LIVRE_UPDATE')->name('restore');

            // CrÃƒÂ©ation groupÃƒÂ©e d'exemplaires pour un livre spÃƒÂ©cifique
            Route::post('/{id}/exemplaires', [ExemplaireController::class, 'storeBulkForLivre'])->middleware('permission:EXEMPLAIRE_CREATE')->name('exemplaires.bulk');
        });

        // 4.3 MODULE EXEMPLAIRES
        Route::prefix('exemplaires')->name('exemplaires.')->group(function () {
            Route::get('/', [ExemplaireController::class, 'index'])->middleware('permission:EXEMPLAIRE_READ')->name('index');
            Route::post('/', [ExemplaireController::class, 'store'])->middleware('permission:EXEMPLAIRE_CREATE')->name('store');
            Route::post('/bulk', [ExemplaireController::class, 'storeBulk'])->middleware('permission:EXEMPLAIRE_CREATE')->name('store.bulk');
            Route::get('/{id}', [ExemplaireController::class, 'show'])->middleware('permission:EXEMPLAIRE_READ')->name('show');
            Route::put('/{id}', [ExemplaireController::class, 'update'])->middleware('permission:EXEMPLAIRE_UPDATE')->name('update');
            Route::patch('/{id}', [ExemplaireController::class, 'update'])->middleware('permission:EXEMPLAIRE_UPDATE')->name('patch');
            Route::delete('/{id}', [ExemplaireController::class, 'destroy'])->middleware('permission:EXEMPLAIRE_DELETE')->name('destroy');
            Route::post('/{id}/restore', [ExemplaireController::class, 'restore'])->middleware('permission:EXEMPLAIRE_UPDATE')->name('restore');
        });

        // =========================================================================
        // 5. MODULES UTILISATEURS & OPÃƒâ€°RATIONS (Ãƒâ€°tape 6)
        // =========================================================================

        // 5.1 MODULE USAGERS
        Route::prefix('usagers')->name('usagers.')->group(function () {
            Route::get('/', [UsagerController::class, 'index'])->middleware('permission:USAGER_READ')->name('index');
            Route::post('/', [UsagerController::class, 'store'])->middleware('permission:USAGER_CREATE')->name('store');
            Route::get('/{id}', [UsagerController::class, 'show'])->middleware('permission:USAGER_READ')->name('show');
            Route::put('/{id}', [UsagerController::class, 'update'])->middleware('permission:USAGER_UPDATE')->name('update');
            Route::patch('/{id}', [UsagerController::class, 'update'])->middleware('permission:USAGER_UPDATE')->name('patch');
            Route::delete('/{id}', [UsagerController::class, 'destroy'])->middleware('permission:USAGER_DELETE')->name('destroy');
            Route::post('/{id}/restore', [UsagerController::class, 'restore'])->middleware('permission:USAGER_UPDATE')->name('restore');
        });

        // 5.2 MODULE USAGER_SITES
        Route::prefix('usager-sites')->name('usager-sites.')->group(function () {
            Route::get('/', [UsagerSiteController::class, 'index'])->middleware('permission:USAGERSITE_READ')->name('index');
            Route::post('/', [UsagerSiteController::class, 'store'])->middleware('permission:USAGERSITE_CREATE')->name('store');
            Route::get('/{id}', [UsagerSiteController::class, 'show'])->middleware('permission:USAGERSITE_READ')->name('show');
            Route::put('/{id}', [UsagerSiteController::class, 'update'])->middleware('permission:USAGERSITE_UPDATE')->name('update');
            Route::patch('/{id}', [UsagerSiteController::class, 'update'])->middleware('permission:USAGERSITE_UPDATE')->name('patch');
            Route::delete('/{id}', [UsagerSiteController::class, 'destroy'])->middleware('permission:USAGERSITE_DELETE')->name('destroy');
            Route::post('/{id}/restore', [UsagerSiteController::class, 'restore'])->middleware('permission:USAGERSITE_UPDATE')->name('restore');
        });

        // 5.3 MODULE PRESENCES
        Route::prefix('presences')->name('presences.')->group(function () {
            Route::get('/', [PresenceController::class, 'index'])->middleware('permission:PRESENCE_READ')->name('index');
            Route::get('/statistiques', [PresenceController::class, 'statistiques'])->middleware('permission:PRESENCE_READ')->name('statistiques');
            Route::post('/', [PresenceController::class, 'store'])->middleware('permission:PRESENCE_CREATE')->name('store');
            Route::post('/{id}/sortie', [PresenceController::class, 'sortie'])->middleware('permission:PRESENCE_UPDATE')->name('sortie');
            Route::get('/{id}', [PresenceController::class, 'show'])->middleware('permission:PRESENCE_READ')->name('show');
            Route::put('/{id}', [PresenceController::class, 'update'])->middleware('permission:PRESENCE_UPDATE')->name('update');
            Route::patch('/{id}', [PresenceController::class, 'update'])->middleware('permission:PRESENCE_UPDATE')->name('patch');
            Route::delete('/{id}', [PresenceController::class, 'destroy'])->middleware('permission:PRESENCE_DELETE')->name('destroy');
            Route::post('/{id}/restore', [PresenceController::class, 'restore'])->middleware('permission:PRESENCE_UPDATE')->name('restore');
        });

        // 5.4 MODULE OPERATIONS
        Route::prefix('operations')->name('operations.')->group(function () {
            Route::get('/', [OperationController::class, 'index'])->middleware('permission:OPERATION_READ')->name('index');
            Route::post('/', [OperationController::class, 'store'])->middleware('permission:OPERATION_CREATE')->name('store');
            Route::post('/{id}/retour', [OperationController::class, 'retour'])->middleware('permission:OPERATION_UPDATE')->name('retour');
            Route::get('/{id}', [OperationController::class, 'show'])->middleware('permission:OPERATION_READ')->name('show');
            Route::put('/{id}', [OperationController::class, 'update'])->middleware('permission:OPERATION_UPDATE')->name('update');
            Route::patch('/{id}', [OperationController::class, 'update'])->middleware('permission:OPERATION_UPDATE')->name('patch');
            Route::delete('/{id}', [OperationController::class, 'destroy'])->middleware('permission:OPERATION_DELETE')->name('destroy');
            Route::post('/{id}/restore', [OperationController::class, 'restore'])->middleware('permission:OPERATION_UPDATE')->name('restore');
        });

        // =========================================================================
        // 6. MODULES RÃƒâ€°SERVATIONS & NOTIFICATIONS (Ãƒâ€°tape 7)
        // =========================================================================

        // 6.1 MODULE RÃƒâ€°SERVATIONS
        Route::prefix('reservations')->name('reservations.')->group(function () {
            Route::get('/', [ReservationController::class, 'index'])->middleware('permission:RESERVATION_READ')->name('index');
            Route::post('/', [ReservationController::class, 'store'])->middleware('permission:RESERVATION_CREATE')->name('store');
            Route::post('/{id}/annuler', [ReservationController::class, 'annuler'])->middleware('permission:RESERVATION_UPDATE')->name('annuler');
            Route::get('/{id}', [ReservationController::class, 'show'])->middleware('permission:RESERVATION_READ')->name('show');
            Route::put('/{id}', [ReservationController::class, 'update'])->middleware('permission:RESERVATION_UPDATE')->name('update');
            Route::patch('/{id}', [ReservationController::class, 'update'])->middleware('permission:RESERVATION_UPDATE')->name('patch');
            Route::delete('/{id}', [ReservationController::class, 'destroy'])->middleware('permission:RESERVATION_DELETE')->name('destroy');
            Route::post('/{id}/restore', [ReservationController::class, 'restore'])->middleware('permission:RESERVATION_UPDATE')->name('restore');
        });

        // 6.2 MODULE NOTIFICATIONS
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->middleware('permission:NOTIFICATION_READ')->name('unread-count');
            Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->middleware('permission:NOTIFICATION_READ')->name('mark-all-read');
            Route::match(['patch', 'post'], '/{id}/mark-as-read', [NotificationController::class, 'markAsRead'])->middleware('permission:NOTIFICATION_READ')->name('mark-as-read');
            Route::get('/', [NotificationController::class, 'index'])->middleware('permission:NOTIFICATION_READ')->name('index');
            Route::post('/', [NotificationController::class, 'store'])->middleware('permission:NOTIFICATION_CREATE')->name('store');
            Route::get('/{id}', [NotificationController::class, 'show'])->middleware('permission:NOTIFICATION_READ')->name('show');
            Route::delete('/{id}', [NotificationController::class, 'destroy'])->middleware('permission:NOTIFICATION_DELETE')->name('destroy');
            Route::post('/{id}/restore', [NotificationController::class, 'restore'])->middleware('permission:NOTIFICATION_DELETE')->name('restore');
        });

        // =========================================================================
        // 7. MODULE DASHBOARD & STATISTIQUES (Ãƒâ€°tape 8)
        // =========================================================================
        Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permission:DASHBOARD_READ')->name('dashboard');

        // =========================================================================
        // 8. MODULE JOURNAL D'ACTIVITÃƒâ€°S (Ãƒâ€°tape 8)
        // =========================================================================
        Route::prefix('journal-activites')->name('journal-activites.')->group(function () {
            Route::get('/', [JournalActiviteController::class, 'index'])->middleware('permission:JOURNAL_READ')->name('index');
            Route::get('/{id}', [JournalActiviteController::class, 'show'])->middleware('permission:JOURNAL_READ')->name('show');
        });

        // =========================================================================
        // 9. MODULE SAUVEGARDES (Étape 8)
        // =========================================================================
        Route::prefix('sauvegardes')->name('sauvegardes.')->group(function () {
            Route::get('/', [SauvegardeController::class, 'index'])->middleware('permission:SAUVEGARDE_READ')->name('index');
            Route::get('/{id}', [SauvegardeController::class, 'show'])->middleware('permission:SAUVEGARDE_READ')->name('show');
            Route::delete('/{id}', [SauvegardeController::class, 'destroy'])->middleware('permission:SAUVEGARDE_DELETE')->name('destroy');
            Route::post('/{id}/restore', [SauvegardeController::class, 'restore'])->middleware('permission:SAUVEGARDE_DELETE')->name('restore');
        });

        // =========================================================================
        // 10. MODULE RAPPORTS & ÉDITIONS (STATISTIQUES AVANCÉES & INVENTAIRE)
        // =========================================================================
        Route::prefix('rapports')->name('rapports.')->group(function () {
            // 1. Fonds documentaire
            Route::get('/fonds-documentaire', [RapportController::class, 'fondsDocumentaire'])->middleware('permission:RAPPORT_READ')->name('fonds-documentaire');
            Route::get('/fonds-documentaire/export', [RapportController::class, 'exportFondsDocumentaire'])->middleware('permission:RAPPORT_EXPORT')->name('fonds-documentaire.export');

            // 2. Emprunts, retours et consultations
            Route::get('/emprunts', [RapportController::class, 'emprunts'])->middleware('permission:RAPPORT_READ')->name('emprunts');
            Route::get('/emprunts/export', [RapportController::class, 'exportEmprunts'])->middleware('permission:RAPPORT_EXPORT')->name('emprunts.export');

            // 3. Livres les plus empruntés
            Route::get('/livres-plus-empruntes', [RapportController::class, 'topLivres'])->middleware('permission:RAPPORT_READ')->name('livres-plus-empruntes');

            // 4. Retards d'emprunts
            Route::get('/retards', [RapportController::class, 'retards'])->middleware('permission:RAPPORT_READ')->name('retards');
            Route::get('/retards/export', [RapportController::class, 'exportRetards'])->middleware('permission:RAPPORT_EXPORT')->name('retards.export');

            // 5. Acquisitions
            Route::get('/acquisitions', [RapportController::class, 'acquisitions'])->middleware('permission:RAPPORT_READ')->name('acquisitions');
            Route::get('/acquisitions/export', [RapportController::class, 'exportAcquisitions'])->middleware('permission:RAPPORT_EXPORT')->name('acquisitions.export');

            // 6. Fréquentation
            Route::get('/frequentation', [RapportController::class, 'frequentation'])->middleware('permission:RAPPORT_READ')->name('frequentation');

            // 7. Inventaire historique
            Route::get('/inventaire/historique', [RapportController::class, 'inventaireHistorique'])->middleware('permission:INVENTAIRE_READ')->name('inventaire.historique');
            Route::get('/inventaire/historique/export', [RapportController::class, 'exportInventaireHistorique'])->middleware('permission:RAPPORT_EXPORT')->name('inventaire.historique.export');
            Route::post('/inventaire/snapshot', [RapportController::class, 'declencherSnapshot'])->middleware('permission:INVENTAIRE_EXECUTE')->name('inventaire.snapshot');
        });
    });

    // =========================================================================
    // 11. ESPACE SUPER ADMIN (Sécurisé par JWT + CheckSuperAdmin)
    // =========================================================================
    Route::prefix('super-admin')->name('super-admin.')->middleware(['auth.jwt:api', 'super_admin'])->group(function () {
        Route::get('/ping', function () {
            return response()->json([
                'success' => true,
                'message' => 'Accès Super Administrateur autorisé.',
            ]);
        })->name('ping');

        // 11.0 DASHBOARD GLOBAL CONSOLIDÉ (Étape 4)
        Route::get('/dashboard', [GlobalDashboardController::class, 'index'])->name('dashboard');

        // 11.1 GESTION DES SITES (Étape 2)
        Route::prefix('sites')->name('sites.')->group(function () {
            Route::get('/', [SiteManagementController::class, 'index'])->name('index');
            Route::post('/', [SiteManagementController::class, 'store'])->name('store');
            Route::get('/{id}', [SiteManagementController::class, 'show'])->name('show');
            Route::put('/{id}', [SiteManagementController::class, 'update'])->name('update');
            Route::patch('/{id}/status', [SiteManagementController::class, 'updateStatus'])->name('status');
            Route::post('/{id}/admin', [SiteManagementController::class, 'assignAdmin'])->name('admin');
            Route::delete('/{id}', [SiteManagementController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/restore', [SiteManagementController::class, 'restore'])->name('restore');
        });

        // 11.2 GESTION DES SUPER ADMINS (Étape 3)
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [SuperAdminManagementController::class, 'index'])->name('index');
            Route::post('/', [SuperAdminManagementController::class, 'store'])->name('store');
            Route::get('/{id}', [SuperAdminManagementController::class, 'show'])->name('show');
            Route::put('/{id}', [SuperAdminManagementController::class, 'update'])->name('update');
            Route::patch('/{id}/status', [SuperAdminManagementController::class, 'updateStatus'])->name('status');
            Route::delete('/{id}', [SuperAdminManagementController::class, 'destroy'])->name('destroy');
        });
    });
});



