<?php

namespace Database\Seeders;

use App\Models\AttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeeTwoYearAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $start = Carbon::today()->subYears(2)->startOfDay();
        $end   = Carbon::today()->endOfDay();

        $this->command?->info("Seeding attendance logs for all employees from {$start->toDateString()} to {$end->toDateString()}...");

        Employee::chunk(100, function ($employees) use ($start, $end) {
            foreach ($employees as $employee) {
                $this->seedForEmployee($employee, $start, $end);
            }
        });

        $this->command?->info('Done seeding two-year attendance history.');
    }

    protected function seedForEmployee(Employee $employee, Carbon $start, Carbon $end): void
    {
        // Remove existing logs in the target range to avoid duplicates when re-running
        AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('logged_at', [$start, $end])
            ->delete();

        $inserts = [];
        $day = $start->copy();
        while ($day->lessThanOrEqualTo($end)) {
            // Skip weekends
            if (!in_array($day->dayOfWeekIso, [6, 7], true)) {
                $rand = crc32($employee->id . '_' . $day->toDateString()) % 100; // deterministic per day/employee

                // Distributions: 80% present, 10% late, 5% half day, 5% absent
                $present = $rand < 80 || ($rand >= 80 && $rand < 90);
                $halfDay = $rand >= 90 && $rand < 95;
                $absent  = $rand >= 95;

                if ($present || $halfDay) {
                    if ($present && $rand < 80) {
                        // On time
                        $inTime  = $day->copy()->setTime(8, 30 + ($rand % 10));
                        $outTime = $day->copy()->setTime(17, 30 + ($rand % 10));
                    } elseif ($present) {
                        // Late
                        $inTime  = $day->copy()->setTime(9, 10 + ($rand % 20));
                        $outTime = $day->copy()->setTime(17, 40 + ($rand % 10));
                    } else { // half day
                        $inTime  = $day->copy()->setTime(8, 30);
                        $outTime = $day->copy()->setTime(12, 30);
                    }

                    $inserts[] = [
                        'employee_id'   => $employee->id,
                        'emp_code'      => $employee->emp_code,
                        'action'        => 'time_in',
                        'logged_at'     => $inTime,
                        'confidence'    => 0.98,
                        'liveness_pass' => true,
                        'device_id'     => 1,
                        'meta'          => json_encode(['source' => 'two_year_seeder']),
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];

                    $inserts[] = [
                        'employee_id'   => $employee->id,
                        'emp_code'      => $employee->emp_code,
                        'action'        => 'time_out',
                        'logged_at'     => $outTime,
                        'confidence'    => 0.99,
                        'liveness_pass' => true,
                        'device_id'     => 1,
                        'meta'          => json_encode(['source' => 'two_year_seeder']),
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];
                }
            }

            if (count($inserts) >= 1000) {
                DB::table('attendance_logs')->insert($inserts);
                $inserts = [];
            }

            $day->addDay();
        }

        if (!empty($inserts)) {
            DB::table('attendance_logs')->insert($inserts);
        }
    }
}

