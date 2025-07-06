<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Resource>
 */
class ResourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->sentence(4);
        $isPublished = $this->faker->boolean(70);
        $publishedAt = $isPublished ? $this->faker->dateTimeBetween('-6 months', 'now') : null;

        $content = $this->generateContent();

        return [
            'title' => $title,
            'description' => $this->faker->paragraph(3),
            'content' => $content,
            'slug' => Str::slug($title) . '-' . $this->faker->unique()->randomNumber(4),
            'category_id' => Category::factory(),
            'resource_type_id' => ResourceType::factory(),
            'created_by' => User::factory(),
            'validated_by' => $isPublished ? User::factory() : null,
            'visibility' => $this->faker->randomElement(['private', 'shared', 'public']),
            'status' => $isPublished ? 'published' : $this->faker->randomElement(['draft', 'pending']),
            'external_url' => $this->faker->optional(0.3)->url(),
            'duration_minutes' => $this->faker->optional(0.7)->numberBetween(5, 120),
            'difficulty_level' => $this->faker->randomElement(['beginner', 'intermediate', 'advanced']),
            'tags' => $this->generateTags(),
            'view_count' => $isPublished ? $this->faker->numberBetween(0, 1000) : 0,
            'download_count' => $isPublished ? $this->faker->numberBetween(0, 200) : 0,
            'favorite_count' => $isPublished ? $this->faker->numberBetween(0, 50) : 0,
            'average_rating' => $isPublished ? $this->faker->optional(0.6)->randomFloat(2, 1, 5) : null,
            'rating_count' => $isPublished ? $this->faker->numberBetween(0, 25) : 0,
            'published_at' => $publishedAt,
            'validated_at' => $isPublished ? $publishedAt : null,
            'last_viewed_at' => $isPublished ? $this->faker->optional(0.8)->dateTimeBetween($publishedAt, 'now') : null,
        ];
    }

    /**
     * Generate realistic content
     */
    private function generateContent(): string
    {
        $paragraphs = [];
        $numParagraphs = $this->faker->numberBetween(3, 8);

        for ($i = 0; $i < $numParagraphs; $i++) {
            $paragraphs[] = $this->faker->paragraph($this->faker->numberBetween(4, 8));
        }

        return '<p>' . implode('</p><p>', $paragraphs) . '</p>';
    }

    /**
     * Generate tag array
     */
    private function generateTags(): array
    {
        $allTags = [
            'communication', 'écoute', 'empathie', 'conflit', 'médiation',
            'couple', 'famille', 'enfant', 'adolescent', 'parent',
            'travail', 'équipe', 'manager', 'collègue', 'stress',
            'confiance', 'estime', 'assertivité', 'négociation', 'dialogue'
        ];

        return $this->faker->randomElements($allTags, $this->faker->numberBetween(2, 6));
    }

    /**
     * Indicate that the resource is published.
     */
    public function published(): static
    {
        return $this->state(function (array $attributes) {
            $publishedAt = $this->faker->dateTimeBetween('-3 months', 'now');

            return [
                'status' => 'published',
                'visibility' => $this->faker->randomElement(['shared', 'public']),
                'published_at' => $publishedAt,
                'validated_at' => $publishedAt,
                'validated_by' => User::factory(),
                'view_count' => $this->faker->numberBetween(10, 1000),
                'favorite_count' => $this->faker->numberBetween(0, 50),
                'average_rating' => $this->faker->randomFloat(2, 3, 5),
                'rating_count' => $this->faker->numberBetween(5, 25),
            ];
        });
    }

    /**
     * Indicate that the resource is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'visibility' => 'private',
            'published_at' => null,
            'validated_at' => null,
            'validated_by' => null,
            'view_count' => 0,
            'download_count' => 0,
            'favorite_count' => 0,
            'average_rating' => null,
            'rating_count' => 0,
        ]);
    }

    /**
     * Indicate that the resource has a file attached.
     */
    public function withFile(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_path' => 'resources/' . $this->faker->uuid() . '.pdf',
            'file_name' => $this->faker->words(3, true) . '.pdf',
            'file_mime_type' => 'application/pdf',
            'file_size' => $this->faker->numberBetween(500000, 5000000), // 500KB to 5MB
        ]);
    }
}
