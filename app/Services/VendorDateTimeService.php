<?php

namespace App\Services;

use App\Models\Vendor;
use App\Models\VendorSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Throwable;

class VendorDateTimeService
{
    public function vendorNow(Vendor|VendorSetting|null $source = null): CarbonInterface
    {
        return now()->setTimezone($this->resolveTimezone($source));
    }

    public function formatDate(mixed $value, Vendor|VendorSetting|null $source = null): ?string
    {
        $dateTime = $this->asCarbon($value, $source);

        return $dateTime?->format($this->datePattern($source));
    }

    public function formatTime(mixed $value, Vendor|VendorSetting|null $source = null): ?string
    {
        $dateTime = $this->asCarbon($value, $source);

        return $dateTime?->format($this->timePattern($source));
    }

    public function formatDateTime(mixed $value, Vendor|VendorSetting|null $source = null): ?string
    {
        $dateTime = $this->asCarbon($value, $source);

        if (! $dateTime) {
            return null;
        }

        return $dateTime->format($this->datePattern($source).' '.$this->timePattern($source));
    }

    public function formatBusinessHours(mixed $businessHours, Vendor|VendorSetting|null $source = null): ?array
    {
        if (is_string($businessHours)) {
            $businessHours = json_decode($businessHours, true);
        }

        if (! is_array($businessHours) || $businessHours === []) {
            return null;
        }

        return collect($businessHours)
            ->map(function ($hours) use ($source) {
                if (! is_array($hours) || ($hours['closed'] ?? false)) {
                    return is_array($hours) ? $hours : ['closed' => true];
                }

                if (! empty($hours['open'])) {
                    $hours['open'] = $this->formatLocalTime($hours['open'], $source);
                }

                if (! empty($hours['close'])) {
                    $hours['close'] = $this->formatLocalTime($hours['close'], $source);
                }

                return $hours;
            })
            ->all();
    }

    public function formatLocalTime(string $time, Vendor|VendorSetting|null $source = null): string
    {
        try {
            return Carbon::parse($time)->format($this->timePattern($source));
        } catch (Throwable) {
            return $time;
        }
    }

    private function datePattern(Vendor|VendorSetting|null $source): string
    {
        return match ($this->settings($source)?->date_format) {
            'MM/DD/YYYY' => 'm/d/Y',
            'YYYY-MM-DD' => 'Y-m-d',
            default => 'd.m.Y',
        };
    }

    private function timePattern(Vendor|VendorSetting|null $source): string
    {
        return $this->settings($source)?->time_format === '12h' ? 'g:i A' : 'H:i';
    }

    private function resolveTimezone(Vendor|VendorSetting|null $source): string
    {
        if ($source instanceof VendorSetting) {
            if (! $source->vendor_id) {
                return 'UTC';
            }

            $vendor = $source->relationLoaded('vendor')
                ? $source->vendor
                : Vendor::find($source->vendor_id);

            return $vendor?->resolveTimezone() ?? 'UTC';
        }

        if ($source instanceof Vendor) {
            return $source->resolveTimezone();
        }

        return 'UTC';
    }

    private function settings(Vendor|VendorSetting|null $source): ?VendorSetting
    {
        if ($source instanceof VendorSetting) {
            if (
                array_key_exists('date_format', $source->getAttributes())
                && array_key_exists('time_format', $source->getAttributes())
            ) {
                return $source;
            }

            return VendorSetting::query()
                ->where('vendor_id', $source->vendor_id)
                ->first(['id', 'vendor_id', 'date_format', 'time_format']);
        }

        if (! $source) {
            return null;
        }

        $source->loadMissing('vendorSetting');
        $settings = $source->vendorSetting;

        if (
            $settings
            && array_key_exists('date_format', $settings->getAttributes())
            && array_key_exists('time_format', $settings->getAttributes())
        ) {
            return $settings;
        }

        return VendorSetting::query()
            ->where('vendor_id', $source->id)
            ->first(['id', 'vendor_id', 'date_format', 'time_format']);
    }

    private function asCarbon(mixed $value, Vendor|VendorSetting|null $source = null): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->copy()->setTimezone($this->resolveTimezone($source));
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->setTimezone($this->resolveTimezone($source));
        }

        try {
            return Carbon::parse((string) $value)->setTimezone($this->resolveTimezone($source));
        } catch (Throwable) {
            return null;
        }
    }
}
