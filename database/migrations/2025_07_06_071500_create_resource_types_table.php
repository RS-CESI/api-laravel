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
        Schema::create('resource_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // Icône pour l'affichage
            $table->string('color', 7)->default('#6B7280'); // Couleur pour l'affichage
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_file')->default(false); // Si ce type nécessite un fichier attaché
            $table->json('allowed_file_types')->nullable(); // Types de fichiers autorisés (pdf, mp4, mp3, etc.)
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_types');
    }
};
