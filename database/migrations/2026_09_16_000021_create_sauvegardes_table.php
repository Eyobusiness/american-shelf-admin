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
        Schema::create('sauvegardes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')
                ->constrained('sites')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('nom_fichier', 255);
            $table->string('chemin_fichier', 255);
            $table->unsignedBigInteger('taille')->default(0); // taille en octets
            $table->dateTime('date_sauvegarde');
            $table->string('statut', 50)->default('TERMINE'); // EN_COURS, TERMINE, ECHEC
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();

            $table->index(['site_id', 'statut', 'date_sauvegarde']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sauvegardes');
    }
};
