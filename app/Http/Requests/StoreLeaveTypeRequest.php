<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization enforced in controller (only Admin/HR). Allow authenticated users here.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:191|unique:leave_types,name',
            'default_days' => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:2000',
        ];
    }
}