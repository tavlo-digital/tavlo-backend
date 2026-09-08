<?php

namespace App\Http\Requests\Analytics;

use App\Services\Analytics\AnalyticsPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AskInsightRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Vendor/team-member auth is enforced by the route's middleware
        // group (auth:vendor,team_member + vendor.staff.access), same as
        // every other vendor.* route — this only validates request shape.
        return true;
    }

    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'min:2', 'max:300'],
            'insightId' => ['sometimes', 'nullable', 'string', 'max:100'],
            'period' => ['sometimes', 'string', Rule::in([
                AnalyticsPeriod::DAILY, AnalyticsPeriod::WEEKLY, AnalyticsPeriod::MONTHLY,
                AnalyticsPeriod::TODAY, AnalyticsPeriod::CUSTOM,
            ])],
            'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function messages(): array
    {
        return [
            'question.required' => 'A question is required.',
            'from.required_if' => 'A start date is required for a custom period.',
            'to.required_if' => 'An end date is required for a custom period.',
            'to.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }
}
