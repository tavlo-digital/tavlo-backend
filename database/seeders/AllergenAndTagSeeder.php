<?php

namespace Database\Seeders;

use App\Models\Allergen;
use App\Models\SpecialTag;
use Illuminate\Database\Seeder;

class AllergenAndTagSeeder extends Seeder
{
    public function run(): void
    {
        // ── Allergens ──────────────────────────────────────────
        $allergens = [
            ['name' => 'Gluten',    'icon' => '🌾', 'sort_order' => 1],
            ['name' => 'Dairy',     'icon' => '🥛', 'sort_order' => 2],
            ['name' => 'Eggs',      'icon' => '🥚', 'sort_order' => 3],
            ['name' => 'Nuts',      'icon' => '🥜', 'sort_order' => 4],
            ['name' => 'Peanuts',   'icon' => '🥜', 'sort_order' => 5],
            ['name' => 'Soy',       'icon' => '🫘', 'sort_order' => 6],
            ['name' => 'Fish',      'icon' => '🐟', 'sort_order' => 7],
            ['name' => 'Shellfish', 'icon' => '🦐', 'sort_order' => 8],
            ['name' => 'Sesame',    'icon' => '🌱', 'sort_order' => 9],
            ['name' => 'Mustard',   'icon' => '🟡', 'sort_order' => 10],
            ['name' => 'Celery',    'icon' => '🥬', 'sort_order' => 11],
            ['name' => 'Sulfites',  'icon' => '🧪', 'sort_order' => 12],
        ];

        foreach ($allergens as $allergen) {
            Allergen::firstOrCreate(
                ['name' => $allergen['name']],
                $allergen
            );
        }

        // ── Special Tags ───────────────────────────────────────
        $tags = [
            ['slug' => 'recommended',    'label' => 'Recommended',    'icon' => '⭐', 'sort_order' => 1],
            ['slug' => 'chefs-pick',     'label' => "Chef's Pick",    'icon' => '👨‍🍳', 'sort_order' => 2],
            ['slug' => 'todays-special', 'label' => "Today's Special",'icon' => '🌟', 'sort_order' => 3],
            ['slug' => 'organic',        'label' => 'Organic / Bio',  'icon' => '🌿', 'sort_order' => 4],
            ['slug' => 'halal',          'label' => 'Halal',          'icon' => '🕌', 'sort_order' => 5],
        ];

        foreach ($tags as $tag) {
            SpecialTag::firstOrCreate(
                ['slug' => $tag['slug']],
                $tag
            );
        }
    }
}
