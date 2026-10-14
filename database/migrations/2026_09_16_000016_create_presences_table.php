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
        Schema::create('presences', function (Blueprint $table) {
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
            $table->date('date_presence');
            $table->time('heure_arrivee');
            $table->time('heure_depart')->nullable();
            $table->string('motif', 255)->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();

            $table->index(['site_id', 'usager_id', 'date_presence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presences');
    }
};
