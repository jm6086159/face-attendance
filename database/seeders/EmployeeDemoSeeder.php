<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Employee;
use App\Models\AttendanceLog;

class EmployeeDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Create or fetch a demo employee
        $employee = Employee::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'emp_code'   => 'EMP001',
                'first_name' => 'Test',
                'last_name'  => 'User',
                'department' => 'CBE',
                'course'     => 'BSIT',
                'position'   => 'BSIT Instructor',
                'active'     => true,
            ]
        );

        // Generate recent attendance logs on weekdays
        $start = Carbon::now()->subDays(20)->startOfDay();
        $end   = Carbon::now()->endOfDay();

        // Clear any existing recent logs for this demo period to avoid duplicates
        AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('logged_at', [$start, $end])
            ->delete();

        $day = clone $start;
        while ($day->lessThanOrEqualTo($end)) {
            // Skip weekends
            if (!in_array($day->dayOfWeekIso, [6,7], true)) {
                // 80% present, 10% late, 10% half day
                $rnd = crc32($day->toDateString()) % 100;
                if ($rnd < 80) {
                    // Present
                    $in  = (clone $day)->setTime(8, 30 + ($rnd % 10));
                    $out = (clone $day)->setTime(17, 30 + ($rnd % 10));
                } elseif ($rnd < 90) {
                    // Late
                    $in  = (clone $day)->setTime(9, 10 + ($rnd % 20));
                    $out = (clone $day)->setTime(17, 40 + ($rnd % 10));
                } else {
                    // Half day
                    $in  = (clone $day)->setTime(8, 30);
                    $out = (clone $day)->setTime(12, 30);
                }

                AttendanceLog::create([
                    'employee_id'  => $employee->id,
                    'emp_code'     => $employee->emp_code,
                    'action'       => 'time_in',
                    'logged_at'    => $in,
                    'confidence'   => 0.98,
                    'liveness_pass'=> true,
                    'device_id'    => 1,
                    'meta'         => ['source' => 'seeder'],
                ]);

                AttendanceLog::create([
                    'employee_id'  => $employee->id,
                    'emp_code'     => $employee->emp_code,
                    'action'       => 'time_out',
                    'logged_at'    => $out,
                    'confidence'   => 0.99,
                    'liveness_pass'=> true,
                    'device_id'    => 1,
                    'meta'         => ['source' => 'seeder'],
                ]);
            }
            $day->addDay();
        }
    }
}
