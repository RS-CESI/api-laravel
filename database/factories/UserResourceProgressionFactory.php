<?php

namespace Database\Factories;

use App\Models\Resource;
use App\Models\User;
use App\Models\UserResourceProgression;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserResourceProgression>
 */
class UserResourceProgressionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement([
            'not_started', 'in_progress', 'completed', 'paused', 'bookmarked'
        ]);

        $progressPercentage = $this->getProgressForStatus($status);
        $timeSpent = $this->getTimeSpentForStatus($status, $progressPercentage);

        $startedAt = null;
        $completedAt = null;
        $lastAccessedAt = null;

        if ($status !== 'not_started') {
            $startedAt = $this->faker->dateTimeBetween('-3 months', '-1 day');
            $lastAccessedAt = $this->faker->dateTimeBetween($startedAt, 'now');

            if ($status === 'completed') {
                $completedAt = $this->faker->dateTimeBetween($startedAt, 'now');
                $lastAccessedAt = $completedAt;
            }
        }

        return [
            'user_id' => User::factory(),
            'resource_id' => Resource::factory(),
            'status' => $status,
            'progress_percentage' => $progressPercentage,
            'progress_data' => $this->generateProgressData($status),
            'user_notes' => $this->faker->optional(0.3)->paragraph(),
            'user_rating' => $status === 'completed' ? $this->faker->optional(0.7)->numberBetween(1, 5) : null,
            'user_review' => $status === 'completed' ? $this->faker->optional(0.4)->sentence(10) : null,
            'time_spent_minutes' => $timeSpent,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'last_accessed_at' => $lastAccessedAt,
        ];
    }

    /**
     * Get progress percentage based on status
     */
    private function getProgressForStatus(string $status): int
    {
        return match($status) {
            'not_started' => 0,
            'bookmarked' => 0,
            'in_progress' => $this->faker->numberBetween(10, 90),
            'paused' => $this->faker->numberBetween(20, 80),
            'completed' => 100,
            default => 0,
        };
    }

    /**
     * Get time spent based on status and progress
     */
    private function getTimeSpentForStatus(string $status, int $progress): int
    {
        return match($status) {
            'not_started', 'bookmarked' => 0,
            'in_progress' => $this->faker->numberBetween(5, 60),
            'paused' => $this->faker->numberBetween(10, 45),
            'completed' => $this->faker->numberBetween(15, 120),
            default => 0,
        };
    }

    /**
     * Generate realistic progress data
     */
    private function generateProgressData(string $status): ?array
    {
        if ($status === 'not_started' || $status === 'bookmarked') {
            return null;
        }

        return [
            'sections_completed' => $this->faker->numberBetween(1, 5),
            'last_section' => $this->faker->randomElement([
                'Introduction', 'Chapitre 1', 'Exercices pratiques',
                'Conclusion', 'Quiz final'
            ]),
            'quiz_scores' => $this->faker->optional(0.5)->randomElements([85, 92, 78, 88], 2),
            'bookmarks' => $this->faker->optional(0.3)->randomElements([
                'page_12', 'section_3', 'exercise_2'
            ], 2),
        ];
    }

    /**
     * Indicate that the progression is completed.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $completedAt = $this->faker->dateTimeBetween('-2 months', 'now');

            return [
                'status' => 'completed',
                'progress_percentage' => 100,
                'user_rating' => $this->faker->numberBetween(3, 5),
                'user_review' => $this->faker->sentence(8),
                'time_spent_minutes' => $this->faker->numberBetween(20, 120),
                'completed_at' => $completedAt,
                'last_accessed_at' => $completedAt,
                'started_at' => $this->faker->dateTimeBetween('-3 months', $completedAt),
            ];
        });
    }

    /**
     * Indicate that the progression is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(function (array $attributes) {
            $startedAt = $this->faker->dateTimeBetween('-1 month', '-1 day');

            return [
                'status' => 'in_progress',
                'progress_percentage' => $this->faker->numberBetween(10, 80),
                'time_spent_minutes' => $this->faker->numberBetween(5, 60),
                'started_at' => $startedAt,
                'last_accessed_at' => $this->faker->dateTimeBetween($startedAt, 'now'),
                'completed_at' => null,
            ];
        });
    }

    /**
     * Indicate that the progression is bookmarked.
     */
    public function bookmarked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'bookmarked',
            'progress_percentage' => 0,
            'time_spent_minutes' => 0,
            'started_at' => null,
            'completed_at' => null,
            'last_accessed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'user_notes' => 'À lire plus tard',
        ]);
    }
}
