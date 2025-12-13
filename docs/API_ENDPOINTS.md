# Face Attendance System - API Endpoints Documentation

## Overview

This document provides comprehensive documentation for all API endpoints in the Face Attendance System. The system uses Laravel's API routes with proper authentication, validation, and error handling.

---

## Authentication Endpoints

### Login
**POST** `/login`

Authenticates a user and creates a session.

**Request Body:**
```json
{
    "email": "user@example.com",
    "password": "password123",
    "remember": true
}
```

**Response (Success):**
```json
{
    "status": "success",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "user@example.com"
    },
    "redirect": "/dashboard"
}
```

**Response (Error):**
```json
{
    "message": "The provided credentials are incorrect.",
    "errors": {
        "email": ["The provided credentials are incorrect."]
    }
}
```

### Logout
**POST** `/logout`

Terminates the user session.

**Response:**
```json
{
    "status": "success",
    "message": "Logged out successfully"
}
```

### Two-Factor Authentication
**POST** `/two-factor-challenge`

Validates two-factor authentication code.

**Request Body:**
```json
{
    "code": "123456"
}
```

**Response (Success):**
```json
{
    "status": "success",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "user@example.com"
    }
}
```

---

## Face Recognition Endpoints

### Register Face
**POST** `/api/register-face`

Registers a new face template for an employee.

**Request:** Multipart form data
- `emp_code` (string, required): Employee code
- `name` (string, required): Full name
- `email` (string, required): Email address
- `image` (file, required): Face image (max 4MB)

**Headers:**
```
X-CSRF-TOKEN: {csrf_token}
Content-Type: multipart/form-data
```

**Response (Success):**
```json
{
    "message": "Face registered successfully",
    "employee_id": 1,
    "emp_code": "EMP001",
    "employee_name": "John Doe",
    "face_template_id": 1,
    "image_path": "/storage/face_templates/1/face.jpg"
}
```

**Response (Error):**
```json
{
    "message": "No face detected or invalid embedding",
    "error": "FastAPI service error: 500"
}
```

### Get Face Embeddings
**GET** `/api/face-embeddings`

Retrieves all face templates for attendance recognition.

**Response:**
```json
[
    {
        "label": "EMP001 - John Doe",
        "descriptor": [0.1, 0.2, 0.3, ...],
        "employee_id": 1,
        "emp_code": "EMP001",
        "model": "face_recognition_dlib"
    },
    {
        "label": "EMP002 - Jane Smith",
        "descriptor": [0.4, 0.5, 0.6, ...],
        "employee_id": 2,
        "emp_code": "EMP002",
        "model": "face_recognition_dlib"
    }
]
```

### Recognize Face (Proxy)
**POST** `/api/recognize-proxy`

Processes face recognition for attendance logging.

**Request:** Multipart form data
- `action` (string, required): "time_in" or "time_out"
- `image` (file, required): Face image
- `device_id` (integer, optional): Device identifier

**Headers:**
```
X-CSRF-TOKEN: {csrf_token}
Content-Type: multipart/form-data
```

**Response (Success):**
```json
{
    "status": "success",
    "employee_id": 1,
    "emp_code": "EMP001",
    "employee_name": "John Doe",
    "action": "time_in",
    "confidence": 0.95,
    "attendance_log_id": 1,
    "logged_at": "2024-01-15T09:00:00Z"
}
```

**Response (No Match):**
```json
{
    "status": "no_match",
    "message": "No matching face found",
    "confidence": 0.25
}
```

**Response (Error):**
```json
{
    "status": "error",
    "message": "FastAPI service unavailable",
    "fallback": true
}
```

---

## Employee Management Endpoints

### List Employees
**GET** `/api/employees`

Retrieves a paginated list of employees.

**Query Parameters:**
- `page` (integer): Page number (default: 1)
- `per_page` (integer): Items per page (default: 15)
- `search` (string): Search term
- `active` (boolean): Filter by active status

**Response:**
```json
{
    "data": [
        {
            "id": 1,
            "emp_code": "EMP001",
            "first_name": "John",
            "last_name": "Doe",
            "email": "john@example.com",
            "department": "IT",
            "position": "Developer",
            "active": true,
            "created_at": "2024-01-01T00:00:00Z",
            "updated_at": "2024-01-01T00:00:00Z"
        }
    ],
    "current_page": 1,
    "per_page": 15,
    "total": 1,
    "last_page": 1
}
```

### Get Employee
**GET** `/api/employees/{id}`

