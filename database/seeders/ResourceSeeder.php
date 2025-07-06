<?php

namespace Database\Seeders;

use App\Models\Resource;
use App\Models\Category;
use App\Models\ResourceType;
use App\Models\User;
use Illuminate\Database\Seeder;

class ResourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupérer les données existantes
        $categories = Category::all();
        $resourceTypes = ResourceType::all();
        $users = User::where('role_id', '!=', 1)->get(); // Exclure les super-admins

        if ($categories->isEmpty() || $resourceTypes->isEmpty() || $users->isEmpty()) {
            $this->command->warn('Attention: Assurez-vous que les categories, resource_types et users sont créés avant les resources.');
            return;
        }

        // Ressources d'exemple réalistes
        $sampleResources = [
            [
                'title' => 'Comment améliorer la communication dans le couple',
                'description' => 'Un guide pratique pour développer une communication bienveillante et efficace avec son partenaire.',
                'content' => $this->getCommunicationContent(),
                'category' => 'Relations amoureuses',
                'type' => 'Article',
                'tags' => ['communication', 'couple', 'écoute', 'dialogue'],
                'difficulty' => 'beginner',
                'duration' => 15,
            ],
            [
                'title' => 'Gérer les conflits familiaux avec sérénité',
                'description' => 'Techniques et outils pour aborder et résoudre les tensions familiales de manière constructive.',
                'content' => $this->getConflictContent(),
                'category' => 'Relations familiales',
                'type' => 'Guide pratique',
                'tags' => ['conflit', 'famille', 'médiation', 'résolution'],
                'difficulty' => 'intermediate',
                'duration' => 25,
            ],
            [
                'title' => 'L\'écoute active en milieu professionnel',
                'description' => 'Développer ses compétences d\'écoute pour améliorer ses relations avec ses collègues et son équipe.',
                'content' => $this->getListeningContent(),
                'category' => 'Relations professionnelles',
                'type' => 'Article',
                'tags' => ['écoute', 'travail', 'équipe', 'management'],
                'difficulty' => 'intermediate',
                'duration' => 20,
            ],
        ];

        // Créer les ressources d'exemple
        foreach ($sampleResources as $resourceData) {
            $category = $categories->where('name', $resourceData['category'])->first() ?? $categories->random();
            $type = $resourceTypes->where('name', $resourceData['type'])->first() ?? $resourceTypes->random();
            $creator = $users->random();
            $validator = $users->where('id', '!=', $creator->id)->random();

            Resource::create([
                'title' => $resourceData['title'],
                'description' => $resourceData['description'],
                'content' => $resourceData['content'],
                'category_id' => $category->id,
                'resource_type_id' => $type->id,
                'created_by' => $creator->id,
                'validated_by' => $validator->id,
                'visibility' => 'public',
                'status' => 'published',
                'tags' => $resourceData['tags'],
                'difficulty_level' => $resourceData['difficulty'],
                'duration_minutes' => $resourceData['duration'],
                'view_count' => rand(50, 500),
                'favorite_count' => rand(5, 50),
                'average_rating' => round(rand(35, 50) / 10, 1),
                'rating_count' => rand(10, 30),
                'published_at' => now()->subDays(rand(1, 90)),
                'validated_at' => now()->subDays(rand(1, 90)),
            ]);
        }

        // Créer des ressources aléatoires supplémentaires
        Resource::factory(30)->published()->create([
            'category_id' => fn() => $categories->random()->id,
            'resource_type_id' => fn() => $resourceTypes->random()->id,
            'created_by' => fn() => $users->random()->id,
            'validated_by' => fn() => $users->random()->id,
        ]);

        // Créer quelques brouillons
        Resource::factory(10)->draft()->create([
            'category_id' => fn() => $categories->random()->id,
            'resource_type_id' => fn() => $resourceTypes->random()->id,
            'created_by' => fn() => $users->random()->id,
        ]);

        // Créer quelques ressources avec fichiers
        Resource::factory(8)->published()->withFile()->create([
            'category_id' => fn() => $categories->random()->id,
            'resource_type_id' => fn() => $resourceTypes->where('requires_file', true)->random()->id,
            'created_by' => fn() => $users->random()->id,
            'validated_by' => fn() => $users->random()->id,
        ]);
    }

    private function getCommunicationContent(): string
    {
        return '<h2>Introduction</h2>
        <p>La communication est le pilier de toute relation amoureuse épanouie. Elle permet de créer du lien, de résoudre les conflits et de maintenir la complicité au fil du temps.</p>

        <h2>Les bases d\'une communication saine</h2>
        <p>Pour bien communiquer, il est essentiel de :</p>
        <ul>
        <li>Choisir le bon moment pour parler</li>
        <li>Utiliser le "je" plutôt que le "tu"</li>
        <li>Écouter activement son partenaire</li>
        <li>Exprimer ses émotions sans agressivité</li>
        </ul>

        <h2>Exercices pratiques</h2>
        <p>Voici quelques exercices à mettre en pratique dès aujourd\'hui...</p>';
    }

    private function getConflictContent(): string
    {
        return '<h2>Comprendre l\'origine des conflits familiaux</h2>
        <p>Les tensions familiales peuvent naître de différences générationnelles, de valeurs divergentes ou de non-dits accumulés.</p>

        <h2>Stratégies de résolution</h2>
        <p>Face à un conflit familial, plusieurs approches peuvent être adoptées :</p>
        <ol>
        <li>Identifier les véritables enjeux</li>
        <li>Créer un espace de dialogue neutre</li>
        <li>Rechercher des solutions gagnant-gagnant</li>
        <li>Accepter les différences irréductibles</li>
        </ol>';
    }

    private function getListeningContent(): string
    {
        return '<h2>Qu\'est-ce que l\'écoute active ?</h2>
        <p>L\'écoute active consiste à porter toute son attention à son interlocuteur, non seulement aux mots qu\'il prononce, mais aussi à ses émotions et besoins sous-jacents.</p>

        <h2>Techniques d\'écoute active</h2>
        <p>Pour développer votre écoute active au travail :</p>
        <ul>
        <li>Maintenez un contact visuel approprié</li>
        <li>Posez des questions ouvertes</li>
        <li>Reformulez ce que vous avez compris</li>
        <li>Montrez de l\'empathie</li>
        </ul>';
    }
}
