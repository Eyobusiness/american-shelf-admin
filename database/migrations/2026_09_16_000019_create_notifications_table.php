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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')
                ->constrained('sites')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignUuid('usager_id')
                ->nullable()
                ->constrained('usagers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('type', 50)->default('EMAIL');
            $table->string('objet', 255);
            $table->text('message');
            $table->string('email_destinataire', 255);
            $table->dateTime('date_envoi')->nullable();
            $table->string('statut', 50)->default('EN_ATTENTE'); // EN_ATTENTE, ENVOYE, ECHEC
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();

            $table->index(['site_id', 'usager_id', 'statut']);
            $table->index('date_envoi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
