<?php

namespace App\Http\Requests\Analytics;

use App\Services\Analytics\AnalyticsPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared query validation for the three analytics GET routes that don't
 * already have their own FormRequest (index, insights, suggestedQuestions).
 *
 * Unlike AskInsightRequest/GetFinancialReportRequest, `from`/`to` are NOT
 * required for `period=custom` here — AnalyticsPeriod::buildCustom() has its
 * own deliberate, tested fallback to a sensible default range when either is
 * missing (see test_custom_period_falls_back_to_a_sensible_default_when_
 * dates_are_missing), and that behavior is preserved on purpose. This only
 * closes the actual gap: a *malformed* date string (e.g. `9999-99-99`, which
 * AnalyticsPeriod's own regex-only check let through and silently resolved
 * to the year 10007) is now rejected before it ever reaches the service
 * layer, the same way it already is on askInsights and Financial Reports.
 */
class GetAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Vendor/team-member auth is enforced by the route's middleware
        // group (auth:vendor,team_member + vendor.staff.access), same as
        // every other vendor.* route — this only validates query shape.
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'string', Rule::in([
                AnalyticsPeriod::DAILY, AnalyticsPeriod::WEEKLY, AnalyticsPeriod::MONTHLY,
                AnalyticsPeriod::TODAY, AnalyticsPeriod::CUSTOM,
            ])],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }
}
