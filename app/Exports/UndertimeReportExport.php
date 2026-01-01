<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UndertimeReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles
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
            'Undertime (Minutes)',
            'Undertime (Hours)',
            'Status',
            'Remarks',
            'Generated At',
            'Filter Range',
        ];
    }

    public function map($record): array
    {
        $filterRange = trim(($this->filters['date_from'] ?? '') . ' - ' . ($this->filters['date_to'] ?? ''));
        
        $undertimeHours = isset($record['undertime_minutes']) && $record['undertime_minutes'] > 0
            ? sprintf('%dh %dm', intdiv($record['undertime_minutes'], 60), $record['undertime_minutes'] % 60)
            : '-';

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
            $record['undertime_minutes'] ?? 0,
            $undertimeHours,
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
