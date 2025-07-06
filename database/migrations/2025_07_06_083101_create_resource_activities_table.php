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
        Schema::create('resource_activities', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();

            // Relations
            $table->foreignId('resource_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            // Configuration de l'activité
            $table->enum('status', ['draft', 'open', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->integer('max_participants')->default(10);
            $table->boolean('is_private')->default(false); // Activité privée (sur invitation uniquement)
            $table->string('access_code', 8)->nullable(); // Code d'accès pour rejoindre

            // Planification
            $table->timestamp('scheduled_at')->nullable(); // Date/heure prévue
            $table->timestamp('started_at')->nullable(); // Début effectif
            $table->timestamp('completed_at')->nullable(); // Fin effective
            $table->integer('estimated_duration_minutes')->nullable(); // Durée estimée

            // Données de l'activité
            $table->json('activity_data')->nullable(); // Configuration spécifique (quiz, jeu, etc.)
            $table->json('results')->nullable(); // Résultats finaux

            // Métadonnées
            $table->integer('participant_count')->default(0);
            $table->text('instructions')->nullable(); // Instructions pour les participants

            $table->timestamps();

            // Index pour les performances
            $table->index(['resource_id', 'status']);
            $table->index(['created_by']);
            $table->index('scheduled_at');
            $table->index('access_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_activities');
    }
};
