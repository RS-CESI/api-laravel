<?php

namespace Database\Seeders;

use App\Models\Resource;
use App\Models\RelationType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ResourceRelationTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $resources = Resource::all();
        $relationTypes = RelationType::all();

        if ($resources->isEmpty() || $relationTypes->isEmpty()) {
            $this->command->warn('Attention: Assurez-vous que les resources et relation_types sont créés avant cette table pivot.');
            return;
        }

        // Associations logiques basées sur les catégories
        $categoryRelationMapping = [
            'Relations familiales' => [
                'Relations parent-enfant',
                'Relations fraternelles',
                'Relations grands-parents/petits-enfants',
                'Relations belle-famille'
            ],
            'Relations amoureuses' => [
                'Relations de couple',
                'Relations belle-famille'
            ],
            'Relations amicales' => [
                'Relations amicales',
                'Relations communautaires'
            ],
            'Relations professionnelles' => [
                'Relations professionnelles hiérarchiques',
                'Relations entre collègues',
                'Relations client-prestataire'
            ],
            'Communication' => [
                'Relations parent-enfant',
                'Relations de couple',
                'Relations entre collègues',
                'Relations professionnelles hiérarchiques'
            ],
            'Gestion des conflits' => [
                'Relations parent-enfant',
                'Relations de couple',
                'Relations fraternelles',
                'Relations entre collègues',
                'Relations de voisinage'
            ],
            'Parentalité' => [
                'Relations parent-enfant',
                'Relations grands-parents/petits-enfants'
            ],
            'Relations sociales' => [
                'Relations amicales',
                'Relations de voisinage',
                'Relations communautaires'
            ]
        ];

        foreach ($resources as $resource) {
            $categoryName = $resource->category->name;

            // Récupérer les types de relations correspondants à la catégorie
            $applicableRelationTypeNames = $categoryRelationMapping[$categoryName] ?? [];

            if (!empty($applicableRelationTypeNames)) {
                // Trouver les RelationType correspondants
                $applicableRelationTypes = $relationTypes->whereIn('name', $applicableRelationTypeNames);

                // Attacher 1 à 3 types de relations aléatoirement parmi les applicables
                $selectedRelationTypes = $applicableRelationTypes->random(
                    min($applicableRelationTypes->count(), rand(1, 3))
                );

                $relationTypeIds = $selectedRelationTypes->pluck('id')->toArray();

                // Attacher sans doublons
                $resource->relationTypes()->syncWithoutDetaching($relationTypeIds);
            } else {
                // Si aucune correspondance, attacher 1-2 types aléatoires
                $randomRelationTypes = $relationTypes->random(rand(1, 2));
                $relationTypeIds = $randomRelationTypes->pluck('id')->toArray();
                $resource->relationTypes()->syncWithoutDetaching($relationTypeIds);
            }
        }

        $this->command->info('Associations ressources ↔ types de relations créées avec succès.');
    }
}
