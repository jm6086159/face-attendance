<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\FaceTemplate;
use App\Models\AttendanceLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class RecognitionController extends Controller
{
    /**
     * Simple health check for the FastAPI service.
     * GET /api/health
     */
    public function health()
    {
        $base = rtrim(config('services.fastapi.url'), '/');

        try {
            $resp = Http::timeout(5)->get("$base/api/health");
            $ok   = $resp->successful();
            return response()->json([
                'ok'     => $ok,
                'status' => $resp->status(),
                'body'   => $ok ? $resp->json() : $resp->body(),
            ], $ok ? 200 : 502);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'   => false,
                'err'  => 'fastapi_unreachable',
                'msg'  => $e->getMessage(),
            ], 502);
        }
    }

    /**
     * Recognize & log (time_in/time_out) by proxying an image to FastAPI.
     * POST /api/recognize-proxy
     */
    public function proxy(Request $req)
    {
        $req->validate([
            'action'    => 'nullable|in:time_in,time_out,auto',
            'image'     => 'required_without:embedding|image|max:4096',
            'embedding' => 'nullable|string',
            'device_id' => 'nullable|integer',
            'api_key'   => 'nullable|string',
        ]);

        $action  = $req->input('action') ?: 'auto';
        $fastapi = rtrim(config('services.fastapi.url'), '/');
        $apiKey  = $req->input('api_key', config('services.fastapi.secret'));

        // Prefer client-provided embedding to avoid model mismatch.
        $probe = null;
        $json  = [];
        if ($req->filled('embedding')) {
            $arr = json_decode($req->input('embedding'), true);
            if (!is_array($arr) || empty($arr)) {
                return response()->json(['message' => 'Invalid embedding payload'], 422);
            }
            $probe = array_map('floatval', array_slice($arr, 0, 512));
            $json['model'] = 'face-api.js';
            $json['liveness_pass'] = false;
        } else {
            // Send the image to FastAPI for face detection + embedding extraction
            try {
                $resp = Http::asMultipart()
                    ->attach(
                        'image',
                        file_get_contents($req->file('image')->getRealPath()),
                        $req->file('image')->getClientOriginalName() ?: 'face.jpg'
                    )
                    ->post("$fastapi/api/recognize", [
                        'api_key'   => $apiKey,
                        'action'    => $action,
                        'device_id' => $req->input('device_id'),
                    ]);
            } catch (\Throwable $e) {
                AttendanceLog::create([
                    'employee_id'   => null,
                    'emp_code'      => null,
                    'action'        => $action,
                    'logged_at'     => now(),
                    'confidence'    => null,
                    'liveness_pass' => false,
                    'device_id'     => $req->input('device_id'),
                    'meta'          => ['error' => 'fastapi_down', 'msg' => $e->getMessage()],
                ]);
                return response()->json(['message' => 'Recognition service unavailable.'], 502);
            }

            if (!$resp->ok()) {
                AttendanceLog::create([
                    'employee_id'   => null,
                    'emp_code'      => null,
                    'action'        => $action,
                    'logged_at'     => now(),
                    'confidence'    => null,
                    'liveness_pass' => false,
                    'device_id'     => $req->input('device_id'),
                    'meta'          => ['error' => 'fastapi_error', 'status' => $resp->status(), 'body' => $resp->body()],
                ]);
                return response()->json(['message' => 'Recognition error.'], 502);
            }

            $json = $resp->json();
            if (empty($json['embedding']) || !is_array($json['embedding'])) {
                AttendanceLog::create([
                    'employee_id'   => null,
                    'emp_code'      => null,
                    'action'        => $action,
                    'logged_at'     => now(),
                    'confidence'    => null,
                    'liveness_pass' => (bool)($json['liveness_pass'] ?? false),
                    'device_id'     => $req->input('device_id'),
                    'meta'          => ['error' => 'no_face', 'model' => $json['model'] ?? null],
                ]);
                return response()->json(['message' => 'No face detected.'], 422);
            }
            $probe = $json['embedding'];
        }
        
        // Improved matching with secondary verification
        [$bestEmployee, $bestScore, $secondBestScore, $matchCount] = $this->findBestMatch($probe);

        // Get threshold from settings or config (increased default from 0.65 to 0.72)
        $recognitionSettings = Setting::getCached('recognition.settings', [
            'threshold' => 0.72,
            'require_liveness' => false,
        ]);
        $threshold = (float) ($recognitionSettings['threshold'] ?? config("services.recognition.threshold", 0.72));
        $requireLiveness = (bool) ($recognitionSettings['require_liveness'] ?? false);
        
        // Validate the match with improved accuracy checks
        [$isValid, $matchType, $rejectReason] = $this->validateMatch($bestScore, $secondBestScore, $threshold);
        
        // Reject invalid matches
        if (!$isValid) {
            return response()->json([
                'message' => 'Face not recognized. Please register first or try again.',
                'confidence' => $bestScore,
                'reason' => $matchType,
                'debug' => [
                    'best_score' => $bestScore,
                    'second_best_score' => $secondBestScore,
                    'threshold' => $threshold,
                    'candidates' => $matchCount,
                ],
            ], 422);
        }
        
        // Optional liveness check (if enabled and available from FastAPI or frontend)
        $livenessPass = (bool)($json['liveness_pass'] ?? $req->input('liveness_pass', false));
        if ($requireLiveness && !$livenessPass) {
            return response()->json([
                'message' => 'Liveness check failed. Please use a real face, not a photo.',
                'confidence' => $bestScore,
            ], 422);
        }

        $employeeId = $bestEmployee?->id;
        $empCode    = $bestEmployee?->emp_code;

        // Load attendance schedule from settings with sensible defaults
        $now = Carbon::now();
        $schedule = Setting::getCached('attendance.schedule', [
            'in_start'  => '06:00',
            'in_end'    => '08:00',
            'out_start' => '16:00',
            'out_end'   => '17:00',
            'days'      => [1,2,3,4,5], // Mon-Fri
            'from_date' => null,
            'to_date'   => null,
            'late_after'=> null,
            'late_grace'=> 0,
        ]);

        // Optional effective date range
        if (!empty($schedule['from_date'])) {
            $from = Carbon::parse($schedule['from_date'])->startOfDay();
            if ($now->lt($from)) {
                return response()->json(['message' => 'Schedule not yet effective.'], 422);
            }
        }
        if (!empty($schedule['to_date'])) {
            $to = Carbon::parse($schedule['to_date'])->endOfDay();
            if ($now->gt($to)) {
                return response()->json(['message' => 'Schedule expired.'], 422);
            }
        }

        $dayIso = $now->dayOfWeekIso; // 1..7
        $enabledDays = collect($schedule['days'] ?? [])->map(fn($d) => (int)$d)->all();
        $dayEnabled = in_array($dayIso, $enabledDays, true);

        $inWindow  = $dayEnabled && $this->timeBetweenWrap($now, $schedule['in_start'], $schedule['in_end']);
        $outWindow = $dayEnabled && $this->timeBetweenWrap($now, $schedule['out_start'], $schedule['out_end']);

        // Late threshold (HH:MM), default falls back to end of in-window
        $lateBase  = $schedule['late_after'] ?? ($schedule['in_end'] ?? null);
        $lateGrace = (int)($schedule['late_grace'] ?? 0);
        $inAllowed = $inWindow;
        if ($dayEnabled && !empty($lateBase)) {
            [$lh, $lm] = array_map('intval', explode(':', $lateBase));
            $lateThreshold = (clone $now)->setTime($lh, $lm, 0)->addMinutes($lateGrace);
            if (!$outWindow && $now->greaterThanOrEqualTo($lateThreshold)) {
                $inAllowed = true;
            }
        }

        // Calculate if employee is late
        $isLate = false;
        if (!empty($lateBase)) {
            [$lh, $lm] = array_map('intval', explode(':', $lateBase));
            $lateThreshold = (clone $now)->setTime($lh, $lm, 0)->addMinutes($lateGrace);
            $isLate = $now->greaterThan($lateThreshold);
        }

        // Decide action when in auto mode based on windows
        $chosenAction = $action;
        if ($action === 'auto') {
            if ($inAllowed) {
                $chosenAction = 'time_in';
            } elseif ($outWindow) {
                $chosenAction = 'time_out';
            } else {
                $msg = sprintf('Outside allowed windows (IN %s–%s%s, OUT %s–%s).',
                    $schedule['in_start'] ?? '06:00',
                    $schedule['in_end'] ?? '08:00',
                    isset($schedule['late_after']) && $schedule['late_after'] ? ', late after '.($schedule['late_after']) : '',
                    $schedule['out_start'] ?? '16:00',
                    $schedule['out_end'] ?? '17:00'
                );
                return response()->json(['message' => $msg], 422);
            }
        }

        // If user picked explicit action, enforce window
        if ($chosenAction === 'time_in' && !$inAllowed) {
            $msg = sprintf('Time-in allowed only between %s and %s%s.',
                $schedule['in_start'] ?? '06:00',
                $schedule['in_end'] ?? '08:00',
                isset($schedule['late_after']) && $schedule['late_after'] ? ' (late after '.($schedule['late_after']).')' : ''
            );
            return response()->json(['message' => $msg], 422);
        }
        if ($chosenAction === 'time_out' && !$outWindow) {
            $msg = sprintf('Time-out allowed only between %s and %s.',
                $schedule['out_start'] ?? '16:00',
                $schedule['out_end'] ?? '17:00'
            );
            return response()->json(['message' => $msg], 422);
        }

        // In auto mode, only create a log when we have a confident match
        if ($action === 'auto' && !$employeeId) {
            return response()->json([
                'message'        => 'No confident match; attendance not recorded.',
                'confidence'     => $bestScore,
                'liveness_pass'  => (bool)($json['liveness_pass'] ?? false),
                'timestamp_local'=> now()->toIso8601String(),
                'action'         => $chosenAction,
            ], 422);
        }

        // Avoid duplicate marking for today & return friendly message
        if ($employeeId) {
            $todayLogs = AttendanceLog::where('employee_id', $employeeId)
                ->whereDate('logged_at', $now->toDateString())
                ->get(['action', 'logged_at']);

            $inLog  = $todayLogs->firstWhere('action', 'time_in');
            $outLog = $todayLogs->firstWhere('action', 'time_out');

            if ($inLog && $outLog) {
                return response()->json([
                    'employee_id'       => $employeeId,
                    'emp_code'          => $empCode,
                    'confidence'        => $bestScore,
                    'liveness_pass'     => (bool)($json['liveness_pass'] ?? false),
                    'timestamp_local'   => $now->toIso8601String(),
                    'action'            => $chosenAction,
                    'already_completed' => true,
                    'message'           => 'Employee already completed attendance today (time in & out).',
                    'time_in_at'        => optional($inLog->logged_at)->toIso8601String(),
                    'time_out_at'       => optional($outLog->logged_at)->toIso8601String(),
                ], 200);
            }

            $existingSame = $todayLogs->firstWhere('action', $chosenAction);
            if ($existingSame) {
                return response()->json([
                    'employee_id'     => $employeeId,
                    'emp_code'        => $empCode,
                    'confidence'      => $bestScore,
                    'liveness_pass'   => (bool)($json['liveness_pass'] ?? false),
                    'timestamp_local' => $now->toIso8601String(),
                    'action'          => $chosenAction,
                    'already_marked'  => true,
                    'message'         => $chosenAction === 'time_in' ? 'Already time-in today.' : 'Already time-out today.',
                ], 200);
            }
        }

        AttendanceLog::create([
            'employee_id'   => $employeeId,
            'emp_code'      => $empCode,
            'action'        => $chosenAction,
            'is_late'       => ($chosenAction === 'time_in' && $isLate),
            'logged_at'     => $now,
            'confidence'    => $bestScore,
            'liveness_pass' => (bool)($json['liveness_pass'] ?? false),
            'device_id'     => $req->input('device_id'),
            'meta'          => ['model' => $json['model'] ?? 'unknown'],
        ]);

        return response()->json([
            'employee_id'     => $employeeId,
            'emp_code'        => $empCode,
            'confidence'      => $bestScore,
            'liveness_pass'   => (bool)($json['liveness_pass'] ?? false),
            'timestamp_local' => $now->toIso8601String(),
            'action'          => $chosenAction,
            'is_late'         => ($chosenAction === 'time_in' && $isLate),
            'device_id'       => $req->input('device_id'),
        ]);
    }

    private function timeBetweenWrap(Carbon $now, string $startHHMM, string $endHHMM): bool
    {
        [$sh, $sm] = array_map('intval', explode(':', $startHHMM));
        [$eh, $em] = array_map('intval', explode(':', $endHHMM));
        $start = (clone $now)->setTime($sh, $sm, 0);
        $end   = (clone $now)->setTime($eh, $em, 59);

        // If the end is before the start, the window wraps past midnight
        if ($end->lt($start)) {
            $end->addDay();
            $nowAdj = clone $now;
            if ($nowAdj->lt($start)) {
                $nowAdj->addDay();
            }
            return $nowAdj->greaterThanOrEqualTo($start) && $nowAdj->lessThanOrEqualTo($end);
        }

        return $now->greaterThanOrEqualTo($start) && $now->lessThanOrEqualTo($end);
    }

    /* -------------------------- internal helpers -------------------------- */

    /**
     * Find the best matching employee with improved accuracy.
     * Returns [Employee|null, bestScore, secondBestScore, matchCount]
     */
    private function findBestMatch(array $probe): array
    {
        $matches = [];

        FaceTemplate::with('employee:id,emp_code')->chunk(300, function ($chunk) use (&$matches, $probe) {
            foreach ($chunk as $tpl) {
                if (!$tpl->employee) continue;
                
                $score = $this->cosine($probe, $tpl->embedding ?? []);
                if ($score > 0) {
                    $empId = $tpl->employee->id;
                    // Keep the best score per employee (they may have multiple templates)
                    if (!isset($matches[$empId]) || $score > $matches[$empId]['score']) {
                        $matches[$empId] = [
                            'employee' => $tpl->employee,
                            'score' => $score,
                        ];
                    }
                }
            }
        });

        if (empty($matches)) {
            return [null, -1.0, -1.0, 0];
        }

        // Sort by score descending
        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);

        $best = $matches[0];
        $secondBest = $matches[1] ?? null;

        return [
            $best['employee'],
            $best['score'],
            $secondBest ? $secondBest['score'] : -1.0,
            count($matches)
        ];
    }

    /**
     * Validate that the match is confident and distinct from other candidates.
     * This prevents false positives when an unknown face partially matches multiple people.
     */
    private function validateMatch(float $bestScore, float $secondBestScore, float $threshold): array
    {
        // Primary check: must exceed threshold
        if ($bestScore < $threshold) {
            return [false, 'below_threshold', "Score {$bestScore} is below threshold {$threshold}"];
        }

        // Secondary check: must be significantly better than second-best match
        // This prevents matching unknown faces that score similarly across multiple people
        $minGap = 0.08; // Require at least 8% gap between best and second-best
        if ($secondBestScore > 0) {
            $gap = $bestScore - $secondBestScore;
            if ($gap < $minGap && $bestScore < ($threshold + 0.15)) {
                return [false, 'ambiguous_match', "Ambiguous: gap between top matches is only {$gap}"];
            }
        }

        // High confidence check: very high scores are always accepted
        if ($bestScore >= 0.85) {
            return [true, 'high_confidence', null];
        }

        // Medium confidence with good separation
        if ($bestScore >= $threshold && ($secondBestScore < 0 || ($bestScore - $secondBestScore) >= $minGap)) {
            return [true, 'confident', null];
        }

        return [true, 'marginal', null];
    }

    private function cosine(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) return -1.0;

        $dot = 0.0; $na = 0.0; $nb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na  += $a[$i] * $a[$i];
            $nb  += $b[$i] * $b[$i];
        }
        $den = sqrt($na) * sqrt($nb);
        return $den > 0 ? $dot / $den : -1.0;
    }
}
