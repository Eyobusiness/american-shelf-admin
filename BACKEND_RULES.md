# AMERICAN SHELF ABOBO — BACKEND RULES

## 1. Objet du document

Ce document définit les règles techniques obligatoires à respecter pour le développement du backend du projet **American Shelf Abobo**.

Toute nouvelle fonctionnalité, modification ou correction doit respecter ces règles.

---

# 2. Stack technique

Le backend doit utiliser :

* Laravel 12
* PHP 8.2+
* MySQL
* Eloquent ORM
* API REST
* Authentification JWT
* Form Requests pour les validations
* API Resources pour les réponses
* Services pour la logique métier
* Middleware pour les contrôles transversaux
* Soft Deletes
* Factories et Seeders pour les données fictives

---

# 3. Architecture générale

Le backend doit respecter une architecture claire :

```text
Request
   ↓
Route API
   ↓
Middleware
   ↓
Form Request
   ↓
Controller
   ↓
Service
   ↓
Model / Eloquent
   ↓
Resource
   ↓
JSON Response
```

## Responsabilités

### Routes

Les routes définissent uniquement les endpoints disponibles.

Elles ne doivent pas contenir de logique métier.

### Middleware

Les middleware gèrent notamment :

* authentification JWT ;
* identification du site/tenant ;
* permissions ;
* sécurité ;
* contrôles transversaux.

### Form Requests

Toute validation des données entrantes doit être effectuée dans les Form Requests.

INTERDIT :

```php
if (empty($request->titre)) {
    ...
}
```

dans un Controller ou un Service lorsque cela relève de la validation de requête.

PRÉFÉRÉ :

```text
StoreLivreRequest
UpdateLivreRequest
```

### Controllers

Les Controllers doivent rester légers.

Ils doivent :

1. recevoir la requête ;
2. utiliser le Form Request ;
3. appeler le Service ;
4. retourner une Resource ou une réponse JSON.

La logique métier ne doit pas être placée directement dans les Controllers.

### Services

Toute logique métier complexe doit être placée dans un Service.

Exemples :

```text
LivreService
ExemplaireService
AcquisitionService
UsagerService
OperationService
ReservationService
UtilisateurService
```

### Models

Les Models doivent gérer :

* relations Eloquent ;
* casts ;
* scopes ;
* attributs ;
* Soft Deletes ;
* configuration propre aux données.

---

# 4. API REST

Toutes les API doivent être versionnées.

Format :

```text
/api/v1/...
```

Exemple :

```text
/api/v1/livres
/api/v1/usagers
/api/v1/exemplaires
```

## CRUD standard

Pour une ressource classique :

```text
GET     /api/v1/livres
GET     /api/v1/livres/{id}
POST    /api/v1/livres
PUT     /api/v1/livres/{id}
DELETE  /api/v1/livres/{id}
```

Les endpoints doivent respecter les conventions REST.

---

# 5. Réponses API

Les réponses JSON doivent avoir une structure cohérente.

### Succès

```json
{
    "success": true,
    "message": "Livre récupéré avec succès.",
    "data": {}
}
```

### Liste

```json
{
    "success": true,
    "message": "Livres récupérés avec succès.",
    "data": [],
    "meta": {}
}
```

### Erreur

```json
{
    "success": false,
    "message": "Une erreur est survenue.",
    "errors": {}
}
```

Les codes HTTP doivent être correctement utilisés :

```text
200 → succès
201 → création
204 → suppression sans contenu
400 → mauvaise requête
401 → non authentifié
403 → accès interdit
404 → ressource inexistante
422 → erreur de validation
500 → erreur serveur
```

---

# 6. Authentification JWT

L'authentification de l'administration doit utiliser JWT.

L'authentification doit permettre :

* connexion ;
* génération du token ;
* vérification du token ;
* récupération de l'utilisateur authentifié ;
* déconnexion/invalidation du token selon la solution JWT utilisée ;
* protection des routes privées.

Les mots de passe doivent toujours être hashés.

INTERDIT :

```php
$user->mot_de_passe = $request->mot_de_passe;
```

PRÉFÉRÉ :

```php
Hash::make($request->mot_de_passe);
```

---

# 7. Deux espaces d'authentification

Le système distingue :

## Utilisateur

Compte administratif :

