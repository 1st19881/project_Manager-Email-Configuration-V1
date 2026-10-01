<?php

namespace App\Http\Requests;

use App\Models\TrcMailNotiMgr;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrcMailNotiMgrRequest extends FormRequest
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
        return [
            'employee_code' => ['required', 'string', 'max:50'],
            'plant' => ['required', 'string', 'max:20'],
            'position_name' => ['nullable', 'string', 'max:200'],
            'department_name' => ['nullable', 'string', 'max:200'],
            'name_th' => ['nullable', 'string', 'max:250'],
            'name_en' => ['nullable', 'string', 'max:250'],
            'email' => [
                'bail',
                'required',
                'email',
                'max:255',
                Rule::unique(TrcMailNotiMgr::class, 'EMAIL'),
            ],
            'active_flag' => ['sometimes', Rule::in(['Y', 'N'])],
            'created_by' => ['nullable', 'string', 'max:50'],
        ];
    }
}
