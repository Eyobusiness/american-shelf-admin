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
        Schema::create('exemplaires', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')
                ->constrained('sites')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignUuid('livre_id')
                ->constrained('livres')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('code', 50); // ex: LIT-ROM-0001-001
            $table->string('etat', 50)->default('BON'); // NEUF, BON, MOYEN, DEGRADE, PERDU
            $table->string('statut', 50)->default('DISPONIBLE'); // DISPONIBLE, EMPRUNTE, EN_CONSULTATION, RESERVE, INDISPONIBLE
            $table->string('localisation', 100)->nullable(); // Rayon, étagère
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();

            $table->unique(['site_id', 'code']);
            $table->index(['site_id', 'livre_id', 'statut', 'etat']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exemplaires');
    }
};
