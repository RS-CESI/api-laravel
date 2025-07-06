<?php

namespace Database\Seeders;

use App\Models\ActivityMessage;
use App\Models\ResourceActivity;
use App\Models\ActivityParticipant;
use Illuminate\Database\Seeder;

class ActivityMessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $activities = ResourceActivity::whereIn('status', ['in_progress', 'completed'])->get();

        if ($activities->isEmpty()) {
            $this->command->warn('Aucune activité en cours ou terminée trouvée.');
            return;
        }

        $this->command->info("Activités trouvées: {$activities->count()}");

        $totalMessages = 0;

        foreach ($activities as $activity) {
            $participants = $activity->participants()
                ->whereIn('status', ['participating', 'completed'])
                ->with('user')
                ->get();

            if ($participants->isEmpty()) {
                continue;
            }

            $this->command->info("Création de messages pour l'activité: {$activity->title}");

            // Messages système de début
            if ($activity->status === 'in_progress' || $activity->status === 'completed') {
                ActivityMessage::createSystemMessage($activity, "L'activité a commencé.");
                $totalMessages++;

                foreach ($participants as $participant) {
                    if ($participant->joined_at) {
                        ActivityMessage::createSystemMessage(
                            $activity,
                            "{$participant->user->name} a rejoint l'activité.",
                            ['user_id' => $participant->user->id]
                        );
                        $totalMessages++;
                    }
                }
            }

            // Message d'accueil du facilitateur
            $facilitator = $participants->where('role', 'facilitator')->first();
            if ($facilitator) {
                ActivityMessage::createAnnouncement(
                    $activity,
                    $facilitator->user,
                    "Bienvenue dans cette session ! N'hésitez pas à poser vos questions et à partager vos expériences."
                );
                $totalMessages++;
            }

            // Messages de conversation entre participants
            if ($participants->count() > 0) {
                $messageCount = rand(8, 20);
                for ($i = 0; $i < $messageCount; $i++) {
                    $participant = $participants->random();

                    $message = ActivityMessage::create([
                        'content' => $this->getRandomConversationMessage(),
                        'resource_activity_id' => $activity->id,
                        'user_id' => $participant->user->id,
                        'type' => 'text',
                        'created_at' => $this->getRandomMessageTime($activity),
                    ]);

                    // 30% de chance d'avoir une réponse
                    if (rand(1, 10) <= 3 && $participants->count() > 1) {
                        $otherParticipants = $participants->where('user.id', '!=', $participant->user->id);
                        if ($otherParticipants->count() > 0) {
                            $replier = $otherParticipants->random();
                            ActivityMessage::create([
                                'content' => $this->getRandomReplyMessage(),
                                'resource_activity_id' => $activity->id,
                                'user_id' => $replier->user->id,
                                'parent_id' => $message->id,
                                'type' => 'text',
                                'created_at' => $this->getRandomMessageTime($activity, $message->created_at),
                            ]);
                            $totalMessages++;
                        }
                    }

                    $totalMessages++;
                }

                // Quelques messages privés entre participants
                if ($participants->count() > 1) {
                    $privateMessageCount = rand(2, 5);
                    for ($i = 0; $i < $privateMessageCount; $i++) {
                        $sender = $participants->random();
                        $otherParticipants = $participants->where('user.id', '!=', $sender->user->id);

                        if ($otherParticipants->count() > 0) {
                            $recipient = $otherParticipants->random();
                            ActivityMessage::createPrivateMessage(
                                $activity,
                                $sender->user,
                                $recipient->user,
                                $this->getRandomPrivateMessage()
                            );
                            $totalMessages++;
                        }
                    }
                }
            }

            // Messages de fin si l'activité est terminée
            if ($activity->status === 'completed') {
                // Annonce de fin
                if ($facilitator) {
                    ActivityMessage::createAnnouncement(
                        $activity,
                        $facilitator->user,
                        "Merci à tous pour votre participation ! N'hésitez pas à continuer les échanges."
                    );
                    $totalMessages++;
                }

                // Message système de fin
                ActivityMessage::createSystemMessage($activity, "L'activité s'est terminée.");
                $totalMessages++;
            }
        }

        // Créer quelques messages supplémentaires via factory
        if ($activities->isNotEmpty()) {
            $allParticipants = ActivityParticipant::whereIn('resource_activity_id', $activities->pluck('id'))->get();

            if ($allParticipants->isNotEmpty()) {
                ActivityMessage::factory(20)->public()->create([
                    'resource_activity_id' => fn() => $activities->random()->id,
                    'user_id' => fn() => $allParticipants->random()->user_id,
                ]);

                $facilitators = $allParticipants->where('role', 'facilitator');
                $userForAnnouncement = $facilitators->isNotEmpty()
                    ? $facilitators->random()->user_id
                    : $allParticipants->random()->user_id;

                ActivityMessage::factory(5)->announcement()->create([
                    'resource_activity_id' => fn() => $activities->random()->id,
                    'user_id' => $userForAnnouncement,
                ]);

                $totalMessages += 25;
            }
        }

        $this->command->info("Total des messages créés: {$totalMessages}");
    }

    private function getRandomConversationMessage(): string
    {
        $messages = [
            "Merci pour cette session, très enrichissante !",
            "J'ai une question sur la technique que vous venez de présenter.",
            "Dans mon expérience, j'ai trouvé que cette approche fonctionne bien.",
            "Pouvez-vous donner un exemple concret d'application ?",
            "C'est exactement le problème que je rencontre au quotidien.",
            "Excellente idée ! Je n'y avais jamais pensé sous cet angle.",
            "Comment gérez-vous cette situation quand il y a de la résistance ?",
            "Je partage complètement ce point de vue.",
            "Avez-vous des ressources complémentaires sur ce sujet ?",
            "Cette méthode a vraiment transformé ma façon de communiquer.",
            "Je pense qu'il faut aussi tenir compte du contexte culturel.",
            "Qu'est-ce qui marche le mieux selon votre expérience ?",
            "Merci pour ce partage, c'est très inspirant !",
            "J'ai testé cette approche et les résultats sont probants.",
            "Comment adapter cela avec des adolescents ?",
        ];

        return $messages[array_rand($messages)];
    }

    private function getRandomReplyMessage(): string
    {
        $replies = [
            "Tout à fait d'accord avec vous !",
            "Merci pour cette question très pertinente.",
            "Je vais vous donner un exemple concret...",
            "C'est un excellent point que vous soulevez.",
            "Dans ce cas, je recommande plutôt...",
            "Effectivement, c'est important à considérer.",
            "Merci pour ce complément d'information !",
            "Votre expérience rejoint la mienne.",
            "C'est une approche intéressante, merci !",
            "Je n'avais pas pensé à cet aspect.",
        ];

        return $replies[array_rand($replies)];
    }

    private function getRandomPrivateMessage(): string
    {
        $privateMessages = [
            "Pourrions-nous en discuter plus en détail après la session ?",
            "Merci pour votre participation, très enrichissante !",
            "Avez-vous d'autres ressources à recommander sur ce thème ?",
            "J'aimerais avoir votre avis sur ma situation personnelle.",
            "Merci pour vos conseils, ils m'aident beaucoup.",
            "Souhaitez-vous qu'on continue cette discussion ?",
            "Votre approche m'intéresse, pouvez-vous m'en dire plus ?",
            "J'ai quelques questions spécifiques à vous poser.",
        ];

        return $privateMessages[array_rand($privateMessages)];
    }

    private function getRandomMessageTime($activity, $after = null)
    {
        $start = $after ?? $activity->started_at ?? now()->subHours(2);
        $end = $activity->completed_at ?? now();

        return fake()->dateTimeBetween($start, $end);
    }
}
