<?php

namespace App\Http\Requests\FinancialReport;

use App\Services\FinancialReports\FinancialReportPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetFinancialReportRequest extends FormRequest
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
            'period' => ['sometimes', 'string', Rule::in(FinancialReportPeriod::KEYS)],
            'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function messages(): array
    {
        return [
            'from.required_if' => 'A start date is required for a custom period.',
            'to.required_if' => 'An end date is required for a custom period.',
            'to.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }
}
