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
        Schema::create('usager_sites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')
                ->constrained('sites')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignUuid('usager_id')
                ->constrained('usagers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('numero', 50)->nullable(); // Numéro local sur le site
            $table->date('date_inscription');
            $table->string('type', 50)->default('USAGER'); // USAGER, ADHERENT
            $table->foreignUuid('categorie_usager_id')
                ->nullable()
                ->constrained('categories_usagers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('statut', 50)->default('ACTIF'); // ACTIF, SUSPENDU, EXPIRE
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();

            $table->unique(['site_id', 'usager_id']);
            $table->index(['site_id', 'type', 'statut']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usager_sites');
    }
};
