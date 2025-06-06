<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $role = fake()->unique()->randomElement([
            [
                'name' => 'Citoyen non connecté',
                'description' => 'Utilisateur anonyme accédant aux ressources publiques sans compte.',
            ],
            [
                'name' => 'Citoyen connecté',
                'description' => 'Utilisateur inscrit et connecté avec un accès étendu aux fonctionnalités.',
            ],
            [
                'name' => 'Modérateur',
                'description' => 'Utilisateur responsable de la validation des ressources et de la modération des échanges.',
            ],
            [
                'name' => 'Administrateur',
                'description' => 'Gestionnaire du catalogue de ressources et des utilisateurs citoyens.',
            ],
            [
                'name' => 'Super-Administrateur',
                'description' => 'Administrateur global avec tous les droits sur la plateforme.',
            ],
        ]);

        return [
            'name' => $role['name'],
            'description' => $role['description'],
        ];
    }
}
