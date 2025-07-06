<?php

namespace Database\Factories;

use App\Models\Resource;
use App\Models\User;
use App\Models\Comment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement(['pending', 'approved', 'rejected', 'hidden']);
        $isModerated = in_array($status, ['approved', 'rejected', 'hidden']);

        $comments = [
            "Merci pour cette ressource très utile ! Je vais la mettre en pratique.",
            "Article très intéressant, j'aurais aimé plus d'exemples concrets.",
            "Excellente approche, cela m'a aidé à mieux comprendre les enjeux.",
            "Je recommande cette ressource à tous ceux qui traversent des difficultés similaires.",
            "Très bien expliqué, facile à comprendre et à appliquer au quotidien.",
            "Cette méthode a vraiment fonctionné pour moi, merci du partage !",
            "J'ai une question sur le point 3, pourriez-vous préciser ?",
            "Ressource de qualité, j'aimerais en voir d'autres sur ce thème.",
            "Cela confirme ce que je pensais, très rassurant !",
            "Un peu théorique à mon goût, mais les conseils restent pertinents."
        ];

        return [
            'content' => $this->faker->randomElement($comments),
            'resource_id' => Resource::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'moderated_by' => $isModerated ? User::factory() : null,
            'status' => $status,
            'moderation_reason' => $status === 'rejected' ? $this->faker->sentence() : null,
            'moderated_at' => $isModerated ? $this->faker->dateTimeBetween('-1 month', 'now') : null,
            'is_pinned' => $this->faker->boolean(5), // 5% de chance d'être épinglé
            'like_count' => $status === 'approved' ? $this->faker->numberBetween(0, 25) : 0,
            'reply_count' => 0, // Sera calculé automatiquement
            'edited_at' => $this->faker->optional(0.1)->dateTimeBetween('-1 week', 'now'),
        ];
    }

    /**
     * Indicate that the comment is approved.
     */
    public function approved(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'approved',
                'moderated_by' => User::factory(),
                'moderated_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
                'moderation_reason' => null,
                'like_count' => $this->faker->numberBetween(0, 15),
            ];
        });
    }

    /**
     * Indicate that the comment is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'moderated_by' => null,
            'moderated_at' => null,
            'moderation_reason' => null,
            'like_count' => 0,
        ]);
    }

    /**
     * Indicate that the comment is a reply.
     */
    public function reply(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => Comment::factory(),
            'content' => $this->faker->randomElement([
                "Je suis tout à fait d'accord avec vous !",
                "Merci pour votre retour, c'est très encourageant.",
                "Intéressant, je n'y avais pas pensé sous cet angle.",
                "Pouvez-vous détailler davantage cette approche ?",
                "C'est exactement mon expérience aussi.",
                "Je vous rejoins sur ce point, très juste !",
            ]),
        ]);
    }

    /**
     * Indicate that the comment is pinned.
     */
    public function pinned(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pinned' => true,
            'status' => 'approved',
            'moderated_by' => User::factory(),
            'moderated_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the comment is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'moderated_by' => User::factory(),
            'moderated_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'moderation_reason' => $this->faker->randomElement([
                'Contenu inapproprié',
                'Hors sujet',
                'Spam détecté',
                'Langage offensant',
                'Information incorrecte',
            ]),
            'like_count' => 0,
        ]);
    }
}
