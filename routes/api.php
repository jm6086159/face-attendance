<?php
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RecognitionController;
use App\Http\Controllers\FaceController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\MobileAuthController;


Route::get('/health', fn () => ['ok' => true, 'ts' => now()->toIso8601String()]);

Route::get('/face-embeddings', [FaceController::class, 'embeddings']);
Route::post('/register-face',  [FaceController::class, 'register']);

Route::post('/recognize-proxy', [RecognitionController::class, 'proxy']);

// Minimal mobile API for the Flutter app
Route::post('/mobile/login', [MobileAuthController::class, 'login']);
Route::get('/attendance', [AttendanceController::class, 'index']);

// Debug route to check database status
Route::get('/debug/database', function () {
    try {
        $employeeCount = \App\Models\Employee::count();
        $faceTemplateCount = \App\Models\FaceTemplate::count();
        $attendanceLogCount = \App\Models\AttendanceLog::count();
        
        return response()->json([
            'status' => 'connected',
            'employees' => $employeeCount,
            'face_templates' => $faceTemplateCount,
            'attendance_logs' => $attendanceLogCount,
            'recent_employees' => \App\Models\Employee::latest()->take(5)->get(['id', 'emp_code', 'first_name', 'last_name']),
            'recent_face_templates' => \App\Models\FaceTemplate::with('employee:id,emp_code')->latest()->take(5)->get(['id', 'employee_id', 'model', 'source'])
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});

// Debug: current schedule state
Route::get('/schedule/state', function () {
    $now = Carbon::now();
    $cfg = Setting::getCached('attendance.schedule', null, 60);
    if (!$cfg) {
        return ['configured' => false, 'now' => $now->toIso8601String()];
    }
    $dayIso = $now->dayOfWeekIso; // 1..7
    $enabledDays = collect($cfg['days'] ?? [])->map(fn($d) => (int)$d)->all();
    $dayEnabled = in_array($dayIso, $enabledDays, true);

    $in = $dayEnabled && _between($now, $cfg['in_start'] ?? '06:00', $cfg['in_end'] ?? '08:00');
    $out = $dayEnabled && _between($now, $cfg['out_start'] ?? '16:00', $cfg['out_end'] ?? '17:00');

    return [
        'configured' => true,
        'now' => $now->toIso8601String(),
        'config' => $cfg,
        'dayEnabled' => $dayEnabled,
        'inWindow' => $in,
        'outWindow' => $out,
    ];
});

if (!function_exists('_between')) {
    function _between(Carbon $now, string $start, string $end): bool {
        [$sh,$sm] = array_map('intval', explode(':', $start));
        [$eh,$em] = array_map('intval', explode(':', $end));
        $s = (clone $now)->setTime($sh,$sm,0);
        $e = (clone $now)->setTime($eh,$em,59);
        return $now->greaterThanOrEqualTo($s) && $now->lessThanOrEqualTo($e);
    }
}


