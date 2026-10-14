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
        Schema::create('journaux_activites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')
                ->constrained('sites')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignUuid('utilisateur_id')
                ->nullable()
                ->constrained('utilisateurs')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('action', 100);
            $table->string('module', 100);
            $table->string('reference', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('adresse_ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['site_id', 'utilisateur_id', 'module', 'action']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journaux_activites');
    }
};
