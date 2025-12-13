<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Models\Employee;
use App\Models\AttendanceLog;
use App\Models\Setting;

class AttendanceController extends Controller
{
    /**
     * GET /api/attendance
     * Query params: email or emp_code, optional from/to (YYYY-MM-DD), limit
     * Returns a list of daily attendance records with check-in/out times.
     */
    public function index(Request $request)
    {
        $data = $request->only(['email', 'emp_code', 'empCode', 'from', 'to', 'limit']);

        $validator = Validator::make($data, [
            'email' => 'nullable|email',
            'emp_code' => 'nullable|string|max:50',
            'empCode' => 'nullable|string|max:50',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'limit' => 'nullable|integer|min:1|max:365',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid parameters',
                'errors' => $validator->errors(),
            ], 422);
        }

        $employee = null;
        if (!empty($data['email'])) {
            $employee = Employee::where('email', $data['email'])->first();
        }
        $reqEmpCode = $data['emp_code'] ?? $data['empCode'] ?? null;
        if (!$employee && !empty($reqEmpCode)) {
            $employee = Employee::where('emp_code', $reqEmpCode)->first();
        }
        if (!$employee && empty($reqEmpCode)) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $to = !empty($data['to']) ? Carbon::createFromFormat('Y-m-d', $data['to'])->endOfDay() : Carbon::now();
        // Do not show future days; clamp $to to end of today
        $now = Carbon::now();
        if ($to->gt($now->endOfDay())) {
            $to = $now->endOfDay();
        }
        $from = !empty($data['from']) ? Carbon::createFromFormat('Y-m-d', $data['from'])->startOfDay() : (clone $to)->subDays(29)->startOfDay();
        $limit = !empty($data['limit']) ? (int)$data['limit'] : 60;

        // Fetch logs for this employee; match by id and also by emp_code as fallback
        // Also allow fallback to created_at if logged_at is null
        $logs = AttendanceLog::where(function($q) use ($employee, $reqEmpCode) {
                if ($employee) {
                    $q->where('employee_id', $employee->id)
                      ->orWhere('emp_code', $employee->emp_code);
                }
                if (!empty($reqEmpCode)) {
                    $q->orWhere('emp_code', $reqEmpCode);
                }
            })
            ->where(function($q) use ($from, $to) {
                $q->whereBetween('logged_at', [$from, $to])
                  ->orWhere(function($q2) use ($from, $to) {
                      $q2->whereNull('logged_at')
                         ->whereBetween('created_at', [$from, $to]);
                  });
            })
            ->orderByRaw('COALESCE(`logged_at`, `created_at`) asc')
            ->get(['id', 'action', 'logged_at', 'created_at']);

        // Group by date (Y-m-d) and build records
        $grouped = [];
        foreach ($logs as $log) {
            $ts = $log->logged_at ?: $log->created_at; // fallback
            $day = $ts->toDateString();
            if (!isset($grouped[$day])) {
                $grouped[$day] = [
                    'date' => $day,
                    'check_in' => null,
                    'check_out' => null,
                ];
            }
            $action = strtolower((string)$log->action);
            $isIn = in_array($action, ['time_in','check_in','check-in','in'], true);
            $isOut = in_array($action, ['time_out','check_out','check-out','out'], true);

            if ($isIn) {
                $grouped[$day]['check_in'] = $grouped[$day]['check_in']
                    ? $grouped[$day]['check_in']
                    : $ts->toIso8601String();
            }
            if ($isOut) {
                $grouped[$day]['check_out'] = $ts->toIso8601String();
            }
        }

        // Ensure we include all days in the requested range, marking missing days as Absent
        $cursor = (clone $from)->startOfDay();
        $endDay = (clone $to)->endOfDay();
        while ($cursor->lte($endDay)) {
            $day = $cursor->toDateString();
            if (!isset($grouped[$day])) {
                $grouped[$day] = [
                    'date' => $day,
                    'check_in' => null,
                    'check_out' => null,
                ];
            }
            $cursor->addDay();
        }

        // Load schedule config for Late detection
        $scheduleCfg = Setting::getCached('attendance.schedule', null, 60);
        $enabledDays = $scheduleCfg['days'] ?? null; // [1..7] ISO weekday
        $lateBase = $scheduleCfg['late_after'] ?? ($scheduleCfg['in_end'] ?? null); // HH:MM
        $lateGrace = (int)($scheduleCfg['late_grace'] ?? 0);

        // Map to client schema
        $records = [];
        foreach ($grouped as $day => $row) {
            $status = 'Present';
            if (!$row['check_in'] && !$row['check_out']) {
                $status = 'Absent';
            } elseif ($row['check_in'] && !$row['check_out']) {
                $status = 'Half Day';
            } else {
                // Late if check-in after threshold and schedule says to evaluate this day
                if (!empty($row['check_in']) && !empty($lateBase)) {
                    try {
                        $cin = Carbon::parse($row['check_in']);
                        $applyDay = true;
                        if (is_array($enabledDays) && count($enabledDays) > 0) {
                            $dow = Carbon::createFromFormat('Y-m-d', $day)->dayOfWeekIso;
                            $applyDay = in_array($dow, array_map('intval', $enabledDays), true);
                        }
                        if ($applyDay) {
                            [$lh, $lm] = array_map('intval', explode(':', $lateBase));
                            $lateThreshold = Carbon::createFromFormat('Y-m-d H:i', $day.' '.$lateBase)->addMinutes($lateGrace);
                            if ($cin->gt($lateThreshold)) {
                                $status = 'Late';
                            }
                        }
                    } catch (\Throwable $e) {
                        // ignore parsing errors
                    }
                }
            }

            $records[] = [
                'id' => ($employee?->id ?? ($reqEmpCode ?? 'emp')) . '_' . $day,
                'checkInTime' => $row['check_in'] ?? ($day . 'T00:00:00Z'),
                'checkOutTime' => $row['check_out'],
                'status' => $status,
                'notes' => null,
            ];
        }

        // Sort descending by date
        usort($records, fn($a, $b) => strcmp($b['id'], $a['id']));
        if (count($records) > $limit) {
            $records = array_slice($records, 0, $limit);
        }

        return response()->json($records);
    }
}