Retrieves a specific employee by ID.

**Response:**
```json
{
    "id": 1,
    "emp_code": "EMP001",
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "department": "IT",
    "position": "Developer",
    "active": true,
    "face_templates": [
        {
            "id": 1,
            "image_path": "/storage/face_templates/1/face.jpg",
            "model": "face_recognition_dlib",
            "created_at": "2024-01-01T00:00:00Z"
        }
    ],
    "attendance_logs": [
        {
            "id": 1,
            "action": "time_in",
            "logged_at": "2024-01-15T09:00:00Z",
            "confidence": 0.95
        }
    ],
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
}
```

### Create Employee
**POST** `/api/employees`

Creates a new employee record.

**Request Body:**
```json
{
    "emp_code": "EMP003",
    "first_name": "Alice",
    "last_name": "Johnson",
    "email": "alice@example.com",
    "department": "HR",
    "position": "Manager",
    "active": true
}
```

**Response:**
```json
{
    "id": 3,
    "emp_code": "EMP003",
    "first_name": "Alice",
    "last_name": "Johnson",
    "email": "alice@example.com",
    "department": "HR",
    "position": "Manager",
    "active": true,
    "created_at": "2024-01-15T10:00:00Z",
    "updated_at": "2024-01-15T10:00:00Z"
}
```

### Update Employee
**PUT** `/api/employees/{id}`

Updates an existing employee record.

**Request Body:**
```json
{
    "first_name": "Alice",
    "last_name": "Johnson-Smith",
    "email": "alice.smith@example.com",
    "department": "HR",
    "position": "Senior Manager"
}
```

**Response:**
```json
{
    "id": 3,
    "emp_code": "EMP003",
    "first_name": "Alice",
    "last_name": "Johnson-Smith",
    "email": "alice.smith@example.com",
    "department": "HR",
    "position": "Senior Manager",
    "active": true,
    "updated_at": "2024-01-15T11:00:00Z"
}
```

### Delete Employee
**DELETE** `/api/employees/{id}`

Soft deletes an employee record.

**Response:**
```json
{
    "status": "success",
    "message": "Employee deleted successfully"
}
```

---

## Attendance Management Endpoints

### List Attendance Logs
**GET** `/api/attendance-logs`

Retrieves attendance logs with filtering options.

**Query Parameters:**
- `page` (integer): Page number
- `per_page` (integer): Items per page
- `employee_id` (integer): Filter by employee
- `action` (string): Filter by action (time_in, time_out)
- `date_from` (date): Start date filter
- `date_to` (date): End date filter

**Response:**
```json
{
    "data": [
        {
            "id": 1,
            "employee_id": 1,
            "emp_code": "EMP001",
            "action": "time_in",
            "logged_at": "2024-01-15T09:00:00Z",
            "confidence": 0.95,
            "liveness_pass": true,
            "device_id": 1,
            "employee": {
                "id": 1,
                "emp_code": "EMP001",
                "first_name": "John",
                "last_name": "Doe"
            }
        }
    ],
    "current_page": 1,
    "per_page": 15,
    "total": 1,
    "last_page": 1
}
```

### Get Attendance Log
**GET** `/api/attendance-logs/{id}`

Retrieves a specific attendance log.

**Response:**
```json
{
    "id": 1,
    "employee_id": 1,
    "emp_code": "EMP001",
    "action": "time_in",
    "logged_at": "2024-01-15T09:00:00Z",
    "confidence": 0.95,
    "liveness_pass": true,
    "device_id": 1,
    "meta": {
        "browser": "Chrome",
        "user_agent": "Mozilla/5.0...",
        "ip_address": "192.168.1.100"
    },
    "employee": {
        "id": 1,
        "emp_code": "EMP001",
        "first_name": "John",
        "last_name": "Doe",
        "email": "john@example.com"
    },
    "created_at": "2024-01-15T09:00:00Z",
    "updated_at": "2024-01-15T09:00:00Z"
}
```

### Delete Attendance Log
**DELETE** `/api/attendance-logs/{id}`

Deletes an attendance log record.

**Response:**
```json
{
    "status": "success",
    "message": "Attendance log deleted successfully"
}
```

---

## System Health Endpoints

### Health Check
**GET** `/api/health`

Returns system health status.

**Response:**
```json
{
    "ok": true,
    "ts": "2024-01-15T12:00:00Z",
    "services": {
        "database": "connected",
        "fastapi": "available",
        "storage": "writable"
    }
}
```

