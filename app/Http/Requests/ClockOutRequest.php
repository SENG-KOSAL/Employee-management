<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClockOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'nullable|exists:employees,id',
        ];
    }
}