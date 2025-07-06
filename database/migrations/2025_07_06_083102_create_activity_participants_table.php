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
        Schema::create('activity_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_activity_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Statut de participation
            $table->enum('status', ['invited', 'accepted', 'declined', 'participating', 'completed', 'left'])->default('invited');
            $table->enum('role', ['participant', 'facilitator', 'observer'])->default('participant');

            // Invitation
            $table->foreignId('invited_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('invitation_message')->nullable();

            // Participation
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->integer('time_spent_minutes')->default(0);

            // Données de participation
            $table->json('participation_data')->nullable(); // Réponses, scores, etc.
            $table->integer('score')->nullable();
            $table->text('notes')->nullable(); // Notes personnelles

            // Évaluation de l'activité
            $table->integer('activity_rating')->nullable(); // Note sur 5
            $table->text('feedback')->nullable(); // Commentaire sur l'activité

            $table->timestamps();

            // Éviter les doublons
            $table->unique(['resource_activity_id', 'user_id']);

            // Index pour les performances
            $table->index(['resource_activity_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('invited_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_participants');
    }
};