* administrateur ;
* bibliothécaire ;
* autres profils administratifs.

Il accède à l'espace d'administration.

## Usager

Compte public :

* usager ;
* adhérent.

Il accède à l'espace public.

Les deux concepts ne doivent pas être mélangés.

---

# 8. Multi-tenant

Le projet est conçu comme un SaaS multi-site.

`Site` représente le tenant.

Les données appartenant à un site doivent être isolées par :

```text
site_id
```

## Règle obligatoire

Une requête d'un site ne doit jamais pouvoir récupérer ou modifier les données d'un autre site.

Le filtrage tenant doit être effectué automatiquement lorsque cela est applicable.

INTERDIT :

```php
Livre::all();
```

si cela permet de retourner les livres de tous les sites dans un contexte tenant.

PRÉFÉRÉ :

```php
Livre::query()
    ->where('site_id', $siteId)
    ->get();
```

ou utiliser un mécanisme centralisé de scope/middleware/service permettant d'assurer cette isolation.

---

# 9. Usager multi-site

Un même usager peut utiliser plusieurs sites.

Il ne faut donc pas placer `site_id` directement dans `Usager`.

La relation doit utiliser :

```text
Usager
   ↕
UsagerSite
   ↕
Site
```

Les informations propres à la relation avec un site doivent être stockées dans `UsagerSite`.

Exemples :

* type USAGER / ADHERENT ;
* catégorie d'usager ;
* statut ;
* numéro local ;
* date d'inscription au site.

---

# 10. Soft Delete

Les entités métier doivent utiliser le Soft Delete lorsque cela est prévu.

Utiliser :

```php
use Illuminate\Database\Eloquent\SoftDeletes;
```

Les tables concernées doivent prévoir :

```text
deleted_at
deleted_by
```

La suppression normale doit être logique.

La suppression physique ne doit être utilisée que lorsqu'elle est explicitement nécessaire.

Une fonctionnalité de restauration doit être prévue pour les entités concernées.

---

# 11. Champs d'audit

Les entités métier doivent utiliser :

```text
created_at
updated_at
deleted_at
deleted_by
```

lorsque cela est pertinent.

Les journaux historiques ne doivent normalement pas être modifiables.

---

# 12. Modèle de données

Les relations doivent être représentées par des clés étrangères.

Exemple :

```text
Livre
    categorie_livre_id → CategorieLivre

Exemplaire
    livre_id → Livre
    site_id → Site

Operation
    usager_id → Usager
    exemplaire_id → Exemplaire
    configuration_id → Configuration
```

Les relations doivent être définies également dans les Models Eloquent.

---

# 13. Livre et Exemplaire

`Livre` représente l'œuvre bibliographique.

`Exemplaire` représente une copie physique.

Ne jamais confondre les deux.

### Livre

Contient notamment :

```text
titre
auteur
éditeur
ISBN
catégorie
image
fichier numérique
visibilité en ligne
```

### Exemplaire

Contient notamment :

```text
code
état
statut
localisation
```

Un livre peut avoir plusieurs exemplaires.

---

# 14. Création groupée des exemplaires

La création des exemplaires doit accepter un nombre d'exemplaires à générer.

Le champ `nombre` est une donnée de saisie du formulaire.

Il ne doit pas être stocké dans chaque ligne `Exemplaire`.

Exemple :

```text
Livre : LIT-ROM-0001
Nombre : 5
```

Résultat :

```text
LIT-ROM-0001-001
LIT-ROM-0001-002
LIT-ROM-0001-003
LIT-ROM-0001-004
LIT-ROM-0001-005
```

La création doit être transactionnelle.

Si une création échoue, l'opération complète doit être annulée lorsque cela est nécessaire pour garantir la cohérence des données.

---

# 15. Acquisition

`Acquisition` est indépendante de `Livre`.

Une acquisition permet d'enregistrer l'entrée de livres dans la bibliothèque :

```text
ACHAT
DON
```

Elle peut contenir :

```text
date
provenance
quantité
coût
référence facture
donateur
nature du don
destination
état
observations
pièce jointe
```

L'enregistrement d'une acquisition ne doit pas obliger l'administrateur à créer immédiatement les livres dans le catalogue.

---

# 16. Consultation et Emprunt

