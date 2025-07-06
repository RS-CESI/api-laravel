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
        Schema::create('activity_messages', function (Blueprint $table) {
            $table->id();
            $table->text('content');

            // Relations
            $table->foreignId('resource_activity_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('activity_messages')->onDelete('cascade'); // Réponses

            // Type de message
            $table->enum('type', ['text', 'system', 'announcement', 'private'])->default('text');
            $table->foreignId('recipient_id')->nullable()->constrained('users')->onDelete('cascade'); // Pour messages privés

            // Métadonnées
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_read')->default(false); // Pour les messages privés
            $table->timestamp('edited_at')->nullable();

            // Données additionnelles
            $table->json('attachments')->nullable(); // Fichiers, liens, etc.
            $table->json('metadata')->nullable(); // Réactions, mentions, etc.

            $table->timestamps();

            // Index pour les performances
            $table->index(['resource_activity_id', 'type']);
            $table->index(['user_id']);
            $table->index(['parent_id']);
            $table->index(['recipient_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_messages');
    }
};
