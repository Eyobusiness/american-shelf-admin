/**
 * =============================================================================
 * AMERICAN SHELF ABOBO — TYPESCRIPT DTOs & MODELS
 * =============================================================================
 * Fichier de définitions TypeScript pour l'application frontend Angular.
 * Généré pour le backend Laravel 12 multi-tenant (/api/v1).
 * 
 * Contient :
 * - Interfaces de réponses API standardisées (ApiResponse, PaginatedResponse)
 * - Modèles de domaine complets (avec Identifiants UUID v4)
 * - DTOs de requêtes (Create, Update, Filters)
 * - Énumérations des états métier
 * =============================================================================
 */

// =============================================================================
// 1. STRUCTURES DE RÉPONSES API GÉNÉRIQUES
// =============================================================================

export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export interface PaginationLinks {
  first: string | null;
  last: string | null;
  prev: string | null;
  next: string | null;
}

export interface PaginationMeta {
  current_page: number;
  from: number | null;
  last_page: number;
  per_page: number;
  to: number | null;
  total: number;
  path?: string;
}

export interface PaginatedResponse<T> {
  success: boolean;
  message: string;
  data: T[];
  links?: PaginationLinks;
  meta: PaginationMeta;
}

export interface ApiErrorResponse {
  success: false;
  message: string;
  errors?: Record<string, string[]>;
}

// =============================================================================
// 2. ÉNUMÉRATIONS ET TYPES MÉTIER
// =============================================================================

export type EntityStatus = 'ACTIF' | 'INACTIF';

export type RoleCode = 'ADMINISTRATEUR' | 'BIBLIOTHECAIRE' | 'GESTIONNAIRE' | string;

export type AcquisitionType = 'ACHAT' | 'DON';
export type AcquisitionEtat = 'COMMANDE' | 'RECU' | 'TRAITE' | 'ANNULE';

export type ExemplaireStatut = 'DISPONIBLE' | 'EMPRUNTE' | 'EN_CONSULTATION' | 'INDISPONIBLE' | 'PERDU';
export type ExemplaireEtat = 'NEUF' | 'BON' | 'MOYEN' | 'MAUVAIS' | 'DEGRADE';

export type UsagerType = 'USAGER' | 'ADHERENT';
export type AdhesionStatut = 'ACTIF' | 'SUSPENDU' | 'EXPIRE';

export type OperationType = 'EMPRUNT' | 'CONSULTATION';
export type OperationStatut = 'EN_COURS' | 'RETOURNE' | 'EN_RETARD' | 'PERDU';

export type ReservationStatut = 'EN_ATTENTE' | 'ACTIVE' | 'EXPIREE' | 'ANNULEE' | 'HONOREE';

export type NotificationType = 'RETARD' | 'RAPPEL' | 'DISPONIBILITE' | 'EXPIRATION_ADHESION' | 'AUTRE';
export type NotificationStatut = 'EN_ATTENTE' | 'ENVOYE' | 'ECHEC' | 'LU';
export type NotificationCanal = 'DATABASE' | 'EMAIL' | 'SMS';

export type SauvegardeStatut = 'REUSSIE' | 'EN_COURS' | 'ECHEC';

// =============================================================================
// 3. AUTHENTIFICATION (ADMINISTRATION & USAGER)
// =============================================================================

export interface LoginRequest {
  email: string;
  mot_de_passe: string;
}

export interface AuthResponse {
  access_token: string;
  token_type: 'bearer';
  expires_in: number;
  user: Utilisateur | Usager;
}

export interface AdminProfile extends Utilisateur {}
export interface UsagerProfile extends Usager {}

// =============================================================================
// 4. SITES (TENANTS)
// =============================================================================

export interface Site {
  id: string;
  code: string;
  nom: string;
  adresse?: string | null;
  telephone?: string | null;
  email?: string | null;
  logo?: string | null;
  logo_url?: string | null;
  statut: EntityStatus;
  created_at: string;
  updated_at: string;
  deleted_at?: string | null;
}

