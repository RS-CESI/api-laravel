<?php

namespace Database\Factories;

use App\Models\Resource;
use App\Models\ResourceActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ResourceActivity>
 */
class ResourceActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $titles = [
            "Atelier communication en couple",
            "Jeu de rôle résolution de conflits",
            "Quiz sur l'écoute active",
            "Exercice pratique empathie",
            "Session brainstorming familial",
            "Challenge bien-être émotionnel",
            "Simulation gestion de crise",
            "Activité team building",
        ];

        $status = $this->faker->randomElement(['draft', 'open', 'in_progress', 'completed']);
        $isPrivate = $this->faker->boolean(30); // 30% privées

        $scheduledAt = null;
        $startedAt = null;
        $completedAt = null;

        if ($status !== 'draft') {
            $scheduledAt = $this->faker->dateTimeBetween('-1 week', '+2 weeks');

            if (in_array($status, ['in_progress', 'completed'])) {
                $startedAt = $this->faker->dateTimeBetween('-1 week', 'now');

                if ($status === 'completed') {
                    $completedAt = $this->faker->dateTimeBetween($startedAt, 'now');
                }
            }
        }

        return [
            'title' => $this->faker->randomElement($titles),
            'description' => $this->faker->paragraph(2),
            'resource_id' => Resource::factory(),
            'created_by' => User::factory(),
            'status' => $status,
            'max_participants' => $this->faker->randomElement([4, 6, 8, 10, 12, 15]),
            'is_private' => $isPrivate,
            'access_code' => strtoupper(Str::random(6)),
            'scheduled_at' => $scheduledAt,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'estimated_duration_minutes' => $this->faker->randomElement([30, 45, 60, 90, 120]),
            'activity_data' => $this->generateActivityData(),
            'results' => $status === 'completed' ? $this->generateResults() : null,
            'participant_count' => $status !== 'draft' ? $this->faker->numberBetween(2, 8) : 0,
            'instructions' => $this->generateInstructions(),
        ];
    }

    /**
     * Generate activity-specific data
     */
    private function generateActivityData(): array
    {
        $questions = [
            "Comment gérez-vous les conflits ?",
            "Quelle est votre technique d'écoute préférée ?",
            "Comment exprimez-vous vos émotions ?",
            "Quel est votre style de communication ?",
            "Comment établissez-vous la confiance ?",
            "Quelle est votre approche pour résoudre les problèmes ?",
            "Comment gérez-vous le stress relationnel ?",
            "Quelle importance accordez-vous à l'empathie ?",
        ];

        $questionCount = $this->faker->numberBetween(3, min(6, count($questions)));

        return [
            'type' => $this->faker->randomElement(['quiz', 'role_play', 'discussion', 'exercise']),
            'questions' => $this->faker->randomElements($questions, $questionCount),
            'scoring' => [
                'max_points' => 100,
                'passing_score' => 70,
            ],
            'settings' => [
                'allow_late_join' => $this->faker->boolean(),
                'show_results' => $this->faker->boolean(80),
                'anonymous_responses' => $this->faker->boolean(20),
            ],
        ];
    }

    /**
     * Generate activity results
     */
    private function generateResults(): array
    {
        return [
            'completion_rate' => $this->faker->numberBetween(70, 100),
            'average_score' => $this->faker->numberBetween(65, 95),
            'total_participants' => $this->faker->numberBetween(3, 10),
            'duration_minutes' => $this->faker->numberBetween(25, 150),
            'feedback_summary' => [
                'positive' => $this->faker->numberBetween(70, 95),
                'neutral' => $this->faker->numberBetween(5, 20),
                'negative' => $this->faker->numberBetween(0, 10),
            ],
        ];
    }

    /**
     * Generate instructions
     */
    private function generateInstructions(): string
    {
        $instructions = [
            "Rejoignez l'activité à l'heure prévue. Préparez un environnement calme.",
            "Munissez-vous de papier et crayon. Participation active encouragée.",
            "Cette activité se déroule en plusieurs étapes. Suivez les instructions à l'écran.",
            "Activité collaborative. Respectez les autres participants et leurs opinions.",
            "Durée estimée indiquée. Possibilité de pause si nécessaire.",
        ];

        return $this->faker->randomElement($instructions);
    }

    /**
     * Indicate that the activity is open for registration.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'open',
            'scheduled_at' => $this->faker->dateTimeBetween('now', '+1 week'),
            'participant_count' => $this->faker->numberBetween(1, 5),
        ]);
    }

    /**
     * Indicate that the activity is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(function (array $attributes) {
            $startedAt = $this->faker->dateTimeBetween('-2 hours', 'now');

            return [
                'status' => 'in_progress',
                'started_at' => $startedAt,
                'scheduled_at' => $startedAt,
                'participant_count' => $this->faker->numberBetween(3, 8),
            ];
        });
    }

    /**
     * Indicate that the activity is completed.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $startedAt = $this->faker->dateTimeBetween('-1 week', '-2 hours');
            $completedAt = $this->faker->dateTimeBetween($startedAt, 'now');

            return [
                'status' => 'completed',
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'scheduled_at' => $startedAt,
                'participant_count' => $this->faker->numberBetween(3, 10),
                'results' => $this->generateResults(),
            ];
        });
    }

    /**
     * Indicate that the activity is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_private' => true,
        ]);
    }
}
