<?php
namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AttendanceLogsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        protected Builder $query,
        protected array $filters = []
    ) {
    }

    public function collection(): Collection
    {
        return $this->query->get();
    }

    public function headings(): array
    {
        return [
            'Employee Code',
            'Employee Name',
            'Department',
            'Course',
            'Position',
            'Action',
            'Status',
            'Logged At',
            'Late Minutes',
            'Minutes Worked',
            'Device / Source',
            'Confidence',
            'Notes',
            'Generated At',
            'Filter Range',
        ];
    }

    public function map($log): array
    {
        $employee = $log->employee;
        $status = $log->action === 'time_in'
            ? ($log->is_late ? 'time_in_late' : 'time_in_on_time')
            : $log->action;

        $filterRange = trim(($this->filters['date_from'] ?? '') . ' - ' . ($this->filters['date_to'] ?? ''));

        $lateMinutes = data_get($log->meta, 'late_minutes', $log->is_late ? 1 : 0);
        $minutesWorked = data_get($log->meta, 'minutes_worked');
        $device = data_get($log->meta, 'device', $log->device_id ?? 'N/A');
        $notes = data_get($log->meta, 'notes');

        return [
            $log->emp_code ?? $employee?->emp_code ?? 'N/A',
            $employee?->full_name ?? 'Unassigned Employee',
            $employee?->department ?? '-',
            $employee?->course ?? '-',
            $employee?->position ?? '-',
            $log->action,
            $status,
            optional($log->logged_at)->format('Y-m-d H:i:s'),
            $lateMinutes,
            $minutesWorked,
            $device,
            $log->confidence ?? '-',
            $notes,
            now()->format('Y-m-d H:i:s'),
            $filterRange,
        ];
    }
}