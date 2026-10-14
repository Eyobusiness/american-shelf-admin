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
        Schema::create('exemplaire_mouvements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')
                ->constrained('sites')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignUuid('exemplaire_id')
                ->constrained('exemplaires')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('ancien_etat', 50)->nullable();
            $table->string('nouveau_etat', 50);
            $table->string('ancien_statut', 50)->nullable();
            $table->string('nouveau_statut', 50);
            $table->string('motif', 255)->nullable();
            $table->foreignUuid('operation_id')
                ->nullable()
                ->constrained('operations')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignUuid('utilisateur_id')
                ->nullable()
                ->constrained('utilisateurs')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['site_id', 'exemplaire_id']);
            $table->index(['site_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exemplaire_mouvements');
    }
};
