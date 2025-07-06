<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryFactory>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = [
            'Relations familiales',
            'Relations amoureuses',
            'Relations amicales',
            'Relations professionnelles',
            'Relations sociales',
            'Communication',
            'Gestion des conflits',
            'Développement personnel',
            'Bien-être émotionnel',
            'Parentalité',
        ];

        $icons = [
            'heart',
            'users',
            'user-friends',
            'briefcase',
            'message-circle',
            'brain',
            'shield',
            'smile',
            'baby',
            'home',
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
            '#F97316', // Orange
            '#6366F1', // Indigo
        ];

        return [
            'name' => $this->faker->unique()->randomElement($categories),
            'description' => $this->faker->sentence(10),
            'color' => $this->faker->randomElement($colors),
            'icon' => $this->faker->randomElement($icons),
            'is_active' => $this->faker->boolean(90), // 90% de chance d'être actif
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate that the category is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the category is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