Les consultations et emprunts sont regroupés dans :

```text
Operation
```

avec :

```text
type = CONSULTATION
type = EMPRUNT
```

Une opération concerne un exemplaire physique.

---

# 17. Configuration

La configuration appartient à un site.

Elle permet notamment de définir :

```text
duree_emprunt
nombre_jours_grace
duree_consultation
quota_emprunt
duree_reservation
```

Une opération doit conserver la configuration utilisée au moment de sa création via :

```text
configuration_id
```

Cela permet de préserver l'historique des règles appliquées.

---

# 18. Calcul automatique

Les dates et statuts doivent être calculés automatiquement lorsque cela relève de la logique métier.

Exemples :

```text
date_retour_prevue
date_expiration_reservation
retard
disponibilité exemplaire
```

Ne pas demander inutilement au frontend de calculer les règles métier critiques.

Le backend reste la source de vérité.

---

# 19. Réservation

Une réservation porte sur :

```text
Livre
```

et non sur un exemplaire précis.

La réservation permet à un usager de réserver un livre depuis l'espace public.

L'usager doit ensuite se rendre physiquement au site pour récupérer le livre.

La lecture numérique n'est pas considérée comme disponible dans la version actuelle.

---

# 20. Notifications

Les notifications utilisateur sont principalement envoyées par email.

Événements possibles :

```text
création du compte
activation du compte
réservation
réservation disponible
rappel de réservation
retour du livre
remerciement après retour
retard
```

Les notifications doivent être traitées de manière centralisée.

Le système ne doit pas dupliquer la logique d'envoi d'email dans plusieurs Controllers.

---

# 21. Autorisations

Les droits d'administration sont gérés par :

```text
Role
Permission
RolePermission
```

La permission doit permettre de contrôler :

```text
Menu
Sous-menu
Action
```

Actions courantes :

```text
CREATE
READ
UPDATE
DELETE
```

Le middleware d'autorisation doit empêcher l'accès à une fonctionnalité non autorisée.

Masquer un bouton dans le frontend ne constitue pas une sécurité suffisante.

Le backend doit toujours vérifier les permissions.

---

# 22. Menu et SousMenu

La navigation administrative est dynamique.

`Menu` contient notamment :

```text
code
libelle
icon
path
order
reference
statut
```

`SousMenu` contient notamment :

```text
menu_id
code
libelle
icon
path
order
reference
statut
```

Les menus et sous-menus doivent pouvoir être gérés sans modifier le code frontend lorsque cette fonctionnalité est activée.

---

# 23. Transactions

Les opérations critiques doivent utiliser les transactions de base de données.

Exemple :

```php
DB::transaction(function () {
    // opérations
});
```

Particulièrement pour :

* création groupée d'exemplaires ;
* emprunt ;
* retour ;
* réservation ;
* opérations modifiant plusieurs tables ;
* actions financières futures éventuelles.

---

# 24. Gestion des exceptions

Ne pas afficher les erreurs SQL ou les exceptions techniques directement au client.

Les erreurs doivent être :

* journalisées côté serveur ;
* transformées en réponses API propres ;
* accompagnées d'un code HTTP approprié.

---

# 25. Pagination

Les listes importantes doivent être paginées.

Exemple :

```text
GET /api/v1/livres?page=1&per_page=20
```

Éviter de retourner inutilement plusieurs milliers d'enregistrements.

---

# 26. Recherche et filtres

Les endpoints de liste doivent pouvoir évoluer avec des filtres.

Exemple :

```text
GET /api/v1/livres?search=laravel
GET /api/v1/livres?categorie_id=1
GET /api/v1/exemplaires?statut=DISPONIBLE
```

Les filtres doivent être appliqués côté backend.

---

# 27. Resources API

Les données retournées par l'API doivent utiliser des API Resources lorsque cela est pertinent.

Exemple :

```text
LivreResource
UsagerResource
ExemplaireResource
OperationResource
ReservationResource
```

Ne pas exposer automatiquement toutes les colonnes sensibles d'un Model.

Le mot de passe ne doit jamais être retourné dans une réponse API.

---

# 28. Données fictives

Le projet doit disposer de :

```text
Factories
Seeders
```

Les données fictives doivent respecter les relations réelles.

Exemple :

