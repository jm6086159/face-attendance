<?php
namespace App\Livewire\Employees;

use App\Models\Employee;
use App\Models\FaceTemplate;
use App\Models\AttendanceLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    public $perPage = 10;
    public $q = '';
    public $department = '';
    public $selectedEmployee = '';

    protected $queryString = ['q', 'department', 'selectedEmployee'];

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function updatedDepartment(): void
    {
        $this->selectedEmployee = '';
        $this->resetPage();
    }

    public function updatedSelectedEmployee(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $selected = trim((string) $this->department);
        $combinedExpression = "TRIM(REPLACE(CONCAT_WS('/', department, NULLIF(course, '')), '//', '/'))";

        $employees = Employee::query()
            ->withCount('templates')
            ->when($this->q, function ($query) {
                $search = '%' . $this->q . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('emp_code', 'like', $search)
                      ->orWhere('first_name', 'like', $search)
                      ->orWhere('last_name', 'like', $search)
                      ->orWhere('department', 'like', $search)
                      ->orWhere('course', 'like', $search)
                      ->orWhereRaw("CONCAT_WS('/', department, course) like ?", [$search]);
                });
            })
            ->when($selected !== '', function ($query) use ($selected, $combinedExpression) {
                $query->where(function ($scope) use ($selected, $combinedExpression) {
                    $scope->whereRaw("$combinedExpression = ?", [$selected])
                          ->orWhereRaw("TRIM(department) = ?", [$selected])
                          ->orWhereRaw("TRIM(course) = ?", [$selected]);
                });
            })
            ->when($this->selectedEmployee, function ($query) {
                $query->where('id', $this->selectedEmployee);
            })
            ->orderBy('id', 'desc')
            ->paginate($this->perPage);

        $departments = Employee::query()
            ->selectRaw("$combinedExpression as label")
            ->whereNotNull('department')
            ->where('department', '<>', '')
            ->distinct()
            ->orderBy('label')
            ->pluck('label');

        $employeeOptionsQuery = Employee::query()
            ->select(['id', 'emp_code', 'first_name', 'last_name', 'department', 'course'])
            ->orderBy('first_name')
            ->orderBy('last_name');

        if ($selected !== '') {
            $employeeOptionsQuery->where(function ($scope) use ($selected, $combinedExpression) {
                $scope->whereRaw("$combinedExpression = ?", [$selected])
                      ->orWhereRaw("TRIM(department) = ?", [$selected])
                      ->orWhereRaw("TRIM(course) = ?", [$selected]);
            });
        }

        $employeeOptions = $employeeOptionsQuery->get();

        return view('livewire.employees.index', [
            'employees' => $employees,
            'departments' => $departments,
            'employeeOptions' => $employeeOptions,
        ]);
    }

    public function delete(int $employeeId): void
    {
        DB::transaction(function () use ($employeeId) {
            $employee = Employee::findOrFail($employeeId);

            $templates = FaceTemplate::where('employee_id', $employee->id)->get();
            foreach ($templates as $tpl) {
                if (!empty($tpl->image_path)) {
                    try {
                        Storage::disk('public')->delete($tpl->image_path);
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
                $tpl->delete();
            }

            try {
                Storage::disk('public')->deleteDirectory('face_templates/' . $employee->id);
            } catch (\Throwable $e) {
                // ignore
            }

            AttendanceLog::where('employee_id', $employee->id)->delete();

            $employee->forceDelete();
        });

        session()->flash('status', 'Employee and related data deleted successfully.');
        $this->resetPage();
    }
}
