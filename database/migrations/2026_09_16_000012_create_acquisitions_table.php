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
        Schema::create('acquisitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')
                ->constrained('sites')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('type', 50); // ACHAT, DON
            $table->date('date_acquisition');
            $table->string('provenance', 255)->nullable();
            $table->integer('quantite')->default(1);
            $table->decimal('cout', 12, 2)->nullable();
            $table->string('reference_facture', 100)->nullable();
            $table->string('donateur', 255)->nullable();
            $table->string('nature_don', 255)->nullable();
            $table->string('destination', 255)->nullable();
            $table->string('etat', 50)->default('RECU');
            $table->text('observations')->nullable();
            $table->string('piece_jointe', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();

            $table->index(['site_id', 'type', 'date_acquisition']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acquisitions');
    }
};