```text
Site
  ↓
Usagers
  ↓
UsagerSite

Site
  ↓
Livres
  ↓
Exemplaires

Site
  ↓
Utilisateurs
  ↓
Roles
  ↓
Permissions
```

Les données de démonstration doivent être cohérentes et exploitables immédiatement après :

```bash
php artisan migrate:fresh --seed
```

---

# 29. Migrations

Les migrations doivent être créées dans l'ordre des dépendances.

Les clés étrangères doivent respecter cet ordre.

Ne jamais contourner une relation simplement pour faire passer une migration.

Avant toute modification importante du schéma, vérifier les relations existantes.

---

# 30. Conventions de nommage

## Tables

Utiliser le pluriel Laravel :

```text
sites
usagers
usager_sites
livres
exemplaires
operations
reservations
```

## Models

Utiliser le singulier :

```text
Site
Usager
UsagerSite
Livre
Exemplaire
Operation
Reservation
```

## Controllers

```text
LivreController
ExemplaireController
OperationController
ReservationController
```

## Requests

```text
StoreLivreRequest
UpdateLivreRequest
```

## Resources

```text
LivreResource
ExemplaireResource
```

## Services

```text
LivreService
ExemplaireService
OperationService
```

---

# 31. Sécurité

Ne jamais :

* stocker les mots de passe en clair ;
* retourner les mots de passe ;
* faire confiance au frontend pour les permissions ;
* permettre à un tenant d'accéder aux données d'un autre tenant ;
* construire des requêtes SQL avec des données utilisateur non paramétrées ;
* exposer les erreurs internes au client.

Toutes les entrées utilisateur doivent être validées.

---

# 32. Principe général

Avant d'ajouter une nouvelle fonctionnalité, vérifier :

1. Quelle entité est concernée ?
2. Quel site possède les données ?
3. Quelle FK est nécessaire ?
4. Quelle validation est nécessaire ?
5. Quelle logique métier doit être exécutée ?
6. Quel Service doit gérer cette logique ?
7. Quel rôle possède le droit d'exécuter l'action ?
8. Quel endpoint REST est nécessaire ?
9. Quelle Resource doit retourner les données ?
10. Faut-il une transaction ?
11. Faut-il une notification ?
12. Faut-il journaliser l'action ?
13. Faut-il gérer le Soft Delete ?

---

# 33. Règle finale

Le code doit rester :

* modulaire ;
* maintenable ;
* sécurisé ;
* testable ;
* évolutif ;
* cohérent avec le modèle de données ;
* compatible avec une architecture SaaS multi-tenant.

Aucune logique métier importante ne doit être ajoutée directement dans le frontend pour contourner les règles du backend.

**Le backend est la source de vérité du système.**

---

# 34. Référentiel Complet des Endpoints API v1 (Finalisé Stage 8)

Toutes les routes sont préfixées par `/api/v1`.

### 1. Diagnostic / Health
| Méthode | Endpoint | Description | Middleware / Permission |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/health` | Vérification de l'état de l'API et de la base | Public |

### 2. Authentification Administration (JWT)
| Méthode | Endpoint | Description | Middleware / Permission |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/v1/auth/admin/login` | Connexion administrateur / génération JWT | Public |
| `GET` | `/api/v1/auth/admin/me` | Profil administrateur connecté | `auth.jwt:api` |
| `POST` | `/api/v1/auth/admin/refresh` | Rafraîchissement du token JWT | `auth.jwt:api` |
| `POST` | `/api/v1/auth/admin/logout` | Déconnexion / invalidation du token | `auth.jwt:api` |

