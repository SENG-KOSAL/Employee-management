<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $leaveTypeId = $this->route('leave_type')?->id ?? $this->route('leaveType')?->id ?? null;

        return [
            'name' => [
                'required',
                'string',
                'max:191',
                Rule::unique('leave_types', 'name')->ignore($leaveTypeId),
            ],
            'default_days' => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:2000',
        ];
    }
}