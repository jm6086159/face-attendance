<?php
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

use App\Livewire\Employees\Index as EmployeesIndex;
use App\Livewire\Employees\Form  as EmployeesForm;
use App\Livewire\Attendance\Index as AttendanceIndex;
use App\Livewire\Attendance\History as AttendanceHistory;
use App\Http\Controllers\RecognitionController;
use App\Http\Controllers\DepartmentEmployeeController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('dashboard', function () {
    $totalEmployees = \App\Models\Employee::count();

    $start = \Carbon\Carbon::today()->startOfDay();
    $end   = \Carbon\Carbon::today()->endOfDay();

    // Present = distinct employees with a check-in today
    $present = \App\Models\AttendanceLog::query()
        ->whereIn('action', ['time_in','check_in','in'])
        ->whereBetween(\Illuminate\Support\Facades\DB::raw('COALESCE(logged_at, created_at)'), [$start, $end])
        ->distinct('employee_id')
        ->count('employee_id');

    // Late = check-in after threshold (from settings or default 09:00)
    $cfg = \App\Models\Setting::getValue('attendance.schedule');
    $threshold = '09:00';
    if (is_array($cfg) && !empty($cfg['in_end'])) { $threshold = $cfg['in_end']; }

    $late = \App\Models\AttendanceLog::query()
        ->whereIn('action', ['time_in','check_in','in'])
        ->whereBetween(\Illuminate\Support\Facades\DB::raw('COALESCE(logged_at, created_at)'), [$start, $end])
        ->whereTime(\Illuminate\Support\Facades\DB::raw('COALESCE(logged_at, created_at)'), '>', $threshold)
        ->distinct('employee_id')
        ->count('employee_id');

    $absent = max(0, $totalEmployees - $present);

    return view('dashboard', compact('totalEmployees','present','late','absent'));
})->middleware(['auth','verified'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    // Attendance schedule settings (Livewire)
    Route::get('settings/attendance-schedule', \App\Livewire\Settings\AttendanceSchedule::class)
        ->name('settings.attendance-schedule');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                []
            ),
        )
        ->name('two-factor.show');

    // Employee pages
    Route::get('/employees', EmployeesIndex::class)->name('employees.index');
    Route::get('/employees/create', EmployeesForm::class)->name('employees.create');
    Route::get('/employees/{employeeId}/edit', EmployeesForm::class)->name('employees.edit');

    // Attendance page
    Route::get('/attendance', AttendanceIndex::class)->name('attendance.index');
    Route::get('/attendance/history', AttendanceHistory::class)->name('attendance.history');

    // Dependent dropdown endpoint (department -> employees)
    Route::get('/departments/{department?}/employees', DepartmentEmployeeController::class)
        ->where('department', '.*')
        ->name('departments.employees');

    // Face capture pages
    Route::get('/face-registration/{employeeId?}', function ($employeeId = null) {
        $employee = null;
        if ($employeeId) {
            $employee = \App\Models\Employee::find($employeeId);
        }
        
        $html = file_get_contents(public_path('FaceApi/frontend/register_user.html'));
        
        // Replace placeholders with actual employee data if available
        if ($employee) {
            $html = str_replace('{{ csrf_token() }}', csrf_token(), $html);
            $html = str_replace('placeholder="Enter employee code (e.g., EMP001)"', 
                               'placeholder="' . $employee->emp_code . '" value="' . $employee->emp_code . '" readonly', $html);
            $html = str_replace('placeholder="Enter your full name"', 
                               'placeholder="' . $employee->full_name . '" value="' . $employee->full_name . '" readonly', $html);
            $html = str_replace('placeholder="Enter your email address"', 
                               'placeholder="' . ($employee->email ?? 'No email') . '" value="' . ($employee->email ?? '') . '" readonly', $html);
        } else {
            $html = str_replace('{{ csrf_token() }}', csrf_token(), $html);
        }
        
        return response($html)->header('Content-Type', 'text/html');
    })->name('face.registration');
    
    Route::get('/face-attendance', function () {
        return response()->file(public_path('FaceApi/frontend/attendance.html'));
    })->name('face.attendance');
    
    // Debug route to check database status
    Route::get('/debug/database', function () {
        try {
            $employeeCount = \App\Models\Employee::count();
            $faceTemplateCount = \App\Models\FaceTemplate::count();
            $attendanceLogCount = \App\Models\AttendanceLog::count();
            
            return view('debug.database', compact('employeeCount', 'faceTemplateCount', 'attendanceLogCount'));
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    })->name('debug.database');
});

// API endpoints are defined in routes/api.php. Avoid duplicating here
// to prevent CSRF conflicts for public webcam pages.

require __DIR__.'/auth.php';