### 3. Authentification Usager (Public / JWT)
| Méthode | Endpoint | Description | Middleware / Permission |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/v1/auth/usager/login` | Connexion usager / génération JWT | Public |
| `GET` | `/api/v1/auth/usager/me` | Profil usager connecté | `auth.jwt:usagers` |
| `POST` | `/api/v1/auth/usager/refresh` | Rafraîchissement du token usager | `auth.jwt:usagers` |
| `POST` | `/api/v1/auth/usager/logout` | Déconnexion usager | `auth.jwt:usagers` |

### 4. Sites (Multi-Tenant)
| Méthode | Endpoint | Description | Permission |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/sites` | Liste paginée des sites | `SITE_READ` |
| `POST` | `/api/v1/sites` | Création d'un site | `SITE_CREATE` |
| `GET` | `/api/v1/sites/{id}` | Détail d'un site | `SITE_READ` |
| `PUT/PATCH` | `/api/v1/sites/{id}` | Mise à jour d'un site | `SITE_UPDATE` |
| `DELETE` | `/api/v1/sites/{id}` | Suppression logique (soft delete) | `SITE_DELETE` |
| `POST` | `/api/v1/sites/{id}/restore` | Restauration d'un site supprimé | `SITE_UPDATE` |
| `POST` | `/api/v1/sites/{id}/logo` | Upload et mise à jour du logo (image multipart) | `SITE_UPDATE` |

### 5. Catégories d'Usagers
| Méthode | Endpoint | Description | Permission |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/categories-usagers` | Liste des catégories usagers | `CATEGORIE_USAGER_READ` |
| `POST` | `/api/v1/categories-usagers` | Création d'une catégorie usager | `CATEGORIE_USAGER_CREATE` |
| `GET` | `/api/v1/categories-usagers/{id}` | Détail d'une catégorie usager | `CATEGORIE_USAGER_READ` |
| `PUT/PATCH` | `/api/v1/categories-usagers/{id}` | Mise à jour d'une catégorie | `CATEGORIE_USAGER_UPDATE` |
| `DELETE` | `/api/v1/categories-usagers/{id}` | Suppression logique | `CATEGORIE_USAGER_DELETE` |
| `POST` | `/api/v1/categories-usagers/{id}/restore` | Restauration d'une catégorie | `CATEGORIE_USAGER_UPDATE` |

### 6. Catégories de Livres
| Méthode | Endpoint | Description | Permission |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/categories-livres` | Liste des catégories de livres | `CATEGORIE_LIVRE_READ` |
| `POST` | `/api/v1/categories-livres` | Création d'une catégorie livre | `CATEGORIE_LIVRE_CREATE` |
| `GET` | `/api/v1/categories-livres/{id}` | Détail d'une catégorie livre | `CATEGORIE_LIVRE_READ` |
| `PUT/PATCH` | `/api/v1/categories-livres/{id}` | Mise à jour d'une catégorie | `CATEGORIE_LIVRE_UPDATE` |
| `DELETE` | `/api/v1/categories-livres/{id}` | Suppression logique | `CATEGORIE_LIVRE_DELETE` |
| `POST` | `/api/v1/categories-livres/{id}/restore` | Restauration d'une catégorie | `CATEGORIE_LIVRE_UPDATE` |

### 7. Configuration du Site
| Méthode | Endpoint | Description | Permission |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/configuration` | Récupération des paramètres du site actif | `CONFIGURATION_READ` |
| `PUT/POST` | `/api/v1/configuration` | Sauvegarde/Mise à jour des paramètres | `CONFIGURATION_UPDATE` |

### 8. Menus & Sous-Menus
| Méthode | Endpoint | Description | Permission |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/menus` | Liste des menus de navigation | `MENU_READ` |
| `POST` | `/api/v1/menus` | Création d'un menu | `MENU_CREATE` |
| `GET` | `/api/v1/menus/{id}` | Détail d'un menu | `MENU_READ` |
| `PUT/PATCH` | `/api/v1/menus/{id}` | Mise à jour d'un menu | `MENU_UPDATE` |
| `DELETE` | `/api/v1/menus/{id}` | Suppression d'un menu | `MENU_DELETE` |
| `POST` | `/api/v1/menus/{id}/restore` | Restauration d'un menu | `MENU_UPDATE` |
| `GET` | `/api/v1/sous-menus` | Liste des sous-menus | `SOUS_MENU_READ` |
| `POST` | `/api/v1/sous-menus` | Création d'un sous-menu | `SOUS_MENU_CREATE` |
| `GET` | `/api/v1/sous-menus/{id}` | Détail d'un sous-menu | `SOUS_MENU_READ` |
| `PUT/PATCH` | `/api/v1/sous-menus/{id}` | Mise à jour d'un sous-menu | `SOUS_MENU_UPDATE` |
| `DELETE` | `/api/v1/sous-menus/{id}` | Suppression d'un sous-menu | `SOUS_MENU_DELETE` |
| `POST` | `/api/v1/sous-menus/{id}/restore` | Restauration d'un sous-menu | `SOUS_MENU_UPDATE` |

