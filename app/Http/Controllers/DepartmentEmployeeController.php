<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class DepartmentEmployeeController extends Controller
{
    /**
     * Return employees that belong to the requested department.
     *
     * @param  string|null  $department Encoded department identifier or "all".
     */
    public function __invoke(?string $department = null): JsonResponse
    {
        $department = ($department === null || $department === '' || strtolower($department) === 'all')
            ? null
            : urldecode($department);

        $deptName = null;
        $courseName = null;
        if ($department) {
            if (Str::contains($department, '/')) {
                [$deptName, $courseName] = array_map('trim', explode('/', $department, 2));
            } else {
                $deptName = trim($department);
            }
        }

        $employees = Employee::query()
            ->when($deptName, function ($query) use ($deptName) {
                $query->where('department', $deptName);
            })
            ->when($courseName, function ($query) use ($courseName) {
                $query->where(function ($scope) use ($courseName) {
                    $scope->where('course', $courseName)
                        ->orWhereNull('course');
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'emp_code', 'first_name', 'last_name', 'department']);

        $data = $employees->map(function (Employee $employee) {
            return [
                'id' => $employee->id,
                'department' => $employee->department ?? '-',
                'label' => trim(($employee->emp_code ?? 'N/A') . ' (' . $employee->full_name . ')'),
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }
}
