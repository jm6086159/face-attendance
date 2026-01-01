<?php

namespace App\Livewire\Reports;

use App\Exports\AttendanceIssuesExport;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Illuminate\Support\Facades\DB;

#[Layout('components.layouts.app')]
class AttendanceIssuesReport extends Component
{
    public string $q = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $quickRange = 'last30';
    public string $department = '';
    public string $employee = '';
    public string $issueType = 'all'; // all | late | undertime | absent | overtime

    public function mount(): void
    {
        $this->applyQuickRange('last30');
    }

    public function updated($field): void
    {
        if (in_array($field, ['dateFrom', 'dateTo'], true)) {
            $this->quickRange = 'custom';
        }
    }

    public function applyQuickRange(string $range): void
    {
        $today = Carbon::today();
        $this->quickRange = $range;

        $ranges = [
            'today'   => [$today, $today],
            'week'    => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'month'   => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'last30'  => [$today->copy()->subDays(29), $today],
        ];

        [$start, $end] = $ranges[$range] ?? [$today->copy()->subDays(29), $today];

        $this->dateFrom = $start->format('Y-m-d');
        $this->dateTo   = $end->format('Y-m-d');
    }

    private function resolveDateRange(): array
    {
        $start = $this->dateFrom ? Carbon::parse($this->dateFrom)->startOfDay() : Carbon::now()->startOfMonth();
        $end   = $this->dateTo   ? Carbon::parse($this->dateTo)->endOfDay()   : Carbon::now()->endOfDay();

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }

    private function getScheduleConfig(): array
    {
        $cfg = Setting::getValue('attendance.schedule');
        
        // Ensure $cfg is an array to prevent "accessing array offset on null" errors
        if (!is_array($cfg)) {
            $cfg = [];
        }
        
        // Handle days - could be JSON string in some databases
        $days = $cfg['days'] ?? [1, 2, 3, 4, 5];
        if (is_string($days)) {
            $decoded = json_decode($days, true);
            $days = is_array($decoded) ? $decoded : [1, 2, 3, 4, 5];
        }
        
        return [
            'in_start'  => $cfg['in_start'] ?? '06:00',
            'in_end'    => $cfg['in_end'] ?? '08:00',
            'out_start' => $cfg['out_start'] ?? '16:00',
            'out_end'   => $cfg['out_end'] ?? '17:00',
            'days'      => $days,
            'late_after' => $cfg['late_after'] ?? null,
            'late_grace' => (int)($cfg['late_grace'] ?? 0),
        ];
    }

    private function calculateExpectedMinutes(): int
    {
        $cfg = $this->getScheduleConfig();
        
        // Calculate expected work hours from schedule
        $inTime = Carbon::parse($cfg['in_end']); // Latest allowed time in
        $outTime = Carbon::parse($cfg['out_start']); // Earliest allowed time out
        
        // Use absolute difference to ensure positive value
        $minutes = abs($inTime->diffInMinutes($outTime));
        
        // Fallback to 8 hours (480 minutes) if calculation seems wrong
        if ($minutes === 0 || $minutes > 1440) { // 0 or more than 24 hours
            $minutes = 480; // Default 8 hours
        }
        
        return $minutes;
    }

    private function generateDateSeries(Carbon $start, Carbon $end): Collection
    {
        $period = CarbonPeriod::create($start->copy()->startOfDay(), '1 day', $end->copy()->startOfDay());
        $dates = collect();
        foreach ($period as $date) {
            $dates->push($date->copy());
        }

        return $dates;
    }

    private function formatDuration(?int $totalMinutes): ?string
    {
        if ($totalMinutes === null || $totalMinutes <= 0) {
            return null;
        }

        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%02dh %02dm', $hours, $minutes);
    }

