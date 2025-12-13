<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Status - Face Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0">Database Status</h3>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h4>{{ $employeeCount }}</h4>
                                        <p class="mb-0">Employees</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h4>{{ $faceTemplateCount }}</h4>
                                        <p class="mb-0">Face Templates</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h4>{{ $attendanceLogCount }}</h4>
                                        <p class="mb-0">Attendance Logs</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <a href="/face-registration" class="btn btn-primary">Register Face</a>
                            <a href="/face-attendance" class="btn btn-success">Face Attendance</a>
                            <a href="/employees" class="btn btn-secondary">Manage Employees</a>
                            <a href="/attendance" class="btn btn-info">View Attendance</a>
                        </div>

                        <div class="alert alert-info">
                            <strong>Instructions:</strong>
                            <ol>
                                <li>Go to "Register Face" to add new employees with face templates</li>
                                <li>Go to "Face Attendance" to test face recognition</li>
                                <li>Check "Manage Employees" to see registered employees</li>
                                <li>Check "View Attendance" to see attendance logs</li>
                            </ol>
                        </div>

                        <div class="mt-4">
                            <h5>API Endpoints:</h5>
                            <ul class="list-group">
                                <li class="list-group-item"><code>GET /api/face-embeddings</code> - Get all face templates</li>
                                <li class="list-group-item"><code>POST /api/register-face</code> - Register new face</li>
                                <li class="list-group-item"><code>POST /api/recognize-proxy</code> - Recognize face for attendance</li>
                                <li class="list-group-item"><code>GET /api/debug/database</code> - API database status</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
