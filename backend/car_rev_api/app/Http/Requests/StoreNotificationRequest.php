<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationRequest extends FormRequest
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
            'event' => ['required', 'string', Rule::in(array_keys($events))],
            'reference_id' => ['required', 'string', 'max:100'],
            'mgr_select_id' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'event' => is_string($this->input('event')) ? strtoupper(trim($this->input('event'))) : $this->input('event'),
            'reference_id' => is_string($this->input('reference_id')) ? trim($this->input('reference_id')) : $this->input('reference_id'),
        ]);
    }
}
