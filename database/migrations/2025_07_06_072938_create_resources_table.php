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
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->longText('content')->nullable(); // Contenu principal (HTML, markdown, etc.)
            $table->string('slug')->unique(); // URL friendly

            // Relations
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('resource_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('validated_by')->nullable()->constrained('users')->onDelete('set null');

            // Visibilité et statut
            $table->enum('visibility', ['private', 'shared', 'public'])->default('private');
            $table->enum('status', ['draft', 'pending', 'published', 'rejected', 'suspended'])->default('draft');

            // Fichiers attachés
            $table->string('file_path')->nullable(); // Chemin vers le fichier principal
            $table->string('file_name')->nullable(); // Nom original du fichier
            $table->string('file_mime_type')->nullable(); // Type MIME
            $table->bigInteger('file_size')->nullable(); // Taille en bytes

            // Lien externe (si applicable)
            $table->text('external_url')->nullable();

            // Métadonnées
            $table->integer('duration_minutes')->nullable(); // Durée estimée (lecture/visionnage)
            $table->enum('difficulty_level', ['beginner', 'intermediate', 'advanced'])->default('beginner');
            $table->json('tags')->nullable(); // Tags libres

            // Statistiques
            $table->bigInteger('view_count')->default(0);
            $table->bigInteger('download_count')->default(0);
            $table->bigInteger('favorite_count')->default(0);
            $table->decimal('average_rating', 3, 2)->nullable(); // Note moyenne /5
            $table->integer('rating_count')->default(0);

            // Dates importantes
            $table->timestamp('published_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();

            $table->timestamps();

            // Index pour les recherches
            $table->index(['status', 'visibility']);
            $table->index(['category_id', 'status']);
            $table->index(['created_by']);
            $table->index('published_at');
            $table->fullText(['title', 'description']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
