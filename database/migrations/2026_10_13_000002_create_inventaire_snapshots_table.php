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
        Schema::create('inventaire_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')
                ->constrained('sites')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->date('date_snapshot');
            $table->integer('annee');
            $table->integer('mois');
            $table->integer('total_livres')->default(0);
            $table->integer('total_exemplaires')->default(0);
            $table->integer('total_disponibles')->default(0);
            $table->integer('total_empruntes')->default(0);
            $table->integer('total_consultation')->default(0);
            $table->integer('total_reserves')->default(0);
            $table->integer('total_indisponibles')->default(0);
            $table->integer('total_perdus')->default(0);
            $table->json('repartition_etats')->nullable();
            $table->json('repartition_statuts')->nullable();
            $table->json('repartition_categories')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'date_snapshot']);
            $table->index(['site_id', 'annee', 'mois']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventaire_snapshots');
    }
};
