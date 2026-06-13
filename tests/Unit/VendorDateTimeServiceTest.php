<?php

namespace Tests\Unit;

use App\Models\VendorSetting;
use App\Services\VendorDateTimeService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VendorDateTimeServiceTest extends TestCase
{
    #[DataProvider('formatProvider')]
    public function test_it_maps_vendor_formats_to_customer_display_values(
        string $dateFormat,
        string $timeFormat,
        string $expected,
    ): void {
        $settings = new VendorSetting([
            'date_format' => $dateFormat,
            'time_format' => $timeFormat,
        ]);

        $actual = app(VendorDateTimeService::class)->formatDateTime(
            Carbon::parse('2026-04-18 14:32:10'),
            $settings,
        );

        $this->assertSame($expected, $actual);
    }

    public static function formatProvider(): array
    {
        return [
            'European 24-hour' => ['DD.MM.YYYY', '24h', '18.04.2026 14:32'],
            'US 12-hour' => ['MM/DD/YYYY', '12h', '04/18/2026 2:32 PM'],
            'ISO-style 24-hour' => ['YYYY-MM-DD', '24h', '2026-04-18 14:32'],
        ];
    }
}
