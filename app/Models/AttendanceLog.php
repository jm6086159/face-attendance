<?php
// app/Models/AttendanceLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AttendanceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'emp_code', 'action', 'is_late', 'is_undertime', 'undertime_minutes', 'logged_at', 'confidence', 'liveness_pass', 'device_id', 'meta',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
        'confidence'=> 'float',
        'liveness_pass' => 'boolean',
        'is_late' => 'boolean',
        'is_undertime' => 'boolean',
        'undertime_minutes' => 'integer',
        'meta' => 'array',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
