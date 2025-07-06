<?php

namespace Database\Factories;

use App\Models\ResourceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceType>
 */
class ResourceTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $resourceTypes = [
            'Article de blog',
            'Guide pratique',
            'Liste de conseils',
            'Témoignage',
            'Étude de cas',
            'Fiche technique',
            'Quiz interactif',
            'Livre numérique',
        ];

        $icons = [
            'file-text',
            'book-open',
            'list',
            'message-square',
            'search',
            'clipboard',
            'help-circle',
            'book',
        ];

        $colors = [
            '#3B82F6', // Blue
            '#EF4444', // Red
            '#10B981', // Green
            '#F59E0B', // Yellow
            '#8B5CF6', // Purple
            '#EC4899', // Pink
            '#06B6D4', // Cyan
            '#84CC16', // Lime
        ];

        $requiresFile = $this->faker->boolean(30); // 30% de chance de nécessiter un fichier

        $allowedFileTypes = $requiresFile ? $this->getAllowedFileTypes() : null;

        return [
            'name' => $this->faker->unique()->randomElement($resourceTypes),
            'description' => $this->faker->sentence(10),
            'icon' => $this->faker->randomElement($icons),
            'color' => $this->faker->randomElement($colors),
            'is_active' => $this->faker->boolean(95),
            'requires_file' => $requiresFile,
            'allowed_file_types' => $allowedFileTypes,
            'sort_order' => $this->faker->numberBetween(0, 50),
        ];
    }

    /**
     * Get random allowed file types
     */
    private function getAllowedFileTypes(): array
    {
        $allTypes = ['pdf', 'doc', 'docx', 'mp4', 'mp3', 'jpg', 'png', 'webp'];

        return $this->faker->randomElements($allTypes, $this->faker->numberBetween(1, 4));
    }

    /**
     * Indicate that the resource type requires a file.
     */
    public function requiresFile(array $fileTypes = ['pdf', 'doc', 'docx']): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_file' => true,
            'allowed_file_types' => $fileTypes,
        ]);
    }

    /**
     * Indicate that the resource type doesn't require a file.
     */
    public function noFile(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_file' => false,
            'allowed_file_types' => null,
        ]);
    }
}
