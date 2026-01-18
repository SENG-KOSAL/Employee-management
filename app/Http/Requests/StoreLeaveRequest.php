<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated user can create a leave for themselves; admins/HR can specify employee_id
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'nullable|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date|before_or_equal:end_date',
            'end_date' => 'required|date|after_or_equal:start_date',
            // optional: will be auto-computed if omitted; allow half-day steps like 0.5, 1.5, 2.5
            'days' => ['nullable', 'numeric', 'min:0.5', 'regex:/^\d+(\.5)?$/'],
            'reason' => 'nullable|string|max:2000',
        ];
    }
}