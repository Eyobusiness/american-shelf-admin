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
        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')
                ->constrained('sites')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignUuid('menu_id')
                ->nullable()
                ->constrained('menus')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignUuid('sous_menu_id')
                ->nullable()
                ->constrained('sous_menus')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('action', 50); // CREATE, READ, UPDATE, DELETE, etc.
            $table->string('code', 100);
            $table->string('libelle', 150);
            $table->timestamps();

            $table->unique(['site_id', 'code']);
            $table->index(['site_id', 'menu_id', 'sous_menu_id', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
