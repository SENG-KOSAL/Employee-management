<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Employee\EmployeeResource;

class LeaveRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            // Use whenLoaded to avoid accessing null relations
            'employee' => EmployeeResource::make($this->whenLoaded('employee')),
            'employee_id' => $this->employee_id,
            'leave_type' => $this->whenLoaded('leaveType', function () {
                return [
                    'id' => $this->leaveType->id,
                    'name' => $this->leaveType->name,
                ];
            }),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'days' => (float) $this->days,
            'reason' => $this->reason,
            'status' => $this->status,
            'approver_id' => $this->approver_id,
            'approver' => $this->whenLoaded('approver', function () {
                return [
                    'id' => $this->approver->id,
                    'name' => $this->approver->name,
                ];
            }),
            'approver_notes' => $this->approver_notes,
            'decided_at' => $this->decided_at,
            'created_at' => $this->created_at,
        ];
    }
}