export interface SiteCreateDto {
  code: string;
  nom: string;
  adresse?: string;
  telephone?: string;
  email?: string;
  logo?: string;
  statut?: EntityStatus;
}

export interface SiteUpdateDto extends Partial<SiteCreateDto> {}

// =============================================================================
// 5. CATÉGORIES (USAGERS & LIVRES)
// =============================================================================

export interface CategorieUsager {
  id: string;
  site_id: string;
  code: string;
  libelle: string;
  description?: string | null;
  statut: EntityStatus;
  created_at: string;
  updated_at: string;
}

export interface CategorieUsagerCreateDto {
  code: string;
  libelle: string;
  description?: string;
  statut?: EntityStatus;
}

export interface CategorieUsagerUpdateDto extends Partial<CategorieUsagerCreateDto> {}

export interface CategorieLivre {
  id: string;
  site_id: string;
  code: string;
  libelle: string;
  description?: string | null;
  statut: EntityStatus;
  created_at: string;
  updated_at: string;
}

export interface CategorieLivreCreateDto {
  code: string;
  libelle: string;
  description?: string;
  statut?: EntityStatus;
}

export interface CategorieLivreUpdateDto extends Partial<CategorieLivreCreateDto> {}

// =============================================================================
// 6. CONFIGURATION DU SITE
// =============================================================================

export interface Configuration {
  id: string;
  site_id: string;
  duree_emprunt_jours: number;
  nombre_max_emprunts: number;
  duree_retard_tolerance_jours: number;
  nombre_max_reservations: number;
  duree_validite_reservation_jours: number;
  montant_cotisation_annuelle: number;
  penalite_par_jour_retard: number;
  created_at: string;
  updated_at: string;
}

export interface ConfigurationSaveDto {
  duree_emprunt_jours: number;
  nombre_max_emprunts: number;
  duree_retard_tolerance_jours?: number;
  nombre_max_reservations: number;
  duree_validite_reservation_jours: number;
  montant_cotisation_annuelle?: number;
  penalite_par_jour_retard?: number;
}

// =============================================================================
// 7. MENUS & SOUS-MENUS DYNAMIQUES
// =============================================================================

export interface SousMenu {
  id: string;
  menu_id: string;
  site_id: string;
  code: string;
  libelle: string;
  icon?: string | null;
  path: string;
  order: number;
  reference?: string | null;
  permission?: string | null;
  statut: EntityStatus;
  created_at: string;
  updated_at: string;
}

export interface Menu {
  id: string;
  code: string;
  libelle: string;
  icon?: string | null;
  path: string;
  order: number;
  reference?: string | null;
  permission?: string | null;
  statut: EntityStatus;
  sous_menus?: SousMenu[];
  sousMenus?: SousMenu[];
  created_at: string;
  updated_at: string;
}

export interface MenuCreateDto {
  code: string;
  libelle: string;
  icon?: string;
  path?: string;
  order?: number;
  reference?: string;
  permission?: string;
  statut?: EntityStatus;
}

export interface MenuUpdateDto extends Partial<MenuCreateDto> {}

export interface SousMenuCreateDto {
  menu_id: string;
  code: string;
  libelle: string;
  icon?: string;
  path?: string;
  order?: number;
  reference?: string;
  permission?: string;
  statut?: EntityStatus;
}

export interface SousMenuUpdateDto extends Partial<SousMenuCreateDto> {}

// =============================================================================
// 8. PERMISSIONS & RÔLES
// =============================================================================

export interface Permission {
  id: string;
  code: string;
  libelle: string;
  module: string;
  action: string;
  description?: string | null;
  created_at: string;
  updated_at: string;
}

export interface PermissionCreateDto {
  code: string;
  libelle: string;
  module: string;
  action: string;
  description?: string;
}

export interface PermissionUpdateDto extends Partial<PermissionCreateDto> {}

export interface Role {
  id: string;
  site_id: string;
  code: string;
  libelle: string;
  description?: string | null;
  statut: EntityStatus;
  permissions?: Permission[];
  created_at: string;
  updated_at: string;
}

