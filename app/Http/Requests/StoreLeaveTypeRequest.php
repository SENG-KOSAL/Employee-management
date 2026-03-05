<?php

namespace App\Http\Requests;

use App\Support\ActiveCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization enforced in controller (only Admin/HR). Allow authenticated users here.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $activeCompanyId = app(ActiveCompany::class)->id() ?? $this->user()?->company_id;

        return [
            'name' => [
                'required',
                'string',
                'max:191',
                Rule::unique('leave_types', 'name')->where(fn ($query) => $query->where('company_id', $activeCompanyId)),
            ],
            'default_days' => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:2000',
        ];
    }
}