### Database Status
**GET** `/api/debug/database`

Returns database status and record counts.

**Response:**
```json
{
    "status": "connected",
    "employees": 25,
    "face_templates": 30,
    "attendance_logs": 150,
    "recent_employees": [
        {
            "id": 1,
            "emp_code": "EMP001",
            "first_name": "John",
            "last_name": "Doe"
        }
    ],
    "recent_face_templates": [
        {
            "id": 1,
            "employee_id": 1,
            "model": "face_recognition_dlib",
            "source": "face_capture"
        }
    ]
}
```

---

## Error Responses

### Validation Error
**Status:** 422 Unprocessable Entity

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "emp_code": ["The emp code field is required."],
        "email": ["The email must be a valid email address."]
    }
}
```

### Authentication Error
**Status:** 401 Unauthorized

```json
{
    "message": "Unauthenticated."
}
```

### Authorization Error
**Status:** 403 Forbidden

```json
{
    "message": "This action is unauthorized."
}
```

### Not Found Error
**Status:** 404 Not Found

```json
{
    "message": "Employee not found."
}
```

### Server Error
**Status:** 500 Internal Server Error

```json
{
    "message": "Server Error",
    "error": "Database connection failed"
}
```

### Service Unavailable
**Status:** 502 Bad Gateway

```json
{
    "message": "FastAPI service error: 500",
    "error": "Connection timeout"
}
```

---

## Rate Limiting

All API endpoints are protected by rate limiting:

- **General endpoints:** 60 requests per minute
- **Authentication endpoints:** 5 requests per minute
- **Face recognition endpoints:** 30 requests per minute

**Rate Limit Headers:**
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1642248000
```

**Rate Limit Exceeded Response:**
```json
{
    "message": "Too Many Attempts.",
    "retry_after": 60
}
```

---

## Authentication

All API endpoints (except health check) require authentication:

1. **Session-based authentication** for web routes
2. **CSRF token** for state-changing operations
3. **API key** for FastAPI service communication

**CSRF Token:**
- Include `X-CSRF-TOKEN` header
- Token available in meta tag: `<meta name="csrf-token" content="{{ csrf_token() }}">`

**Session Authentication:**
- Laravel session middleware
- Automatic cookie handling
- Session timeout: 2 hours

---

## Data Formats

### Date/Time Format
All timestamps use ISO 8601 format:
```
2024-01-15T09:00:00Z
```

### File Upload Format
- **Content-Type:** `multipart/form-data`
- **Max file size:** 4MB
- **Supported formats:** JPEG, PNG, WebP
- **Image requirements:** Minimum 100x100 pixels

### Embedding Format
Face embeddings are returned as arrays of floating-point numbers:
```json
{
    "embedding": [0.1, 0.2, 0.3, 0.4, ...]
}
```

---

## Testing

### cURL Examples

**Register Face:**
```bash
curl -X POST "http://localhost/api/register-face" \
  -H "X-CSRF-TOKEN: {csrf_token}" \
  -F "emp_code=EMP001" \
  -F "name=John Doe" \
  -F "email=john@example.com" \
  -F "image=@face.jpg"
```

**Get Face Embeddings:**
```bash
curl -X GET "http://localhost/api/face-embeddings" \
  -H "Accept: application/json"
```

**Mark Attendance:**
```bash
curl -X POST "http://localhost/api/recognize-proxy" \
  -H "X-CSRF-TOKEN: {csrf_token}" \
  -F "action=time_in" \
  -F "image=@face.jpg" \
  -F "device_id=1"
```

### JavaScript Examples

**Register Face:**
```javascript
const formData = new FormData();
formData.append('emp_code', 'EMP001');
formData.append('name', 'John Doe');
formData.append('email', 'john@example.com');
formData.append('image', imageBlob, 'face.jpg');

const response = await fetch('/api/register-face', {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: formData
});

const result = await response.json();
```

**Get Face Embeddings:**
```javascript
const response = await fetch('/api/face-embeddings');
const embeddings = await response.json();
```

**Mark Attendance:**
```javascript
const formData = new FormData();
formData.append('action', 'time_in');
formData.append('image', imageBlob, 'face.jpg');
formData.append('device_id', 1);

const response = await fetch('/api/recognize-proxy', {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: formData
});

const result = await response.json();
```

---

## Conclusion

This API documentation provides comprehensive coverage of all endpoints in the Face Attendance System. For implementation details and troubleshooting, refer to the main system documentation and troubleshooting guide.
