<?php

namespace Database\Factories;

use App\Models\RelationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RelationType>
 */
class RelationTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $relationTypes = [
            'Relations parent-enfant',
            'Relations de couple',
            'Relations fraternelles',
            'Relations grands-parents-petits-enfants',
            'Relations belle-famille',
            'Relations manager-employé',
            'Relations entre collègues',
            'Relations client-fournisseur',
            'Relations enseignant-élève',
            'Relations de voisinage',
            'Relations communautaires',
            'Relations de mentorat',
        ];

        $icons = [
            'baby',
            'heart',
            'users',
            'user-plus',
            'home',
            'briefcase',
            'handshake',
            'shopping-bag',
            'graduation-cap',
            'map-pin',
            'globe',
            'award',
        ];

        return [
            'name' => $this->faker->unique()->randomElement($relationTypes),
            'description' => $this->faker->sentence(12),
            'icon' => $this->faker->randomElement($icons),
            'is_active' => $this->faker->boolean(95), // 95% de chance d'être actif
            'sort_order' => $this->faker->numberBetween(0, 50),
        ];
    }

    /**
     * Indicate that the relation type is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the relation type is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
