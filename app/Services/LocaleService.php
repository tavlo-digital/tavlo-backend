<?php

namespace App\Services;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class LocaleService
{
    public const LANGUAGES = [
        'en', 'de', 'it', 'fr', 'ar', 'tr', 'zh', 'ja', 'sr', 'cs', 'es', 'nl',
    ];

    public function normalize(mixed $language): ?string
    {
        if (! is_string($language)) {
            return null;
        }

        $language = strtolower(trim(str_replace('_', '-', $language)));
        $language = explode('-', $language)[0] ?? '';

        return in_array($language, self::LANGUAGES, true) ? $language : null;
    }

    public function normalizeList(mixed $languages): array
    {
        return collect(is_array($languages) ? $languages : [])
            ->map(fn ($language) => $this->normalize($language))
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

        return 'en';
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

        $normalized = [];
        foreach ($entries as $language => $entry) {
            $language = $this->normalize($language);
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

}
