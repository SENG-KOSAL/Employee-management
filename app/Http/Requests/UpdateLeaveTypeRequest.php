<?php

namespace App\Http\Requests;

use App\Support\ActiveCompany;
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
        $activeCompanyId = app(ActiveCompany::class)->id() ?? $this->user()?->company_id;

        return [
            'name' => [
                'required',
                'string',
                'max:191',
                Rule::unique('leave_types', 'name')
                    ->where(fn ($query) => $query->where('company_id', $activeCompanyId))
                    ->ignore($leaveTypeId),
            ],
            'default_days' => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:2000',
        ];
    }
}