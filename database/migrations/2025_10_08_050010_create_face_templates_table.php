<?php
// database/migrations/2025_10_08_050010_create_face_templates_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('face_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('image_path')->nullable();    // path of uploaded image in /storage/public
            $table->json('embedding')->nullable();        // JSON array of 128 floats if using FastAPI to return embedding
            $table->string('model')->default('face_recognition_dlib'); // model used to extract embeddings
            $table->float('score')->nullable();           // optional similarity score from registration
            $table->string('source')->nullable();         // e.g. 'admin_upload' or 'fastapi'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_templates');
    }
};
