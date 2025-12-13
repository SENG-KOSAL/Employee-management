<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'date' => $this->date?->toDateString(),
            'check_in' => $this->check_in?->toIso8601String(),
            'check_out' => $this->check_out?->toIso8601String(),
            'check_in_time' => $this->check_in?->format('H:i:s'),
            'check_out_time' => $this->check_out?->format('H:i:s'),
            'total_hours' => $this->total_hours !== null ? (float) $this->total_hours : null,
            'is_late' => (bool) $this->is_late,
            'overtime_hours' => (float) $this->overtime_hours,
            'attendance_status' => $this->attendance_status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}