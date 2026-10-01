<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendTestMailRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'event' => ['required', 'string', Rule::in(array_keys($events))],
            'reference_id' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $referenceId = $this->input('reference_id');

        $this->merge([
            'email' => is_string($this->input('email')) ? trim($this->input('email')) : $this->input('email'),
            'event' => is_string($this->input('event')) ? strtoupper(trim($this->input('event'))) : 'BOOKING_CREATED',
            'reference_id' => is_string($referenceId) && trim($referenceId) !== '' ? trim($referenceId) : null,
        ]);
    }
}
