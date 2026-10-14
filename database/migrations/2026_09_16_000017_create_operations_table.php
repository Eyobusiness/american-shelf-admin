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
        Schema::create('operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')
                ->constrained('sites')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignUuid('configuration_id')
                ->constrained('configurations')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignUuid('usager_id')
                ->constrained('usagers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignUuid('exemplaire_id')
                ->constrained('exemplaires')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('type', 50); // CONSULTATION, EMPRUNT
            $table->dateTime('date_operation');
            $table->dateTime('date_retour_prevue')->nullable();
            $table->dateTime('date_retour_effective')->nullable();
            $table->string('statut', 50)->default('EN_COURS'); // EN_COURS, RETOURNE, EN_RETARD, PERDU
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();

            $table->index(['site_id', 'usager_id', 'type', 'statut']);
            $table->index(['site_id', 'exemplaire_id', 'statut']);
            $table->index('date_operation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operations');
    }
};
