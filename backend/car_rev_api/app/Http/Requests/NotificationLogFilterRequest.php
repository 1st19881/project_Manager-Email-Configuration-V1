<?php

namespace App\Http\Requests;

use App\Models\TrcMailNotiLog;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationLogFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var array<string, string> $events */
        $events = config('notification.events', []);

        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::in([
                TrcMailNotiLog::STATUS_PENDING,
                TrcMailNotiLog::STATUS_PROCESSING,
                TrcMailNotiLog::STATUS_SENT,
                TrcMailNotiLog::STATUS_FAILED,
            ])],
            'event_code' => ['nullable', Rule::in(array_keys($events))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * The requested calendar days as a half-open UTC range. Administrators pick
     * days on the report clock while CREATED_AT is stored in UTC, so "1 Oct"
     * means 30 Sep 17:00 UTC up to (not including) 1 Oct 17:00 UTC.
     *
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    public function utcRange(): array
    {
        $timezone = (string) config('notification.report_timezone');
        $from = $this->validated('date_from');
        $to = $this->validated('date_to');

        return [
            $from ? CarbonImmutable::parse($from, $timezone)->startOfDay()->utc() : null,
            $to ? CarbonImmutable::parse($to, $timezone)->addDay()->startOfDay()->utc() : null,
        ];
    }
}
