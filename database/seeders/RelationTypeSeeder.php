<?php

namespace Database\Seeders;

use App\Models\RelationType;
use Illuminate\Database\Seeder;

class RelationTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $relationTypes = [
            [
                'name' => 'Relations parent-enfant',
                'description' => 'Relations entre parents et leurs enfants, à tous les âges de la vie.',
                'icon' => 'baby',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Relations de couple',
                'description' => 'Relations amoureuses et conjugales, union libre, mariage, PACS.',
                'icon' => 'heart',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Relations fraternelles',
                'description' => 'Relations entre frères et sœurs, demi-frères et demi-sœurs.',
                'icon' => 'users',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Relations grands-parents/petits-enfants',
                'description' => 'Relations intergénérationnelles entre grands-parents et petits-enfants.',
                'icon' => 'user-plus',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Relations belle-famille',
                'description' => 'Relations avec la belle-famille, beaux-parents, beaux-frères et belles-sœurs.',
                'icon' => 'home',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Relations amicales',
                'description' => 'Amitiés de longue date, nouvelles amitiés, cercles d\'amis.',
                'icon' => 'smile',
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Relations professionnelles hiérarchiques',
                'description' => 'Relations manager-employé, supérieur-subordonné.',
                'icon' => 'briefcase',
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Relations entre collègues',
                'description' => 'Relations horizontales au travail, équipes, collaboration.',
                'icon' => 'handshake',
                'is_active' => true,
                'sort_order' => 8,
            ],
            [
                'name' => 'Relations client-prestataire',
                'description' => 'Relations commerciales, service client, relations fournisseurs.',
                'icon' => 'shopping-bag',
                'is_active' => true,
                'sort_order' => 9,
            ],
            [
                'name' => 'Relations pédagogiques',
                'description' => 'Relations enseignant-élève, formateur-apprenant, mentor-mentoré.',
                'icon' => 'graduation-cap',
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'Relations de voisinage',
                'description' => 'Relations avec les voisins, syndic, copropriété.',
                'icon' => 'map-pin',
                'is_active' => true,
                'sort_order' => 11,
            ],
            [
                'name' => 'Relations communautaires',
                'description' => 'Relations dans les associations, clubs, communautés religieuses.',
                'icon' => 'globe',
                'is_active' => true,
                'sort_order' => 12,
            ],
        ];

        foreach ($relationTypes as $relationType) {
            RelationType::create($relationType);
        }
    }
}
