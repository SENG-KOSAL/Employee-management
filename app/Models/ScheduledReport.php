<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduledReport extends Model
{
    use HasFactory;
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'created_by',
        'name',
        'report_type',
        'format',
        'frequency',
        'filters',
        'recipients',
        'notify_on_ready',
        'is_active',
        'next_run_at',
        'last_run_at',
        'last_generated_at',
        'last_status',
        'last_error',
        'last_file_path',
    ];

    protected $casts = [
        'filters' => 'array',
        'recipients' => 'array',
        'notify_on_ready' => 'boolean',
        'is_active' => 'boolean',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
        'last_generated_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
