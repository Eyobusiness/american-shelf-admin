<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('livres', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')
                ->constrained('sites')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('code', 50); // ex: LIT-ROM-0001
            $table->string('titre', 255);
            $table->string('auteur', 255);
            $table->string('editeur', 255)->nullable();
            $table->integer('annee_publication')->nullable();
            $table->string('isbn', 50)->nullable();
            $table->foreignUuid('categorie_livre_id')
                ->constrained('categories_livres')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('image_principale', 255)->nullable();
            $table->string('fichier_numerique', 255)->nullable();
            $table->boolean('visible_en_ligne')->default(false);
            $table->string('statut', 50)->default('ACTIF');
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();

            $table->unique(['site_id', 'code']);
            $table->index(['site_id', 'categorie_livre_id', 'statut']);
            $table->index('isbn');
            $table->index(['titre', 'auteur']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('livres');
    }
};