### 9. Permissions & Rôles
| Méthode | Endpoint | Description | Permission |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/permissions` | Liste des permissions globales | `PERMISSION_READ` |
| `POST` | `/api/v1/permissions` | Création d'une permission | `PERMISSION_CREATE` |
| `GET` | `/api/v1/permissions/{id}` | Détail d'une permission | `PERMISSION_READ` |
| `PUT/PATCH` | `/api/v1/permissions/{id}` | Mise à jour d'une permission | `PERMISSION_UPDATE` |
| `DELETE` | `/api/v1/permissions/{id}` | Suppression d'une permission | `PERMISSION_DELETE` |
| `GET` | `/api/v1/roles` | Liste des rôles du site | `ROLE_READ` |
| `POST` | `/api/v1/roles` | Création d'un rôle | `ROLE_CREATE` |
| `GET` | `/api/v1/roles/{id}` | Détail d'un rôle | `ROLE_READ` |
| `PUT/PATCH` | `/api/v1/roles/{id}` | Mise à jour d'un rôle | `ROLE_UPDATE` |
| `DELETE` | `/api/v1/roles/{id}` | Suppression d'un rôle | `ROLE_DELETE` |
| `POST` | `/api/v1/roles/{id}/restore` | Restauration d'un rôle | `ROLE_UPDATE` |
| `PUT` | `/api/v1/roles/{id}/permissions` | Synchronisation des permissions d'un rôle | `ROLE_UPDATE` |

### 10. Utilisateurs Administratifs
| Méthode | Endpoint | Description | Permission |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/utilisateurs` | Liste des utilisateurs administratifs | `UTILISATEUR_READ` |
| `POST` | `/api/v1/utilisateurs` | Création d'un compte administratif | `UTILISATEUR_CREATE` |
| `GET` | `/api/v1/utilisateurs/{id}` | Détail d'un utilisateur | `UTILISATEUR_READ` |
| `PUT/PATCH` | `/api/v1/utilisateurs/{id}` | Mise à jour d'un compte | `UTILISATEUR_UPDATE` |
| `DELETE` | `/api/v1/utilisateurs/{id}` | Désactivation / suppression logique | `UTILISATEUR_DELETE` |
| `POST` | `/api/v1/utilisateurs/{id}/restore` | Réactivation d'un compte | `UTILISATEUR_UPDATE` |

### 11. Acquisitions Documentaires
| Méthode | Endpoint | Description | Permission |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/acquisitions` | Liste des acquisitions documentaires | `ACQUISITION_READ` |
| `POST` | `/api/v1/acquisitions` | Enregistrement d'une acquisition | `ACQUISITION_CREATE` |
| `GET` | `/api/v1/acquisitions/{id}` | Détail d'une acquisition | `ACQUISITION_READ` |
| `PUT/PATCH` | `/api/v1/acquisitions/{id}` | Modification d'une acquisition | `ACQUISITION_UPDATE` |
| `DELETE` | `/api/v1/acquisitions/{id}` | Suppression logique | `ACQUISITION_DELETE` |
| `POST` | `/api/v1/acquisitions/{id}/restore` | Restauration d'une acquisition | `ACQUISITION_UPDATE` |

### 12. Livres & Exemplaires
| Méthode | Endpoint | Description | Permission |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/livres` | Catalogue des livres | `LIVRE_READ` |
| `POST` | `/api/v1/livres` | Création d'une notice bibliographique | `LIVRE_CREATE` |
| `GET` | `/api/v1/livres/{id}` | Fiche détaillée d'un livre | `LIVRE_READ` |
| `PUT/PATCH` | `/api/v1/livres/{id}` | Modification d'une notice | `LIVRE_UPDATE` |
| `DELETE` | `/api/v1/livres/{id}` | Suppression logique | `LIVRE_DELETE` |
| `POST` | `/api/v1/livres/{id}/restore` | Restauration d'un livre | `LIVRE_UPDATE` |
| `POST` | `/api/v1/livres/{id}/exemplaires` | Création groupée d'exemplaires pour un livre | `EXEMPLAIRE_CREATE` |
| `GET` | `/api/v1/exemplaires` | Inventaire des exemplaires | `EXEMPLAIRE_READ` |
| `POST` | `/api/v1/exemplaires` | Création d'un exemplaire individuel | `EXEMPLAIRE_CREATE` |
| `POST` | `/api/v1/exemplaires/bulk` | Génération en lot d'exemplaires | `EXEMPLAIRE_CREATE` |
| `GET` | `/api/v1/exemplaires/{id}` | Fiche détaillée d'un exemplaire | `EXEMPLAIRE_READ` |
| `PUT/PATCH` | `/api/v1/exemplaires/{id}` | Mise à jour (statut, état, code) | `EXEMPLAIRE_UPDATE` |
| `DELETE` | `/api/v1/exemplaires/{id}` | Retrait d'un exemplaire (soft delete) | `EXEMPLAIRE_DELETE` |
| `POST` | `/api/v1/exemplaires/{id}/restore` | Restauration d'un exemplaire | `EXEMPLAIRE_UPDATE` |

