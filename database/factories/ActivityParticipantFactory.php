<?php

namespace Database\Factories;

use App\Models\ActivityParticipant;
use App\Models\ResourceActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityParticipant>
 */
class ActivityParticipantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement([
            'invited', 'accepted', 'declined', 'participating', 'completed', 'left'
        ]);

        $role = $this->faker->randomElement(['participant', 'facilitator', 'observer']);

        $invitedAt = $this->faker->dateTimeBetween('-2 weeks', '-1 day');
        $respondedAt = null;
        $joinedAt = null;
        $leftAt = null;
        $timeSpent = 0;
        $score = null;
        $rating = null;
        $feedback = null;

        // Générer des dates cohérentes selon le statut
        if (in_array($status, ['accepted', 'declined', 'participating', 'completed', 'left'])) {
            $respondedAt = $this->faker->dateTimeBetween($invitedAt, 'now');
        }

        if (in_array($status, ['participating', 'completed', 'left'])) {
            $joinedAt = $this->faker->dateTimeBetween($respondedAt ?? $invitedAt, 'now');
            $timeSpent = $this->faker->numberBetween(10, 120);
        }

        if (in_array($status, ['completed', 'left'])) {
            $leftAt = $this->faker->dateTimeBetween($joinedAt, 'now');

            if ($status === 'completed') {
                $score = $this->faker->numberBetween(60, 100);
                $rating = $this->faker->optional(0.7)->numberBetween(3, 5);
                $feedback = $this->faker->optional(0.5)->sentence(8);
            }
        }

        return [
            'resource_activity_id' => ResourceActivity::factory(),
            'user_id' => User::factory(),
            'status' => $status,
            'role' => $role,
            'invited_by' => User::factory(),
            'invited_at' => $invitedAt,
            'responded_at' => $respondedAt,
            'invitation_message' => $this->faker->optional(0.3)->sentence(6),
            'joined_at' => $joinedAt,
            'left_at' => $leftAt,
            'time_spent_minutes' => $timeSpent,
            'participation_data' => $this->generateParticipationData($status),
            'score' => $score,
            'notes' => $this->faker->optional(0.2)->paragraph(),
            'activity_rating' => $rating,
            'feedback' => $feedback,
        ];
    }

    /**
     * Generate participation-specific data
     */
    private function generateParticipationData(string $status): ?array
    {
        if (!in_array($status, ['participating', 'completed'])) {
            return null;
        }

        return [
            'responses' => [
                'question_1' => $this->faker->sentence(),
                'question_2' => $this->faker->sentence(),
                'exercise_1' => $this->faker->boolean(),
            ],
            'interactions' => $this->faker->numberBetween(2, 15),
            'engagement_level' => $this->faker->randomElement(['low', 'medium', 'high']),
            'milestones_reached' => $this->faker->randomElements([
                'introduction_completed',
                'first_exercise_done',
                'group_discussion_participated',
                'final_quiz_completed'
            ], $this->faker->numberBetween(1, 4)),
        ];
    }

    /**
     * Indicate that the participant is invited.
     */
    public function invited(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'invited',
            'responded_at' => null,
            'joined_at' => null,
            'left_at' => null,
            'time_spent_minutes' => 0,
            'score' => null,
            'activity_rating' => null,
            'feedback' => null,
            'participation_data' => null,
        ]);
    }

    /**
     * Indicate that the participant has accepted.
     */
    public function accepted(): static
    {
        return $this->state(function (array $attributes) {
            $invitedAt = $this->faker->dateTimeBetween('-1 week', '-1 day');

            return [
                'status' => 'accepted',
                'invited_at' => $invitedAt,
                'responded_at' => $this->faker->dateTimeBetween($invitedAt, 'now'),
                'joined_at' => null,
                'left_at' => null,
                'time_spent_minutes' => 0,
                'score' => null,
                'activity_rating' => null,
                'feedback' => null,
                'participation_data' => null,
            ];
        });
    }

    /**
     * Indicate that the participant is currently participating.
     */
    public function participating(): static
    {
        return $this->state(function (array $attributes) {
            $invitedAt = $this->faker->dateTimeBetween('-1 week', '-2 hours');
            $respondedAt = $this->faker->dateTimeBetween($invitedAt, '-1 hour');
            $joinedAt = $this->faker->dateTimeBetween($respondedAt, 'now');

            return [
                'status' => 'participating',
                'invited_at' => $invitedAt,
                'responded_at' => $respondedAt,
                'joined_at' => $joinedAt,
                'left_at' => null,
                'time_spent_minutes' => $this->faker->numberBetween(5, 60),
                'score' => null,
                'activity_rating' => null,
                'feedback' => null,
                'participation_data' => $this->generateParticipationData('participating'),
            ];
        });
    }

    /**
     * Indicate that the participant has completed the activity.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $invitedAt = $this->faker->dateTimeBetween('-2 weeks', '-1 week');
            $respondedAt = $this->faker->dateTimeBetween($invitedAt, '-5 days');
            $joinedAt = $this->faker->dateTimeBetween($respondedAt, '-3 days');
            $leftAt = $this->faker->dateTimeBetween($joinedAt, '-1 day');

            return [
                'status' => 'completed',
                'invited_at' => $invitedAt,
                'responded_at' => $respondedAt,
                'joined_at' => $joinedAt,
                'left_at' => $leftAt,
                'time_spent_minutes' => $this->faker->numberBetween(30, 150),
                'score' => $this->faker->numberBetween(65, 100),
                'activity_rating' => $this->faker->numberBetween(3, 5),
                'feedback' => $this->faker->sentence(10),
                'participation_data' => $this->generateParticipationData('completed'),
            ];
        });
    }

    /**
     * Indicate that the participant declined the invitation.
     */
    public function declined(): static
    {
        return $this->state(function (array $attributes) {
            $invitedAt = $this->faker->dateTimeBetween('-1 week', '-1 day');

            return [
                'status' => 'declined',
                'invited_at' => $invitedAt,
                'responded_at' => $this->faker->dateTimeBetween($invitedAt, 'now'),
                'joined_at' => null,
                'left_at' => null,
                'time_spent_minutes' => 0,
                'score' => null,
                'activity_rating' => null,
                'feedback' => null,
                'participation_data' => null,
            ];
        });
    }

    /**
     * Indicate that the participant is a facilitator.
     */
    public function facilitator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'facilitator',
            'status' => 'accepted', // Les facilitateurs acceptent généralement
        ]);
    }

    /**
     * Indicate that the participant left early.
     */
    public function leftEarly(): static
    {
        return $this->state(function (array $attributes) {
            $invitedAt = $this->faker->dateTimeBetween('-1 week', '-3 days');
            $respondedAt = $this->faker->dateTimeBetween($invitedAt, '-2 days');
            $joinedAt = $this->faker->dateTimeBetween($respondedAt, '-1 day');
            $leftAt = $this->faker->dateTimeBetween($joinedAt, 'now');

            return [
                'status' => 'left',
                'invited_at' => $invitedAt,
                'responded_at' => $respondedAt,
                'joined_at' => $joinedAt,
                'left_at' => $leftAt,
                'time_spent_minutes' => $this->faker->numberBetween(5, 45), // Moins de temps
                'score' => null, // Pas de score car pas terminé
                'activity_rating' => $this->faker->optional(0.3)->numberBetween(1, 3), // Note plus basse
                'feedback' => $this->faker->optional(0.5)->randomElement([
                    'Problème technique',
                    'Urgence personnelle',
                    'Pas assez de temps',
                    'Contenu pas adapté'
                ]),
                'participation_data' => [
                    'reason_for_leaving' => 'early_exit',
                    'completion_percentage' => $this->faker->numberBetween(10, 60),
                ],
            ];
        });
    }
}
