<?php

namespace Database\Seeders;

use App\Models\ResourceActivity;
use App\Models\ActivityParticipant;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Seeder;

class ResourceActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $resources = Resource::whereHas('resourceType', function($query) {
            $query->where('name', 'Activité/Jeu');
        })->get();

        $users = User::all();

        if ($resources->isEmpty()) {
            $this->command->warn('Aucune ressource de type "Activité/Jeu" trouvée. Utilisation de toutes les ressources.');
            $resources = Resource::all();
        }

        if ($resources->isEmpty() || $users->isEmpty()) {
            $this->command->error('Ressources ou utilisateurs manquants.');
            return;
        }

        $this->command->info("Ressources d'activité trouvées: {$resources->count()}");
        $this->command->info("Utilisateurs trouvés: {$users->count()}");

        $totalActivities = 0;
        $totalParticipants = 0;

        // Créer des activités réalistes
        foreach ($resources->take(8) as $resource) {
            $activity = $this->createRealisticActivity($resource, $users);
            if ($activity) {
                $totalActivities++;
                $participantCount = $this->addParticipants($activity, $users);
                $totalParticipants += $participantCount;
            }
        }

        // Créer des activités supplémentaires via factory
        $additionalActivities = [
            ResourceActivity::factory(3)->open()->create([
                'resource_id' => fn() => $resources->random()->id,
                'created_by' => fn() => $users->random()->id,
            ]),
            ResourceActivity::factory(2)->inProgress()->create([
                'resource_id' => fn() => $resources->random()->id,
                'created_by' => fn() => $users->random()->id,
            ]),
            ResourceActivity::factory(4)->completed()->create([
                'resource_id' => fn() => $resources->random()->id,
                'created_by' => fn() => $users->random()->id,
            ]),
        ];

        foreach ($additionalActivities as $activities) {
            foreach ($activities as $activity) {
                $participantCount = $this->addParticipants($activity, $users);
                $totalParticipants += $participantCount;
                $totalActivities++;
            }
        }

        $this->command->info("Total des activités créées: {$totalActivities}");
        $this->command->info("Total des participants: {$totalParticipants}");
    }

    private function createRealisticActivity(Resource $resource, $users): ?ResourceActivity
    {
        $creator = $users->random();

        $activityTitles = [
            "Atelier pratique: {$resource->title}",
            "Session collaborative: {$resource->title}",
            "Exercice en groupe: {$resource->title}",
            "Mise en pratique: {$resource->title}",
        ];

        $status = $this->getRandomStatus();
        $isPrivate = rand(1, 4) === 1; // 25% privées

        $scheduledAt = null;
        $startedAt = null;
        $completedAt = null;

        switch ($status) {
            case 'open':
                $scheduledAt = now()->addDays(rand(1, 14));
                break;
            case 'in_progress':
                $startedAt = now()->subHours(rand(1, 4));
                $scheduledAt = $startedAt;
                break;
            case 'completed':
                $completedAt = now()->subDays(rand(1, 30));
                $startedAt = $completedAt->copy()->subHours(rand(1, 3));
                $scheduledAt = $startedAt;
                break;
        }

        return ResourceActivity::create([
            'title' => $activityTitles[array_rand($activityTitles)],
            'description' => "Activité collaborative basée sur la ressource '{$resource->title}'. Participez pour mettre en pratique les concepts abordés.",
            'resource_id' => $resource->id,
            'created_by' => $creator->id,
            'status' => $status,
            'max_participants' => rand(4, 12),
            'is_private' => $isPrivate,
            'scheduled_at' => $scheduledAt,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'estimated_duration_minutes' => [30, 45, 60, 90, 120][array_rand([30, 45, 60, 90, 120])],
            'activity_data' => $this->generateActivityData(),
            'results' => $status === 'completed' ? $this->generateResults() : null,
            'instructions' => $this->generateInstructions(),
        ]);
    }

    private function addParticipants(ResourceActivity $activity, $users): int
    {
        $participantCount = 0;
        $maxParticipants = min($activity->max_participants, rand(2, 8));

        // Le créateur participe toujours (sauf s'il est juste organisateur)
        if (rand(1, 3) !== 1) { // 66% de chance que le créateur participe
            ActivityParticipant::create([
                'resource_activity_id' => $activity->id,
                'user_id' => $activity->created_by,
                'status' => $this->getParticipantStatus($activity->status),
                'role' => 'facilitator',
                'joined_at' => $activity->started_at,
                'time_spent_minutes' => $activity->status === 'completed' ? rand(30, 120) : rand(0, 60),
                'score' => $activity->status === 'completed' ? rand(70, 100) : null,
            ]);
            $participantCount++;
        }

        // Ajouter d'autres participants
        $selectedUsers = $users->where('id', '!=', $activity->created_by)->random(min($maxParticipants - 1, $users->count() - 1));

        foreach ($selectedUsers as $user) {
            $participantStatus = $this->getParticipantStatus($activity->status);

            $participant = ActivityParticipant::create([
                'resource_activity_id' => $activity->id,
                'user_id' => $user->id,
                'status' => $participantStatus,
                'role' => 'participant',
                'invited_by' => $activity->created_by,
                'invited_at' => $activity->created_at,
                'responded_at' => in_array($participantStatus, ['accepted', 'participating', 'completed']) ? now()->subDays(rand(0, 7)) : null,
                'joined_at' => in_array($participantStatus, ['participating', 'completed']) ? $activity->started_at : null,
                'time_spent_minutes' => $activity->status === 'completed' ? rand(20, 120) : rand(0, 60),
                'score' => $activity->status === 'completed' && $participantStatus === 'completed' ? rand(60, 100) : null,
                'participation_data' => $this->generateParticipationData($activity->status),
                'activity_rating' => $activity->status === 'completed' && $participantStatus === 'completed' ? rand(3, 5) : null,
                'feedback' => $activity->status === 'completed' && $participantStatus === 'completed' ? $this->getRandomFeedback() : null,
            ]);

            $participantCount++;
        }

        // Mettre à jour le compteur de participants de l'activité
        $activity->update(['participant_count' => $participantCount]);

        return $participantCount;
    }

    private function getRandomStatus(): string
    {
        $statuses = ['open', 'in_progress', 'completed', 'completed']; // Plus de completed pour l'historique
        return $statuses[array_rand($statuses)];
    }

    private function getParticipantStatus(string $activityStatus): string
    {
        return match($activityStatus) {
            'open' => ['invited', 'accepted'][array_rand(['invited', 'accepted'])],
            'in_progress' => ['participating', 'participating', 'accepted'][array_rand(['participating', 'participating', 'accepted'])],
            'completed' => ['completed', 'completed', 'left'][array_rand(['completed', 'completed', 'left'])],
            default => 'invited',
        };
    }

    private function generateActivityData(): array
    {
        return [
            'type' => ['quiz', 'role_play', 'discussion', 'exercise'][array_rand(['quiz', 'role_play', 'discussion', 'exercise'])],
            'questions' => [
                "Comment gérez-vous les situations de conflit ?",
                "Quelle est votre technique d'écoute préférée ?",
                "Comment exprimez-vous vos émotions difficiles ?",
                "Quel est votre style de communication naturel ?",
            ],
            'scoring' => [
                'max_points' => 100,
                'passing_score' => 70,
            ],
            'settings' => [
                'allow_late_join' => rand(0, 1) === 1,
                'show_results' => rand(0, 1) === 1,
                'anonymous_responses' => rand(0, 1) === 1,
            ],
        ];
    }

    private function generateResults(): array
    {
        return [
            'completion_rate' => rand(75, 100),
            'average_score' => rand(70, 95),
            'total_participants' => rand(3, 10),
            'duration_minutes' => rand(30, 150),
            'feedback_summary' => [
                'positive' => rand(70, 90),
                'neutral' => rand(5, 20),
                'negative' => rand(0, 10),
            ],
        ];
    }

    private function generateInstructions(): string
    {
        $instructions = [
            "Rejoignez l'activité à l'heure prévue. Préparez un environnement calme et sans distractions.",
            "Munissez-vous de papier et crayon pour prendre des notes. Participation active encouragée.",
            "Cette activité se déroule en plusieurs étapes interactives. Suivez les instructions à l'écran.",
            "Activité collaborative basée sur le respect mutuel. Écoutez les autres participants.",
            "Durée estimée respectée dans la mesure du possible. Possibilité de pause si nécessaire.",
        ];

        return $instructions[array_rand($instructions)];
    }

    private function generateParticipationData(string $activityStatus): ?array
    {
        if ($activityStatus !== 'completed') {
            return null;
        }

        return [
            'responses' => [
                'question_1' => ['Communication directe', 'Écoute active'],
                'question_2' => 'Je reformule ce que j\'ai compris',
                'question_3' => 'J\'exprime mes besoins clairement',
            ],
            'exercises_completed' => rand(2, 5),
            'engagement_score' => rand(70, 100),
            'peer_interactions' => rand(5, 15),
        ];
    }

    private function getRandomFeedback(): string
    {
        $feedbacks = [
            "Très enrichissant ! J'ai appris de nouvelles techniques.",
            "Excellente animation, activité bien structurée.",
            "Les échanges avec les autres participants étaient formateurs.",
            "Activité pratique qui m'a donné des outils concrets.",
            "Ambiance bienveillante, je recommande !",
            "Un peu long mais très utile dans l'ensemble.",
            "J'aurais aimé plus d'exemples concrets.",
            "Parfait pour mettre en pratique la théorie.",
        ];

        return $feedbacks[array_rand($feedbacks)];
    }
}
