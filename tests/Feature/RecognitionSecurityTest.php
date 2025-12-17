<?php

use App\Models\Employee;
use App\Models\FaceTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use function Pest\Laravel\{postJson};

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set up test environment
    config(['services.recognition.threshold' => 0.65]);
    config(['services.fastapi.url' => 'http://localhost:8000']);
});

it('rejects low confidence matches below threshold', function () {
    // Create an employee with a face template
    $employee = Employee::factory()->create(['emp_code' => 'EMP001']);
    
    // Create a face template with a known embedding
    FaceTemplate::factory()->create([
        'employee_id' => $employee->id,
        'embedding' => array_fill(0, 512, 0.5),
    ]);

    // Mock FastAPI response with low confidence
    Http::fake([
        '*/api/recognize' => Http::response([
            'embedding' => array_fill(0, 512, 0.1), // Very different embedding
            'liveness_pass' => true,
            'model' => 'test-model',
        ], 200),
    ]);

    $image = UploadedFile::fake()->image('face.jpg');
    
    $response = postJson('/api/recognize-proxy', [
        'action' => 'time_in',
        'image' => $image,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Face not recognized. Please register first.',
        ])
        ->assertJsonStructure(['confidence']);
});

it('rejects requests when liveness check fails', function () {
    // Create an employee with a face template
    $employee = Employee::factory()->create(['emp_code' => 'EMP001']);
    
    FaceTemplate::factory()->create([
        'employee_id' => $employee->id,
        'embedding' => array_fill(0, 512, 0.5),
    ]);

    // Mock FastAPI response with failed liveness
    Http::fake([
        '*/api/recognize' => Http::response([
            'embedding' => array_fill(0, 512, 0.5), // Matching embedding
            'liveness_pass' => false,
            'model' => 'test-model',
        ], 200),
    ]);

    $image = UploadedFile::fake()->image('face.jpg');
    
    $response = postJson('/api/recognize-proxy', [
        'action' => 'time_in',
        'image' => $image,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Liveness check failed.',
        ]);
});

it('rejects client-provided embeddings without liveness', function () {
    // Create an employee with a face template
    $employee = Employee::factory()->create(['emp_code' => 'EMP001']);
    
    FaceTemplate::factory()->create([
        'employee_id' => $employee->id,
        'embedding' => array_fill(0, 512, 0.5),
    ]);

    // Simulate client-provided embedding (which sets liveness_pass to false)
    $embedding = json_encode(array_fill(0, 512, 0.5));
    
    $response = postJson('/api/recognize-proxy', [
        'action' => 'time_in',
        'embedding' => $embedding,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Liveness check failed.',
        ]);
});

it('accepts high confidence matches with liveness pass', function () {
    // Create an employee with a face template
    $employee = Employee::factory()->create(['emp_code' => 'EMP001']);
    
    $matchingEmbedding = array_fill(0, 512, 0.5);
    FaceTemplate::factory()->create([
        'employee_id' => $employee->id,
        'embedding' => $matchingEmbedding,
    ]);

    // Mock FastAPI response with high confidence and liveness pass
    Http::fake([
        '*/api/recognize' => Http::response([
            'embedding' => $matchingEmbedding, // Exact match
            'liveness_pass' => true,
            'model' => 'test-model',
        ], 200),
    ]);

    $image = UploadedFile::fake()->image('face.jpg');
    
    $response = postJson('/api/recognize-proxy', [
        'action' => 'time_in',
        'image' => $image,
    ]);

    // Should succeed if within allowed time window, or fail with window message
    expect($response->status())->toBeIn([200, 422]);
    
    if ($response->status() === 422) {
        // If it's 422, it should be due to time window, not recognition
        expect($response->json('message'))->toContain('allowed');
    }
});