export interface RoleCreateDto {
  code: string;
  libelle: string;
  description?: string;
  statut?: EntityStatus;
  permission_ids?: string[];
}

export interface RoleUpdateDto extends Partial<RoleCreateDto> {}

export interface RoleSyncPermissionsDto {
  permissions: string[];
}

// =============================================================================
// 9. UTILISATEURS ADMINISTRATIFS
// =============================================================================

export interface Utilisateur {
  id: string;
  site_id: string;
  nom: string;
  prenom: string;
  email: string;
  role_id: string;
  role?: Role | null;
  statut: EntityStatus;
  derniere_connexion?: string | null;
  created_at: string;
  updated_at: string;
}

export interface UtilisateurCreateDto {
  nom: string;
  prenom: string;
  email: string;
  mot_de_passe: string;
  mot_de_passe_confirmation?: string;
  role_id: string;
  statut?: EntityStatus;
}

export interface UtilisateurUpdateDto {
  nom?: string;
  prenom?: string;
  email?: string;
  mot_de_passe?: string;
  mot_de_passe_confirmation?: string;
  role_id?: string;
  statut?: EntityStatus;
}

// =============================================================================
// 10. ACQUISITIONS DOCUMENTAIRES
// =============================================================================

export interface Acquisition {
  id: string;
  site_id: string;
  fournisseur_id?: string | null;
  reference_facture?: string | null;
  type: AcquisitionType;
  provenance?: string | null;
  cout: number;
  quantite: number;
  date_acquisition: string;
  donateur?: string | null;
  etat: AcquisitionEtat;
  observations?: string | null;
  created_at: string;
  updated_at: string;
}

export interface AcquisitionCreateDto {
  type: AcquisitionType;
  fournisseur_id?: string;
  reference_facture?: string;
  provenance?: string;
  cout: number;
  quantite: number;
  date_acquisition: string;
  donateur?: string;
  etat?: AcquisitionEtat;
  observations?: string;
}

export interface AcquisitionUpdateDto extends Partial<AcquisitionCreateDto> {}

// =============================================================================
// 11. LIVRES & EXEMPLAIRES
// =============================================================================

export interface Livre {
  id: string;
  site_id: string;
  categorie_livre_id: string;
  categorie?: CategorieLivre | null;
  titre: string;
  isbn?: string | null;
  editeur?: string | null;
  annee_publication?: number | null;
  auteur: string;
  langue?: string;
  resume?: string | null;
  nombre_pages?: number | null;
  image_couverture?: string | null;
  statut: EntityStatus;
  nombre_exemplaires?: number;
  exemplaires_disponibles_count?: number;
  exemplaires?: Exemplaire[];
  created_at: string;
  updated_at: string;
}

export interface LivreCreateDto {
  categorie_livre_id: string;
  titre: string;
  isbn?: string;
  editeur?: string;
  annee_publication?: number;
  auteur: string;
  langue?: string;
  resume?: string;
  nombre_pages?: number;
  image_couverture?: string;
  statut?: EntityStatus;
}

export interface LivreUpdateDto extends Partial<LivreCreateDto> {}

export interface Exemplaire {
  id: string;
  site_id: string;
  livre_id: string;
  livre?: Livre | null;
  code: string;
  statut: ExemplaireStatut;
  etat: ExemplaireEtat;
  emplacement?: string | null;
  created_at: string;
  updated_at: string;
}

export interface ExemplaireCreateDto {
  livre_id: string;
  code: string;
  statut?: ExemplaireStatut;
  etat?: ExemplaireEtat;
  emplacement?: string;
}

export interface ExemplaireUpdateDto {
  code?: string;
  statut?: ExemplaireStatut;
  etat?: ExemplaireEtat;
  emplacement?: string;
}

export interface ExemplaireBulkCreateDto {
  livre_id?: string;
  quantite: number;
  prefixe?: string;
  etat?: ExemplaireEtat;
  emplacement?: string;
}

// =============================================================================
// 12. USAGERS & USAGER_SITES
// =============================================================================

