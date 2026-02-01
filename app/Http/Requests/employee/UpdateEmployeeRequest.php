<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Employee;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeParam = $this->route('employee');
        $employeeId = $employeeParam;
        $userId = null;
        if ($employeeParam instanceof Employee) {
            $userId = $employeeParam->user?->id;
        }

        return [
            'employee_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('employees', 'employee_code')->ignore($employeeId),
            ],
            'first_name' => 'required|string|max:120',
            'last_name' => 'nullable|string|max:120',
            'email' => [
                'required',
                'email',
                Rule::unique('employees', 'email')->ignore($employeeId),
                Rule::unique('users', 'email')->ignore($userId),
            ],
            // User account fields
            'name' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'name')->ignore($userId),
            ],
            'role' => 'nullable|in:admin,hr,manager,employee',
            'password' => 'nullable|string|min:8',
            'phone' => 'nullable|string|max:30',
            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'address' => 'nullable|string|max:1000',
            'department' => 'nullable|string|max:120',
            'position' => 'nullable|string|max:120',
            'start_date' => 'nullable|date',
            'salary' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,inactive',
            'photo' => 'nullable|image|max:4096',

            // National & Legal Information
            'national_id_number' => 'nullable|string|max:50',
            'nssf_number' => 'nullable|string|max:50',
            'passport_number' => 'nullable|string|max:50',
            'work_permit_number' => 'nullable|string|max:50',
            'nationality' => 'nullable|in:khmer,foreign',

            // Emergency Contact
            'emergency_contact_name' => 'nullable|string|max:150',
            'emergency_contact_phone' => 'nullable|string|max:30',
            'emergency_contact_relationship' => 'nullable|string|max:50',
        ];
    }
}