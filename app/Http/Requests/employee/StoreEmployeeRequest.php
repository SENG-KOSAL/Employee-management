<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Adjust authorization logic (policies) as needed
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('status')) {
            return;
        }

        $status = $this->input('status');

        // Accept booleans / numeric flags from some frontends.
        if (is_bool($status)) {
            $status = $status ? 'active' : 'inactive';
        } elseif (is_numeric($status)) {
            $status = ((int) $status) === 1 ? 'active' : 'inactive';
        }

        if (is_string($status)) {
            $normalized = strtolower(trim($status));

            // Map common synonyms to supported enum values.
            $map = [
                'active' => 'active',
                'inactive' => 'inactive',
                'disabled' => 'inactive',
                'suspended' => 'inactive',
                'terminated' => 'inactive',
            ];

            if (array_key_exists($normalized, $map)) {
                $status = $map[$normalized];
            } else {
                $status = $normalized;
            }
        }

        $this->merge(['status' => $status]);
    }

    public function rules(): array
    {
        return [
            'employee_code' => 'required|string|max:50|unique:employees,employee_code',
            'first_name' => 'required|string|max:120',
            'last_name' => 'nullable|string|max:120',
            // Email must be unique across employees + users (since we auto-create a user)
            'email' => 'required|email|unique:employees,email|unique:users,email',
            // User account fields (login uses users.name)
            'name' => 'nullable|string|max:255|unique:users,name',
            'role' => 'nullable|in:admin,hr,manager,employee',
            // Require a password for auto-linked user account
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:30',
            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'address' => 'nullable|string|max:1000',
            'department' => 'nullable|string|max:120',
            'position' => 'nullable|string|max:120',
            'start_date' => 'nullable|date',
            'salary' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,inactive',
            'photo' => 'nullable|image|max:4096', // max 4MB

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