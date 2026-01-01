<?php

namespace App\Livewire\Reports;

use App\Exports\UndertimeReportExport;
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
class UndertimeReport extends Component
{
    public string $q = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $quickRange = 'last30';
    public string $department = '';
    public string $employee = '';
    public int $minimumUndertime = 1; // Minimum undertime minutes to show

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
        
        return [
            'in_start'  => $cfg['in_start'] ?? '06:00',
            'in_end'    => $cfg['in_end'] ?? '08:00',
            'out_start' => $cfg['out_start'] ?? '16:00',
            'out_end'   => $cfg['out_end'] ?? '17:00',
            'days'      => $cfg['days'] ?? [1, 2, 3, 4, 5],
        ];
    }

    private function calculateExpectedMinutes(): int
    {
        $cfg = $this->getScheduleConfig();
        
        // Calculate expected work hours from schedule
        $inTime = Carbon::parse($cfg['in_end']); // Latest allowed time in
        $outTime = Carbon::parse($cfg['out_start']); // Earliest allowed time out
        
        return $inTime->diffInMinutes($outTime);
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

    public function getUndertimeRecords(): Collection
    {
        [$start, $end] = $this->resolveDateRange();
        $dates = $this->generateDateSeries($start, $end);
        $cfg = $this->getScheduleConfig();
        $expectedMinutes = $this->calculateExpectedMinutes();
        $workDays = $cfg['days'];

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

                if ($dayLogs->isEmpty()) {
                    // Employee was absent - full day undertime
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
                        'undertime_minutes' => $expectedMinutes,
                        'status' => 'Absent',
                        'remarks' => 'Full day absent - marked as undertime',
                    ]);
                    continue;
                }

                $timeInLog = $dayLogs->firstWhere('action', 'time_in') ?? $dayLogs->first();
                $timeOutLog = $dayLogs->filter(fn ($log) => $log->action === 'time_out')->last();
                
                if (!$timeOutLog && $dayLogs->count() > 1) {
                    $timeOutLog = $dayLogs->last();
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

                // Calculate undertime
                $undertimeMinutes = max(0, $expectedMinutes - $actualMinutes);

                // Only include records with undertime above minimum threshold
                if ($undertimeMinutes >= $this->minimumUndertime) {
                    $remarks = collect();
                    
                    if (!$timeInLog) {
                        $remarks->push('Missing time in');
                    }
                    if (!$timeOutLog) {
                        $remarks->push('Missing time out');
                        $undertimeMinutes = $expectedMinutes; // Full undertime if no time out
                    }
                    if ($undertimeMinutes > 0 && $timeInLog && $timeOutLog) {
                        $remarks->push('Left early or incomplete hours');
                    }

                    $status = 'Undertime';
                    if ($undertimeMinutes >= $expectedMinutes) {
                        $status = 'Incomplete';
                    }

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
                        'undertime_minutes' => $undertimeMinutes,
                        'status' => $status,
                        'remarks' => $remarks->isEmpty() ? null : $remarks->implode(', '),
                    ]);
                }
            }
        }

        return $records->sortByDesc('undertime_minutes')->values();
    }

    private function buildSummary(Collection $records): array
    {
        $totalUndertimeMinutes = $records->sum('undertime_minutes');
        $totalRecords = $records->count();
        $uniqueEmployees = $records->unique('emp_code')->count();
        $avgUndertimeMinutes = $totalRecords > 0 ? round($totalUndertimeMinutes / $totalRecords) : 0;

        return [
            'total_records' => $totalRecords,
            'unique_employees' => $uniqueEmployees,
            'total_undertime_minutes' => $totalUndertimeMinutes,
            'total_undertime_hours' => $this->formatDuration($totalUndertimeMinutes),
            'avg_undertime_minutes' => $avgUndertimeMinutes,
            'avg_undertime_hours' => $this->formatDuration($avgUndertimeMinutes),
        ];
    }

    public function exportExcel()
    {
        $records = $this->getUndertimeRecords();
        
        if ($records->isEmpty()) {
            session()->flash('status', 'No undertime records to export.');
            return null;
        }

        $filename = 'undertime_report_' . now()->format('Y-m-d_His') . '.xlsx';
        
        return Excel::download(new UndertimeReportExport($records, [
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'department' => $this->department,
            'employee' => $this->employee,
        ]), $filename, ExcelWriter::XLSX);
    }

    public function exportPdf()
    {
        $records = $this->getUndertimeRecords();
        
        if ($records->isEmpty()) {
            session()->flash('status', 'No undertime records to export.');
            return null;
        }

        $summary = $this->buildSummary($records);

        $pdf = Pdf::loadView('pdf.undertime-report', [
            'records' => $records,
            'summary' => $summary,
            'filters' => [
                'date_from' => $this->dateFrom,
                'date_to' => $this->dateTo,
                'department' => $this->department,
                'employee' => $this->employee,
            ],
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'undertime_report_' . now()->format('Y-m-d_His') . '.pdf'
        );
    }

    public function render()
    {
        $records = $this->getUndertimeRecords();
        $summary = $this->buildSummary($records);

        $departments = Employee::distinct()->pluck('department')->filter()->sort()->values();
        $employees = Employee::orderBy('first_name')->orderBy('last_name')
            ->when($this->department, fn ($q) => $q->where('department', $this->department))
            ->get(['id', 'emp_code', 'first_name', 'last_name', 'department']);

        return view('livewire.reports.undertime-report', [
            'records' => $records,
            'summary' => $summary,
            'departments' => $departments,
            'employees' => $employees,
        ]);
    }
}
