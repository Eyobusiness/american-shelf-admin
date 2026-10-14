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
        Schema::create('reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')
                ->constrained('sites')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignUuid('usager_id')
                ->constrained('usagers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignUuid('livre_id')
                ->constrained('livres')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->dateTime('date_reservation');
            $table->dateTime('date_expiration');
            $table->string('statut', 50)->default('EN_ATTENTE'); // EN_ATTENTE, DISPONIBLE, HONOREE, EXPIREE, ANNULEE
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();

            $table->index(['site_id', 'usager_id', 'livre_id', 'statut']);
            $table->index('date_reservation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
