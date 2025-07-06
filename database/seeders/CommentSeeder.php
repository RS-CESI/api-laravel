<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupérer toutes les ressources (pas seulement publiques)
        $resources = Resource::where('status', 'published')->get();

        // Si pas de ressources publiées, prendre toutes les ressources
        if ($resources->isEmpty()) {
            $resources = Resource::all();
            $this->command->info('Aucune ressource publiée trouvée, utilisation de toutes les ressources.');
        }

        // Récupérer tous les utilisateurs (pas seulement citoyens)
        $users = User::all();

        // Récupérer les modérateurs/admins
        $moderators = User::whereHas('role', function($query) {
            $query->whereIn('name', ['moderator', 'administrator', 'super-administrator']);
        })->get();

        if ($resources->isEmpty()) {
            $this->command->error('Aucune ressource trouvée. Créez d\'abord des ressources.');
            return;
        }

        if ($users->isEmpty()) {
            $this->command->error('Aucun utilisateur trouvé. Créez d\'abord des utilisateurs.');
            return;
        }

        $this->command->info("Ressources trouvées: {$resources->count()}");
        $this->command->info("Utilisateurs trouvés: {$users->count()}");
        $this->command->info("Modérateurs trouvés: {$moderators->count()}");

        foreach ($resources->take(10) as $resource) {
            // Créer 2-6 commentaires principaux par ressource
            $commentCount = rand(2, 6);

            $this->command->info("Création de {$commentCount} commentaires pour la ressource: {$resource->title}");

            for ($i = 0; $i < $commentCount; $i++) {
                $comment = Comment::create([
                    'content' => $this->getRandomComment(),
                    'resource_id' => $resource->id,
                    'user_id' => $users->random()->id,
                    'status' => $this->getRandomStatus(),
                    'moderated_by' => $moderators->isNotEmpty() ? $moderators->random()->id : null,
                    'moderated_at' => now()->subDays(rand(0, 30)),
                    'like_count' => rand(0, 15),
                    'is_pinned' => rand(1, 20) === 1, // 5% de chance d'être épinglé
                ]);

                // Ajouter 0-2 réponses à certains commentaires
                if (rand(1, 4) === 1) { // 25% de chance d'avoir des réponses
                    $replyCount = rand(1, 2);

                    for ($j = 0; $j < $replyCount; $j++) {
                        Comment::create([
                            'content' => $this->getRandomReply(),
                            'resource_id' => $resource->id,
                            'user_id' => $users->random()->id,
                            'parent_id' => $comment->id,
                            'status' => 'approved',
                            'moderated_by' => $moderators->isNotEmpty() ? $moderators->random()->id : null,
                            'moderated_at' => now()->subDays(rand(0, 15)),
                            'like_count' => rand(0, 8),
                        ]);
                    }
                }
            }
        }

        // Créer quelques commentaires supplémentaires via factory
        if ($resources->isNotEmpty() && $users->isNotEmpty()) {
            Comment::factory(15)->approved()->create([
                'resource_id' => fn() => $resources->random()->id,
                'user_id' => fn() => $users->random()->id,
                'moderated_by' => fn() => $moderators->isNotEmpty() ? $moderators->random()->id : null,
            ]);

            // Créer quelques commentaires en attente
            Comment::factory(5)->pending()->create([
                'resource_id' => fn() => $resources->random()->id,
                'user_id' => fn() => $users->random()->id,
            ]);
        }

        $totalComments = Comment::count();
        $this->command->info("Total des commentaires créés: {$totalComments}");
    }

    private function getRandomComment(): string
    {
        $comments = [
            "Merci beaucoup pour cette ressource ! Elle m'a vraiment aidé(e) à mieux comprendre la situation.",
            "Article très intéressant et bien écrit. J'aurais aimé avoir plus d'exemples concrets.",
            "Cette approche correspond exactement à ce que je cherchais. Je vais la mettre en pratique dès demain.",
            "Excellente ressource ! Je la recommande à tous ceux qui traversent des difficultés similaires.",
            "Très bien expliqué, facile à comprendre même pour quelqu'un qui débute sur le sujet.",
            "J'ai appliqué ces conseils et ça fonctionne vraiment ! Merci du partage.",
            "Ressource de qualité, j'aimerais en voir d'autres sur ce thème. Avez-vous d'autres suggestions ?",
            "Cela confirme ce que je pensais depuis longtemps. C'est rassurant de voir que je ne suis pas le/la seul(e).",
            "Un peu théorique à mon goût, mais les conseils pratiques sont pertinents.",
            "Formidable ! Cette lecture tombe à pic, j'en avais vraiment besoin en ce moment.",
        ];

        return $comments[array_rand($comments)];
    }

    private function getRandomReply(): string
    {
        $replies = [
            "Je suis tout à fait d'accord avec vous ! C'est exactement mon expérience aussi.",
            "Merci pour votre retour, c'est très encourageant de lire des témoignages positifs.",
            "Intéressant, je n'y avais pas pensé sous cet angle. Merci pour cette perspective.",
            "Pouvez-vous détailler davantage cette approche ? J'aimerais en savoir plus.",
            "C'est rassurant de voir que d'autres vivent la même chose. Merci du partage !",
            "Je vous rejoins sur ce point, c'est très juste et bien observé.",
            "Excellente question ! J'espère que l'auteur pourra nous éclairer sur ce point.",
            "Même ressenti de mon côté. Cette ressource est vraiment bien faite.",
        ];

        return $replies[array_rand($replies)];
    }

    private function getRandomStatus(): string
    {
        // 80% approved, 15% pending, 5% rejected
        $random = rand(1, 100);
        if ($random <= 80) return 'approved';
        if ($random <= 95) return 'pending';
        return 'rejected';
    }
}
