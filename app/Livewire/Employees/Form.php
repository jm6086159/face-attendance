<?php
namespace App\Livewire\Employees;

use App\Models\Employee;
use App\Models\FaceTemplate;
use App\Http\Controllers\RecognitionController;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;

#[Layout('components.layouts.app')]
class Form extends Component
{
    use WithFileUploads;

    public ?Employee $employee = null;
    public string $emp_code = '';
    public string $first_name = '';
    public string $last_name = '';
    public ?string $email = null;
    public ?string $department = null;
    public ?string $position = null;
    public ?string $photo_url = null;
    public bool $active = true;
    public ?string $password = null;
    public ?string $password_confirmation = null;

    public array $photos = [];

    // Combined department/course options
    public array $departmentOptions = [
        'CBE/BSIT',
        'CBE/BSBA',
        'CBE/BSA',
        'CBE/BSOA',
        'CTE/BSED',
        'CTE/BEED',
        'CCJE/BSCRIM',
    ];

    /**
     * Maps combined department/course to their positions.
     */
    protected array $departmentPositions = [
        'CBE/BSIT' => [
            'Head',
            'Instructor',
            'Associate Instructor',
        ],
        'CBE/BSBA' => [
            'Head',
            'Instructor',
            'Associate Instructor',
        ],
        'CBE/BSA' => [
            'Head',
            'Instructor',
            'Associate Instructor',
        ],
        'CBE/BSOA' => [
            'Head',
            'Instructor',
            'Associate Instructor',
        ],
        'CTE/BSED' => [
            'Head',
            'Instructor',
            'Associate Instructor',
        ],
        'CTE/BEED' => [
            'Head',
            'Instructor',
            'Associate Instructor',
        ],
        'CCJE/BSCRIM' => [
            'Head',
            'Instructor',
            'Associate Instructor',
        ],
    ];

    public function mount(?int $employeeId = null): void
    {
        if ($employeeId) {
            $this->employee = Employee::findOrFail($employeeId);
            $this->fill($this->employee->only([
                'emp_code','first_name','last_name','email','department','position','photo_url','active'
            ]));

            $this->department = $this->formatDepartmentCourseSelection(
                $this->employee->department,
                $this->employee->course
            );
        }
    }

    public function rules(): array
    {
        return [
            'emp_code'   => ['required','string','max:50', Rule::unique('employees','emp_code')->ignore($this->employee?->id)],
            'first_name' => ['required','string','max:100'],
            'last_name'  => ['required','string','max:100'],
            'email'      => ['nullable','email','max:255'],
            'department' => ['nullable','string', Rule::in($this->departmentOptions)],
            'position'   => ['nullable','string','required_with:department', Rule::in($this->getPositionOptionsProperty())],
            'photo_url'  => ['nullable','url','max:2048'],
            'active'     => ['boolean'],
            'photos.*'   => ['image','max:5120'],
            'password'   => [$this->employee ? 'nullable' : 'required','string','min:6','confirmed'],
        ];
    }

    public function updatedDepartment($value): void
    {
        // Reset position when department changes
        $this->position = null;
    }

    public function getPositionOptionsProperty(): array
    {
        if (!$this->department || !isset($this->departmentPositions[$this->department])) {
            return [];
        }

        return $this->departmentPositions[$this->department];
    }

    protected function parseDepartmentCourse(?string $combo): array
    {
        if (!$combo || strpos($combo, '/') === false) {
            $trimmed = $combo ? trim($combo) : null;
            return [$trimmed, null];
        }

        [$dept, $course] = explode('/', $combo, 2);
        return [trim($dept), trim($course)];
    }

    protected function formatDepartmentCourseSelection(?string $dept, ?string $course): ?string
    {
        if (!$dept) {
            return null;
        }

        return $course && trim($course) !== ''
            ? trim($dept).'/'.trim($course)
            : trim($dept);
    }

    public function save()
    {
        $data = $this->validate();
        if (!array_key_exists('password', $data) || $data['password'] === null || $data['password'] === '') {
            unset($data['password']);
        }

        [$deptValue, $courseValue] = $this->parseDepartmentCourse($data['department'] ?? null);
        $data['department'] = $deptValue;
        $data['course'] = $courseValue;

        DB::transaction(function () use ($data) {
            if ($this->employee) {
                $this->employee->update($data);
            } else {
                $this->employee = Employee::create($data);
            }

            // Process each uploaded photo
            foreach ($this->photos as $upload) {
                $path = $upload->store('face_templates/'.$this->employee->id, 'public');

                $tpl = FaceTemplate::create([
                    'employee_id' => $this->employee->id,
                    'image_path'  => $path,
                    'source'      => 'admin_upload',
                    'model'       => null,
                    'score'       => null,
                ]);

                // Generate a public URL for FastAPI to access the image
                $imageUrl = Storage::url($path);
                // Send to FastAPI for embedding; may update FaceTemplate if needed
                RecognitionController::registerFaceViaApi($this->employee->id, $imageUrl);
            }
        });

        if ($this->employee->wasRecentlyCreated) {
            session()->flash('employee_created', $this->employee);
            session()->flash('status', 'Employee registered successfully!');
            return redirect()->route('employees.create');
        } else {
            session()->flash('status', 'Employee updated.');
            return redirect()->route('employees.index');
        }
    }

    public function render()
    {
        return view('livewire.employees.form');
    }
}
