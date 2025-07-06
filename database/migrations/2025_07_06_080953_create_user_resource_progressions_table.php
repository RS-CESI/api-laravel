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
        Schema::create('user_resource_progressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('resource_id')->constrained()->onDelete('cascade');

            // Statut de progression
            $table->enum('status', [
                'not_started',    // Pas encore commencé
                'in_progress',    // En cours
                'completed',      // Terminé/exploité
                'paused',         // Mis en pause
                'bookmarked'      // Mis de côté pour plus tard
            ])->default('not_started');

            // Progression détaillée
            $table->integer('progress_percentage')->default(0); // Pourcentage de completion (0-100)
            $table->json('progress_data')->nullable(); // Données spécifiques (quiz, étapes, etc.)
            $table->text('user_notes')->nullable(); // Notes personnelles de l'utilisateur

            // Évaluation de l'utilisateur
            $table->integer('user_rating')->nullable(); // Note sur 5
            $table->text('user_review')->nullable(); // Avis personnel

            // Temps passé et dates importantes
            $table->integer('time_spent_minutes')->default(0); // Temps total passé
            $table->timestamp('started_at')->nullable(); // Première interaction
            $table->timestamp('completed_at')->nullable(); // Date de completion
            $table->timestamp('last_accessed_at')->nullable(); // Dernier accès

            $table->timestamps();

            // Éviter les doublons
            $table->unique(['user_id', 'resource_id']);

            // Index pour les performances
            $table->index(['user_id', 'status']);
            $table->index(['resource_id', 'status']);
            $table->index('completed_at');
            $table->index('last_accessed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_resource_progressions');
    }
};
