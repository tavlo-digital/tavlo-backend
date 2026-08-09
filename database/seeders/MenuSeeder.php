<?php

namespace Database\Seeders;

use App\Models\Allergen;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventorySettings;
use App\Models\MasterMenuCategory;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\SpecialTag;
use App\Models\TaxCategory;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Full menus for the demo vendors.
 *
 * Writes every representation the API reads from:
 *  - `menu_items.allergies` / `.special_tags` JSON *and* the
 *    `menu_item_allergens` / `menu_item_tags` pivots
 *  - `menu_item_translations` alongside the legacy `menu_items.translations` JSON
 *  - modifier groups/options with their own translations
 *  - inventory categories, items and recipe links
 */
class MenuSeeder extends Seeder
{
    /** @var Collection<string, Allergen> */
    private Collection $allergens;

    /** @var Collection<string, SpecialTag> */
    private Collection $tags;

    public function run(): void
    {
        $this->allergens = Allergen::all()->keyBy(fn (Allergen $a) => mb_strtolower($a->name));
        $this->tags = SpecialTag::all()->keyBy('slug');

        if ($this->allergens->isEmpty() || $this->tags->isEmpty()) {
            $this->command->warn('MenuSeeder: reference data missing — run ReferenceDataSeeder first.');

            return;
        }

        foreach ($this->menus() as $slug => $menu) {
            $vendor = Vendor::where('slug', $slug)->first();

            if (! $vendor) {
                $this->command->warn("MenuSeeder: vendor [{$slug}] not found — run VendorSeeder first.");

                continue;
            }

            $this->purgeMenu($vendor);

            $categories = $this->createCategories($vendor, $menu['categories']);
            $modifierGroups = $this->createModifierGroups($vendor, $menu['modifier_groups']);
            $inventory = $this->createInventory($vendor, $menu['inventory']);

            $this->createItems($vendor, $menu['items'], $categories, $modifierGroups, $inventory);

            $this->command->info(sprintf(
                'MenuSeeder: %s — %d items in %d categories, %d modifier groups, %d inventory items.',
                $vendor->name,
                count($menu['items']),
                count($menu['categories']),
                count($menu['modifier_groups']),
                count($menu['inventory'])
            ));
        }
    }

    // ─── Category / modifier / inventory creation ───────────────────────────

    /**
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array<string, MenuCategory> keyed by master category slug
     */
    private function createCategories(Vendor $vendor, array $definitions): array
    {
        $created = [];

        foreach ($definitions as $sortOrder => $definition) {
            $master = MasterMenuCategory::where('slug', $definition['master'])->first();

            if (! $master) {
                $this->command->warn("MenuSeeder: master category [{$definition['master']}] missing — skipped.");

                continue;
            }

            $taxCategory = $this->taxCategory($vendor, $definition['tax']);

            $category = $vendor->menuCategories()->create([
                'master_menu_category_id' => $master->id,
                'default_tax_category' => $definition['tax'],
                'tax_category_id' => $taxCategory?->id,
                'sort_order' => $sortOrder,
                'is_active' => true,
            ]);

            $this->syncTranslations($category, 'localizedTranslations', [
                'en' => ['name' => $definition['name'] ?? $master->name],
            ] + $this->wrap($definition['translations'] ?? [], 'name'));

            $created[$definition['master']] = $category;
        }

        return $created;
    }

    /**
     * @param  array<string, array<string, mixed>>  $definitions
     * @return array<string, ModifierGroup>
     */
    private function createModifierGroups(Vendor $vendor, array $definitions): array
    {
        $created = [];
        $sortOrder = 0;

        foreach ($definitions as $key => $definition) {
            $group = $vendor->modifierGroups()->create([
                'name' => $definition['name'],
                'type' => $definition['type'],
                'min_selection' => $definition['min'],
                'max_selection' => $definition['max'],
                'is_required' => $definition['min'] > 0,
                'tax_category' => $definition['tax'],
                'sort_order' => $sortOrder,
                'is_active' => true,
            ]);

            $this->syncTranslations($group, 'localizedTranslations', [
                'en' => ['name' => $definition['name']],
            ] + $this->wrap($definition['translations'] ?? [], 'name'));

            foreach ($definition['options'] as $optionOrder => $option) {
                $modifierOption = ModifierOption::create([
                    'modifier_group_id' => $group->id,
                    'name' => $option['name'],
                    'price_adjustment' => $option['price'],
                    'sort_order' => $optionOrder,
                    'is_active' => true,
                ]);

                $this->syncTranslations($modifierOption, 'localizedTranslations', [
                    'en' => ['name' => $option['name']],
                ] + $this->wrap($option['translations'] ?? [], 'name'));
            }

            $created[$key] = $group;
            $sortOrder++;
        }

        return $created;
    }

    /**
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array<string, InventoryItem> keyed by item name
     */
    private function createInventory(Vendor $vendor, array $definitions): array
    {
        $categories = [];
        $items = [];

        foreach ($definitions as $definition) {
            $categoryName = $definition['category'];

            if (! isset($categories[$categoryName])) {
                $category = InventoryCategory::create([
                    'vendor_id' => $vendor->id,
                    'name' => $categoryName,
                    'sort_order' => count($categories),
                ]);

                $this->syncTranslations($category, 'localizedTranslations', [
                    'en' => ['name' => $categoryName],
                ]);

                $categories[$categoryName] = $category;
            }

            $item = $vendor->inventoryItems()->create([
                'inventory_category_id' => $categories[$categoryName]->id,
                'name' => $definition['name'],
                'category' => $categoryName,
                'quantity' => $definition['quantity'],
                'unit' => $definition['unit'],
                'min_stock' => $definition['min_stock'],
                'reorder_quantity' => $definition['min_stock'] * 2,
                'cost_per_unit' => $definition['cost'],
                'supplier' => $definition['supplier'],
                'is_critical' => $definition['critical'] ?? false,
                'track_stock' => true,
                'auto_reorder' => false,
                'nutrition' => $definition['nutrition'] ?? null,
            ]);

            $this->syncTranslations($item, 'localizedTranslations', [
                'en' => ['name' => $definition['name'], 'supplier' => $definition['supplier']],
            ]);

            $items[$definition['name']] = $item;
        }

        InventorySettings::updateOrCreate(
            ['vendor_id' => $vendor->id],
            [
                'low_stock_alerts' => true,
                'auto_reorder_enabled' => false,
                'low_stock_threshold' => 10,
                'track_nutrition' => true,
                'link_menu_items' => true,
                'categories' => array_keys($categories),
                'units' => ['g', 'kg', 'ml', 'l', 'piece'],
            ]
        );

        return $items;
    }

