<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Relations familiales',
                'description' => 'Ressources pour améliorer les relations avec la famille : parents, enfants, fratrie, famille élargie.',
                'color' => '#3B82F6',
                'icon' => 'home',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Relations amoureuses',
                'description' => 'Outils et conseils pour développer et maintenir des relations de couple saines et épanouissantes.',
                'color' => '#EF4444',
                'icon' => 'heart',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Relations amicales',
                'description' => 'Conseils pour créer, entretenir et approfondir ses amitiés.',
                'color' => '#10B981',
                'icon' => 'users',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Relations professionnelles',
                'description' => 'Améliorer ses relations au travail : collègues, managers, équipes.',
                'color' => '#F59E0B',
                'icon' => 'briefcase',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Communication',
                'description' => 'Techniques de communication pour mieux exprimer ses besoins et écouter les autres.',
                'color' => '#8B5CF6',
                'icon' => 'message-circle',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Gestion des conflits',
                'description' => 'Outils pour gérer et résoudre les conflits de manière constructive.',
                'color' => '#EC4899',
                'icon' => 'shield',
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Développement personnel',
                'description' => 'Ressources pour mieux se connaître et développer sa confiance en soi.',
                'color' => '#06B6D4',
                'icon' => 'brain',
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Bien-être émotionnel',
                'description' => 'Techniques pour gérer ses émotions et maintenir un équilibre psychologique.',
                'color' => '#84CC16',
                'icon' => 'smile',
                'is_active' => true,
                'sort_order' => 8,
            ],
            [
                'name' => 'Parentalité',
                'description' => 'Conseils et outils pour les parents dans leur relation avec leurs enfants.',
                'color' => '#F97316',
                'icon' => 'baby',
                'is_active' => true,
                'sort_order' => 9,
            ],
            [
                'name' => 'Relations sociales',
                'description' => 'Améliorer ses interactions sociales et développer son réseau social.',
                'color' => '#6366F1',
                'icon' => 'users-2',
                'is_active' => true,
                'sort_order' => 10,
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }

        // Créer quelques catégories supplémentaires via la factory pour les tests
        Category::factory(5)->create();
    }
}
