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
        Schema::create('resource_relation_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained()->onDelete('cascade');
            $table->foreignId('relation_type_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Éviter les doublons
            $table->unique(['resource_id', 'relation_type_id']);

            // Index pour les performances
            $table->index('resource_id');
            $table->index('relation_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_relation_types');
    }
};
