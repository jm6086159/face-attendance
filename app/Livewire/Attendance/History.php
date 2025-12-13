<?php

namespace App\Livewire\Attendance;

use App\Exports\AttendanceLogsExport;
use App\Models\AttendanceLog;
use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Illuminate\Support\Facades\DB;

class History extends Component
{
    public string $q = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $quickRange = 'last30';
    public string $department = '';
    public string $employee = '';
    public string $status = 'all'; // all | present | late | missing_out | absent

    public function mount(): void
    {
        $this->applyQuickRange('last30');
    }

    public function updated($field): void
    {
        if (in_array($field, ['q', 'dateFrom', 'dateTo', 'department', 'employee', 'status'], true)) {
            // Reset quick range when manual date change happens
            if (in_array($field, ['dateFrom', 'dateTo'], true)) {
                $this->quickRange = 'custom';
            }
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

    private function getFilteredQuery()
    {
        return AttendanceLog::query()
            ->with('employee:id,emp_code,first_name,last_name,department')
            ->when($this->q, function ($query) {
                $search = '%' . $this->q . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('emp_code', 'like', $search)
                        ->orWhereHas('employee', function ($eq) use ($search) {
                            $eq->where('first_name', 'like', $search)
                                ->orWhere('last_name', 'like', $search);
                        });
                });
            })
            ->when($this->department, function ($query) {
                $query->whereHas('employee', fn ($q) => $q->where('department', $this->department));
            })
            ->when($this->employee, function ($query) {
                $query->where(function ($sub) {
                    $sub->where('employee_id', $this->employee);
                    $employee = Employee::find($this->employee);
                    if ($employee && $employee->emp_code) {
                        $sub->orWhere('emp_code', $employee->emp_code);
                    }
                });
            });
    }

    private function getHistoryRecords(): Collection
    {
        [$start, $end] = $this->resolveDateRange();
        $dates = $this->generateDateSeries($start, $end);

        $logs = $this->getFilteredQuery()
            ->whereBetween(DB::raw('COALESCE(logged_at, created_at)'), [$start, $end])
            ->orderBy('logged_at')
            ->get();

        if ($logs->isEmpty()) {
            return collect();
        }

        $grouped = $logs
            ->groupBy(function ($log) {
                $emp = $log->employee;
                $code = $emp->emp_code ?? $log->emp_code ?? 'N/A';
                $name = $emp?->full_name ?? trim(($emp->first_name ?? '') . ' ' . ($emp->last_name ?? ''));
                $name = $name !== '' ? $name : 'Unassigned Employee';
                $dept = $emp->department ?? '-';
                return "{$code}|{$name}|{$dept}";
            })
            ->sortKeys();

        $records = collect();

        foreach ($grouped as $key => $employeeLogs) {
            [$code, $name, $dept] = explode('|', $key);

            $logsByDate = $employeeLogs
                ->sortBy('logged_at')
                ->groupBy(fn ($log) => ($log->logged_at ?? $log->created_at)->toDateString());

            foreach ($dates as $date) {
                $dateKey = $date->toDateString();
                $dayLogs = $logsByDate->get($dateKey, collect())->sortBy('logged_at')->values();

                if ($dayLogs->isEmpty()) {
                    $records->push([
                        'date' => $dateKey,
                        'date_label' => $date->translatedFormat('M d, Y (D)'),
                        'employee_name' => $name,
                        'emp_code' => $code,
                        'department' => $dept,
                        'time_in' => null,
                        'time_out' => null,
                        'total_duration' => null,
                        'status' => 'Absent',
                        'is_late' => false,
                        'remarks' => 'Absent',
                    ]);
                    continue;
                }

                $timeInLog = $dayLogs->firstWhere('action', 'time_in') ?? $dayLogs->first();
                $timeOutLog = $dayLogs->filter(fn ($log) => $log->action === 'time_out')->last();
                if (!$timeOutLog && $dayLogs->count() > 1) {
                    $timeOutLog = $dayLogs->last();
                }

                $remarks = collect();
                $isLate = (bool)($timeInLog?->is_late);

                if (!$timeInLog) {
                    $remarks->push('Missing time in');
                } elseif ($isLate) {
                    $remarks->push('Late');
                }
                if (!$timeOutLog) {
                    $remarks->push('Missing time out');
                }

                $status = 'Present';
                if ($remarks->contains('Missing time in') || $remarks->contains('Missing time out')) {
                    $status = 'Incomplete';
                }
                if ($remarks->contains('Late')) {
                    $status = 'Late';
                }

                $totalMinutes = null;
                if ($timeInLog && $timeOutLog && ($timeOutLog->logged_at ?? $timeOutLog->created_at)->gt($timeInLog->logged_at ?? $timeInLog->created_at)) {
                    $totalMinutes = ($timeInLog->logged_at ?? $timeInLog->created_at)->diffInMinutes($timeOutLog->logged_at ?? $timeOutLog->created_at);
                }

                $records->push([
                    'date' => $dateKey,
                    'date_label' => $date->translatedFormat('M d, Y (D)'),
                    'employee_name' => $name,
                    'emp_code' => $code,
                    'department' => $dept,
                    'time_in' => $timeInLog?->logged_at?->format('h:i A'),
                    'time_out' => $timeOutLog?->logged_at?->format('h:i A'),
                    'total_duration' => $this->formatDuration($totalMinutes),
                    'status' => $status,
                    'is_late' => $isLate,
                    'remarks' => $remarks->isEmpty() ? null : $remarks->implode(', '),
                ]);
            }
        }

        return $records
            ->when($this->status !== 'all', function ($collection) {
                return $collection->filter(function ($item) {
                    return match ($this->status) {
                        'present'     => $item['status'] === 'Present',
                        'late'        => $item['is_late'] === true,
                        'missing_out' => str_contains(strtolower($item['remarks'] ?? ''), 'out'),
                        'absent'      => $item['status'] === 'Absent',
                        default       => true,
                    };
                });
            })
            ->sortByDesc('date')
            ->values();
    }

