<?php

namespace Database\Seeders;

use App\Models\Allergen;
use App\Models\MenuItem;
use App\Models\SpecialTag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MigrateMenuItemRelationSeeder extends Seeder
{
    public function run(): void
    {
        // Cache lookups
        $allergensByName = Allergen::all()->keyBy(fn ($a) => strtolower(trim($a->name)));
        $tagsBySlug      = SpecialTag::all()->keyBy(fn ($t) => strtolower(trim($t->slug)));
        $tagsByLabel     = SpecialTag::all()->keyBy(fn ($t) => strtolower(trim($t->label)));

        $items = MenuItem::all();
        $allergensMigrated = 0;
        $tagsMigrated      = 0;

        foreach ($items as $item) {
            // ---- Allergens ------------------------------------------------
            $allergyStrings = is_array($item->allergies) ? $item->allergies : [];
            if (count($allergyStrings) > 0 && $item->allergens()->count() === 0) {
                $ids = [];
                foreach ($allergyStrings as $str) {
                    $key     = strtolower(trim($str));
                    $allergen = $allergensByName[$key] ?? null;
                    if ($allergen) {
                        $ids[] = $allergen->id;
                    }
                }
                if ($ids) {
                    $item->allergens()->sync($ids);
                    $allergensMigrated += count($ids);
                }
            }

            // ---- Special Tags ---------------------------------------------
            $tagStrings = is_array($item->special_tags) ? $item->special_tags : [];
            if (count($tagStrings) > 0 && $item->tags()->count() === 0) {
                $ids = [];
                foreach ($tagStrings as $str) {
                    $key = strtolower(trim($str));
                    $tag = $tagsBySlug[$key] ?? $tagsByLabel[$key] ?? null;
                    if ($tag) {
                        $ids[] = $tag->id;
                    }
                }
                if ($ids) {
                    $item->tags()->sync($ids);
                    $tagsMigrated += count($ids);
                }
            }
        }

        $this->command->info("Allergen pivots created: {$allergensMigrated}");
        $this->command->info("Tag pivots created:      {$tagsMigrated}");
    }
}
