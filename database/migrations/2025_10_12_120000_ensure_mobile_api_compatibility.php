<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Ensure employees table has required columns
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (!Schema::hasColumn('employees', 'emp_code')) {
                    $table->string('emp_code')->nullable()->unique()->after('id');
                }
                if (!Schema::hasColumn('employees', 'first_name')) {
                    $table->string('first_name')->nullable()->after('emp_code');
                }
                if (!Schema::hasColumn('employees', 'last_name')) {
                    $table->string('last_name')->nullable()->after('first_name');
                }
                if (!Schema::hasColumn('employees', 'active')) {
                    $table->boolean('active')->default(true)->after('photo_url');
                }
            });

            // Populate missing emp_code with a deterministic value
            if (Schema::hasColumn('employees', 'emp_code')) {
                $rows = DB::table('employees')->whereNull('emp_code')->orWhere('emp_code', '')->get(['id']);
                foreach ($rows as $row) {
                    DB::table('employees')->where('id', $row->id)->update([
                        'emp_code' => 'EMP'.str_pad((string)$row->id, 4, '0', STR_PAD_LEFT),
                    ]);
                }
            }
        }

        // Ensure attendance_logs table exists/has required columns
        if (!Schema::hasTable('attendance_logs')) {
            Schema::create('attendance_logs', function (Blueprint $table) {
                $table->id();
                if (Schema::hasTable('employees')) {
                    $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('employee_id')->nullable();
                }
                $table->string('emp_code')->nullable();
                $table->string('action', 20); // 'time_in' | 'time_out'
                $table->timestamp('logged_at')->nullable();
                $table->float('confidence')->nullable();
                $table->boolean('liveness_pass')->default(false);
                $table->unsignedBigInteger('device_id')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('attendance_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('attendance_logs', 'employee_id')) {
                    $table->unsignedBigInteger('employee_id')->nullable()->after('id');
                }
                if (!Schema::hasColumn('attendance_logs', 'emp_code')) {
                    $table->string('emp_code')->nullable()->after('employee_id');
                }
                if (!Schema::hasColumn('attendance_logs', 'action')) {
                    $table->string('action', 20)->after('emp_code');
                }
                if (!Schema::hasColumn('attendance_logs', 'logged_at')) {
                    $table->timestamp('logged_at')->nullable()->after('action');
                }
                if (!Schema::hasColumn('attendance_logs', 'meta')) {
                    $table->json('meta')->nullable();
                }
                if (!Schema::hasColumn('attendance_logs', 'liveness_pass')) {
                    $table->boolean('liveness_pass')->default(false);
                }
            });
        }

        // Add index for performance if missing
        if (Schema::hasTable('attendance_logs')) {
            // MySQL-safe: check index existence via information_schema
            $connection = config('database.default');
            $driver = config("database.connections.$connection.driver");
            if ($driver === 'mysql') {
                $schema = DB::getDatabaseName();
                $exists = DB::table('information_schema.statistics')
                    ->where('table_schema', $schema)
                    ->where('table_name', 'attendance_logs')
                    ->where('index_name', 'idx_attendance_employee_date')
                    ->exists();
                if (!$exists) {
                    DB::statement('CREATE INDEX idx_attendance_employee_date ON attendance_logs (employee_id, logged_at)');
                }
            }
        }

        // Sessions table if using database session driver
        if (env('SESSION_DRIVER') === 'database' && !Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    public function down(): void
    {
        // Do not drop or revert compatibility changes automatically.
        // This migration is additive and safe to keep in place.
    }
};

