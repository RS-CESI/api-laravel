<?php

namespace Database\Seeders;

use App\Models\UserResourceFavorite;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserResourceFavoriteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $resources = Resource::where('status', 'published')->get();

        if ($users->isEmpty()) {
            $this->command->error('Aucun utilisateur trouvé.');
            return;
        }

        if ($resources->isEmpty()) {
            $this->command->error('Aucune ressource publiée trouvée.');
            return;
        }

        $this->command->info("Utilisateurs trouvés: {$users->count()}");
        $this->command->info("Ressources trouvées: {$resources->count()}");

        $totalFavorites = 0;

        foreach ($users as $user) {
            // Chaque utilisateur aura entre 2 et 8 favoris
            $favoriteCount = rand(2, 8);

            // Sélectionner des ressources aléatoires
            $selectedResources = $resources->random(min($favoriteCount, $resources->count()));

            foreach ($selectedResources as $resource) {
                try {
                    UserResourceFavorite::create([
                        'user_id' => $user->id,
                        'resource_id' => $resource->id,
                    ]);
                    $totalFavorites++;
                } catch (\Exception $e) {
                    // Ignorer les doublons (contrainte unique)
                    continue;
                }
            }
        }

        $this->command->info("Total des favoris créés: {$totalFavorites}");

        // Créer quelques favoris supplémentaires via factory
        try {
            UserResourceFavorite::factory(20)->create([
                'user_id' => fn() => $users->random()->id,
                'resource_id' => fn() => $resources->random()->id,
            ]);
        } catch (\Exception $e) {
            // Ignorer les erreurs de doublons
            $this->command->info("Quelques doublons ignorés lors de la création via factory.");
        }

        $finalCount = UserResourceFavorite::count();
        $this->command->info("Nombre final de favoris: {$finalCount}");
    }
}
