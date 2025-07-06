<?php

namespace Database\Seeders;

use App\Models\UserResourceProgression;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserResourceProgressionSeeder extends Seeder
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

        $totalProgressions = 0;

        foreach ($users as $user) {
            // Chaque utilisateur aura des progressions sur 3-10 ressources
            $progressionCount = rand(3, 10);

            // Sélectionner des ressources aléatoires
            $selectedResources = $resources->random(min($progressionCount, $resources->count()));

            foreach ($selectedResources as $resource) {
                try {
                    $progression = $this->createRealisticProgression($user, $resource);
                    if ($progression) {
                        $totalProgressions++;
                    }
                } catch (\Exception $e) {
                    // Ignorer les doublons (contrainte unique)
                    continue;
                }
            }
        }

        $this->command->info("Total des progressions créées: {$totalProgressions}");

        // Créer quelques progressions supplémentaires via factory
        try {
            // Progressions terminées
            UserResourceProgression::factory(15)->completed()->create([
                'user_id' => fn() => $users->random()->id,
                'resource_id' => fn() => $resources->random()->id,
            ]);

            // Progressions en cours
            UserResourceProgression::factory(20)->inProgress()->create([
                'user_id' => fn() => $users->random()->id,
                'resource_id' => fn() => $resources->random()->id,
            ]);

            // Ressources mises de côté
            UserResourceProgression::factory(10)->bookmarked()->create([
                'user_id' => fn() => $users->random()->id,
                'resource_id' => fn() => $resources->random()->id,
            ]);

        } catch (\Exception $e) {
            $this->command->info("Quelques doublons ignorés lors de la création via factory.");
        }

        $finalCount = UserResourceProgression::count();
        $this->command->info("Nombre final de progressions: {$finalCount}");

        // Afficher les statistiques
        $this->displayStatistics();
    }

    private function createRealisticProgression(User $user, Resource $resource): ?UserResourceProgression
    {
        // Distribution réaliste des statuts
        $statusDistribution = [
            'completed' => 25,     // 25%
            'in_progress' => 30,   // 30%
            'bookmarked' => 20,    // 20%
            'paused' => 15,        // 15%
            'not_started' => 10,   // 10%
        ];

        $random = rand(1, 100);
        $cumulativePercent = 0;
        $status = 'not_started';

        foreach ($statusDistribution as $statusOption => $percent) {
            $cumulativePercent += $percent;
            if ($random <= $cumulativePercent) {
                $status = $statusOption;
                break;
            }
        }

        $data = [
            'user_id' => $user->id,
            'resource_id' => $resource->id,
            'status' => $status,
        ];

        // Générer des données spécifiques selon le statut
        switch ($status) {
            case 'completed':
                $completedAt = now()->subDays(rand(1, 90));
                $startedAt = $completedAt->copy()->subDays(rand(1, 30));

                $data = array_merge($data, [
                    'progress_percentage' => 100,
                    'user_rating' => rand(3, 5),
                    'user_review' => $this->getRandomReview(),
                    'time_spent_minutes' => rand(15, 120),
                    'started_at' => $startedAt,
                    'completed_at' => $completedAt,
                    'last_accessed_at' => $completedAt,
                    'progress_data' => [
                        'sections_completed' => rand(3, 8),
                        'quiz_completed' => true,
                        'final_score' => rand(70, 100),
                    ],
                ]);
                break;

            case 'in_progress':
                $startedAt = now()->subDays(rand(1, 30));

                $data = array_merge($data, [
                    'progress_percentage' => rand(10, 85),
                    'time_spent_minutes' => rand(5, 60),
                    'started_at' => $startedAt,
                    'last_accessed_at' => now()->subDays(rand(0, 7)),
                    'user_notes' => $this->getRandomNote(),
                    'progress_data' => [
                        'sections_completed' => rand(1, 5),
                        'current_section' => 'Chapitre ' . rand(2, 6),
                    ],
                ]);
                break;

            case 'bookmarked':
                $data = array_merge($data, [
                    'progress_percentage' => 0,
                    'time_spent_minutes' => 0,
                    'last_accessed_at' => now()->subDays(rand(1, 14)),
                    'user_notes' => 'À lire quand j\'aurai le temps',
                ]);
                break;

            case 'paused':
                $startedAt = now()->subDays(rand(7, 60));

                $data = array_merge($data, [
                    'progress_percentage' => rand(20, 70),
                    'time_spent_minutes' => rand(10, 45),
                    'started_at' => $startedAt,
                    'last_accessed_at' => now()->subDays(rand(7, 30)),
                    'user_notes' => 'Mis en pause, à reprendre plus tard',
                ]);
                break;

            case 'not_started':
                $data = array_merge($data, [
                    'progress_percentage' => 0,
                    'time_spent_minutes' => 0,
                ]);
                break;
        }

        return UserResourceProgression::create($data);
    }

    private function getRandomReview(): string
    {
        $reviews = [
            "Très utile pour ma situation personnelle !",
            "Bien expliqué et facile à appliquer.",
            "J'ai appris beaucoup de nouvelles techniques.",
            "Parfait pour débuter sur ce sujet.",
            "Les exemples concrets sont très aidants.",
            "Exactement ce que je cherchais.",
            "Je recommande vivement cette ressource.",
            "Approche intéressante et bien structurée.",
        ];

        return $reviews[array_rand($reviews)];
    }

    private function getRandomNote(): string
    {
        $notes = [
            "Point important à retenir pour plus tard",
            "Technique intéressante à tester avec mon conjoint",
            "À appliquer avec les enfants",
            "Exercice pratique à faire ce weekend",
            "Bien noter les étapes 2 et 3",
            "Revoir la partie sur la communication",
            "Partager avec ma famille",
        ];

        return $notes[array_rand($notes)];
    }

    private function displayStatistics(): void
    {
        $stats = [
            'completed' => UserResourceProgression::where('status', 'completed')->count(),
            'in_progress' => UserResourceProgression::where('status', 'in_progress')->count(),
            'bookmarked' => UserResourceProgression::where('status', 'bookmarked')->count(),
            'paused' => UserResourceProgression::where('status', 'paused')->count(),
            'not_started' => UserResourceProgression::where('status', 'not_started')->count(),
        ];

        $this->command->info("\n=== Statistiques des progressions ===");
        foreach ($stats as $status => $count) {
            $this->command->info("$status: $count");
        }
    }
}
