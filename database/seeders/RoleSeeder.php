<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
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
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']],
                ['description' => $role['description']]
            );
        }
    }
}
