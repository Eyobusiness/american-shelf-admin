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
        Schema::table('menus', function (Blueprint $table) {
            if (!Schema::hasColumn('menus', 'permission')) {
                $table->string('permission', 255)->nullable()->after('reference');
            }
        });

        Schema::table('sous_menus', function (Blueprint $table) {
            if (!Schema::hasColumn('sous_menus', 'permission')) {
                $table->string('permission', 255)->nullable()->after('reference');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            if (Schema::hasColumn('menus', 'permission')) {
                $table->dropColumn('permission');
            }
        });

        Schema::table('sous_menus', function (Blueprint $table) {
            if (Schema::hasColumn('sous_menus', 'permission')) {
                $table->dropColumn('permission');
            }
        });
    }
};