export interface Usager {
  id: string;
  nom: string;
  prenom: string;
  email: string;
  telephone?: string | null;
  adresse?: string | null;
  date_naissance?: string | null;
  photo?: string | null;
  sites?: UsagerSite[];
  created_at: string;
  updated_at: string;
}

export interface UsagerCreateDto {
  nom: string;
  prenom: string;
  email: string;
  mot_de_passe?: string;
  telephone?: string;
  adresse?: string;
  date_naissance?: string;
  photo?: string;
  // Rattachement immédiat au site courant
  categorie_usager_id: string;
  type?: UsagerType;
  date_inscription?: string;
  date_expiration?: string;
  remarques?: string;
}

export interface UsagerUpdateDto {
  nom?: string;
  prenom?: string;
  email?: string;
  telephone?: string;
  adresse?: string;
  date_naissance?: string;
  photo?: string;
}

export interface UsagerSite {
  id: string;
  usager_id: string;
  usager?: Usager | null;
  site_id: string;
  site?: Site | null;
  categorie_usager_id: string;
  categorie?: CategorieUsager | null;
  numero_adherent: string;
  type: UsagerType;
  date_inscription: string;
  date_expiration?: string | null;
  statut: AdhesionStatut;
  remarques?: string | null;
  created_at: string;
  updated_at: string;
}

export interface UsagerSiteCreateDto {
  usager_id: string;
  categorie_usager_id: string;
  type?: UsagerType;
  date_inscription?: string;
  date_expiration?: string;
  statut?: AdhesionStatut;
  remarques?: string;
}

export interface UsagerSiteUpdateDto {
  categorie_usager_id?: string;
  type?: UsagerType;
  date_inscription?: string;
  date_expiration?: string;
  statut?: AdhesionStatut;
  remarques?: string;
}

// =============================================================================
// 13. PRÉSENCES
// =============================================================================

export interface Presence {
  id: string;
  site_id: string;
  usager_id?: string | null;
  usager?: Usager | null;
  date_presence: string;
  heure_arrivee: string;
  heure_depart?: string | null;
  motif?: string | null;
  observations?: string | null;
  created_at: string;
  updated_at: string;
}

export interface PresenceCreateDto {
  usager_id?: string;
  date_presence?: string;
  heure_arrivee?: string;
  heure_depart?: string;
  motif?: string;
  observations?: string;
}

export interface PresenceUpdateDto {
  heure_depart?: string;
  motif?: string;
  observations?: string;
}

// =============================================================================
// 14. OPÉRATIONS (EMPRUNTS & CONSULTATIONS)
// =============================================================================

export interface Operation {
  id: string;
  site_id: string;
  configuration_id: string;
  usager_id: string;
  usager?: Usager | null;
  exemplaire_id: string;
  exemplaire?: Exemplaire | null;
  type: OperationType;
  statut: OperationStatut;
  date_operation: string;
  date_retour_prevue?: string | null;
  date_retour_effective?: string | null;
  etat_retour?: ExemplaireEtat | null;
  jours_retard?: number;
  montant_penalite?: number;
  penalite_payee?: boolean;
  observations?: string | null;
  created_at: string;
  updated_at: string;
}

export interface OperationCreateDto {
  usager_id: string;
  exemplaire_id: string;
  type: OperationType;
  date_operation?: string;
  date_retour_prevue?: string;
  observations?: string;
}

export interface OperationRetourDto {
  date_retour_effective?: string;
  etat_retour: ExemplaireEtat;
  observations?: string;
  penalite_payee?: boolean;
}

export interface OperationUpdateDto {
  date_retour_prevue?: string;
  observations?: string;
  statut?: OperationStatut;
  penalite_payee?: boolean;
}

// =============================================================================
// 15. RÉSERVATIONS
// =============================================================================

export interface Reservation {
  id: string;
  site_id: string;
  configuration_id: string;
  usager_id: string;
  usager?: Usager | null;
  livre_id: string;
  livre?: Livre | null;
  statut: ReservationStatut;
  date_reservation: string;
  date_expiration: string;
  date_annulation?: string | null;
  motif_annulation?: string | null;
  created_at: string;
  updated_at: string;
}

