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
        Schema::create('configurations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')
                ->constrained('sites')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->integer('duree_emprunt')->default(14); // en jours
            $table->integer('nombre_jours_grace')->default(3); // en jours
            $table->integer('duree_consultation')->default(1); // en jours
            $table->integer('quota_emprunt')->default(3); // nombre max d'emprunts simultanés
            $table->integer('duree_reservation')->default(2); // durée validité réservation en jours
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();

            $table->index('site_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configurations');
    }
};
