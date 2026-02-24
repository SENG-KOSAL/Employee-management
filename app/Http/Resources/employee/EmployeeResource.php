<?php

namespace App\Http\Resources\Employee;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'employee_code' => $this->employee_code,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth ? $this->date_of_birth->toDateString() : null,
            'address' => $this->address,
            'department' => $this->department,
            'position' => $this->position,
            'start_date' => $this->start_date ? $this->start_date->toDateString() : null,
            'salary' => (float) $this->salary,
            'status' => $this->status,
            'photo_url' => $this->photo_url,

            // National & Legal Information
            'national_id_number' => $this->national_id_number,
            'nssf_number' => $this->nssf_number,
            'passport_number' => $this->passport_number,
            'work_permit_number' => $this->work_permit_number,
            'nationality' => $this->nationality,

            // Emergency Contact
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_relationship' => $this->emergency_contact_relationship,

            // Documents (separate table)
            'documents' => $this->whenLoaded('document', function () {
                return [
                    'id_card_file_path' => $this->document?->id_card_file_path,
                    'contract_file_path' => $this->document?->contract_file_path,
                    'cv_file_path' => $this->document?->cv_file_path,
                    'certificate_file_path' => $this->document?->certificate_file_path,
                ];
            }),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'role' => $this->user->role,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}