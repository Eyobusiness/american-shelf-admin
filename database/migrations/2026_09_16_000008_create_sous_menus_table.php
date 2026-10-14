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
        Schema::create('sous_menus', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')
                ->constrained('sites')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignUuid('menu_id')
                ->constrained('menus')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('code', 50);
            $table->string('libelle', 100);
            $table->string('icon', 100)->nullable();
            $table->string('path', 255)->nullable();
            $table->integer('order')->default(0);
            $table->string('reference', 100)->nullable();
            $table->string('permission', 255)->nullable();
            $table->string('statut', 50)->default('ACTIF');
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();

            $table->unique(['site_id', 'menu_id', 'code']);
            $table->index(['site_id', 'menu_id', 'order', 'statut']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sous_menus');
    }
};
