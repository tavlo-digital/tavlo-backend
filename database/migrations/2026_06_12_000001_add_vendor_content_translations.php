<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_settings', function (Blueprint $table) {
            $table->string('dashboard_language', 10)->default('en')->after('default_language');
        });

        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['vendor_id', 'name']);
        });

        Schema::create('inventory_category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_category_id')->constrained()->cascadeOnDelete();
            $table->string('language', 10);
            $table->string('name');
            $table->timestamps();

            $table->unique(
                ['inventory_category_id', 'language'],
                'inventory_category_lang_unique'
            );
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignId('inventory_category_id')
                ->nullable()
                ->after('vendor_id')
                ->constrained('inventory_categories')
                ->nullOnDelete();
        });

        Schema::create('inventory_item_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->string('language', 10);
            $table->string('name');
            $table->timestamps();

            $table->unique(
                ['inventory_item_id', 'language'],
                'inventory_item_lang_unique'
            );
        });

        Schema::create('menu_category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_category_id')->constrained()->cascadeOnDelete();
            $table->string('language', 10);
            $table->string('name');
            $table->timestamps();

            $table->unique(
                ['menu_category_id', 'language'],
                'menu_category_lang_unique'
            );
        });

        Schema::create('modifier_group_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->string('language', 10);
            $table->string('name');
            $table->timestamps();

            $table->unique(
                ['modifier_group_id', 'language'],
                'modifier_group_lang_unique'
            );
        });

        Schema::create('modifier_option_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_option_id')->constrained()->cascadeOnDelete();
            $table->string('language', 10);
            $table->string('name');
            $table->timestamps();

            $table->unique(
                ['modifier_option_id', 'language'],
                'modifier_option_lang_unique'
            );
        });

        $this->backfillInventoryCategories();
        $this->backfillTranslations();
        $this->normalizeVendorLanguages();
    }

    public function down(): void
    {
        Schema::dropIfExists('modifier_option_translations');
        Schema::dropIfExists('modifier_group_translations');
        Schema::dropIfExists('menu_category_translations');
        Schema::dropIfExists('inventory_item_translations');

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_category_id');
        });

        Schema::dropIfExists('inventory_category_translations');
        Schema::dropIfExists('inventory_categories');

        Schema::table('vendor_settings', function (Blueprint $table) {
            $table->dropColumn('dashboard_language');
        });
    }

    private function backfillInventoryCategories(): void
    {
        $namesByVendor = [];

        DB::table('inventory_settings')
            ->select(['vendor_id', 'categories'])
            ->orderBy('id')
            ->each(function ($settings) use (&$namesByVendor) {
                $categories = json_decode($settings->categories ?? '[]', true);
                foreach (is_array($categories) ? $categories : [] as $name) {
                    if (is_string($name) && trim($name) !== '') {
                        $namesByVendor[$settings->vendor_id][] = trim($name);
                    }
                }
            });

        DB::table('inventory_items')
            ->select(['vendor_id', 'category'])
            ->whereNotNull('category')
            ->orderBy('id')
            ->each(function ($item) use (&$namesByVendor) {
                if (trim((string) $item->category) !== '') {
                    $namesByVendor[$item->vendor_id][] = trim($item->category);
                }
            });

        foreach ($namesByVendor as $vendorId => $names) {
            $uniqueNames = collect($names)
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->unique(fn ($name) => mb_strtolower($name))
                ->values();

            foreach ($uniqueNames as $sortOrder => $name) {
                $categoryId = DB::table('inventory_categories')->insertGetId([
                    'vendor_id' => $vendorId,
                    'name' => $name,
                    'sort_order' => $sortOrder,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('inventory_category_translations')->insert([
                    'inventory_category_id' => $categoryId,
                    'language' => 'en',
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('inventory_items')
                    ->where('vendor_id', $vendorId)
                    ->whereRaw('LOWER(category) = ?', [mb_strtolower($name)])
                    ->update(['inventory_category_id' => $categoryId]);
            }
        }
    }

    private function backfillTranslations(): void
    {
        DB::table('inventory_items')->orderBy('id')->each(function ($item) {
            DB::table('inventory_item_translations')->updateOrInsert(
                ['inventory_item_id' => $item->id, 'language' => 'en'],
                ['name' => $item->name, 'created_at' => now(), 'updated_at' => now()]
            );
        });

        DB::table('menu_categories')
            ->leftJoin('master_menu_categories', 'master_menu_categories.id', '=', 'menu_categories.master_menu_category_id')
            ->select(['menu_categories.id', 'master_menu_categories.name'])
            ->whereNotNull('master_menu_categories.name')
            ->orderBy('menu_categories.id')
            ->each(function ($category) {
                DB::table('menu_category_translations')->updateOrInsert(
                    ['menu_category_id' => $category->id, 'language' => 'en'],
                    ['name' => $category->name, 'created_at' => now(), 'updated_at' => now()]
                );
            });

        DB::table('modifier_groups')->orderBy('id')->each(function ($group) {
            DB::table('modifier_group_translations')->updateOrInsert(
                ['modifier_group_id' => $group->id, 'language' => 'en'],
                ['name' => $group->name, 'created_at' => now(), 'updated_at' => now()]
            );
        });

        DB::table('modifier_options')->orderBy('id')->each(function ($option) {
            DB::table('modifier_option_translations')->updateOrInsert(
                ['modifier_option_id' => $option->id, 'language' => 'en'],
                ['name' => $option->name, 'created_at' => now(), 'updated_at' => now()]
            );
        });

        DB::table('menu_items')->orderBy('id')->each(function ($item) {
            DB::table('menu_item_translations')->updateOrInsert(
                ['menu_item_id' => $item->id, 'language' => 'en'],
                [
                    'name' => $item->name,
                    'description' => $item->description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $translations = json_decode($item->translations ?? '[]', true);
            foreach (is_array($translations) ? $translations : [] as $language => $translation) {
                if (! is_string($language) || ! is_array($translation) || empty($translation['name'])) {
                    continue;
                }

                DB::table('menu_item_translations')->updateOrInsert(
                    ['menu_item_id' => $item->id, 'language' => strtolower(substr($language, 0, 10))],
                    [
                        'name' => $translation['name'],
                        'description' => $translation['description'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        });
    }

    private function normalizeVendorLanguages(): void
    {
        DB::table('vendor_settings')->orderBy('id')->each(function ($settings) {
            $default = strtolower((string) ($settings->default_language ?: 'en'));
            $supported = json_decode($settings->supported_languages ?? '[]', true);
            $supported = is_array($supported) ? $supported : [];
            $supported = array_values(array_unique(array_filter([
                $default,
                'en',
                ...array_map(fn ($language) => strtolower((string) $language), $supported),
            ])));

            DB::table('vendor_settings')->where('id', $settings->id)->update([
                'dashboard_language' => $default,
                'supported_languages' => json_encode($supported),
            ]);
        });
    }
};
