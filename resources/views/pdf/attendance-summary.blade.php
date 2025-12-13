<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daily Time Record</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; margin: 18px; color: #111; }
        h1 { margin-bottom: 4px; font-size: 18px; }
        .meta { font-size: 10px; margin-bottom: 12px; line-height: 1.4; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 5px 6px; text-align: left; }
        th { background: #f0f0f0; font-size: 10px; }
        tbody tr:nth-child(even) { background: #fafafa; }
    </style>
</head>
<body>
    <h1>Daily Time Record</h1>
    <div class="meta">
        Period: {{ $filters['date_from'] ?? 'N/A' }} - {{ $filters['date_to'] ?? 'N/A' }}<br>
        Department: {{ $filters['department'] ?: 'All' }} | Action: {{ $filters['action'] ?: 'All' }} | Late only: {{ ($filters['late_only'] ?? false) ? 'Yes' : 'No' }}<br>
        Generated at: {{ now()->format('Y-m-d H:i:s') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Employee</th>
                <th>Department</th>
                <th>Date</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Total</th>
                <th>Status</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($summary as $row)
                <tr>
                    <td>{{ $row['emp_code'] }}</td>
                    <td>{{ $row['employee_name'] }}</td>
                    <td>{{ $row['department'] }}</td>
                    <td>{{ $row['date_label'] }}</td>
                    <td>{{ $row['time_in'] ?? '--' }}</td>
                    <td>{{ $row['time_out'] ?? '--' }}</td>
                    <td>{{ $row['total_duration'] ?? '--' }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td>{{ $row['remarks'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
