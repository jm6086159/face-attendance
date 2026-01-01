<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceIssuesExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        protected Collection $records,
        protected array $filters = []
    ) {
    }

    public function collection(): Collection
    {
        return $this->records;
    }

    public function headings(): array
    {
        return [
            'Date',
            'Employee Code',
            'Employee Name',
            'Department',
            'Course',
            'Position',
            'Time In',
            'Time Out',
            'Expected Hours',
            'Actual Hours',
            'Late (Minutes)',
            'Undertime (Minutes)',
            'Overtime (Minutes)',
            'Issue Type',
            'Status',
            'Remarks',
            'Generated At',
            'Filter Range',
        ];
    }

    public function map($record): array
    {
        $filterRange = trim(($this->filters['date_from'] ?? '') . ' - ' . ($this->filters['date_to'] ?? ''));

        return [
            $record['date'] ?? '-',
            $record['emp_code'] ?? '-',
            $record['employee_name'] ?? '-',
            $record['department'] ?? '-',
            $record['course'] ?? '-',
            $record['position'] ?? '-',
            $record['time_in'] ?? '-',
            $record['time_out'] ?? '-',
            $record['expected_hours'] ?? '-',
            $record['actual_hours'] ?? '-',
            $record['late_minutes'] ?? 0,
            $record['undertime_minutes'] ?? 0,
            $record['overtime_minutes'] ?? 0,
            ucfirst($record['issue_type'] ?? '-'),
            $record['status'] ?? '-',
            $record['remarks'] ?? '-',
            now()->format('Y-m-d H:i:s'),
            $filterRange,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