### 13. Usagers & UsagerSites
| Méthode | Endpoint | Description | Permission |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/usagers` | Registre global des usagers | `USAGER_READ` |
| `POST` | `/api/v1/usagers` | Inscription d'un usager | `USAGER_CREATE` |
| `GET` | `/api/v1/usagers/{id}` | Fiche détaillée d'un usager | `USAGER_READ` |
| `PUT/PATCH` | `/api/v1/usagers/{id}` | Mise à jour usager | `USAGER_UPDATE` |
| `DELETE` | `/api/v1/usagers/{id}` | Suppression logique | `USAGER_DELETE` |
| `POST` | `/api/v1/usagers/{id}/restore` | Restauration d'un usager | `USAGER_UPDATE` |
| `GET` | `/api/v1/usager-sites` | Affiliations usagers du site | `USAGERSITE_READ` |
| `POST` | `/api/v1/usager-sites` | Affiliation d'un usager au site | `USAGERSITE_CREATE` |
| `GET` | `/api/v1/usager-sites/{id}` | Détail d'une affiliation | `USAGERSITE_READ` |
| `PUT/PATCH` | `/api/v1/usager-sites/{id}` | Modification adhésion/statut | `USAGERSITE_UPDATE` |
| `DELETE` | `/api/v1/usager-sites/{id}` | Résiliation / suppression adhésion | `USAGERSITE_DELETE` |
| `POST` | `/api/v1/usager-sites/{id}/restore` | Restauration de l'adhésion | `USAGERSITE_UPDATE` |

### 14. Présences
| Méthode | Endpoint | Description | Permission |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/presences` | Journal des présences | `PRESENCE_READ` |
| `POST` | `/api/v1/presences` | Enregistrement d'une entrée | `PRESENCE_CREATE` |
| `GET` | `/api/v1/presences/{id}` | Détail d'une présence | `PRESENCE_READ` |
| `PUT/PATCH` | `/api/v1/presences/{id}` | Enregistrement sortie / mise à jour | `PRESENCE_UPDATE` |
| `DELETE` | `/api/v1/presences/{id}` | Annulation d'une présence | `PRESENCE_DELETE` |
| `POST` | `/api/v1/presences/{id}/restore` | Restauration | `PRESENCE_UPDATE` |

### 15. Opérations (Emprunts & Consultations)
| Méthode | Endpoint | Description | Permission |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/operations` | Liste des opérations (emprunts / consultations) | `OPERATION_READ` |
| `POST` | `/api/v1/operations` | Création d'un emprunt ou d'une consultation | `OPERATION_CREATE` |
| `POST` | `/api/v1/operations/{id}/retour` | Clôture d'un emprunt / retour de document | `OPERATION_UPDATE` |
| `GET` | `/api/v1/operations/{id}` | Détail d'une opération | `OPERATION_READ` |
| `PUT/PATCH` | `/api/v1/operations/{id}` | Modification d'une opération | `OPERATION_UPDATE` |
| `DELETE` | `/api/v1/operations/{id}` | Suppression logique | `OPERATION_DELETE` |
| `POST` | `/api/v1/operations/{id}/restore` | Restauration d'une opération | `OPERATION_UPDATE` |

### 16. Réservations
| Méthode | Endpoint | Description | Permission |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/reservations` | Liste des réservations | `RESERVATION_READ` |
| `POST` | `/api/v1/reservations` | Enregistrement d'une réservation | `RESERVATION_CREATE` |
| `POST` | `/api/v1/reservations/{id}/annuler` | Annulation manuelle d'une réservation | `RESERVATION_UPDATE` |
| `GET` | `/api/v1/reservations/{id}` | Détail d'une réservation | `RESERVATION_READ` |
| `PUT/PATCH` | `/api/v1/reservations/{id}` | Modification d'une réservation | `RESERVATION_UPDATE` |
| `DELETE` | `/api/v1/reservations/{id}` | Suppression logique | `RESERVATION_DELETE` |
| `POST` | `/api/v1/reservations/{id}/restore` | Restauration d'une réservation | `RESERVATION_UPDATE` |