export interface ReservationCreateDto {
  usager_id: string;
  livre_id: string;
  date_reservation?: string;
  date_expiration?: string;
}

export interface ReservationAnnulerDto {
  motif_annulation?: string;
}

export interface ReservationUpdateDto {
  statut?: ReservationStatut;
  date_expiration?: string;
  motif_annulation?: string;
}

// =============================================================================
// 16. NOTIFICATIONS
// =============================================================================

export interface Notification {
  id: string;
  site_id: string;
  usager_id: string;
  usager?: Usager | null;
  type: NotificationType;
  statut: NotificationStatut;
  canal: NotificationCanal;
  titre: string;
  message: string;
  date_envoi?: string | null;
  date_lecture?: string | null;
  created_at: string;
  updated_at: string;
}

export interface NotificationCreateDto {
  usager_id: string;
  type: NotificationType;
  canal?: NotificationCanal;
  titre: string;
  message: string;
}

// =============================================================================
// 17. DASHBOARD & STATISTIQUES DYNAMIQUES
// =============================================================================

export interface DashboardFilterParams {
  date_debut?: string; // Format YYYY-MM-DD
  date_fin?: string;   // Format YYYY-MM-DD
}

export interface DocumentsStats {
  total_livres: number;
  total_exemplaires: number;
  exemplaires_disponibles: number;
  exemplaires_empruntes: number;
  exemplaires_en_consultation: number;
  exemplaires_indisponibles: number;
}

export interface UsagersStats {
  total_usagers: number;
  adhesions_actives: number;
  adhesions_suspendues: number;
  adhesions_expirees: number;
  nouveaux_usagers_periode: number;
}

export interface ActivitesStats {
  emprunts: {
    du_jour: number;
    de_la_periode: number;
    en_cours: number;
    retournes: number;
    en_retard: number;
  };
  consultations: {
    du_jour: number;
    de_la_periode: number;
    en_cours: number;
    terminees: number;
  };
  reservations: {
    actives: number;
    en_attente: number;
    expirees: number;
    annulees: number;
    honorees: number;
  };
  presences: {
    total_periode: number;
  };
}

export interface TopLivreStat {
  livre_id: string;
  titre: string;
  auteur: string;
  isbn?: string | null;
  nombre_emprunts: number;
}

export interface CategoriesStats {
  livres_par_categorie: Array<{
    categorie_id: string;
    libelle: string;
    nombre_livres: number;
  }>;
  emprunts_par_categorie: Array<{
    categorie_id: string;
    libelle: string;
    nombre_emprunts: number;
  }>;
}

export interface DashboardMetrics {
  periode: {
    date_debut: string;
    date_fin: string;
  };
  documents: DocumentsStats;
  usagers: UsagersStats;
  activites: ActivitesStats;
  top_livres: TopLivreStat[];
  categories: CategoriesStats;
}

// =============================================================================
// 18. JOURNAL D'ACTIVITÉS (IMMUTABLE)
// =============================================================================

export interface JournalActivite {
  id: string;
  site_id: string;
  utilisateur_id?: string | null;
  utilisateur?: Utilisateur | null;
  action: string;
  module: string;
  reference?: string | null;
  description?: string | null;
  adresse_ip?: string | null;
  user_agent?: string | null;
  created_at: string;
}

export interface JournalFilterParams {
  search?: string;
  utilisateur_id?: string;
  module?: string;
  action?: string;
  date_debut?: string;
  date_fin?: string;
  per_page?: number;
  page?: number;
}

// =============================================================================
// 19. SAUVEGARDES
// =============================================================================

export interface Sauvegarde {
  id: string;
  site_id: string;
  nom_fichier: string;
  chemin_fichier: string;
  taille_octets: number;
  taille_lisible: string;
  statut: SauvegardeStatut;
  type: string;
  description?: string | null;
  date_sauvegarde: string;
  created_at: string;
  updated_at: string;
}

export interface SauvegardeFilterParams {
  statut?: SauvegardeStatut;
  type?: string;
  per_page?: number;
  page?: number;
}
