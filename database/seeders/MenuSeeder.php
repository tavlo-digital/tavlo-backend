<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\InventorySettings;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $vendor = Vendor::where('slug', 'bella-italia')->first();

        if (! $vendor) {
            $this->command->warn('Bella Italia vendor not found. Run VendorSeeder first.');
            return;
        }

        // Clear existing menu data for this vendor
        $vendor->menuItems()->delete();
        $vendor->menuCategories()->delete();
        $vendor->inventoryItems()->delete();
        InventorySettings::where('vendor_id', $vendor->id)->delete();

        // ---- Categories ----
        $categories = [
            ['name' => 'Antipasti', 'slug' => 'antipasti', 'default_tax_category' => 'food', 'sort_order' => 0],
            ['name' => 'Pasta', 'slug' => 'pasta', 'default_tax_category' => 'food', 'sort_order' => 1],
            ['name' => 'Risotto', 'slug' => 'risotto', 'default_tax_category' => 'food', 'sort_order' => 2],
            ['name' => 'Pesce', 'slug' => 'pesce', 'default_tax_category' => 'food', 'sort_order' => 3],
            ['name' => 'Pizza', 'slug' => 'pizza', 'default_tax_category' => 'food', 'sort_order' => 4],
            ['name' => 'Dolci', 'slug' => 'dolci', 'default_tax_category' => 'food', 'sort_order' => 5],
            ['name' => 'Bevande', 'slug' => 'bevande', 'default_tax_category' => 'drinks_non_alcoholic', 'sort_order' => 6],
            ['name' => 'Vino & Cocktails', 'slug' => 'vino-cocktails', 'default_tax_category' => 'drinks_alcoholic', 'sort_order' => 7],
        ];

        $categoryMap = [];
        foreach ($categories as $cat) {
            $created = $vendor->menuCategories()->create($cat);
            $categoryMap[$cat['slug']] = $created->id;
        }

        // ---- Menu Items ----
        $items = [
            // Antipasti
            [
                'menu_category_id' => $categoryMap['antipasti'],
                'name' => 'Bruschetta al Pomodoro',
                'description' => 'Toasted bread topped with fresh tomatoes, basil, garlic, and extra virgin olive oil.',
                'price' => 8.90,
                'calories' => 280,
                'fat' => 12,
                'carbs' => 35,
                'protein' => 6,
                'vat_rate' => 10,
                'tax_category' => 'food',
                'dietary_preference' => 'vegan',
                'allergies' => ['Gluten'],
                'special_tags' => ['popular'],
                'rating' => 4.6,
                'review_count' => 42,
                'ordered_count' => 187,
                'sort_order' => 0,
            ],
            [
                'menu_category_id' => $categoryMap['antipasti'],
                'name' => 'Caprese Salad',
                'description' => 'Fresh mozzarella, vine-ripened tomatoes, basil, and aged balsamic reduction.',
                'price' => 11.50,
                'calories' => 320,
                'fat' => 22,
                'carbs' => 8,
                'protein' => 18,
                'vat_rate' => 10,
                'tax_category' => 'food',
                'dietary_preference' => 'vegetarian',
                'allergies' => ['Dairy'],
                'special_tags' => [],
                'rating' => 4.8,
                'review_count' => 36,
                'ordered_count' => 156,
                'sort_order' => 1,
            ],

            // Pasta
            [
                'menu_category_id' => $categoryMap['pasta'],
                'name' => 'Spaghetti Carbonara',
                'description' => 'Classic Roman pasta with guanciale, egg yolk, Pecorino Romano, and black pepper.',
                'price' => 16.90,
                'calories' => 620,
                'fat' => 28,
                'carbs' => 65,
                'protein' => 24,
                'vat_rate' => 10,
                'tax_category' => 'food',
                'allergies' => ['Gluten', 'Eggs', 'Dairy'],
                'special_tags' => ['chef_special'],
                'rating' => 4.9,
                'review_count' => 89,
                'ordered_count' => 423,
                'paid_addons' => [['name' => 'Extra Guanciale', 'price' => 3.50], ['name' => 'Truffle Shavings', 'price' => 5.00]],
                'free_addons' => ['Extra Parmesan', 'Extra Pepper'],
                'removable_items' => ['Guanciale'],
                'sort_order' => 0,
            ],
            [
                'menu_category_id' => $categoryMap['pasta'],
                'name' => 'Penne Arrabbiata',
                'description' => 'Penne with spicy tomato sauce, garlic, and chili flakes.',
                'price' => 13.90,
                'calories' => 480,
                'fat' => 14,
                'carbs' => 72,
                'protein' => 12,
                'vat_rate' => 10,
                'tax_category' => 'food',
                'dietary_preference' => 'vegan',
                'allergies' => ['Gluten'],
                'special_tags' => [],
                'rating' => 4.3,
                'review_count' => 28,
                'ordered_count' => 142,
                'sort_order' => 1,
            ],

            // Risotto
            [
                'menu_category_id' => $categoryMap['risotto'],
                'name' => 'Truffle Mushroom Risotto',
                'description' => 'Arborio rice with wild mushrooms, black truffle, and aged Parmigiano-Reggiano.',
                'price' => 19.90,
                'calories' => 550,
                'fat' => 22,
                'carbs' => 68,
                'protein' => 14,
                'vat_rate' => 10,
                'tax_category' => 'food',
                'dietary_preference' => 'vegetarian',
                'allergies' => ['Dairy'],
                'special_tags' => ['chef_special', 'popular'],
                'rating' => 4.7,
                'review_count' => 67,
                'ordered_count' => 298,
                'has_discount' => true,
                'discount_percent' => 10,
                'discounted_price' => 17.91,
                'sort_order' => 0,
            ],

            // Pesce
            [
                'menu_category_id' => $categoryMap['pesce'],
                'name' => 'Grilled Salmon Fillet',
                'description' => 'Atlantic salmon with lemon butter sauce, capers, and seasonal vegetables.',
                'price' => 24.90,
                'calories' => 480,
                'fat' => 26,
                'carbs' => 8,
                'protein' => 42,
                'vat_rate' => 10,
                'tax_category' => 'food',
                'allergies' => ['Fish', 'Dairy'],
                'special_tags' => ['popular'],
                'rating' => 4.5,
                'review_count' => 51,
                'ordered_count' => 203,
                'paid_addons' => [['name' => 'Side Pasta', 'price' => 4.50]],
                'sort_order' => 0,
            ],

            // Pizza
            [
                'menu_category_id' => $categoryMap['pizza'],
                'name' => 'Margherita Pizza',
                'description' => 'San Marzano tomato sauce, fresh mozzarella, basil, and extra virgin olive oil.',
                'price' => 12.90,
                'calories' => 750,
                'fat' => 28,
                'carbs' => 90,
                'protein' => 32,
                'vat_rate' => 10,
                'tax_category' => 'food',
                'dietary_preference' => 'vegetarian',
                'allergies' => ['Gluten', 'Dairy'],
                'special_tags' => ['popular'],
                'rating' => 4.6,
                'review_count' => 74,
                'ordered_count' => 512,
                'free_addons' => ['Extra Basil'],
                'paid_addons' => [['name' => 'Extra Mozzarella', 'price' => 2.50], ['name' => 'Prosciutto', 'price' => 3.00]],
                'sort_order' => 0,
            ],

            // Dolci
            [
                'menu_category_id' => $categoryMap['dolci'],
                'name' => 'Tiramisu',
                'description' => 'Espresso-soaked ladyfingers layered with mascarpone cream and cocoa.',
                'price' => 9.50,
                'calories' => 420,
                'fat' => 24,
                'carbs' => 42,
                'protein' => 8,
                'vat_rate' => 10,
                'tax_category' => 'food',
                'dietary_preference' => 'vegetarian',
                'allergies' => ['Gluten', 'Eggs', 'Dairy'],
                'special_tags' => ['popular'],
                'rating' => 4.9,
                'review_count' => 93,
                'ordered_count' => 467,
                'sort_order' => 0,
            ],
            [
                'menu_category_id' => $categoryMap['dolci'],
                'name' => 'Panna Cotta',
                'description' => 'Silky vanilla cream with mixed berry compote.',
                'price' => 8.50,
                'calories' => 340,
                'fat' => 22,
                'carbs' => 30,
                'protein' => 4,
                'vat_rate' => 10,
                'tax_category' => 'food',
                'dietary_preference' => 'vegetarian',
                'allergies' => ['Dairy'],
                'special_tags' => [],
                'rating' => 4.5,
                'review_count' => 29,
                'ordered_count' => 156,
                'sort_order' => 1,
            ],

            // Bevande (non-alcoholic)
            [
                'menu_category_id' => $categoryMap['bevande'],
                'name' => 'San Pellegrino Sparkling Water',
                'description' => '750ml bottle.',
                'price' => 4.50,
                'calories' => 0,
                'fat' => 0,
                'carbs' => 0,
                'protein' => 0,
                'vat_rate' => 20,
                'tax_category' => 'drinks_non_alcoholic',
                'special_tags' => [],
                'rating' => 4.2,
                'review_count' => 5,
                'ordered_count' => 312,
                'sort_order' => 0,
            ],
            [
                'menu_category_id' => $categoryMap['bevande'],
                'name' => 'Fresh Lemonade',
                'description' => 'House-made with Sicilian lemons and mint.',
                'price' => 5.50,
                'calories' => 120,
                'fat' => 0,
                'carbs' => 30,
                'protein' => 0,
                'vat_rate' => 20,
                'tax_category' => 'drinks_non_alcoholic',
                'dietary_preference' => 'vegan',
                'special_tags' => [],
                'rating' => 4.4,
                'review_count' => 18,
                'ordered_count' => 189,
                'sort_order' => 1,
            ],

            // Vino & Cocktails
            [
                'menu_category_id' => $categoryMap['vino-cocktails'],
                'name' => 'Prosecco DOC',
                'description' => 'Glass of chilled Prosecco from Veneto.',
                'price' => 7.50,
                'calories' => 90,
                'fat' => 0,
                'carbs' => 3,
                'protein' => 0,
                'vat_rate' => 20,
                'tax_category' => 'drinks_alcoholic',
                'allergies' => ['Sulfites'],
                'special_tags' => ['popular'],
                'rating' => 4.6,
                'review_count' => 31,
                'ordered_count' => 278,
                'sort_order' => 0,
            ],
            [
                'menu_category_id' => $categoryMap['vino-cocktails'],
                'name' => 'Aperol Spritz',
                'description' => 'Aperol, Prosecco, soda water, and a slice of orange.',
                'price' => 9.90,
                'calories' => 150,
                'fat' => 0,
                'carbs' => 15,
                'protein' => 0,
                'vat_rate' => 20,
                'tax_category' => 'drinks_alcoholic',
                'allergies' => ['Sulfites'],
                'special_tags' => ['popular'],
                'rating' => 4.7,
                'review_count' => 45,
                'ordered_count' => 356,
                'sort_order' => 1,
            ],
        ];

        foreach ($items as $itemData) {
            $vendor->menuItems()->create(array_merge($itemData, [
                'vendor_id' => $vendor->id,
            ]));
        }

        // ---- Inventory Items ----
        $inventoryItems = [
            ['name' => 'Arborio Rice', 'category' => 'Grains', 'quantity' => 25000, 'unit' => 'g', 'min_stock' => 5000, 'cost_per_unit' => 0.004, 'supplier' => 'Italian Imports GmbH', 'nutrition' => ['calories' => 360, 'fat' => 0.6, 'protein' => 6.8, 'carbs' => 79]],
            ['name' => 'Fresh Mozzarella', 'category' => 'Dairy', 'quantity' => 5000, 'unit' => 'g', 'min_stock' => 2000, 'cost_per_unit' => 0.012, 'supplier' => 'Alps Dairy', 'is_critical' => true, 'nutrition' => ['calories' => 280, 'fat' => 22, 'protein' => 22, 'carbs' => 2.2]],
            ['name' => 'San Marzano Tomatoes', 'category' => 'Produce', 'quantity' => 15000, 'unit' => 'g', 'min_stock' => 5000, 'cost_per_unit' => 0.003, 'supplier' => 'Italian Imports GmbH', 'nutrition' => ['calories' => 26, 'fat' => 0.2, 'protein' => 1.2, 'carbs' => 5.1]],
            ['name' => 'Guanciale', 'category' => 'Meat', 'quantity' => 3000, 'unit' => 'g', 'min_stock' => 1000, 'cost_per_unit' => 0.025, 'supplier' => 'Italian Imports GmbH', 'is_critical' => true, 'nutrition' => ['calories' => 655, 'fat' => 69, 'protein' => 8, 'carbs' => 0]],
            ['name' => 'Pecorino Romano', 'category' => 'Dairy', 'quantity' => 4000, 'unit' => 'g', 'min_stock' => 1000, 'cost_per_unit' => 0.022, 'supplier' => 'Alps Dairy', 'nutrition' => ['calories' => 387, 'fat' => 27, 'protein' => 32, 'carbs' => 3.6]],
            ['name' => 'Extra Virgin Olive Oil', 'category' => 'Oils', 'quantity' => 10000, 'unit' => 'ml', 'min_stock' => 3000, 'cost_per_unit' => 0.008, 'supplier' => 'Mediterranean Oils', 'nutrition' => ['calories' => 884, 'fat' => 100, 'protein' => 0, 'carbs' => 0]],
            ['name' => 'Fresh Basil', 'category' => 'Herbs', 'quantity' => 500, 'unit' => 'g', 'min_stock' => 200, 'cost_per_unit' => 0.040, 'supplier' => 'Local Farm', 'nutrition' => ['calories' => 22, 'fat' => 0.6, 'protein' => 3.2, 'carbs' => 2.6]],
            ['name' => 'Black Truffle', 'category' => 'Premium', 'quantity' => 200, 'unit' => 'g', 'min_stock' => 50, 'cost_per_unit' => 1.500, 'supplier' => 'Truffle Traders', 'is_critical' => true, 'nutrition' => ['calories' => 36, 'fat' => 0.5, 'protein' => 5.5, 'carbs' => 7.4]],
            ['name' => 'Atlantic Salmon', 'category' => 'Fish', 'quantity' => 8000, 'unit' => 'g', 'min_stock' => 3000, 'cost_per_unit' => 0.018, 'supplier' => 'Nordic Fresh', 'is_critical' => true, 'nutrition' => ['calories' => 208, 'fat' => 13, 'protein' => 20, 'carbs' => 0]],
            ['name' => 'Mascarpone', 'category' => 'Dairy', 'quantity' => 3000, 'unit' => 'g', 'min_stock' => 1000, 'cost_per_unit' => 0.010, 'supplier' => 'Alps Dairy', 'nutrition' => ['calories' => 429, 'fat' => 44, 'protein' => 4.8, 'carbs' => 3.5]],
        ];

        foreach ($inventoryItems as $item) {
            $vendor->inventoryItems()->create($item);
        }

        // ---- Inventory Settings ----
        InventorySettings::create([
            'vendor_id' => $vendor->id,
            'low_stock_alerts' => true,
            'auto_reorder_enabled' => false,
            'low_stock_threshold' => 10,
            'track_nutrition' => true,
            'link_menu_items' => true,
        ]);

        $this->command->info('Menu seeded for Bella Italia with ' . count($items) . ' items in ' . count($categories) . ' categories.');
    }
}