    // ─── Menu items ─────────────────────────────────────────────────────────

    /**
     * @param  array<int, array<string, mixed>>  $definitions
     * @param  array<string, MenuCategory>  $categories
     * @param  array<string, ModifierGroup>  $modifierGroups
     * @param  array<string, InventoryItem>  $inventory
     */
    private function createItems(
        Vendor $vendor,
        array $definitions,
        array $categories,
        array $modifierGroups,
        array $inventory,
    ): void {
        $sortOrders = [];

        foreach ($definitions as $definition) {
            $category = $categories[$definition['category']] ?? null;

            if (! $category) {
                $this->command->warn("MenuSeeder: category [{$definition['category']}] missing for [{$definition['name']}] — skipped.");

                continue;
            }

            $taxSlug = $definition['tax'];
            $vatRate = (float) ($this->taxCategory($vendor, $taxSlug)?->vat_rate ?? 20);
            $price = (float) $definition['price'];
            $discountPercent = (float) ($definition['discount_percent'] ?? 0);
            $hasDiscount = $discountPercent > 0;

            $sortOrders[$definition['category']] = ($sortOrders[$definition['category']] ?? -1) + 1;

            $translations = $definition['translations'] ?? [];

            $item = $vendor->menuItems()->create([
                'menu_category_id' => $category->id,
                'name' => $definition['name'],
                'description' => $definition['description'],
                'price' => $price,
                'available' => $definition['available'] ?? true,
                'is_active' => true,
                'calories' => $definition['calories'] ?? 0,
                'fat' => $definition['fat'] ?? 0,
                'carbs' => $definition['carbs'] ?? 0,
                'protein' => $definition['protein'] ?? 0,
                'vat_rate' => $vatRate,
                'tax_category' => $taxSlug,
                'dietary_preference' => $definition['dietary'] ?? null,
                'allergies' => $definition['allergens'] ?? [],
                'special_tags' => $definition['tags'] ?? [],
                'has_discount' => $hasDiscount,
                'discount_percent' => $discountPercent,
                'discounted_price' => $hasDiscount ? round($price * (1 - $discountPercent / 100), 2) : null,
                'paid_addons' => $definition['paid_addons'] ?? [],
                'free_addons' => $definition['free_addons'] ?? [],
                'removable_items' => $definition['removable'] ?? [],
                'translations' => $translations,
                'ingredients' => [],
                'rating' => $definition['rating'] ?? 0,
                'review_count' => $definition['review_count'] ?? 0,
                'ordered_count' => $definition['ordered_count'] ?? 0,
                'sort_order' => $sortOrders[$definition['category']],
            ]);

            $this->syncTranslations($item, 'itemTranslations', [
                'en' => ['name' => $definition['name'], 'description' => $definition['description']],
            ] + $translations);

            $this->attachAllergens($item, $definition['allergens'] ?? []);
            $this->attachTags($item, $definition['tags'] ?? []);
            $this->attachModifierGroups($item, $modifierGroups, $definition['modifiers'] ?? []);
            $this->attachIngredients($item, $inventory, $definition['ingredients'] ?? []);
        }
    }

    /**
     * @param  array<int, string>  $names  canonical allergen names
     */
    private function attachAllergens(MenuItem $item, array $names): void
    {
        $ids = collect($names)
            ->map(fn (string $name) => $this->allergens->get(mb_strtolower($name))?->id)
            ->filter()
            ->unique()
            ->all();

        if ($ids) {
            $item->allergens()->sync($ids);
        }
    }

    /**
     * @param  array<int, string>  $slugs
     */
    private function attachTags(MenuItem $item, array $slugs): void
    {
        $ids = collect($slugs)
            ->map(fn (string $slug) => $this->tags->get($slug)?->id)
            ->filter()
            ->unique()
            ->all();

        if ($ids) {
            $item->tags()->sync($ids);
        }
    }

    /**
     * @param  array<string, ModifierGroup>  $groups
     * @param  array<int, string>  $keys
     */
    private function attachModifierGroups(MenuItem $item, array $groups, array $keys): void
    {
        $payload = [];

        foreach (array_values($keys) as $sortOrder => $key) {
            if (isset($groups[$key])) {
                $payload[$groups[$key]->id] = ['sort_order' => $sortOrder];
            }
        }

        if ($payload) {
            $item->modifierGroups()->sync($payload);
        }
    }