### 17. Notifications
| Méthode | Endpoint | Description | Permission |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/notifications` | Liste des notifications envoyées | `NOTIFICATION_READ` |
| `POST` | `/api/v1/notifications` | Émission d'une notification | `NOTIFICATION_CREATE` |
| `GET` | `/api/v1/notifications/{id}` | Détail d'une notification | `NOTIFICATION_READ` |
| `DELETE` | `/api/v1/notifications/{id}` | Suppression logique | `NOTIFICATION_DELETE` |
| `POST` | `/api/v1/notifications/{id}/restore` | Restauration d'une notification | `NOTIFICATION_DELETE` |

### 18. Dashboard & Statistiques Dynamiques (Stage 8)
| Méthode | Endpoint | Description | Permission |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/dashboard` | Métriques globales et agrégées dynamiques par période | `DASHBOARD_READ` |

Paramètres optionnels :
* `date_debut` (format `Y-m-d`, <= `date_fin`)
* `date_fin` (format `Y-m-d`, >= `date_debut`)

### 19. Journal d'Activités (Stage 8 - Immutable)
| Méthode | Endpoint | Description | Permission |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/journal-activites` | Consultation paginée et filtrée des logs d'audit | `JOURNAL_READ` |
| `GET` | `/api/v1/journal-activites/{id}` | Détail d'un log d'activité spécifique | `JOURNAL_READ` |

Filtres disponibles : `search`, `utilisateur_id`, `module`, `action`, `date_debut`, `date_fin`, `per_page`.  
*Note : Le journal est strictement immutable. Aucun endpoint PUT, PATCH ou DELETE n'existe.*

### 20. Sauvegardes (Stage 8)
| Méthode | Endpoint | Description | Permission |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/sauvegardes` | Inventaire des sauvegardes du tenant | `SAUVEGARDE_READ` |
| `GET` | `/api/v1/sauvegardes/{id}` | Détail d'une sauvegarde | `SAUVEGARDE_READ` |
| `DELETE` | `/api/v1/sauvegardes/{id}` | Suppression logique d'une sauvegarde | `SAUVEGARDE_DELETE` |
| `POST` | `/api/v1/sauvegardes/{id}/restore` | Restauration d'une sauvegarde supprimée | `SAUVEGARDE_DELETE` |

---

# 35. Format JSON Standardisé & Traitement d'Erreurs

### Réponse Succès Standard (200 / 201)
```json
{
    "success": true,
    "message": "Opération exécutée avec succès.",
    "data": { ... }
}
```

### Réponse Liste Paginée (200)
```json
{
    "success": true,
    "message": "Liste récupérée avec succès.",
    "data": [ ... ],
    "links": {
        "first": "...",
        "last": "...",
        "prev": null,
        "next": null
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 1,
        "per_page": 15,
        "to": 10,
        "total": 10
    }
}
```

### Réponse Erreur de Validation (422)
```json
{
    "success": false,
    "message": "Données non valides.",
    "errors": {
        "date_fin": [
            "La date de fin doit être une date postérieure ou égale à la date de début."
        ]
    }
}
```

### Réponse Erreur d'Autorisation (403)
```json
{
    "success": false,
    "message": "Accès refusé. Permission requise : DASHBOARD_READ"
}
```

### Réponse Erreur Ressource Inexistante ou IDOR (404)
```json
{
    "success": false,
    "message": "Ressource introuvable pour ce site."
}
```

