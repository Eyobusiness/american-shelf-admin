<?php

namespace Tests\Feature;

use App\Models\CategorieLivre;
use App\Models\Exemplaire;
use App\Models\Livre;
use App\Models\Site;
use App\Models\Usager;
use App\Models\UsagerSite;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use DatabaseTransactions;
    /**
     * Test that all 21 domain tables exist in the database.
     */
    public function test_all_21_tables_exist_in_database(): void
    {
        $expectedTables = [
            'sites',
            'usagers',
            'categories_usagers',
            'categories_livres',
            'configurations',
            'roles',
            'menus',
            'sous_menus',
            'permissions',
            'role_permissions',
            'utilisateurs',
            'acquisitions',
            'livres',
            'exemplaires',
            'usager_sites',
            'presences',
            'operations',
            'reservations',
            'notifications',
            'journaux_activites',
            'sauvegardes',
        ];

        foreach ($expectedTables as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "La table attendue '{$table}' n'existe pas dans la base de données."
            );
        }
    }

    /**
     * Test Site -> Livre -> Exemplaires relationship.
     */
    public function test_site_livre_and_exemplaires_relationship(): void
    {
        $site = Site::create([
            'code' => 'SITE-TEST-01',
            'nom' => 'Site Test Abobo',
            'statut' => 'ACTIF',
        ]);

        $categorie = CategorieLivre::create([
            'site_id' => $site->id,
            'code' => 'LIT',
            'nom' => 'Littérature',
            'statut' => 'ACTIF',
        ]);

        $livre = Livre::create([
            'site_id' => $site->id,
            'code' => 'LIT-ROM-0001',
            'titre' => 'L\'Enfant Noir',
            'auteur' => 'Camara Laye',
            'categorie_livre_id' => $categorie->id,
            'statut' => 'ACTIF',
        ]);

        $exemplaire1 = Exemplaire::create([
            'site_id' => $site->id,
            'livre_id' => $livre->id,
            'code' => 'LIT-ROM-0001-001',
            'statut' => 'DISPONIBLE',
        ]);

        $exemplaire2 = Exemplaire::create([
            'site_id' => $site->id,
            'livre_id' => $livre->id,
            'code' => 'LIT-ROM-0001-002',
            'statut' => 'DISPONIBLE',
        ]);

        $this->assertEquals(2, $livre->exemplaires()->count());
        $this->assertEquals('L\'Enfant Noir', $exemplaire1->livre->titre);
        $this->assertEquals('Site Test Abobo', $livre->site->nom);
    }

    /**
     * Test global Usager associated with multiple Sites via UsagerSite.
     */
    public function test_usager_can_be_associated_with_multiple_sites(): void
    {
        $siteA = Site::create(['code' => 'SITE-A', 'nom' => 'Site A', 'statut' => 'ACTIF']);
        $siteB = Site::create(['code' => 'SITE-B', 'nom' => 'Site B', 'statut' => 'ACTIF']);

        $usager = Usager::create([
            'numero' => 'USG-GLOBAL-001',
            'nom' => 'Kouassi',
            'prenom' => 'Jean',
            'email' => 'jean.kouassi@example.com',
        ]);

        UsagerSite::create([
            'site_id' => $siteA->id,
            'usager_id' => $usager->id,
            'date_inscription' => now()->toDateString(),
            'type' => 'ADHERENT',
            'statut' => 'ACTIF',
        ]);

        UsagerSite::create([
            'site_id' => $siteB->id,
            'usager_id' => $usager->id,
            'date_inscription' => now()->toDateString(),
            'type' => 'USAGER',
            'statut' => 'ACTIF',
        ]);

        $this->assertEquals(2, $usager->sites()->count());
        $this->assertFalse(Schema::hasColumn('usagers', 'site_id'), "usagers ne doit PAS contenir site_id.");
    }

    /**
     * Test code uniqueness within a tenant site.
     */
    public function test_code_uniqueness_within_a_site(): void
    {
        $site = Site::create(['code' => 'SITE-UNIQ', 'nom' => 'Site Uniq', 'statut' => 'ACTIF']);
        $categorie = CategorieLivre::create([
            'site_id' => $site->id,
            'code' => 'CAT-UNIQ',
            'nom' => 'Catégorie Unique',
        ]);

        Livre::create([
            'site_id' => $site->id,
            'code' => 'BOOK-DUPLICATE',
            'titre' => 'Livre 1',
            'auteur' => 'Auteur 1',
            'categorie_livre_id' => $categorie->id,
        ]);

        $this->expectException(QueryException::class);

        // Doit échouer en raison de la contrainte UNIQUE(site_id, code)
        Livre::create([
            'site_id' => $site->id,
            'code' => 'BOOK-DUPLICATE',
            'titre' => 'Livre 2',
            'auteur' => 'Auteur 2',
            'categorie_livre_id' => $categorie->id,
        ]);
    }

    /**
     * Test Soft Deletes functionality.
     */
    public function test_soft_deletes_are_functioning(): void
    {
        $site = Site::create(['code' => 'SITE-SD', 'nom' => 'Site Soft Delete']);

        $site->delete();

        $this->assertSoftDeleted('sites', ['id' => $site->id]);
        $this->assertNotNull(Site::withTrashed()->find($site->id));
        $this->assertNull(Site::find($site->id));

        $site->restore();
        $this->assertNotNull(Site::find($site->id));
    }

    /**
     * Test foreign key constraint integrity.
     */
    public function test_foreign_key_restricts_invalid_reference(): void
    {
        $this->expectException(QueryException::class);

        // Ne doit pas permettre la création d'un livre avec un site_id inexistant
        Livre::create([
            'site_id' => '00000000-0000-0000-0000-000000000000',
            'code' => 'LIVRE-ORPHELIN',
            'titre' => 'Orphelin',
            'auteur' => 'Inconnu',
            'categorie_livre_id' => '00000000-0000-0000-0000-000000000001',
        ]);
    }

    /**
     * Test that books table does not contain physical copy attributes or acquisition_id.
     */
    public function test_books_table_structure_separation(): void
    {
        $this->assertFalse(Schema::hasColumn('livres', 'nombre_exemplaires'));
        $this->assertFalse(Schema::hasColumn('livres', 'localisation'));
        $this->assertFalse(Schema::hasColumn('livres', 'etat'));
        $this->assertFalse(Schema::hasColumn('livres', 'acquisition_id'));
        $this->assertFalse(Schema::hasColumn('exemplaires', 'nombre'));
    }
}
