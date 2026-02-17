<?php

namespace App\Http\Resources\Users;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        // $this->resource is an instance of App\Models\User
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'company' => $this->whenLoaded('company', function () {
                return [
                    'id' => $this->company->id,
                    'name' => $this->company->name,
                    'slug' => $this->company->slug,
                    'status' => $this->company->status,
                ];
            }),
            // optionally include a minimal employee payload if loaded
            'employee' => $this->whenLoaded('employee', function () {
                return [
                    'id' => $this->employee->id,
                    'employee_code' => $this->employee->employee_code ?? null,
                    'full_name' => $this->employee->full_name ?? null,
                    'position' => $this->employee->position ?? null,
                    'department' => $this->employee->department ?? null,
                ];
            }),
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
        ];
    }
}