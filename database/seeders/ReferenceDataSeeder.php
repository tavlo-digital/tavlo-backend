<?php

namespace Database\Seeders;

use App\Models\Allergen;
use App\Models\DietaryPreference;
use App\Models\MasterMenuCategory;
use App\Models\SpecialTag;
use App\Models\TaxCategory;
use Illuminate\Database\Seeder;

/**
 * Global, tenant-independent reference data.
 *
 * These rows are shared by every vendor and are referenced by slug/name from
 * menu items (`menu_items.allergies` stores allergen names, `special_tags`
 * stores tag slugs, `dietary_preference` stores a dietary preference slug).
 *
 * Fully idempotent — safe to run on an existing database.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAllergens();
        $this->seedSpecialTags();
        $this->seedDietaryPreferences();
        $this->seedMasterMenuCategories();
        $this->seedTaxCategoryTranslations();
    }

    /**
     * The 14 allergens required by EU food labelling law.
     * `name` is the canonical key stored in `menu_items.allergies`.
     */
    private function seedAllergens(): void
    {
        $allergens = [
            ['name' => 'Gluten',    'icon' => '🌾', 'de' => 'Gluten'],
            ['name' => 'Shellfish', 'icon' => '🦐', 'de' => 'Krebstiere'],
            ['name' => 'Eggs',      'icon' => '🥚', 'de' => 'Eier'],
            ['name' => 'Fish',      'icon' => '🐟', 'de' => 'Fisch'],
            ['name' => 'Peanuts',   'icon' => '🥜', 'de' => 'Erdnüsse'],
            ['name' => 'Soy',       'icon' => '🫘', 'de' => 'Soja'],
            ['name' => 'Dairy',     'icon' => '🥛', 'de' => 'Milch'],
            ['name' => 'Nuts',      'icon' => '🌰', 'de' => 'Schalenfrüchte'],
            ['name' => 'Celery',    'icon' => '🥬', 'de' => 'Sellerie'],
            ['name' => 'Mustard',   'icon' => '🟡', 'de' => 'Senf'],
            ['name' => 'Sesame',    'icon' => '🌱', 'de' => 'Sesam'],
            ['name' => 'Sulfites',  'icon' => '🧪', 'de' => 'Sulfite'],
            ['name' => 'Lupins',    'icon' => '🌸', 'de' => 'Lupinen'],
            ['name' => 'Molluscs',  'icon' => '🐚', 'de' => 'Weichtiere'],
        ];

        foreach ($allergens as $sortOrder => $data) {
            $allergen = Allergen::updateOrCreate(
                ['name' => $data['name']],
                [
                    'icon' => $data['icon'],
                    'sort_order' => $sortOrder + 1,
                    'is_active' => true,
                ]
            );

            $this->syncTranslations($allergen, 'localizedTranslations', 'name', [
                'en' => $data['name'],
                'de' => $data['de'],
            ]);
        }
    }

    /**
     * Customer-facing badges. `slug` is what `menu_items.special_tags` stores.
     * Dietary information lives in `dietary_preferences`, not here.
     */
    private function seedSpecialTags(): void
    {
        $tags = [
            ['slug' => 'recommended',    'label' => 'Recommended',     'icon' => '⭐',   'de' => 'Empfohlen'],
            ['slug' => 'chefs_pick',     'label' => "Chef's Pick",     'icon' => '👨‍🍳', 'de' => 'Empfehlung des Küchenchefs'],
            ['slug' => 'todays_special', 'label' => "Today's Special", 'icon' => '🌟',   'de' => 'Tagesempfehlung'],
            ['slug' => 'popular',        'label' => 'Popular',         'icon' => '🔥',   'de' => 'Beliebt'],
            ['slug' => 'new',            'label' => 'New',             'icon' => '🆕',   'de' => 'Neu'],
            ['slug' => 'spicy',          'label' => 'Spicy',           'icon' => '🌶️',   'de' => 'Scharf'],
            ['slug' => 'organic',        'label' => 'Organic / Bio',   'icon' => '🌿',   'de' => 'Bio'],
            ['slug' => 'halal',          'label' => 'Halal',           'icon' => '🕌',   'de' => 'Halal'],
        ];

        foreach ($tags as $sortOrder => $data) {
            $tag = SpecialTag::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'label' => $data['label'],
                    'icon' => $data['icon'],
                    'sort_order' => $sortOrder + 1,
                    'is_active' => true,
                ]
            );

            $this->syncTranslations($tag, 'localizedTranslations', 'label', [
                'en' => $data['label'],
                'de' => $data['de'],
            ]);
        }
    }

    /**
     * The base rows ship with the dietary preference migration; this only tops
     * up the translations so the admin language screen has content.
     */
    private function seedDietaryPreferences(): void
    {
        $preferences = [
            ['slug' => 'vegetarian',  'name' => 'Vegetarian',  'icon' => '🥬', 'de' => 'Vegetarisch'],
            ['slug' => 'vegan',       'name' => 'Vegan',       'icon' => '🌱', 'de' => 'Vegan'],
            ['slug' => 'pescetarian', 'name' => 'Pescetarian', 'icon' => '🐟', 'de' => 'Pescetarisch'],
        ];

        foreach ($preferences as $sortOrder => $data) {
            $preference = DietaryPreference::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'icon' => $data['icon'],
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                ]
            );

            $this->syncTranslations($preference, 'localizedTranslations', 'name', [
                'en' => $data['name'],
                'de' => $data['de'],
            ]);
        }
    }

    /**
     * The shared category catalogue every vendor picks from.
     * Vendor categories reference these through `master_menu_category_id`.
     */
    private function seedMasterMenuCategories(): void
    {
        $categories = [
            ['slug' => 'starters',        'name' => 'Starters',         'icon' => '🍽️', 'de' => 'Vorspeisen'],
            ['slug' => 'salads',          'name' => 'Salads',           'icon' => '🥗',  'de' => 'Salate'],
            ['slug' => 'soups',           'name' => 'Soups',            'icon' => '🍲',  'de' => 'Suppen'],
            ['slug' => 'pasta',           'name' => 'Pasta',            'icon' => '🍝',  'de' => 'Pasta'],
            ['slug' => 'risotto',         'name' => 'Risotto',          'icon' => '🍚',  'de' => 'Risotto'],
            ['slug' => 'pizza',           'name' => 'Pizza',            'icon' => '🍕',  'de' => 'Pizza'],
            ['slug' => 'burgers',         'name' => 'Burgers',          'icon' => '🍔',  'de' => 'Burger'],
            ['slug' => 'mains',           'name' => 'Main Courses',     'icon' => '🍖',  'de' => 'Hauptspeisen'],
            ['slug' => 'seafood',         'name' => 'Seafood',          'icon' => '🐟',  'de' => 'Fisch & Meeresfrüchte'],
            ['slug' => 'sides',           'name' => 'Sides',            'icon' => '🍟',  'de' => 'Beilagen'],
            ['slug' => 'desserts',        'name' => 'Desserts',         'icon' => '🍰',  'de' => 'Desserts'],
            ['slug' => 'soft-drinks',     'name' => 'Soft Drinks',      'icon' => '🥤',  'de' => 'Alkoholfreie Getränke'],
            ['slug' => 'hot-drinks',      'name' => 'Hot Drinks',       'icon' => '☕',  'de' => 'Heißgetränke'],
            ['slug' => 'beer',            'name' => 'Beer',             'icon' => '🍺',  'de' => 'Bier'],
            ['slug' => 'wine-cocktails',  'name' => 'Wine & Cocktails', 'icon' => '🍷',  'de' => 'Wein & Cocktails'],
        ];

        foreach ($categories as $sortOrder => $data) {
            $category = MasterMenuCategory::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'icon' => $data['icon'],
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                ]
            );

            $this->syncTranslations($category, 'localizedTranslations', 'name', [
                'en' => $data['name'],
                'de' => $data['de'],
            ]);
        }
    }

    /**
     * Tax categories themselves are created by their migration; this adds the
     * German labels used by the vendor dashboard when it runs in `de`.
     */
    private function seedTaxCategoryTranslations(): void
    {
        $germanNames = [
            'food' => 'Speisen',
            'beverage_non_alcoholic' => 'Getränke (alkoholfrei)',
            'beverage_alcoholic' => 'Getränke (alkoholisch)',
        ];

        TaxCategory::query()->get()->each(function (TaxCategory $category) use ($germanNames) {
            $this->syncTranslations($category, 'localizedTranslations', 'name', array_filter([
                'en' => $category->name,
                'de' => $germanNames[$category->slug] ?? null,
            ]));
        });
    }

    /**
     * Upsert one translation row per language on the given HasMany relation.
     *
     * @param  array<string, string>  $values  language => translated value
     */
    private function syncTranslations(mixed $model, string $relation, string $column, array $values): void
    {
        foreach ($values as $language => $value) {
            $model->{$relation}()->updateOrCreate(
                ['language' => $language],
                [$column => $value]
            );
        }
    }
}
