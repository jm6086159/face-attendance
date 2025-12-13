<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\FaceTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class FaceController extends Controller
{
    // GET /api/face-embeddings
    public function embeddings()
    {
        $rows = FaceTemplate::with('employee:id,emp_code,first_name,last_name')
            ->whereNotNull('embedding')
            ->get()
            ->map(fn($t) => [
                'label'     => $t->employee?->emp_code . ' - ' . $t->employee?->full_name,
                'descriptor' => $t->embedding,
                'employee_id' => $t->employee_id,
                'emp_code'  => $t->employee?->emp_code,
                'model'     => $t->model,
            ]);
        return response()->json($rows);
    }

    // POST /api/register-face
    public function register(Request $req)
    {
        $req->validate([
            'emp_code'   => 'required|string|max:50',
            'image'      => 'required|image|max:4096',
            'name'       => 'nullable|string|max:200',
            'email'      => 'nullable|email|max:255',
            'embedding'  => 'nullable|string',
        ]);

        $fastapi = rtrim(config('services.fastapi.url'), '/');
        $apiKey  = config('services.fastapi.secret');

        $embedding = null;
        $model     = null;
        $score     = null;

        if ($req->filled('embedding')) {
            $decoded = json_decode($req->input('embedding'), true);

            if (!is_array($decoded) || empty($decoded)) {
                return response()->json([
                    'message' => 'Invalid embedding payload supplied.',
                ], 422);
            }

            // Force floats and clamp array length to a sane maximum (face-api.js = 128)
            $embedding = array_map('floatval', array_slice($decoded, 0, 512));
            $model     = 'face-api.js';
            $score     = 1.0;
        }

        try {
            if (!$embedding) {
                // Send image to FastAPI for embedding extraction
                $resp = Http::timeout(30)
                    ->asMultipart()
                    ->attach('image', file_get_contents($req->file('image')->getRealPath()), 'face.jpg')
                    ->post("$fastapi/api/embed", ['api_key' => $apiKey]);

                if (!$resp->successful()) {
                    return response()->json([
                        'message' => 'FastAPI service error: ' . $resp->status(),
                        'error' => $resp->body()
                    ], 502);
                }

                $responseData = $resp->json();
                
                if (empty($responseData['embedding']) || !is_array($responseData['embedding'])) {
                    return response()->json(['message' => 'No face detected or invalid embedding'], 422);
                }

                $embedding = $responseData['embedding'];
                $model     = $responseData['model'] ?? 'face_recognition_dlib';
                $score     = $responseData['score'] ?? null;
            }

            // Use database transaction for data integrity
            return DB::transaction(function () use ($req, $embedding, $model, $score) {
                // Find or create employee
                $employee = Employee::where('emp_code', $req->emp_code)->first();
                
                if (!$employee) {
                    // Create new employee if doesn't exist
                    $nameParts = explode(' ', $req->name ?? 'Unknown User', 2);
                    $employee = Employee::create([
                        'emp_code'   => $req->emp_code,
                        'first_name' => $nameParts[0] ?? 'Unknown',
                        'last_name'  => $nameParts[1] ?? 'User',
                        'email'      => $req->email,
                        'active'     => true,
                    ]);
                }

                // Store the uploaded image
                $imagePath = $req->file('image')->store('face_templates/' . $employee->id, 'public');

                // Store face template with all required fields
                $faceTemplate = FaceTemplate::create([
                    'employee_id' => $employee->id,
                    'image_path'  => $imagePath,
                    'embedding'   => $embedding,
                    'model'       => $model ?? 'face_recognition_dlib',
                    'score'       => $score,
                    'source'      => 'face_capture',
                ]);

                return response()->json([
                    'message' => 'Face registered successfully',
                    'employee_id' => $employee->id,
                    'emp_code' => $employee->emp_code,
                    'employee_name' => $employee->full_name,
                    'face_template_id' => $faceTemplate->id,
                    'image_path' => Storage::url($imagePath)
                ], 201);

            });

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
