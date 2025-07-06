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
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->text('content');

            // Relations
            $table->foreignId('resource_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('comments')->onDelete('cascade'); // Pour les réponses
            $table->foreignId('moderated_by')->nullable()->constrained('users')->onDelete('set null');

            // Statut et modération
            $table->enum('status', ['pending', 'approved', 'rejected', 'hidden'])->default('pending');
            $table->text('moderation_reason')->nullable(); // Raison du rejet/masquage
            $table->timestamp('moderated_at')->nullable();

            // Métadonnées
            $table->boolean('is_pinned')->default(false); // Épingler un commentaire important
            $table->integer('like_count')->default(0);
            $table->integer('reply_count')->default(0); // Nombre de réponses

            // Dates
            $table->timestamp('edited_at')->nullable(); // Dernière modification
            $table->timestamps();

            // Index pour les performances
            $table->index(['resource_id', 'status']);
            $table->index(['user_id']);
            $table->index(['parent_id']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
