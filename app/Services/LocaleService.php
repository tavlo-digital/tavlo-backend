<?php

namespace App\Services;

use App\Models\Language;
use App\Models\TaxCategory;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class LocaleService
{
    public function activeLanguageCodes(): array
    {
        return collect($this->languageOptions())
            ->pluck('code')
            ->all();
    }

    public function languageOptions(): array
    {
        return once(fn () => Language::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->sortBy(fn (Language $language) => $language->code === 'en' ? -1 : $language->sort_order)
            ->values()
            ->map(fn (Language $language) => [
                'code' => $language->code,
                'name' => $language->name,
                'nativeName' => $language->native_name,
                'flag' => $language->flag,
                'direction' => $language->direction,
            ])
            ->all());
    }

    public function normalize(mixed $language): ?string
    {
        return $this->normalizeAgainst($language, $this->activeLanguageCodes());
    }

    public function normalizeList(mixed $languages): array
    {
        $active = $this->activeLanguageCodes();

        return collect(is_array($languages) ? $languages : [])
            ->map(fn ($language) => $this->normalizeAgainst($language, $active))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function supportedLanguages(Vendor $vendor): array
    {
        $vendor->loadMissing('vendorSetting');

        return collect($this->normalizeList($vendor->vendorSetting?->supported_languages ?? []))
            ->prepend('en')
            ->unique()
            ->values()
            ->all();
    }

    public function dashboardLanguage(Vendor $vendor): string
    {
        $vendor->loadMissing('vendorSetting');

        return $this->normalize($vendor->vendorSetting?->dashboard_language) ?? 'en';
    }

    public function resolveCustomerLocale(Request $request, Vendor $vendor): string
    {
        $supported = $this->supportedLanguages($vendor);

        if ($request->query->has('lang')) {
            $explicit = $this->normalize($request->query('lang'));

            return in_array($explicit, $supported, true)
                ? $explicit
                : 'en';
        }

        $preferred = $this->normalize($request->getPreferredLanguage($supported));

        return in_array($preferred, $supported, true)
            ? $preferred
            : 'en';
    }

    public function resolveCustomerLocaleFromHeader(Request $request, Vendor $vendor): string
    {
        $supported = $this->supportedLanguages($vendor);
        $preferred = $this->normalize($request->getPreferredLanguage($supported));

        return in_array($preferred, $supported, true)
            ? $preferred
            : 'en';
    }

    public function resolveHeaderLocale(Request $request): string
    {
        $languages = $this->activeLanguageCodes();
        $preferred = $this->normalize($request->getPreferredLanguage($languages));

        return in_array($preferred, $languages, true)
            ? $preferred
            : 'en';
    }

    public function fallbackChain(Vendor $vendor, string $locale): array
    {
        return collect([
            $this->normalize($locale),
            'en',
        ])->filter()->unique()->values()->all();
    }

    public function translated(
        Model $model,
        string $relation,
        string $field,
        Vendor $vendor,
        string $locale,
        mixed $baseValue = null,
    ): mixed {
        $translations = $model->relationLoaded($relation)
            ? $model->getRelation($relation)
            : $model->{$relation}()->get();

        foreach ($this->fallbackChain($vendor, $locale) as $language) {
            $value = $translations->firstWhere('language', $language)?->{$field};
            if ($value !== null && $value !== '') {
                return $value;
            }
            if ($language === 'en' && $baseValue !== null && $baseValue !== '') {
                return $baseValue;
            }
        }

        return $baseValue;
    }

    public function translationMap(
        Model $model,
        string $relation,
        array $fields,
        array $baseEnglish = [],
    ): array {
        $translations = $model->relationLoaded($relation)
            ? $model->getRelation($relation)
            : $model->{$relation}()->get();

        $map = $translations
            ->mapWithKeys(function ($translation) use ($fields) {
                $values = [];
                foreach ($fields as $field) {
                    $values[$field] = $translation->{$field};
                }

                return [$translation->language => $values];
            })
            ->all();

        if ($baseEnglish !== []) {
            $map['en'] = array_merge($baseEnglish, $map['en'] ?? []);
        }

        return $map;
    }

    public function normalizeTranslationPayload(mixed $value, array $fields): array
    {
        if (! is_array($value)) {
            return [];
        }

        $entries = array_is_list($value)
            ? collect($value)->mapWithKeys(fn ($entry) => [
                is_array($entry) ? ($entry['language'] ?? '') : '' => $entry,
            ])->all()
            : $value;

        $active = $this->activeLanguageCodes();
        $normalized = [];
        foreach ($entries as $language => $entry) {
            $language = $this->normalizeAgainst($language, $active);
            if ($language === null || ! is_array($entry)) {
                continue;
            }

            $normalized[$language] = [];
            foreach ($fields as $field) {
                if (array_key_exists($field, $entry)) {
                    $normalized[$language][$field] = is_string($entry[$field])
                        ? trim($entry[$field])
                        : $entry[$field];
                }
            }
        }

        return $normalized;
    }

    public function syncTranslations(
        Model $model,
        string $relation,
        mixed $payload,
        array $fields,
    ): void {
        foreach ($this->normalizeTranslationPayload($payload, $fields) as $language => $values) {
            if ($values === []) {
                continue;
            }

            $hasContent = collect($values)->contains(fn ($value) => $value !== null && $value !== '');
            if (! $hasContent) {
                $model->{$relation}()->where('language', $language)->delete();

                continue;
            }

            $model->{$relation}()->updateOrCreate(['language' => $language], $values);
        }
    }

    public function translatedTaxCategoryName(string $slug, string $country, string $locale): string
    {
        $lookup = $this->taxCategoryLookup($country, $locale);

        return $lookup[$slug] ?? ucfirst(str_replace(['_', '-'], ' ', $slug));
    }

    private function taxCategoryLookup(string $country, string $locale): array
    {
        static $cache = [];
        $key = "{$country}:{$locale}";

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $categories = TaxCategory::where('country', $country)
            ->with('localizedTranslations')
            ->get();

        $map = [];
        foreach ($categories as $tc) {
            $name = null;

            if ($locale !== 'en') {
                $name = $tc->localizedTranslations->firstWhere('language', $locale)?->name;
            }

            if (! $name) {
                $name = $tc->localizedTranslations->firstWhere('language', 'en')?->name ?? $tc->name;
            }

            $map[$tc->slug] = $name ?: $tc->slug;
        }

        $cache[$key] = $map;

        return $map;
    }

    private function normalizeAgainst(mixed $language, array $active): ?string
    {
        if (! is_string($language)) {
            return null;
        }

        $language = strtolower(trim(str_replace('_', '-', $language)));

        if (in_array($language, $active, true)) {
            return $language;
        }

        $primary = explode('-', $language)[0] ?? '';

        return in_array($primary, $active, true) ? $primary : null;
    }
}
