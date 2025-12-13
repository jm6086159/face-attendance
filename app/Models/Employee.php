<?php
// app/Models/Employee.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'emp_code',
        'first_name',
        'last_name',
        'email',
        'department',
        'course',
        'position',
        'photo_url',
        'active',
        'password',
    ];

    protected $casts = [
        'active' => 'boolean',
        // Store employee passwords securely if used
        'password' => 'hashed',
    ];

    // Prevent password from being exposed when serialized
    protected $hidden = ['password'];

    // Relationship: an employee has many face templates
    public function templates()
    {
        return $this->hasMany(FaceTemplate::class);
    }

    // Relationship: an employee has many attendance logs
    public function logs()
    {
        return $this->hasMany(AttendanceLog::class);
    }

    // Append full_name attribute
    protected $appends = ['full_name'];

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
