<?php
// app/Models/FaceTemplate.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FaceTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'image_path', 'embedding', 'model', 'score', 'source',
    ];

    protected $casts = [
        'embedding' => 'array',
        'score'     => 'float',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
