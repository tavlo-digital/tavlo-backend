<?php

namespace Database\Seeders;

use App\Models\Allergen;
use App\Models\SpecialTag;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        // Allergens (EU food labelling law — 14 major allergens)
        $allergens = [
            ['name' => 'Gluten', 'sort_order' => 1],
            ['name' => 'Dairy', 'sort_order' => 2],
            ['name' => 'Eggs', 'sort_order' => 3],
            ['name' => 'Peanuts', 'sort_order' => 4],
            ['name' => 'Nuts', 'sort_order' => 5],
            ['name' => 'Soy', 'sort_order' => 6],
            ['name' => 'Fish', 'sort_order' => 7],
            ['name' => 'Shellfish', 'sort_order' => 8],
            ['name' => 'Sesame', 'sort_order' => 9],
            ['name' => 'Mustard', 'sort_order' => 10],
            ['name' => 'Celery', 'sort_order' => 11],
            ['name' => 'Lupins', 'sort_order' => 12],
            ['name' => 'Molluscs', 'sort_order' => 13],
            ['name' => 'Sulphites', 'sort_order' => 14],
        ];

        foreach ($allergens as $allergen) {
            Allergen::firstOrCreate(
                ['name' => $allergen['name']],
                ['sort_order' => $allergen['sort_order'], 'is_active' => true]
            );
        }

        $this->command->info('Allergens seeded: ' . Allergen::count());

        // Special tags
        $tags = [
            ['slug' => 'recommended', 'label' => 'Recommended', 'sort_order' => 1],
            ['slug' => 'chefs_pick', 'label' => "Chef's Pick", 'sort_order' => 2],
            ['slug' => 'todays_special', 'label' => "Today's Special", 'sort_order' => 3],
            ['slug' => 'organic', 'label' => 'Organic / Bio', 'sort_order' => 4],
            ['slug' => 'halal', 'label' => 'Halal', 'sort_order' => 5],
            ['slug' => 'popular', 'label' => 'Popular', 'sort_order' => 6],
            ['slug' => 'new', 'label' => 'New', 'sort_order' => 7],
            ['slug' => 'spicy', 'label' => 'Spicy', 'sort_order' => 8],
            ['slug' => 'vegetarian', 'label' => 'Vegetarian', 'sort_order' => 9],
            ['slug' => 'vegan', 'label' => 'Vegan', 'sort_order' => 10],
        ];

        foreach ($tags as $tag) {
            SpecialTag::firstOrCreate(
                ['slug' => $tag['slug']],
                ['label' => $tag['label'], 'sort_order' => $tag['sort_order'], 'is_active' => true]
            );
        }

        $this->command->info('Special tags seeded: ' . SpecialTag::count());
    }
}
