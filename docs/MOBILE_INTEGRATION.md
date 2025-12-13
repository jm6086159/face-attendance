# Mobile Integration (Flutter)

## Summary

The Laravel backend exposes minimal endpoints for the Flutter app so employees can log in and view their attendance.

## Endpoints

- POST `/api/mobile/login`
  - Body (JSON): `{ "email": "user@example.com", "password": "secret" }` or `{ "emp_code": "EMP001", "password": "secret" }`
  - Returns: `{ success, token, user }`
  - Notes: Password is required and verified against the Employees table (hashed).
- GET `/api/attendance?email={email}` or `?emp_code={code}`
  - Returns an array of records: `{ id, checkInTime, checkOutTime, status, notes }`

## Local Setup

1) Install and configure backend

```
cd face_attendance_backend
composer install
cp .env.example .env
php artisan key:generate
# For SQLite
mkdir -p database && type NUL > database\\database.sqlite
php artisan migrate --seed
php artisan serve --host 0.0.0.0 --port 8000
```

2) Configure Flutter base URL

Edit `mobileattends/lib/config/api_config.dart`:

```
static const String baseUrl = 'http://127.0.0.1:8000/api';
```

Notes:
- Android emulator: use `http://10.0.2.2:8000/api`
- Physical device: use your PC’s LAN IP, e.g. `http://192.168.1.10:8000/api`

3) Run the Flutter app

```
cd mobileattends
flutter pub get
flutter run
```

## Test Flow

- Login with an employee's email or code and their password.
- The home screen fetches attendance via `/api/attendance?email=test@example.com`.
- The included `EmployeeDemoSeeder` creates recent weekday check-in/out records for quick testing.

## Production Notes

- Implement proper auth (Laravel Sanctum/Passport) and validate the `Authorization: Bearer <token>` header.
- Enable and configure CORS if building Flutter Web.
- Always use HTTPS for production endpoints.
