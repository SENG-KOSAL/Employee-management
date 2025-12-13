<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClockInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // optional: allow specifying employee_id if admin/HR
            'employee_id' => 'nullable|exists:employees,id',
            // optional override shift_start used for determining lateness (HH:MM)
            'shift_start' => 'nullable|date_format:H:i',
        ];
    }
}