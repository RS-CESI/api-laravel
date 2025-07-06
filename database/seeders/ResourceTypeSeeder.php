<?php

namespace Database\Seeders;

use App\Models\ResourceType;
use Illuminate\Database\Seeder;

class ResourceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $resourceTypes = [
            [
                'name' => 'Article',
                'description' => 'Article de blog ou contenu textuel informatif.',
                'icon' => 'file-text',
                'color' => '#3B82F6',
                'is_active' => true,
                'requires_file' => false,
                'allowed_file_types' => null,
                'sort_order' => 1,
            ],
            [
                'name' => 'Vidéo',
                'description' => 'Contenu vidéo éducatif ou informatif.',
                'icon' => 'play',
                'color' => '#EF4444',
                'is_active' => true,
                'requires_file' => true,
                'allowed_file_types' => ['mp4', 'avi', 'mov', 'webm'],
                'sort_order' => 2,
            ],
            [
                'name' => 'Podcast/Audio',
                'description' => 'Contenu audio, podcast ou enregistrement.',
                'icon' => 'headphones',
                'color' => '#10B981',
                'is_active' => true,
                'requires_file' => true,
                'allowed_file_types' => ['mp3', 'wav', 'ogg', 'm4a'],
                'sort_order' => 3,
            ],
            [
                'name' => 'Document PDF',
                'description' => 'Guide, livret ou document au format PDF.',
                'icon' => 'file',
                'color' => '#F59E0B',
                'is_active' => true,
                'requires_file' => true,
                'allowed_file_types' => ['pdf'],
                'sort_order' => 4,
            ],
            [
                'name' => 'Activité/Jeu',
                'description' => 'Activité interactive ou jeu éducatif.',
                'icon' => 'gamepad-2',
                'color' => '#8B5CF6',
                'is_active' => true,
                'requires_file' => false,
                'allowed_file_types' => null,
                'sort_order' => 5,
            ],
            [
                'name' => 'Quiz',
                'description' => 'Quiz interactif ou questionnaire d\'auto-évaluation.',
                'icon' => 'help-circle',
                'color' => '#EC4899',
                'is_active' => true,
                'requires_file' => false,
                'allowed_file_types' => null,
                'sort_order' => 6,
            ],
            [
                'name' => 'Exercice pratique',
                'description' => 'Exercice à réaliser ou fiche pratique.',
                'icon' => 'clipboard-check',
                'color' => '#06B6D4',
                'is_active' => true,
                'requires_file' => false,
                'allowed_file_types' => null,
                'sort_order' => 7,
            ],
            [
                'name' => 'Témoignage',
                'description' => 'Témoignage personnel ou retour d\'expérience.',
                'icon' => 'message-square',
                'color' => '#84CC16',
                'is_active' => true,
                'requires_file' => false,
                'allowed_file_types' => null,
                'sort_order' => 8,
            ],
            [
                'name' => 'Infographie',
                'description' => 'Infographie ou contenu visuel informatif.',
                'icon' => 'image',
                'color' => '#F97316',
                'is_active' => true,
                'requires_file' => true,
                'allowed_file_types' => ['jpg', 'jpeg', 'png', 'webp', 'svg'],
                'sort_order' => 9,
            ],
            [
                'name' => 'Lien externe',
                'description' => 'Lien vers un site web ou une ressource externe.',
                'icon' => 'external-link',
                'color' => '#6366F1',
                'is_active' => true,
                'requires_file' => false,
                'allowed_file_types' => null,
                'sort_order' => 10,
            ],
        ];

        foreach ($resourceTypes as $resourceType) {
            ResourceType::create($resourceType);
        }

        // Créer quelques types supplémentaires via la factory pour les tests
        ResourceType::factory(3)->create();
    }
}
