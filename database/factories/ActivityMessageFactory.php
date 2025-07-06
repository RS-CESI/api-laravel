<?php

namespace Database\Factories;

use App\Models\ResourceActivity;
use App\Models\User;
use App\Models\ActivityMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityMessage>
 */
class ActivityMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['text', 'system', 'announcement', 'private']);

        $messages = [
            'text' => [
                "Excellente question ! Je pense que...",
                "Je suis d'accord avec cette approche.",
                "Pouvez-vous donner un exemple concret ?",
                "Dans mon expérience, j'ai trouvé que...",
                "C'est intéressant, je n'y avais pas pensé.",
                "Merci pour ce partage, très enrichissant !",
                "Comment appliquer cela au quotidien ?",
                "J'ai une question sur ce point...",
            ],
            'system' => [
                "L'activité a commencé.",
                "Un nouveau participant a rejoint l'activité.",
                "Pause de 10 minutes.",
                "Reprise de l'activité.",
                "L'activité se termine dans 5 minutes.",
                "Activité terminée. Merci à tous !",
            ],
            'announcement' => [
                "Bienvenue à tous dans cette session !",
                "N'hésitez pas à poser vos questions.",
                "Nous allons maintenant passer à l'exercice pratique.",
                "Prenons 5 minutes pour réfléchir individuellement.",
            ],
            'private' => [
                "Avez-vous bien compris cette partie ?",
                "Merci pour votre participation active !",
                "Pouvons-nous en discuter après la session ?",
                "J'ai quelques ressources complémentaires à vous partager.",
            ],
        ];

        return [
            'content' => $this->faker->randomElement($messages[$type]),
            'resource_activity_id' => ResourceActivity::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'type' => $type,
            'recipient_id' => $type === 'private' ? User::factory() : null,
            'is_pinned' => $type === 'announcement' ? $this->faker->boolean(70) : false,
            'is_read' => $type === 'private' ? $this->faker->boolean(60) : true,
            'edited_at' => $this->faker->optional(0.1)->dateTimeBetween('-1 week', 'now'),
            'attachments' => $this->faker->optional(0.15)->passthrough($this->generateAttachments()),
            'metadata' => $this->faker->optional(0.3)->passthrough($this->generateMetadata()),
        ];
    }

    /**
     * Generate attachments data
     */
    private function generateAttachments(): array
    {
        return [
            [
                'type' => 'link',
                'url' => $this->faker->url(),
                'title' => $this->faker->sentence(3),
                'description' => $this->faker->sentence(8),
            ],
        ];
    }

    /**
     * Generate metadata with reactions
     */
    private function generateMetadata(): array
    {
        $reactions = ['👍', '❤️', '😊', '🤔', '👏'];
        $selectedReactions = $this->faker->randomElements($reactions, $this->faker->numberBetween(1, 3));

        $reactionData = [];
        foreach ($selectedReactions as $reaction) {
            $reactionData[$reaction] = $this->faker->randomElements(
                range(1, 10), // IDs d'utilisateurs fictifs
                $this->faker->numberBetween(1, 5)
            );
        }

        return [
            'reactions' => $reactionData,
            'mentions' => $this->faker->optional(0.3)->randomElements(range(1, 10), 2),
        ];
    }

    /**
     * Indicate that the message is a public text message.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'text',
            'recipient_id' => null,
            'content' => $this->faker->randomElement([
                "Très bonne explication, merci !",
                "Je partage complètement cette vision.",
                "Quelqu'un a-t-il déjà testé cette méthode ?",
                "C'est exactement ce que je vis en ce moment.",
                "Merci pour ces conseils pratiques !",
            ]),
        ]);
    }

    /**
     * Indicate that the message is a system message.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'system',
            'recipient_id' => null,
            'is_pinned' => false,
            'content' => $this->faker->randomElement([
                "L'activité a démarré.",
                "Alice a rejoint l'activité.",
                "Bob a quitté l'activité.",
                "Passage à l'étape suivante.",
                "Fin de l'activité.",
            ]),
        ]);
    }

    /**
     * Indicate that the message is an announcement.
     */
    public function announcement(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'announcement',
            'recipient_id' => null,
            'is_pinned' => true,
            'content' => $this->faker->randomElement([
                "Bienvenue dans cette session collaborative !",
                "Nous allons maintenant commencer l'exercice principal.",
                "N'oubliez pas de partager vos expériences.",
                "Prenons quelques minutes pour récapituler.",
            ]),
        ]);
    }

    /**
     * Indicate that the message is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'private',
            'recipient_id' => User::factory(),
            'is_pinned' => false,
            'is_read' => $this->faker->boolean(50),
            'content' => $this->faker->randomElement([
                "Pouvez-vous m'expliquer ce point en privé ?",
                "Merci pour votre aide pendant la session.",
                "Avez-vous d'autres ressources sur ce sujet ?",
                "Souhaitez-vous qu'on en discute après ?",
            ]),
        ]);
    }

    /**
     * Indicate that the message is a reply.
     */
    public function reply(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => ActivityMessage::factory(),
            'type' => 'text',
            'content' => $this->faker->randomElement([
                "Tout à fait d'accord avec vous !",
                "Merci pour cette précision.",
                "Intéressant, je n'y avais pas pensé.",
                "Pouvez-vous développer ce point ?",
                "C'est exactement ça !",
            ]),
        ]);
    }

    /**
     * Indicate that the message is pinned.
     */
    public function pinned(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pinned' => true,
        ]);
    }

    /**
     * Indicate that the message has reactions.
     */
    public function withReactions(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => $this->generateMetadata(),
        ]);
    }
}
