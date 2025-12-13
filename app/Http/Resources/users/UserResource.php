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
            'email' => $this->email,
            'role' => $this->role,
            'employee_id' => $this->employee_id,
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
            'created_at' => $this->created_at,
        ];
    }
}