    private function buildSummary(Collection $records): array
    {
        [$start, $end] = $this->resolveDateRange();
        $days = (int) $start->diffInDays($end) + 1;
        $totalEmployees = (int) Employee::count();

        $present = (int) $records->whereIn('status', ['Present', 'Late'])->count();
        $late = (int) $records->where('status', 'Late')->count();
        $absent = (int) max(0, ($totalEmployees * $days) - $present);

        return [
            'days' => $days,
            'present' => $present,
            'late' => $late,
            'absent' => $absent,
            'leave' => 0,
        ];
    }

    public function exportExcel()
    {
        [$start, $end] = $this->resolveDateRange();
        $query = $this->getFilteredQuery()
            ->whereBetween(DB::raw('COALESCE(logged_at, created_at)'), [$start, $end]);

        $filename = 'attendance_history_' . now()->format('Y-m-d_His') . '.xlsx';
        return Excel::download(new AttendanceLogsExport($query, [
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'department' => $this->department,
            'status' => $this->status,
            'employee' => $this->employee,
        ]), $filename, ExcelWriter::XLSX);
    }

    public function exportPdf()
    {
        $records = $this->getHistoryRecords();
        if ($records->isEmpty()) {
            session()->flash('status', 'No data to export for the selected filters.');
            return null;
        }

        $pdf = Pdf::loadView('pdf.attendance-summary', [
            'summary' => $records,
            'filters' => [
                'date_from' => $this->dateFrom,
                'date_to' => $this->dateTo,
                'department' => $this->department,
                'status' => $this->status,
                'employee' => $this->employee,
            ],
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'attendance_history_' . now()->format('Y-m-d_His') . '.pdf'
        );
    }

    public function render()
    {
        $records = $this->getHistoryRecords();
        $summary = $this->buildSummary($records);

        $departments = Employee::distinct()->pluck('department')->filter()->sort()->values();
        $employees = Employee::orderBy('first_name')->orderBy('last_name')
            ->when($this->department, fn ($q) => $q->where('department', $this->department))
            ->get(['id', 'emp_code', 'first_name', 'last_name', 'department']);

        return view('livewire.attendance.history', [
            'records' => $records,
            'summary' => $summary,
            'departments' => $departments,
            'employees' => $employees,
        ])->layout('components.layouts.app');
    }
}
