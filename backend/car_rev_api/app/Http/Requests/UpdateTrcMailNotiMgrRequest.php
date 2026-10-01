<?php

namespace App\Http\Requests;

use App\Models\TrcMailNotiMgr;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTrcMailNotiMgrRequest extends FormRequest
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
        /** @var TrcMailNotiMgr $mailNotificationManager */
        $mailNotificationManager = $this->route('mailNotificationManager');

        return [
            'employee_code' => ['sometimes', 'required', 'string', 'max:50'],
            'plant' => ['sometimes', 'required', 'string', 'max:20'],
            'position_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'department_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'name_th' => ['sometimes', 'nullable', 'string', 'max:250'],
            'name_en' => ['sometimes', 'nullable', 'string', 'max:250'],
            'email' => [
                'bail',
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique(TrcMailNotiMgr::class, 'EMAIL')->ignore($mailNotificationManager),
            ],
            'active_flag' => ['sometimes', Rule::in(['Y', 'N'])],
            'updated_by' => ['nullable', 'string', 'max:50'],
        ];
    }
}
