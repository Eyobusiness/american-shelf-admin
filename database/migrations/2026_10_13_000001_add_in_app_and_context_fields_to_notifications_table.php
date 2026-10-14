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
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignUuid('utilisateur_id')
                ->nullable()
                ->after('usager_id')
                ->constrained('utilisateurs')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('destinataire_type', 30)->default('USAGER')->after('utilisateur_id');
            $table->dateTime('date_lecture')->nullable()->after('date_envoi');
            $table->string('entite_type', 50)->nullable()->after('date_lecture');
            $table->uuid('entite_id')->nullable()->after('entite_type');
            $table->string('action_url', 255)->nullable()->after('entite_id');
            $table->string('email_destinataire', 255)->nullable()->change();

            $table->index(['site_id', 'destinataire_type', 'date_lecture'], 'idx_notif_site_dest_read');
            $table->index(['utilisateur_id', 'date_lecture'], 'idx_notif_user_read');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notif_site_dest_read');
            $table->dropIndex('idx_notif_user_read');
            $table->dropForeign(['utilisateur_id']);
            $table->dropColumn([
                'utilisateur_id',
                'destinataire_type',
                'date_lecture',
                'entite_type',
                'entite_id',
                'action_url',
            ]);
        });
    }
};