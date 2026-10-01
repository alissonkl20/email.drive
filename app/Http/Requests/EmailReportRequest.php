<?php

namespace App\Http\Requests;

use App\Services\Email\EmailReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmailReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['string', Rule::in(EmailReportService::FIELDS)],
            'filters' => ['sometimes', 'array'],
            'filters.folders' => ['sometimes', 'array'],
            'filters.folders.*' => ['string'],
            'filters.date' => ['sometimes', 'array'],
            'filters.date.from' => ['nullable', 'date_format:Y-m-d'],
            'filters.date.to' => ['nullable', 'date_format:Y-m-d'],
            'filters.from' => ['sometimes', 'array'],
            'filters.from.*' => ['string'],
            'filters.subject' => ['sometimes', 'array'],
            'filters.subject.*' => ['string'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['array'],
            'tags.*.subject' => ['sometimes', 'array'],
            'tags.*.subject.*' => ['string'],
            'tags.*.from' => ['sometimes', 'array'],
            'tags.*.from.*' => ['string'],
            'tags.*.body' => ['sometimes', 'array'],
            'tags.*.body.*' => ['string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:5000'],
        ];
    }
}