    private function getFilteredEmployees()
    {
        return Employee::query()
            ->when($this->q, function ($query) {
                $search = '%' . $this->q . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('emp_code', 'like', $search)
                        ->orWhere('first_name', 'like', $search)
                        ->orWhere('last_name', 'like', $search);
                });
            })
            ->when($this->department, fn ($q) => $q->where('department', $this->department))
            ->when($this->employee, fn ($q) => $q->where('id', $this->employee));
    }

    public function getAttendanceRecords(): Collection
    {
        [$start, $end] = $this->resolveDateRange();
        $dates = $this->generateDateSeries($start, $end);
        $cfg = $this->getScheduleConfig();
        $expectedMinutes = $this->calculateExpectedMinutes();
        $workDays = $cfg['days'];

        // Parse schedule times
        $scheduleInEnd = Carbon::parse($cfg['in_end']);
        $lateThreshold = $cfg['late_after'] ? Carbon::parse($cfg['late_after']) : $scheduleInEnd;
        $graceMinutes = $cfg['late_grace'];
        $scheduleOutStart = Carbon::parse($cfg['out_start']);

        $employees = $this->getFilteredEmployees()->get();

        if ($employees->isEmpty()) {
            return collect();
        }

        $employeeIds = $employees->pluck('id');

        $logs = AttendanceLog::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween(DB::raw('COALESCE(logged_at, created_at)'), [$start, $end])
            ->orderBy('logged_at')
            ->get();

        $records = collect();

        foreach ($employees as $employee) {
            $employeeLogs = $logs->where('employee_id', $employee->id);
            
            $logsByDate = $employeeLogs
                ->sortBy('logged_at')
                ->groupBy(fn ($log) => ($log->logged_at ?? $log->created_at)->toDateString());

            foreach ($dates as $date) {
                // Skip non-work days
                $dayOfWeek = $date->dayOfWeekIso;
                if (!in_array($dayOfWeek, $workDays)) {
                    continue;
                }

                $dateKey = $date->toDateString();
                $dayLogs = $logsByDate->get($dateKey, collect())->sortBy('logged_at')->values();

                $issues = [];
                $lateMinutes = 0;
                $undertimeMinutes = 0;
                $overtimeMinutes = 0;
                $isAbsent = false;
                $isLate = false;
                $hasUndertime = false;
                $hasOvertime = false;

                if ($dayLogs->isEmpty()) {
                    // Employee was absent
                    $isAbsent = true;
                    $issues[] = 'Absent';
                    
                    $records->push([
                        'date' => $dateKey,
                        'date_label' => $date->translatedFormat('M d, Y (D)'),
                        'employee_name' => $employee->full_name,
                        'emp_code' => $employee->emp_code,
                        'department' => $employee->department ?? '-',
                        'course' => $employee->course ?? '-',
                        'position' => $employee->position ?? '-',
                        'time_in' => null,
                        'time_out' => null,
                        'expected_hours' => $this->formatDuration($expectedMinutes),
                        'actual_hours' => '00h 00m',
                        'late_minutes' => 0,
                        'undertime_minutes' => 0,
                        'overtime_minutes' => 0,
                        'issue_type' => 'absent',
                        'status' => 'Absent',
                        'remarks' => 'No attendance record for this day',
                    ]);
                    continue;
                }

                $timeInLog = $dayLogs->firstWhere('action', 'time_in') ?? $dayLogs->first();
                $timeOutLog = $dayLogs->filter(fn ($log) => $log->action === 'time_out')->last();
                
                if (!$timeOutLog && $dayLogs->count() > 1) {
                    $timeOutLog = $dayLogs->last();
                }

                // Calculate late minutes
                if ($timeInLog) {
                    $actualIn = $timeInLog->logged_at ?? $timeInLog->created_at;
                    $thresholdTime = $date->copy()->setTimeFrom($lateThreshold)->addMinutes($graceMinutes);
                    
                    if ($actualIn->gt($thresholdTime)) {
                        $lateMinutes = $thresholdTime->diffInMinutes($actualIn);
                        $isLate = true;
                        $issues[] = 'Late';
                    }
                }

                // Calculate actual worked minutes
                $actualMinutes = 0;
                if ($timeInLog && $timeOutLog) {
                    $inTime = $timeInLog->logged_at ?? $timeInLog->created_at;
                    $outTime = $timeOutLog->logged_at ?? $timeOutLog->created_at;
                    
                    if ($outTime->gt($inTime)) {
                        $actualMinutes = $inTime->diffInMinutes($outTime);
                    }
                }

                // Calculate undertime (left early or worked less than expected)
                if ($actualMinutes < $expectedMinutes) {
                    $undertimeMinutes = $expectedMinutes - $actualMinutes;
                    if ($undertimeMinutes > 0) {
                        $hasUndertime = true;
                        $issues[] = 'Undertime';
                    }
                }

                // Calculate overtime (worked more than expected)
                if ($actualMinutes > $expectedMinutes) {
                    $overtimeMinutes = $actualMinutes - $expectedMinutes;
                    if ($overtimeMinutes > 0) {
                        $hasOvertime = true;
                        $issues[] = 'Overtime';
                    }
                }

                // Build remarks
                $remarks = collect();
                if (!$timeInLog) {
                    $remarks->push('Missing time in');
                }
                if (!$timeOutLog) {
                    $remarks->push('Missing time out');
                }
                if ($isLate) {
                    $remarks->push("Late by {$lateMinutes} min");
                }
                if ($hasUndertime) {
                    $remarks->push("Undertime: {$undertimeMinutes} min");
                }
                if ($hasOvertime) {
                    $remarks->push("Overtime: {$overtimeMinutes} min");
                }

                // Determine primary issue type for filtering
                $primaryIssue = 'present';
                if ($isAbsent) {
                    $primaryIssue = 'absent';
                } elseif ($isLate) {
                    $primaryIssue = 'late';
                } elseif ($hasUndertime) {
                    $primaryIssue = 'undertime';
                } elseif ($hasOvertime) {
                    $primaryIssue = 'overtime';
                }

                // Only add records that have issues (or all if showing overtime as positive)
                if ($isLate || $hasUndertime || $hasOvertime || !$timeInLog || !$timeOutLog) {
                    $status = implode(' / ', $issues) ?: 'Incomplete';

                    $records->push([
                        'date' => $dateKey,
                        'date_label' => $date->translatedFormat('M d, Y (D)'),
                        'employee_name' => $employee->full_name,
                        'emp_code' => $employee->emp_code,
                        'department' => $employee->department ?? '-',
                        'course' => $employee->course ?? '-',
                        'position' => $employee->position ?? '-',
                        'time_in' => $timeInLog?->logged_at?->format('h:i A'),
                        'time_out' => $timeOutLog?->logged_at?->format('h:i A'),
                        'expected_hours' => $this->formatDuration($expectedMinutes),
                        'actual_hours' => $this->formatDuration($actualMinutes),
                        'late_minutes' => $lateMinutes,
                        'undertime_minutes' => $undertimeMinutes,
                        'overtime_minutes' => $overtimeMinutes,
                        'issue_type' => $primaryIssue,
                        'status' => $status,
                        'remarks' => $remarks->isEmpty() ? null : $remarks->implode(', '),
                    ]);
                }
            }
        }

        // Filter by issue type
        $filtered = $records->when($this->issueType !== 'all', function ($collection) {
            return $collection->filter(fn ($item) => $item['issue_type'] === $this->issueType);
        });

        return $filtered->sortByDesc('date')->values();
    }

    private function buildSummary(Collection $records): array
    {
        $allRecords = $this->getAttendanceRecords();
        
        // Count records that have each issue (not just primary issue type)
        $lateCount = $allRecords->filter(fn ($r) => $r['late_minutes'] > 0)->count();
        $undertimeCount = $allRecords->filter(fn ($r) => $r['undertime_minutes'] > 0)->count();
        $absentCount = $allRecords->where('issue_type', 'absent')->count();
        $overtimeCount = $allRecords->filter(fn ($r) => $r['overtime_minutes'] > 0)->count();

        $totalLateMinutes = $allRecords->sum('late_minutes');
        $totalUndertimeMinutes = $allRecords->sum('undertime_minutes');
        $totalOvertimeMinutes = $allRecords->sum('overtime_minutes');

        $uniqueEmployees = $records->unique('emp_code')->count();

        return [
            'total_records' => $records->count(),
            'unique_employees' => $uniqueEmployees,
            'late_count' => $lateCount,
            'undertime_count' => $undertimeCount,
            'absent_count' => $absentCount,
            'overtime_count' => $overtimeCount,
            'total_late_minutes' => $totalLateMinutes,
            'total_late_hours' => $this->formatDuration($totalLateMinutes),
            'total_undertime_minutes' => $totalUndertimeMinutes,
            'total_undertime_hours' => $this->formatDuration($totalUndertimeMinutes),
            'total_overtime_minutes' => $totalOvertimeMinutes,
            'total_overtime_hours' => $this->formatDuration($totalOvertimeMinutes),
        ];
    }

    /**
     * Get total work hours summary per employee for the selected period
     */
    public function getEmployeeWorkHoursSummary(): Collection
    {
        [$start, $end] = $this->resolveDateRange();
        $cfg = $this->getScheduleConfig();
        $workDays = $cfg['days'];
        $expectedMinutesPerDay = $this->calculateExpectedMinutes();
        
        $employees = $this->getFilteredEmployees()->get();
        
        if ($employees->isEmpty()) {
            return collect();
        }
        
        $employeeIds = $employees->pluck('id');
        $dates = $this->generateDateSeries($start, $end);
        
        // Count expected work days in the period
        $totalWorkDays = $dates->filter(fn ($date) => in_array($date->dayOfWeekIso, $workDays))->count();
        $totalExpectedMinutes = $totalWorkDays * $expectedMinutesPerDay;
        
        // Get all attendance logs for the period
        $logs = AttendanceLog::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween(DB::raw('COALESCE(logged_at, created_at)'), [$start, $end])
            ->orderBy('logged_at')
            ->get();
        
        $summaries = collect();
        
        foreach ($employees as $employee) {
            $employeeLogs = $logs->where('employee_id', $employee->id);
            $logsByDate = $employeeLogs
                ->sortBy('logged_at')
                ->groupBy(fn ($log) => ($log->logged_at ?? $log->created_at)->toDateString());
            
            $totalWorkedMinutes = 0;
            $daysPresent = 0;
            $daysAbsent = 0;
            $totalLateMinutes = 0;
            $totalUndertimeMinutes = 0;
            $totalOvertimeMinutes = 0;
            
            foreach ($dates as $date) {
                $dayOfWeek = $date->dayOfWeekIso;
                if (!in_array($dayOfWeek, $workDays)) {
                    continue;
                }
                
                $dateKey = $date->toDateString();
                $dayLogs = $logsByDate->get($dateKey, collect())->sortBy('logged_at')->values();
                
                if ($dayLogs->isEmpty()) {
                    $daysAbsent++;
                    continue;
                }
                
                $daysPresent++;
                
                $timeInLog = $dayLogs->firstWhere('action', 'time_in') ?? $dayLogs->first();
                $timeOutLog = $dayLogs->filter(fn ($log) => $log->action === 'time_out')->last();
                
                if (!$timeOutLog && $dayLogs->count() > 1) {
                    $timeOutLog = $dayLogs->last();
                }
                
                // Calculate actual worked minutes for this day
                $dayWorkedMinutes = 0;
                if ($timeInLog && $timeOutLog) {
                    $inTime = $timeInLog->logged_at ?? $timeInLog->created_at;
                    $outTime = $timeOutLog->logged_at ?? $timeOutLog->created_at;
                    
                    if ($outTime->gt($inTime)) {
                        $dayWorkedMinutes = $inTime->diffInMinutes($outTime);
                    }
                }
                
                $totalWorkedMinutes += $dayWorkedMinutes;
                
                // Calculate late
                if ($timeInLog) {
                    $actualIn = $timeInLog->logged_at ?? $timeInLog->created_at;
                    $lateThreshold = $cfg['late_after'] ? Carbon::parse($cfg['late_after']) : Carbon::parse($cfg['in_end']);
                    $thresholdTime = $date->copy()->setTimeFrom($lateThreshold)->addMinutes($cfg['late_grace']);
                    
                    if ($actualIn->gt($thresholdTime)) {
                        $totalLateMinutes += $thresholdTime->diffInMinutes($actualIn);
                    }
                }
                
                // Calculate undertime/overtime
                if ($dayWorkedMinutes < $expectedMinutesPerDay) {
                    $totalUndertimeMinutes += ($expectedMinutesPerDay - $dayWorkedMinutes);
                } elseif ($dayWorkedMinutes > $expectedMinutesPerDay) {
                    $totalOvertimeMinutes += ($dayWorkedMinutes - $expectedMinutesPerDay);
                }
            }
            
            $summaries->push([
                'employee_id' => $employee->id,
                'employee_name' => $employee->full_name,
                'emp_code' => $employee->emp_code,
                'department' => $employee->department ?? '-',
                'days_present' => $daysPresent,
                'days_absent' => $daysAbsent,
                'total_work_days' => $totalWorkDays,
                'total_worked_minutes' => $totalWorkedMinutes,
                'total_worked_hours' => $this->formatDuration($totalWorkedMinutes),
                'expected_minutes' => $totalExpectedMinutes,
                'expected_hours' => $this->formatDuration($totalExpectedMinutes),
                'total_late_minutes' => $totalLateMinutes,
                'total_late_hours' => $this->formatDuration($totalLateMinutes),
                'total_undertime_minutes' => $totalUndertimeMinutes,
                'total_undertime_hours' => $this->formatDuration($totalUndertimeMinutes),
                'total_overtime_minutes' => $totalOvertimeMinutes,
                'total_overtime_hours' => $this->formatDuration($totalOvertimeMinutes),
                'attendance_rate' => $totalWorkDays > 0 ? round(($daysPresent / $totalWorkDays) * 100, 1) : 0,
                'efficiency_rate' => $totalExpectedMinutes > 0 ? round(($totalWorkedMinutes / $totalExpectedMinutes) * 100, 1) : 0,
            ]);
        }
        
        return $summaries->sortByDesc('total_worked_minutes')->values();
    }

    public function exportExcel()
    {
        $records = $this->getAttendanceRecords();
        
        if ($records->isEmpty()) {
            session()->flash('status', 'No records to export.');
            return null;
        }

        $filename = 'attendance_issues_report_' . now()->format('Y-m-d_His') . '.xlsx';
        
        return Excel::download(new AttendanceIssuesExport($records, [
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'department' => $this->department,
            'employee' => $this->employee,
            'issue_type' => $this->issueType,
        ]), $filename, ExcelWriter::XLSX);
    }

    public function exportPdf()
    {
        $records = $this->getAttendanceRecords();
        
        if ($records->isEmpty()) {
            session()->flash('status', 'No records to export.');
            return null;
        }

        $summary = $this->buildSummary($records);
        $workHoursSummary = $this->getEmployeeWorkHoursSummary();

        $pdf = Pdf::loadView('pdf.attendance-issues-report', [
            'records' => $records,
            'summary' => $summary,
            'workHoursSummary' => $workHoursSummary,
            'filters' => [
                'date_from' => $this->dateFrom,
                'date_to' => $this->dateTo,
                'department' => $this->department,
                'employee' => $this->employee,
                'issue_type' => $this->issueType,
            ],
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'attendance_issues_report_' . now()->format('Y-m-d_His') . '.pdf'
        );
    }

    public function render()
    {
        $records = $this->getAttendanceRecords();
        $summary = $this->buildSummary($records);
        $workHoursSummary = $this->getEmployeeWorkHoursSummary();

        $departments = Employee::distinct()->pluck('department')->filter()->sort()->values();
        $employees = Employee::orderBy('first_name')->orderBy('last_name')
            ->when($this->department, fn ($q) => $q->where('department', $this->department))
            ->get(['id', 'emp_code', 'first_name', 'last_name', 'department']);

        return view('livewire.reports.attendance-issues-report', [
            'records' => $records,
            'summary' => $summary,
            'workHoursSummary' => $workHoursSummary,
            'departments' => $departments,
            'employees' => $employees,
        ]);
    }
}
