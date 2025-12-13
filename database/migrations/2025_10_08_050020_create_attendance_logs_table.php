<?php
// database/migrations/2025_10_08_050020_create_attendance_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('emp_code')->nullable();      // copy of employee code
            $table->enum('action', ['time_in','time_out']);
            $table->timestamp('logged_at');
            $table->float('confidence')->nullable();      // similarity score
            $table->boolean('liveness_pass')->default(false);
            $table->unsignedBigInteger('device_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
