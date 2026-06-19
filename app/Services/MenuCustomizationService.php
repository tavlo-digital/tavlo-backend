<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Vendor;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MenuCustomizationService
{
    private array $modifierGroups = [];

    private array $modifierOptions = [];

    public function __construct(private readonly LocaleService $locales) {}

    public function paidAddonDefinitions(array $addons): Collection
    {
        return collect($addons)
            ->filter(fn ($addon) => is_array($addon) && trim((string) ($addon['name'] ?? '')) !== '')
            ->values()
            ->map(fn (array $addon, int $index) => [
                'id' => $this->optionId($addon, $index),
                'name' => trim((string) $addon['name']),
                'price' => round((float) ($addon['price'] ?? 0), 2),
                'taxCategory' => $addon['taxCategory'] ?? $addon['tax_category'] ?? null,
                'translations' => $this->translationMap($addon['translations'] ?? []),
            ]);
    }

    public function namedDefinitions(array $entries): Collection
    {
        return collect($entries)
            ->filter(fn ($entry) => is_string($entry) || is_array($entry))
            ->values()
            ->map(function ($entry, int $index) {
                $data = is_array($entry) ? $entry : ['name' => $entry];

                return [
                    'id' => $this->optionId($data, $index),
                    'name' => trim((string) ($data['name'] ?? '')),
                    'translations' => $this->translationMap($data['translations'] ?? []),
                ];
            })
            ->filter(fn (array $entry) => $entry['name'] !== '')
            ->values();
    }

    public function normalizePaidAddons(MenuItem $item, array $selected): array
    {
        $definitions = $this->paidAddonDefinitions($item->paid_addons ?? []);

        return collect($selected)
            ->map(fn ($selection) => $this->matchDefinition($definitions, $selection, 'paid_addons'))
            ->unique('id')
            ->sortBy('id')
            ->map(fn (array $definition) => array_filter([
                'id' => $definition['id'],
                'price' => $definition['price'],
                'taxCategory' => $definition['taxCategory'],
            ], fn ($value) => $value !== null))
            ->values()
            ->all();
    }

    public function normalizeNamedSelections(string $field, array $configured, array $selected): array
    {
        $definitions = $this->namedDefinitions($configured);

        return collect($selected)
            ->map(fn ($selection) => $this->matchDefinition($definitions, $selection, $field)['id'])
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function normalizeMenuPayloadCustomizations(array $data): array
    {
        foreach (['paidAddons', 'freeAddons', 'removableItems'] as $field) {
            if (! array_key_exists($field, $data) || ! is_array($data[$field])) {
                continue;
            }

            $data[$field] = match ($field) {
                'paidAddons' => $this->paidAddonDefinitions($data[$field])->values()->all(),
                default => $this->namedDefinitions($data[$field])->values()->all(),
            };
        }

        return $data;
    }

    public function formatPaidAddons(MenuItem $item, array $selected, Vendor $vendor, string $locale, string $itemTaxCategory, string $vendorCountry): array
    {
        $definitions = $this->paidAddonDefinitions($item->paid_addons ?? [])->keyBy('id');

        return collect($selected)->map(function ($addon) use ($definitions, $vendor, $locale, $itemTaxCategory, $vendorCountry) {
            $definition = $this->findDefinition($definitions, $addon);
            $id = $definition['id'] ?? $this->selectionId($addon);
            $legacyName = is_array($addon) ? ($addon['name'] ?? null) : null;
            $vatRate = TaxCalculationService::addonVatRate(is_array($addon) ? $addon : [], $itemTaxCategory, $vendorCountry);

            return array_filter([
                'id' => $id,
                'name' => $definition
                    ? $this->definitionName($definition, $vendor, $locale)
                    : (string) ($legacyName ?? ''),
                'price' => TaxCalculationService::gross((float) (is_array($addon) ? ($addon['price'] ?? 0) : 0), $vatRate),
                'vat_rate' => $vatRate,
            ], fn ($value) => $value !== null);
        })->values()->all();
    }

    public function formatNamedSelections(array $configured, array $selected, Vendor $vendor, string $locale): array
    {
        $definitions = $this->namedDefinitions($configured)->keyBy('id');

        return collect($selected)->map(function ($selection) use ($definitions, $vendor, $locale) {
            $definition = $this->findDefinition($definitions, $selection);

            if ($definition) {
                return $this->definitionName($definition, $vendor, $locale);
            }

            if (is_array($selection)) {
                return (string) ($selection['name'] ?? '');
            }

            if (is_string($selection)) {
                return $selection;
            }

            return '';
        })->filter(fn (string $name) => $name !== '')->values()->all();
    }

    public function formatSelectedModifiers(array $selected, Vendor $vendor, string $locale, string $itemTaxCategory, string $vendorCountry): array
    {
        return collect($selected)->map(function ($group) use ($vendor, $locale, $itemTaxCategory, $vendorCountry) {
            if (! is_array($group)) {
                return null;
            }

            $groupId = (int) ($group['modifier_group_id'] ?? $group['id'] ?? 0);
            $model = $this->modifierGroup($groupId);
            $groupTaxCategory = $group['tax_category'] ?? $model?->tax_category ?? '';
            $vatRate = TaxCalculationService::modifierGroupVatRate($groupTaxCategory, $itemTaxCategory, $vendorCountry);

            $options = collect($group['options'] ?? [])->map(function ($option) use ($vatRate, $vendor, $locale) {
                if (! is_array($option)) {
                    return null;
                }

                $optionId = (int) ($option['id'] ?? $option['option_id'] ?? 0);
                $model = $this->modifierOption($optionId);

                return [
                    'id' => $optionId ?: null,
                    'name' => $model
                        ? $this->locales->translated($model, 'localizedTranslations', 'name', $vendor, $locale, $model->name)
                        : ($option['name'] ?? null),
                    'price_adjustment' => TaxCalculationService::gross((float) ($option['price_adjustment'] ?? 0), $vatRate),
                ];
            })->filter()->values()->all();

            return array_filter(array_merge($group, [
                'name' => $model
                    ? $this->locales->translated($model, 'localizedTranslations', 'name', $vendor, $locale, $model->name)
                    : ($group['name'] ?? null),
                'type' => $group['type'] ?? $model?->type,
                'is_required' => array_key_exists('is_required', $group) ? (bool) $group['is_required'] : (bool) ($model?->is_required ?? false),
                'min_selection' => (int) ($group['min_selection'] ?? $model?->min_selection ?? 0),
                'max_selection' => (int) ($group['max_selection'] ?? $model?->max_selection ?? 1),
                'vat_rate' => $vatRate,
                'options' => $options,
            ]), fn ($value) => $value !== null);
        })->filter()->values()->all();
    }

    public function formatPaidAddonDefinitions(array $addons, Vendor $vendor, string $locale, string $itemTaxCategory, string $vendorCountry): array
    {
        return $this->paidAddonDefinitions($addons)
            ->map(function (array $definition) use ($vendor, $locale, $itemTaxCategory, $vendorCountry) {
                $vatRate = TaxCalculationService::addonVatRate($definition, $itemTaxCategory, $vendorCountry);

                return [
                    'id' => $definition['id'],
                    'name' => $this->definitionName($definition, $vendor, $locale),
                    'price' => TaxCalculationService::gross((float) $definition['price'], $vatRate),
                    'vat_rate' => $vatRate,
                ];
            })->values()->all();
    }

    public function formatNamedDefinitions(array $entries, Vendor $vendor, string $locale): array
    {
        return $this->namedDefinitions($entries)
            ->map(fn (array $definition) => [
                'id' => $definition['id'],
                'name' => $this->definitionName($definition, $vendor, $locale),
            ])
            ->values()
            ->all();
    }

    public function menuItemName(MenuItem $item, Vendor $vendor, string $locale): string
    {
        $translations = $item->relationLoaded('itemTranslations')
            ? $item->itemTranslations
            : $item->itemTranslations()->get();

        foreach ($this->locales->fallbackChain($vendor, $locale) as $language) {
            $relationalName = $translations->firstWhere('language', $language)?->name;
            if (is_string($relationalName) && trim($relationalName) !== '') {
                return trim($relationalName);
            }

            $legacyName = $item->translations[$language]['name'] ?? null;
            if (is_string($legacyName) && trim($legacyName) !== '') {
                return trim($legacyName);
            }

            if ($language === 'en' && trim((string) $item->name) !== '') {
                return (string) $item->name;
            }
        }

        return (string) $item->name;
    }

    private function matchDefinition(Collection $definitions, mixed $selection, string $field): array
    {
        $matched = $this->findDefinition($definitions, $selection);
        if ($matched) {
            return $matched;
        }

        throw ValidationException::withMessages([
            $field => ["The selected {$field} value is not available for this menu item."],
        ]);
    }

    private function findDefinition(Collection $definitions, mixed $selection): ?array
    {
        $id = $this->selectionId($selection);
        if ($id !== null) {
            $matched = $definitions->firstWhere('id', $id);
            if ($matched) {
                return $matched;
            }
        }

        $name = $this->selectionName($selection);
        if ($name === null) {
            return null;
        }

        return $definitions->first(
            fn (array $definition) => $this->definitionMatchesName($definition, $name)
        );
    }

    private function selectionId(mixed $selection): ?int
    {
        if (is_int($selection) || (is_string($selection) && ctype_digit($selection))) {
            return (int) $selection;
        }

        if (is_array($selection)) {
            $id = $selection['id'] ?? $selection['option_id'] ?? null;

            return is_numeric($id) ? (int) $id : null;
        }

        return null;
    }

    private function selectionName(mixed $selection): ?string
    {
        if (is_string($selection) && ! ctype_digit($selection)) {
            return trim($selection);
        }

        if (is_array($selection)) {
            $name = trim((string) ($selection['name'] ?? ''));

            return $name !== '' ? $name : null;
        }

        return null;
    }

    private function optionId(array $data, int $index): int
    {
        $id = $data['id'] ?? null;

        return is_numeric($id) && (int) $id > 0 ? (int) $id : $index + 1;
    }

    private function definitionName(array $definition, Vendor $vendor, string $locale): string
    {
        foreach ($this->locales->fallbackChain($vendor, $locale) as $language) {
            $translation = $definition['translations'][$language] ?? null;
            if (is_array($translation)) {
                $translation = $translation['name'] ?? null;
            }

            if (is_string($translation) && trim($translation) !== '') {
                return trim($translation);
            }
        }

        return $definition['name'];
    }

    private function definitionMatchesName(array $definition, string $name): bool
    {
        $needle = mb_strtolower(trim($name));
        if ($needle === mb_strtolower(trim((string) $definition['name']))) {
            return true;
        }

        foreach ($definition['translations'] ?? [] as $translation) {
            $translated = is_array($translation) ? ($translation['name'] ?? '') : $translation;

            if ($needle === mb_strtolower(trim((string) $translated))) {
                return true;
            }
        }

        return false;
    }

    private function translationMap(mixed $translations): array
    {
        if (! is_array($translations)) {
            return [];
        }

        return collect($translations)->mapWithKeys(function ($value, $language) {
            $language = $this->locales->normalize($language);
            if (! $language) {
                return [];
            }

            if (is_array($value)) {
                $name = trim((string) ($value['name'] ?? ''));
            } else {
                $name = trim((string) $value);
            }

            return $name !== '' ? [$language => ['name' => $name]] : [];
        })->all();
    }

    private function modifierGroup(int $id): ?ModifierGroup
    {
        if ($id <= 0) {
            return null;
        }

        if (! array_key_exists($id, $this->modifierGroups)) {
            $this->modifierGroups[$id] = ModifierGroup::with('localizedTranslations')->find($id);
        }

        return $this->modifierGroups[$id];
    }

    private function modifierOption(int $id): ?ModifierOption
    {
        if ($id <= 0) {
            return null;
        }

        if (! array_key_exists($id, $this->modifierOptions)) {
            $this->modifierOptions[$id] = ModifierOption::with('localizedTranslations')->find($id);
        }

        return $this->modifierOptions[$id];
    }
}