    /**
     * @param  array<string, InventoryItem>  $inventory
     * @param  array<string, array{0: float, 1: string}>  $ingredients  name => [quantity, unit]
     */
    private function attachIngredients(MenuItem $item, array $inventory, array $ingredients): void
    {
        foreach ($ingredients as $name => [$quantity, $unit]) {
            $inventoryItem = $inventory[$name] ?? null;

            if (! $inventoryItem) {
                continue;
            }

            $item->recipeIngredients()->create([
                'inventory_item_id' => $inventoryItem->id,
                'quantity' => $quantity,
                'unit' => $unit,
                'is_critical' => (bool) $inventoryItem->is_critical,
            ]);
        }
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    /**
     * Remove the vendor's current menu so re-seeding is repeatable.
     * Pivots, translations and recipe links go with them via cascade.
     */
    private function purgeMenu(Vendor $vendor): void
    {
        $vendor->menuItems()->withTrashed()->forceDelete();
        $vendor->menuCategories()->delete();
        $vendor->modifierGroups()->withTrashed()->forceDelete();
        $vendor->inventoryItems()->delete();
        $vendor->inventoryCategories()->delete();
        InventorySettings::where('vendor_id', $vendor->id)->delete();
    }

    private function taxCategory(Vendor $vendor, string $slug): ?TaxCategory
    {
        return TaxCategory::where('country', $this->countryCode($vendor->country ?? 'AT'))
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Mirrors MenuItem::resolveCountryCode so seeded VAT matches runtime VAT.
     */
    private function countryCode(string $country): string
    {
        return match (mb_strtolower(trim($country))) {
            'austria' => 'AT',
            'germany' => 'DE',
            'united kingdom', 'uk', 'great britain' => 'GB',
            default => mb_strtoupper(mb_substr($country, 0, 2)),
        };
    }

    /**
     * Turn ['de' => 'Vorspeisen'] into ['de' => ['name' => 'Vorspeisen']].
     *
     * @param  array<string, string>  $values
     * @return array<string, array<string, string>>
     */
    private function wrap(array $values, string $column): array
    {
        return collect($values)
            ->map(fn (string $value) => [$column => $value])
            ->all();
    }

    /**
     * @param  array<string, array<string, string|null>>  $translations  language => columns
     */
    private function syncTranslations(mixed $model, string $relation, array $translations): void
    {
        foreach ($translations as $language => $columns) {
            $model->{$relation}()->updateOrCreate(['language' => $language], $columns);
        }
    }

    // ─── Menu definitions ───────────────────────────────────────────────────

    /**
     * @return array<string, array<string, mixed>> vendor slug => menu
     */
    private function menus(): array
    {
        return [
            'bella-italia' => $this->bellaItaliaMenu(),
            'burger-palace' => $this->burgerPalaceMenu(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bellaItaliaMenu(): array
    {
        return [
            'categories' => [
                ['master' => 'starters', 'tax' => 'food', 'translations' => ['de' => 'Vorspeisen', 'it' => 'Antipasti']],
                ['master' => 'pasta', 'tax' => 'food', 'translations' => ['de' => 'Pasta', 'it' => 'Pasta']],
                ['master' => 'risotto', 'tax' => 'food', 'translations' => ['de' => 'Risotto', 'it' => 'Risotto']],
                ['master' => 'pizza', 'tax' => 'food', 'translations' => ['de' => 'Pizza', 'it' => 'Pizza']],
                ['master' => 'seafood', 'tax' => 'food', 'translations' => ['de' => 'Fisch', 'it' => 'Pesce']],
                ['master' => 'desserts', 'tax' => 'food', 'translations' => ['de' => 'Desserts', 'it' => 'Dolci']],
                ['master' => 'soft-drinks', 'tax' => 'beverage_non_alcoholic', 'translations' => ['de' => 'Alkoholfreie Getränke', 'it' => 'Bevande']],
                ['master' => 'wine-cocktails', 'tax' => 'beverage_alcoholic', 'translations' => ['de' => 'Wein & Cocktails', 'it' => 'Vino & Cocktail']],
            ],

            'modifier_groups' => [
                'portion' => [
                    'name' => 'Portion Size',
                    'type' => 'single',
                    'min' => 1,
                    'max' => 1,
                    'tax' => 'food',
                    'translations' => ['de' => 'Portionsgröße', 'it' => 'Porzione'],
                    'options' => [
                        ['name' => 'Regular', 'price' => 0, 'translations' => ['de' => 'Normal', 'it' => 'Normale']],
                        ['name' => 'Large', 'price' => 3.50, 'translations' => ['de' => 'Groß', 'it' => 'Grande']],
                    ],
                ],
                'pizza_toppings' => [
                    'name' => 'Extra Toppings',
                    'type' => 'multiple',
                    'min' => 0,
                    'max' => 4,
                    'tax' => 'food',
                    'translations' => ['de' => 'Extra Beläge', 'it' => 'Aggiunte'],
                    'options' => [
                        ['name' => 'Extra Mozzarella', 'price' => 2.50, 'translations' => ['de' => 'Extra Mozzarella']],
                        ['name' => 'Prosciutto', 'price' => 3.00, 'translations' => ['de' => 'Prosciutto']],
                        ['name' => 'Truffle Shavings', 'price' => 5.00, 'translations' => ['de' => 'Trüffelhobel']],
                        ['name' => 'Rocket', 'price' => 1.50, 'translations' => ['de' => 'Rucola']],
                    ],
                ],
                'wine_serving' => [
                    'name' => 'Serving',
                    'type' => 'single',
                    'min' => 1,
                    'max' => 1,
                    'tax' => 'beverage_alcoholic',
                    'translations' => ['de' => 'Ausschank', 'it' => 'Servizio'],
                    'options' => [
                        ['name' => 'Glass 0.125 l', 'price' => 0, 'translations' => ['de' => 'Glas 0,125 l']],
                        ['name' => 'Carafe 0.5 l', 'price' => 12.00, 'translations' => ['de' => 'Karaffe 0,5 l']],
                    ],
                ],
            ],

            'inventory' => [
                ['name' => 'Arborio Rice', 'category' => 'Grains', 'quantity' => 25000, 'unit' => 'g', 'min_stock' => 5000, 'cost' => 0.004, 'supplier' => 'Italian Imports GmbH', 'nutrition' => ['calories' => 360, 'fat' => 0.6, 'protein' => 6.8, 'carbs' => 79]],
                ['name' => 'Spaghetti', 'category' => 'Grains', 'quantity' => 30000, 'unit' => 'g', 'min_stock' => 8000, 'cost' => 0.003, 'supplier' => 'Italian Imports GmbH', 'nutrition' => ['calories' => 371, 'fat' => 1.5, 'protein' => 13, 'carbs' => 75]],
                ['name' => 'Fresh Mozzarella', 'category' => 'Dairy', 'quantity' => 5000, 'unit' => 'g', 'min_stock' => 2000, 'cost' => 0.012, 'supplier' => 'Alps Dairy', 'critical' => true, 'nutrition' => ['calories' => 280, 'fat' => 22, 'protein' => 22, 'carbs' => 2.2]],
                ['name' => 'Pecorino Romano', 'category' => 'Dairy', 'quantity' => 4000, 'unit' => 'g', 'min_stock' => 1000, 'cost' => 0.022, 'supplier' => 'Alps Dairy', 'nutrition' => ['calories' => 387, 'fat' => 27, 'protein' => 32, 'carbs' => 3.6]],
                ['name' => 'Mascarpone', 'category' => 'Dairy', 'quantity' => 3000, 'unit' => 'g', 'min_stock' => 1000, 'cost' => 0.010, 'supplier' => 'Alps Dairy', 'nutrition' => ['calories' => 429, 'fat' => 44, 'protein' => 4.8, 'carbs' => 3.5]],
                ['name' => 'San Marzano Tomatoes', 'category' => 'Produce', 'quantity' => 15000, 'unit' => 'g', 'min_stock' => 5000, 'cost' => 0.003, 'supplier' => 'Italian Imports GmbH', 'nutrition' => ['calories' => 26, 'fat' => 0.2, 'protein' => 1.2, 'carbs' => 5.1]],
                ['name' => 'Fresh Basil', 'category' => 'Herbs', 'quantity' => 500, 'unit' => 'g', 'min_stock' => 200, 'cost' => 0.040, 'supplier' => 'Local Farm', 'nutrition' => ['calories' => 22, 'fat' => 0.6, 'protein' => 3.2, 'carbs' => 2.6]],
                ['name' => 'Guanciale', 'category' => 'Meat', 'quantity' => 3000, 'unit' => 'g', 'min_stock' => 1000, 'cost' => 0.025, 'supplier' => 'Italian Imports GmbH', 'critical' => true, 'nutrition' => ['calories' => 655, 'fat' => 69, 'protein' => 8, 'carbs' => 0]],
                ['name' => 'Atlantic Salmon', 'category' => 'Fish', 'quantity' => 8000, 'unit' => 'g', 'min_stock' => 3000, 'cost' => 0.018, 'supplier' => 'Nordic Fresh', 'critical' => true, 'nutrition' => ['calories' => 208, 'fat' => 13, 'protein' => 20, 'carbs' => 0]],
                ['name' => 'Extra Virgin Olive Oil', 'category' => 'Oils', 'quantity' => 10000, 'unit' => 'ml', 'min_stock' => 3000, 'cost' => 0.008, 'supplier' => 'Mediterranean Oils', 'nutrition' => ['calories' => 884, 'fat' => 100, 'protein' => 0, 'carbs' => 0]],
                ['name' => 'Black Truffle', 'category' => 'Premium', 'quantity' => 200, 'unit' => 'g', 'min_stock' => 50, 'cost' => 1.500, 'supplier' => 'Truffle Traders', 'critical' => true, 'nutrition' => ['calories' => 36, 'fat' => 0.5, 'protein' => 5.5, 'carbs' => 7.4]],
            ],

            'items' => [
                [
                    'category' => 'starters',
                    'name' => 'Bruschetta al Pomodoro',
                    'description' => 'Toasted bread topped with fresh tomatoes, basil, garlic and extra virgin olive oil.',
                    'translations' => [
                        'de' => ['name' => 'Bruschetta al Pomodoro', 'description' => 'Geröstetes Brot mit frischen Tomaten, Basilikum, Knoblauch und nativem Olivenöl.'],
                        'it' => ['name' => 'Bruschetta al Pomodoro', 'description' => 'Pane tostato con pomodori freschi, basilico, aglio e olio extravergine.'],
                    ],
                    'price' => 8.90, 'tax' => 'food',
                    'calories' => 280, 'fat' => 12, 'carbs' => 35, 'protein' => 6,
                    'dietary' => 'vegan',
                    'allergens' => ['Gluten'],
                    'tags' => ['popular'],
                    'rating' => 4.6, 'review_count' => 42, 'ordered_count' => 187,
                    'ingredients' => ['San Marzano Tomatoes' => [80, 'g'], 'Fresh Basil' => [3, 'g'], 'Extra Virgin Olive Oil' => [10, 'ml']],
                ],
                [
                    'category' => 'starters',
                    'name' => 'Caprese Salad',
                    'description' => 'Fresh mozzarella, vine-ripened tomatoes, basil and aged balsamic reduction.',
                    'translations' => [
                        'de' => ['name' => 'Caprese Salat', 'description' => 'Frischer Mozzarella, Tomaten, Basilikum und gereifte Balsamico-Reduktion.'],
                        'it' => ['name' => 'Insalata Caprese', 'description' => 'Mozzarella fresca, pomodori maturi, basilico e riduzione di balsamico.'],
                    ],
                    'price' => 11.50, 'tax' => 'food',
                    'calories' => 320, 'fat' => 22, 'carbs' => 8, 'protein' => 18,
                    'dietary' => 'vegetarian',
                    'allergens' => ['Dairy'],
                    'tags' => ['organic'],
                    'rating' => 4.8, 'review_count' => 36, 'ordered_count' => 156,
                    'ingredients' => ['Fresh Mozzarella' => [125, 'g'], 'San Marzano Tomatoes' => [120, 'g'], 'Fresh Basil' => [4, 'g']],
                ],
                [
                    'category' => 'pasta',
                    'name' => 'Spaghetti Carbonara',
                    'description' => 'Classic Roman pasta with guanciale, egg yolk, Pecorino Romano and black pepper.',
                    'translations' => [
                        'de' => ['name' => 'Spaghetti Carbonara', 'description' => 'Römischer Klassiker mit Guanciale, Eigelb, Pecorino Romano und schwarzem Pfeffer.'],
                        'it' => ['name' => 'Spaghetti alla Carbonara', 'description' => 'Classico romano con guanciale, tuorlo, pecorino romano e pepe nero.'],
                    ],
                    'price' => 16.90, 'tax' => 'food',
                    'calories' => 620, 'fat' => 28, 'carbs' => 65, 'protein' => 24,
                    'allergens' => ['Gluten', 'Eggs', 'Dairy'],
                    'tags' => ['chefs_pick', 'popular'],
                    'rating' => 4.9, 'review_count' => 89, 'ordered_count' => 423,
                    'free_addons' => ['Extra Parmesan', 'Extra Pepper'],
                    'paid_addons' => [
                        ['name' => 'Extra Guanciale', 'price' => 3.50, 'taxCategory' => 'food'],
                        ['name' => 'Truffle Shavings', 'price' => 5.00, 'taxCategory' => 'food'],
                    ],
                    'removable' => ['Guanciale', 'Black Pepper'],
                    'modifiers' => ['portion'],
                    'ingredients' => ['Spaghetti' => [125, 'g'], 'Guanciale' => [60, 'g'], 'Pecorino Romano' => [30, 'g']],
                ],
                [
                    'category' => 'pasta',
                    'name' => 'Penne Arrabbiata',
                    'description' => 'Penne with spicy tomato sauce, garlic and chili flakes.',
                    'translations' => [
                        'de' => ['name' => 'Penne Arrabbiata', 'description' => 'Penne mit scharfer Tomatensauce, Knoblauch und Chiliflocken.'],
                        'it' => ['name' => "Penne all'Arrabbiata", 'description' => 'Penne con sugo piccante di pomodoro, aglio e peperoncino.'],
                    ],
                    'price' => 13.90, 'tax' => 'food',
                    'calories' => 480, 'fat' => 14, 'carbs' => 72, 'protein' => 12,
                    'dietary' => 'vegan',
                    'allergens' => ['Gluten'],
                    'tags' => ['spicy'],
                    'rating' => 4.3, 'review_count' => 28, 'ordered_count' => 142,
                    'modifiers' => ['portion'],
                    'ingredients' => ['Spaghetti' => [125, 'g'], 'San Marzano Tomatoes' => [180, 'g']],
                ],
                [
                    'category' => 'risotto',
                    'name' => 'Truffle Mushroom Risotto',
                    'description' => 'Arborio rice with wild mushrooms, black truffle and aged Parmigiano-Reggiano.',
                    'translations' => [
                        'de' => ['name' => 'Trüffel-Pilz-Risotto', 'description' => 'Arborio-Reis mit Waldpilzen, schwarzem Trüffel und gereiftem Parmigiano-Reggiano.'],
                        'it' => ['name' => 'Risotto ai Funghi e Tartufo', 'description' => 'Riso arborio con funghi di bosco, tartufo nero e Parmigiano-Reggiano.'],
                    ],
                    'price' => 19.90, 'tax' => 'food',
                    'calories' => 550, 'fat' => 22, 'carbs' => 68, 'protein' => 14,
                    'dietary' => 'vegetarian',
                    'allergens' => ['Dairy'],
                    'tags' => ['chefs_pick', 'todays_special'],
                    'discount_percent' => 10,
                    'rating' => 4.7, 'review_count' => 67, 'ordered_count' => 298,
                    'ingredients' => ['Arborio Rice' => [110, 'g'], 'Black Truffle' => [4, 'g'], 'Pecorino Romano' => [20, 'g']],
                ],
                [
                    'category' => 'pizza',
                    'name' => 'Margherita Pizza',
                    'description' => 'San Marzano tomato sauce, fresh mozzarella, basil and extra virgin olive oil.',
                    'translations' => [
                        'de' => ['name' => 'Pizza Margherita', 'description' => 'San-Marzano-Tomatensauce, frischer Mozzarella, Basilikum und natives Olivenöl.'],
                        'it' => ['name' => 'Pizza Margherita', 'description' => 'Salsa di pomodoro San Marzano, mozzarella fresca, basilico e olio extravergine.'],
                    ],
                    'price' => 12.90, 'tax' => 'food',
                    'calories' => 750, 'fat' => 28, 'carbs' => 90, 'protein' => 32,
                    'dietary' => 'vegetarian',
                    'allergens' => ['Gluten', 'Dairy'],
                    'tags' => ['popular'],
                    'rating' => 4.6, 'review_count' => 74, 'ordered_count' => 512,
                    'free_addons' => ['Extra Basil', 'Oregano'],
                    'modifiers' => ['pizza_toppings'],
                    'ingredients' => ['San Marzano Tomatoes' => [100, 'g'], 'Fresh Mozzarella' => [125, 'g'], 'Fresh Basil' => [3, 'g']],
                ],
                [
                    'category' => 'pizza',
                    'name' => 'Pizza Diavola',
                    'description' => 'Spicy salami, tomato sauce, mozzarella and chili oil.',
                    'translations' => [
                        'de' => ['name' => 'Pizza Diavola', 'description' => 'Scharfe Salami, Tomatensauce, Mozzarella und Chiliöl.'],
                        'it' => ['name' => 'Pizza Diavola', 'description' => 'Salame piccante, salsa di pomodoro, mozzarella e olio al peperoncino.'],
                    ],
                    'price' => 15.50, 'tax' => 'food',
                    'calories' => 890, 'fat' => 38, 'carbs' => 88, 'protein' => 38,
                    'allergens' => ['Gluten', 'Dairy'],
                    'tags' => ['spicy', 'new'],
                    'rating' => 4.5, 'review_count' => 21, 'ordered_count' => 96,
                    'modifiers' => ['pizza_toppings'],
                    'ingredients' => ['San Marzano Tomatoes' => [100, 'g'], 'Fresh Mozzarella' => [110, 'g']],
                ],
                [
                    'category' => 'seafood',
                    'name' => 'Grilled Salmon Fillet',
                    'description' => 'Atlantic salmon with lemon butter sauce, capers and seasonal vegetables.',
                    'translations' => [
                        'de' => ['name' => 'Gegrilltes Lachsfilet', 'description' => 'Atlantiklachs mit Zitronen-Buttersauce, Kapern und saisonalem Gemüse.'],
                        'it' => ['name' => 'Filetto di Salmone alla Griglia', 'description' => 'Salmone atlantico con salsa al burro e limone, capperi e verdure di stagione.'],
                    ],
                    'price' => 24.90, 'tax' => 'food',
                    'calories' => 480, 'fat' => 26, 'carbs' => 8, 'protein' => 42,
                    'dietary' => 'pescetarian',
                    'allergens' => ['Fish', 'Dairy'],
                    'tags' => ['recommended'],
                    'rating' => 4.5, 'review_count' => 51, 'ordered_count' => 203,
                    'paid_addons' => [['name' => 'Side Pasta', 'price' => 4.50, 'taxCategory' => 'food']],
                    'ingredients' => ['Atlantic Salmon' => [180, 'g'], 'Extra Virgin Olive Oil' => [8, 'ml']],
                ],
                [
                    'category' => 'desserts',
                    'name' => 'Tiramisu',
                    'description' => 'Espresso-soaked ladyfingers layered with mascarpone cream and cocoa.',
                    'translations' => [
                        'de' => ['name' => 'Tiramisu', 'description' => 'In Espresso getränkte Löffelbiskuits mit Mascarponecreme und Kakao.'],
                        'it' => ['name' => 'Tiramisù', 'description' => 'Savoiardi inzuppati nel caffè con crema al mascarpone e cacao.'],
                    ],
                    'price' => 9.50, 'tax' => 'food',
                    'calories' => 420, 'fat' => 24, 'carbs' => 42, 'protein' => 8,
                    'dietary' => 'vegetarian',
                    'allergens' => ['Gluten', 'Eggs', 'Dairy'],
                    'tags' => ['popular', 'chefs_pick'],
                    'rating' => 4.9, 'review_count' => 93, 'ordered_count' => 467,
                    'ingredients' => ['Mascarpone' => [90, 'g']],
                ],
                [
                    'category' => 'desserts',
                    'name' => 'Panna Cotta',
                    'description' => 'Silky vanilla cream with mixed berry compote.',
                    'translations' => [
                        'de' => ['name' => 'Panna Cotta', 'description' => 'Seidige Vanillecreme mit Beerenkompott.'],
                        'it' => ['name' => 'Panna Cotta', 'description' => 'Crema alla vaniglia con composta di frutti di bosco.'],
                    ],
                    'price' => 8.50, 'tax' => 'food',
                    'calories' => 340, 'fat' => 22, 'carbs' => 30, 'protein' => 4,
                    'dietary' => 'vegetarian',
                    'allergens' => ['Dairy'],
                    'tags' => [],
                    'rating' => 4.5, 'review_count' => 29, 'ordered_count' => 156,
                ],
                [
                    'category' => 'soft-drinks',
                    'name' => 'San Pellegrino Sparkling Water',
                    'description' => '750 ml bottle of Italian sparkling mineral water.',
                    'translations' => [
                        'de' => ['name' => 'San Pellegrino Mineralwasser', 'description' => '750-ml-Flasche italienisches Mineralwasser mit Kohlensäure.'],
                        'it' => ['name' => 'San Pellegrino Frizzante', 'description' => 'Bottiglia da 750 ml di acqua minerale frizzante.'],
                    ],
                    'price' => 4.50, 'tax' => 'beverage_non_alcoholic',
                    'calories' => 0,
                    'tags' => [],
                    'rating' => 4.2, 'review_count' => 5, 'ordered_count' => 312,
                ],
                [
                    'category' => 'soft-drinks',
                    'name' => 'Fresh Lemonade',
                    'description' => 'House-made with Sicilian lemons and mint.',
                    'translations' => [
                        'de' => ['name' => 'Hausgemachte Limonade', 'description' => 'Hausgemacht mit sizilianischen Zitronen und Minze.'],
                        'it' => ['name' => 'Limonata Fresca', 'description' => 'Fatta in casa con limoni siciliani e menta.'],
                    ],
                    'price' => 5.50, 'tax' => 'beverage_non_alcoholic',
                    'calories' => 120, 'carbs' => 30,
                    'dietary' => 'vegan',
                    'tags' => ['popular'],
                    'rating' => 4.4, 'review_count' => 18, 'ordered_count' => 189,
                ],
                [
                    'category' => 'wine-cocktails',
                    'name' => 'Prosecco DOC',
                    'description' => 'Chilled Prosecco from Veneto.',
                    'translations' => [
                        'de' => ['name' => 'Prosecco DOC', 'description' => 'Gekühlter Prosecco aus Venetien.'],
                        'it' => ['name' => 'Prosecco DOC', 'description' => 'Prosecco fresco del Veneto.'],
                    ],
                    'price' => 7.50, 'tax' => 'beverage_alcoholic',
                    'calories' => 90, 'carbs' => 3,
                    'allergens' => ['Sulfites'],
                    'tags' => ['popular'],
                    'rating' => 4.6, 'review_count' => 31, 'ordered_count' => 278,
                    'modifiers' => ['wine_serving'],
                ],
                [
                    'category' => 'wine-cocktails',
                    'name' => 'Aperol Spritz',
                    'description' => 'Aperol, Prosecco, soda water and a slice of orange.',
                    'translations' => [
                        'de' => ['name' => 'Aperol Spritz', 'description' => 'Aperol, Prosecco, Soda und eine Orangenscheibe.'],
                        'it' => ['name' => 'Aperol Spritz', 'description' => 'Aperol, prosecco, soda e una fetta di arancia.'],
                    ],
                    'price' => 9.90, 'tax' => 'beverage_alcoholic',
                    'calories' => 150, 'carbs' => 15,
                    'allergens' => ['Sulfites'],
                    'tags' => ['popular', 'recommended'],
                    'rating' => 4.7, 'review_count' => 45, 'ordered_count' => 356,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function burgerPalaceMenu(): array
    {
        return [
            'categories' => [
                ['master' => 'starters', 'tax' => 'food', 'translations' => ['de' => 'Vorspeisen']],
                ['master' => 'burgers', 'tax' => 'food', 'translations' => ['de' => 'Burger']],
                ['master' => 'sides', 'tax' => 'food', 'translations' => ['de' => 'Beilagen']],
                ['master' => 'desserts', 'tax' => 'food', 'translations' => ['de' => 'Desserts']],
                ['master' => 'soft-drinks', 'tax' => 'beverage_non_alcoholic', 'translations' => ['de' => 'Alkoholfreie Getränke']],
                ['master' => 'beer', 'tax' => 'beverage_alcoholic', 'translations' => ['de' => 'Bier']],
            ],

            'modifier_groups' => [
                'doneness' => [
                    'name' => 'Cooking Level',
                    'type' => 'single',
                    'min' => 1,
                    'max' => 1,
                    'tax' => 'food',
                    'translations' => ['de' => 'Garstufe'],
                    'options' => [
                        ['name' => 'Medium', 'price' => 0, 'translations' => ['de' => 'Medium']],
                        ['name' => 'Medium Well', 'price' => 0, 'translations' => ['de' => 'Medium Well']],
                        ['name' => 'Well Done', 'price' => 0, 'translations' => ['de' => 'Durch']],
                    ],
                ],
                'burger_extras' => [
                    'name' => 'Extras',
                    'type' => 'multiple',
                    'min' => 0,
                    'max' => 4,
                    'tax' => 'food',
                    'translations' => ['de' => 'Extras'],
                    'options' => [
                        ['name' => 'Extra Patty', 'price' => 4.50, 'translations' => ['de' => 'Extra Patty']],
                        ['name' => 'Bacon', 'price' => 2.00, 'translations' => ['de' => 'Speck']],
                        ['name' => 'Extra Cheddar', 'price' => 1.50, 'translations' => ['de' => 'Extra Cheddar']],
                        ['name' => 'Jalapeños', 'price' => 1.00, 'translations' => ['de' => 'Jalapeños']],
                    ],
                ],
                'side_choice' => [
                    'name' => 'Side Choice',
                    'type' => 'single',
                    'min' => 1,
                    'max' => 1,
                    'tax' => 'food',
                    'translations' => ['de' => 'Beilage'],
                    'options' => [
                        ['name' => 'Hand-cut Fries', 'price' => 0, 'translations' => ['de' => 'Hausgemachte Pommes']],
                        ['name' => 'Sweet Potato Fries', 'price' => 1.50, 'translations' => ['de' => 'Süßkartoffel-Pommes']],
                        ['name' => 'Side Salad', 'price' => 1.00, 'translations' => ['de' => 'Beilagensalat']],
                    ],
                ],
            ],

            'inventory' => [
                ['name' => 'Beef Patty 180g', 'category' => 'Meat', 'quantity' => 120, 'unit' => 'piece', 'min_stock' => 40, 'cost' => 2.10, 'supplier' => 'Vienna Butchery', 'critical' => true, 'nutrition' => ['calories' => 250, 'fat' => 20, 'protein' => 26, 'carbs' => 0]],
                ['name' => 'Streaky Bacon', 'category' => 'Meat', 'quantity' => 4000, 'unit' => 'g', 'min_stock' => 1500, 'cost' => 0.014, 'supplier' => 'Vienna Butchery', 'nutrition' => ['calories' => 541, 'fat' => 42, 'protein' => 37, 'carbs' => 1.4]],
                ['name' => 'Brioche Bun', 'category' => 'Bakery', 'quantity' => 150, 'unit' => 'piece', 'min_stock' => 50, 'cost' => 0.55, 'supplier' => 'Prater Bakery', 'critical' => true, 'nutrition' => ['calories' => 310, 'fat' => 9, 'protein' => 9, 'carbs' => 48]],
                ['name' => 'Cheddar Slices', 'category' => 'Dairy', 'quantity' => 3000, 'unit' => 'g', 'min_stock' => 1000, 'cost' => 0.016, 'supplier' => 'Alps Dairy', 'nutrition' => ['calories' => 403, 'fat' => 33, 'protein' => 25, 'carbs' => 1.3]],
                ['name' => 'Potatoes', 'category' => 'Produce', 'quantity' => 40000, 'unit' => 'g', 'min_stock' => 10000, 'cost' => 0.002, 'supplier' => 'Local Farm', 'nutrition' => ['calories' => 77, 'fat' => 0.1, 'protein' => 2, 'carbs' => 17]],
                ['name' => 'Iceberg Lettuce', 'category' => 'Produce', 'quantity' => 6000, 'unit' => 'g', 'min_stock' => 2000, 'cost' => 0.003, 'supplier' => 'Local Farm', 'nutrition' => ['calories' => 14, 'fat' => 0.1, 'protein' => 0.9, 'carbs' => 3]],
            ],

            'items' => [
                [
                    'category' => 'starters',
                    'name' => 'Loaded Nachos',
                    'description' => 'Corn tortilla chips with molten cheddar, jalapeños and sour cream.',
                    'translations' => ['de' => ['name' => 'Loaded Nachos', 'description' => 'Tortillachips mit geschmolzenem Cheddar, Jalapeños und Sauerrahm.']],
                    'price' => 9.90, 'tax' => 'food',
                    'calories' => 620, 'fat' => 34, 'carbs' => 58, 'protein' => 18,
                    'dietary' => 'vegetarian',
                    'allergens' => ['Dairy'],
                    'tags' => ['popular', 'spicy'],
                    'rating' => 4.4, 'review_count' => 38, 'ordered_count' => 241,
                    'ingredients' => ['Cheddar Slices' => [60, 'g']],
                ],
                [
                    'category' => 'burgers',
                    'name' => 'Classic Cheeseburger',
                    'description' => '180 g beef patty, cheddar, lettuce, tomato and house sauce in a brioche bun.',
                    'translations' => ['de' => ['name' => 'Classic Cheeseburger', 'description' => '180-g-Rindfleisch-Patty, Cheddar, Salat, Tomate und Hausmacher-Sauce im Briochebrötchen.']],
                    'price' => 14.90, 'tax' => 'food',
                    'calories' => 820, 'fat' => 44, 'carbs' => 52, 'protein' => 46,
                    'allergens' => ['Gluten', 'Dairy', 'Mustard'],
                    'tags' => ['popular'],
                    'rating' => 4.7, 'review_count' => 128, 'ordered_count' => 934,
                    'free_addons' => ['No Onion', 'Extra Sauce'],
                    'removable' => ['Tomato', 'Lettuce', 'House Sauce'],
                    'modifiers' => ['doneness', 'burger_extras', 'side_choice'],
                    'ingredients' => ['Beef Patty 180g' => [1, 'piece'], 'Brioche Bun' => [1, 'piece'], 'Cheddar Slices' => [25, 'g'], 'Iceberg Lettuce' => [20, 'g']],
                ],
                [
                    'category' => 'burgers',
                    'name' => 'Bacon BBQ Burger',
                    'description' => 'Double patty, streaky bacon, smoked cheddar, crispy onions and BBQ sauce.',
                    'translations' => ['de' => ['name' => 'Bacon BBQ Burger', 'description' => 'Doppel-Patty, Frühstücksspeck, geräucherter Cheddar, Röstzwiebeln und BBQ-Sauce.']],
                    'price' => 18.90, 'tax' => 'food',
                    'calories' => 1120, 'fat' => 66, 'carbs' => 58, 'protein' => 68,
                    'allergens' => ['Gluten', 'Dairy', 'Mustard'],
                    'tags' => ['chefs_pick'],
                    'rating' => 4.8, 'review_count' => 96, 'ordered_count' => 612,
                    'modifiers' => ['doneness', 'burger_extras', 'side_choice'],
                    'ingredients' => ['Beef Patty 180g' => [2, 'piece'], 'Brioche Bun' => [1, 'piece'], 'Streaky Bacon' => [40, 'g'], 'Cheddar Slices' => [25, 'g']],
                ],
                [
                    'category' => 'burgers',
                    'name' => 'Garden Veggie Burger',
                    'description' => 'Grilled vegetable patty, avocado, rocket and vegan aioli.',
                    'translations' => ['de' => ['name' => 'Garden Veggie Burger', 'description' => 'Gegrilltes Gemüse-Patty, Avocado, Rucola und veganes Aioli.']],
                    'price' => 13.90, 'tax' => 'food',
                    'calories' => 560, 'fat' => 24, 'carbs' => 64, 'protein' => 18,
                    'dietary' => 'vegan',
                    'allergens' => ['Gluten', 'Soy'],
                    'tags' => ['new', 'organic'],
                    'rating' => 4.3, 'review_count' => 24, 'ordered_count' => 118,
                    'modifiers' => ['side_choice'],
                    'ingredients' => ['Brioche Bun' => [1, 'piece'], 'Iceberg Lettuce' => [25, 'g']],
                ],
                [
                    'category' => 'sides',
                    'name' => 'Hand-cut Fries',
                    'description' => 'Twice-fried potatoes with rosemary salt.',
                    'translations' => ['de' => ['name' => 'Hausgemachte Pommes', 'description' => 'Zweifach frittierte Kartoffeln mit Rosmarinsalz.']],
                    'price' => 5.50, 'tax' => 'food',
                    'calories' => 380, 'fat' => 18, 'carbs' => 48, 'protein' => 5,
                    'dietary' => 'vegan',
                    'tags' => ['popular'],
                    'rating' => 4.6, 'review_count' => 87, 'ordered_count' => 1042,
                    'ingredients' => ['Potatoes' => [250, 'g']],
                ],
                [
                    'category' => 'sides',
                    'name' => 'Crispy Onion Rings',
                    'description' => 'Beer-battered onion rings with smoked paprika dip.',
                    'translations' => ['de' => ['name' => 'Knusprige Zwiebelringe', 'description' => 'In Bierteig frittierte Zwiebelringe mit geräuchertem Paprika-Dip.']],
                    'price' => 6.50, 'tax' => 'food',
                    'calories' => 420, 'fat' => 24, 'carbs' => 44, 'protein' => 6,
                    'dietary' => 'vegetarian',
                    'allergens' => ['Gluten', 'Eggs'],
                    'tags' => [],
                    'rating' => 4.2, 'review_count' => 33, 'ordered_count' => 288,
                ],
                [
                    'category' => 'desserts',
                    'name' => 'New York Cheesecake',
                    'description' => 'Baked vanilla cheesecake with berry coulis.',
                    'translations' => ['de' => ['name' => 'New York Cheesecake', 'description' => 'Gebackener Vanille-Käsekuchen mit Beerencoulis.']],
                    'price' => 7.90, 'tax' => 'food',
                    'calories' => 450, 'fat' => 28, 'carbs' => 42, 'protein' => 8,
                    'dietary' => 'vegetarian',
                    'allergens' => ['Gluten', 'Eggs', 'Dairy'],
                    'tags' => ['recommended'],
                    'rating' => 4.6, 'review_count' => 41, 'ordered_count' => 197,
                ],
                [
                    'category' => 'soft-drinks',
                    'name' => 'Craft Cola',
                    'description' => 'Austrian craft cola, 0.33 l bottle.',
                    'translations' => ['de' => ['name' => 'Craft Cola', 'description' => 'Österreichische Craft-Cola, 0,33-l-Flasche.']],
                    'price' => 4.20, 'tax' => 'beverage_non_alcoholic',
                    'calories' => 140, 'carbs' => 35,
                    'dietary' => 'vegan',
                    'tags' => [],
                    'rating' => 4.1, 'review_count' => 12, 'ordered_count' => 486,
                ],
                [
                    'category' => 'soft-drinks',
                    'name' => 'Vanilla Milkshake',
                    'description' => 'Thick shake with Madagascar vanilla ice cream.',
                    'translations' => ['de' => ['name' => 'Vanille-Milchshake', 'description' => 'Cremiger Shake mit Madagaskar-Vanilleeis.']],
                    'price' => 6.90, 'tax' => 'beverage_non_alcoholic',
                    'calories' => 520, 'fat' => 22, 'carbs' => 68, 'protein' => 12,
                    'dietary' => 'vegetarian',
                    'allergens' => ['Dairy'],
                    'tags' => ['popular'],
                    'rating' => 4.7, 'review_count' => 56, 'ordered_count' => 374,
                ],
                [
                    'category' => 'beer',
                    'name' => 'Vienna Lager 0.5 l',
                    'description' => 'Local amber lager on tap.',
                    'translations' => ['de' => ['name' => 'Wiener Lager 0,5 l', 'description' => 'Regionales Bernstein-Lager vom Fass.']],
                    'price' => 5.90, 'tax' => 'beverage_alcoholic',
                    'calories' => 210, 'carbs' => 17,
                    'allergens' => ['Gluten'],
                    'tags' => ['popular'],
                    'rating' => 4.5, 'review_count' => 62, 'ordered_count' => 703,
                ],
            ],
        ];
    }
}
