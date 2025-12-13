<?php
namespace App\Livewire\Attendance;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Exports\AttendanceLogsExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    public $perPage = 15;
    public $q = '';
    
    // Advanced Filters
    public $dateFrom = '';
    public $dateTo = '';
    public $selectedDepartment = '';
    public $selectedAction = '';
    public $lateOnly = false;
    public $selectedEmployee = '';

    protected $queryString = ['q', 'dateFrom', 'dateTo', 'selectedDepartment', 'selectedAction', 'lateOnly', 'selectedEmployee'];

    public function mount()
    {
        // Set default date range to current month
        $this->dateFrom = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = Carbon::now()->endOfMonth()->format('Y-m-d');
    }

    public function updatingQ()
    {
        $this->resetPage();
    }

    public function updatedDateFrom()
    {
        $this->resetPage();
    }

    public function updatedDateTo()
    {
        $this->resetPage();
    }

    public function updatedSelectedDepartment($value)
    {
        if ($this->selectedEmployee) {
            $employee = Employee::find($this->selectedEmployee);
            if (!$employee || ($value && $employee->department !== $value)) {
                $this->selectedEmployee = '';
            }
        }

        $this->resetPage();
    }

    public function updatedSelectedAction()
    {
        $this->resetPage();
    }

    public function updatedLateOnly()
    {
        $this->resetPage();
    }

    public function updatedSelectedEmployee()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->q = '';
        $this->dateFrom = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = Carbon::now()->endOfMonth()->format('Y-m-d');
        $this->selectedDepartment = '';
        $this->selectedAction = '';
        $this->lateOnly = false;
        $this->selectedEmployee = '';
        $this->resetPage();
    }

    private function getFilteredQuery()
    {
        $selectedEmployee = $this->selectedEmployee
            ? Employee::find($this->selectedEmployee)
            : null;

        return AttendanceLog::query()
            ->with('employee:id,emp_code,first_name,last_name,department,position')
            ->when($this->q, function ($query) {
                $search = '%' . $this->q . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('emp_code', 'like', $search)
                      ->orWhereHas('employee', function ($eq) use ($search) {
                          $eq->where('first_name','like',$search)
                             ->orWhere('last_name','like',$search);
                      });
                });
            })
            ->when($this->dateFrom, function ($query) {
                $query->whereDate('logged_at', '>=', $this->dateFrom);
            })
            ->when($this->dateTo, function ($query) {
                $query->whereDate('logged_at', '<=', $this->dateTo);
            })
            ->when($this->selectedDepartment, function ($query) {
                $query->whereHas('employee', function ($q) {
                    $q->where('department', $this->selectedDepartment);
                });
            })
            ->when($this->selectedAction, function ($query) {
                $query->where('action', $this->selectedAction);
            })
            ->when($this->lateOnly, function ($query) {
                $query->where('action', 'time_in')->where('is_late', true);
            })
            ->when($selectedEmployee, function ($query) use ($selectedEmployee) {
                $query->where(function ($sub) use ($selectedEmployee) {
                    $sub->where('employee_id', $selectedEmployee->id);

                    if ($selectedEmployee->emp_code) {
                        $sub->orWhere('emp_code', $selectedEmployee->emp_code);
                    }
                });
            })
            ->orderBy('logged_at', 'desc');
    }

    private function getDailyTimeRecords(): Collection
    {
        [$startDate, $endDate] = $this->resolveDateRange();
        $dates = $this->generateDateSeries($startDate, $endDate);

        $logs = $this->getFilteredQuery()->reorder()->get();
        if ($logs->isEmpty()) {
            return collect();
        }

        return $logs
            ->groupBy(function ($log) {
                $empId = $log->employee_id ?: 'unassigned';
                $empCode = $log->emp_code ?: optional($log->employee)->emp_code ?: 'N/A';
                return "{$empId}-{$empCode}";
            })
            ->sortBy(function ($employeeLogs) {
                $employee = $employeeLogs->first()->employee;
                if ($employee) {
                    return strtoupper($employee->full_name);
                }

                $code = $employeeLogs->first()->emp_code;
                return $code ? "ZZZ-{$code}" : 'ZZZ-UNASSIGNED';
            })
            ->flatMap(function ($employeeLogs) use ($dates) {
                $firstLog = $employeeLogs->first();
                $employee = $firstLog->employee;
                $empCode = $employee->emp_code ?? $firstLog->emp_code ?? 'N/A';
                $employeeName = $employee?->full_name;
                $employeeName = $employeeName && trim($employeeName) !== '' ? $employeeName : 'Unassigned Employee';
                $department = $employee->department ?? '-';

                $logsByDate = $employeeLogs
                    ->sortBy('logged_at')
                    ->groupBy(fn ($log) => $log->logged_at->toDateString());

                return $dates->map(function (Carbon $date) use ($logsByDate, $empCode, $employeeName, $department) {
                    $dateKey = $date->toDateString();
                    $dayLogs = $logsByDate->get($dateKey, collect())->sortBy('logged_at')->values();

                    if ($dayLogs->isEmpty()) {
                        return [
                            'emp_code' => $empCode,
                            'employee_name' => $employeeName,
                            'department' => $department,
                            'date' => $dateKey,
                            'date_label' => $date->translatedFormat('M d, Y (D)'),
                            'time_in' => null,
                            'time_out' => null,
                            'total_duration' => null,
                            'status' => 'Absent',
                            'remarks' => 'Absent',
                        ];
                    }

                    $timeInLog = $dayLogs->firstWhere('action', 'time_in') ?? $dayLogs->first();
                    $timeOutLog = $dayLogs->filter(fn ($log) => $log->action === 'time_out')->last();
                    if (!$timeOutLog && $dayLogs->count() > 1) {
                        $timeOutLog = $dayLogs->last();
                    }

                    $remarks = collect();
                    if (!$timeInLog) {
                        $remarks->push('Missing time in');
                    } elseif ($timeInLog->is_late) {
                        $remarks->push('Late');
                    }
                    if (!$timeOutLog) {
                        $remarks->push('Missing time out');
                    }

                    $status = 'Present';
                    if ($remarks->contains('Missing time in') || $remarks->contains('Missing time out')) {
                        $status = 'Incomplete';
                    }

                    $totalMinutes = null;
                    if ($timeInLog && $timeOutLog && $timeOutLog->logged_at->gt($timeInLog->logged_at)) {
                        $totalMinutes = $timeInLog->logged_at->diffInMinutes($timeOutLog->logged_at);
                    }

                    return [
                        'emp_code' => $empCode,
                        'employee_name' => $employeeName,
                        'department' => $department,
                        'date' => $dateKey,
                        'date_label' => $date->translatedFormat('M d, Y (D)'),
                        'time_in' => $timeInLog?->logged_at?->format('h:i A'),
                        'time_out' => $timeOutLog?->logged_at?->format('h:i A'),
                        'total_duration' => $this->formatDuration($totalMinutes),
                        'status' => $status,
                        'remarks' => $remarks->isEmpty() ? null : $remarks->implode(', '),
                    ];
                });
            })
            ->values();
    }

    private function currentFilters(): array
    {
        return [
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'department' => $this->selectedDepartment,
            'action' => $this->selectedAction,
            'late_only' => $this->lateOnly,
        ];
    }

    public function render()
    {
        $logs = $this->getFilteredQuery()->paginate($this->perPage);
        
        // Get unique departments for filter dropdown
        $departments = Employee::distinct()->pluck('department')->filter()->sort()->values();
        $employeesQuery = Employee::orderBy('first_name')
            ->orderBy('last_name')
            ->select(['id', 'emp_code', 'first_name', 'last_name', 'department']);

        if ($this->selectedDepartment) {
            $employeesQuery->where('department', $this->selectedDepartment);
        }

        $employees = $employeesQuery->get();

        return view('livewire.attendance.index', [
            'logs' => $logs,
            'departments' => $departments,
            'employees' => $employees,
        ]);
    }

    public function exportExcel()
    {
        $query = $this->getFilteredQuery();
        
        $filename = 'attendance_report_' . date('Y-m-d_His') . '.xlsx';
        
        return Excel::download(new AttendanceLogsExport($query, $this->currentFilters()), $filename, ExcelWriter::XLSX);
    }

    public function exportSummaryCsv()
    {
        $summary = $this->getDailyTimeRecords();
        if ($summary->isEmpty()) {
            session()->flash('status', 'No attendance summary data available for the selected filters.');
            return null;
        }

        $filename = 'attendance_summary_' . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->streamDownload(function () use ($summary) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Employee Code', 'Employee Name', 'Department', 'Date', 'Time In', 'Time Out', 'Total Hours', 'Status', 'Remarks']);

            foreach ($summary as $row) {
                fputcsv($output, [
                    $row['emp_code'],
                    $row['employee_name'],
                    $row['department'],
                    $row['date_label'],
                    $row['time_in'] ?? '--',
                    $row['time_out'] ?? '--',
                    $row['total_duration'] ?? '--',
                    $row['status'],
                    $row['remarks'] ?? '',
                ]);
            }

            fclose($output);
        }, $filename, $headers);
    }

    public function exportSummaryPdf()
    {
        $summary = $this->getDailyTimeRecords();
        if ($summary->isEmpty()) {
            session()->flash('status', 'No attendance summary data available for the selected filters.');
            return null;
        }

        $pdf = Pdf::loadView('pdf.attendance-summary', [
            'summary' => $summary,
            'filters' => $this->currentFilters(),
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'attendance_summary_' . date('Y-m-d_His') . '.pdf'
        );
    }

    public function delete(int $logId): void
    {
        $log = AttendanceLog::find($logId);
        if ($log) {
            $log->delete();
            session()->flash('status', 'Attendance log deleted.');
        }
        $this->resetPage();
    }

    private function resolveDateRange(): array
    {
        $start = $this->dateFrom ? Carbon::parse($this->dateFrom)->startOfDay() : Carbon::now()->startOfMonth();
        $end = $this->dateTo ? Carbon::parse($this->dateTo)->endOfDay() : Carbon::now()->endOfDay();

        if ($start->gt($end)) {
            $swap = $start->copy();
            $start = $end->copy();
            $end = $swap;
        }

        return [$start, $end];
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
        if ($totalMinutes === null) {
            return null;
        }

        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%02dh %02dm', $hours, $minutes);
    }
}
