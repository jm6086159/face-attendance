# Face Attendance System - Quick Start Guide

## Overview

This guide provides step-by-step instructions to set up, configure, and test the Face Attendance System quickly.

---

## Prerequisites

### System Requirements
- **PHP:** 8.2 or higher
- **Node.js:** 18 or higher
- **Composer:** Latest version
- **SQLite:** 3.x (included with PHP)
- **Webcam:** For face capture functionality

### Development Tools
- **Git:** For version control
- **Code Editor:** VS Code, PhpStorm, or similar
- **Terminal:** Command line access

---

## Installation

### 1. Clone the Repository
```bash
git clone <repository-url>
cd face_attendance_backend
```

### 2. Install PHP Dependencies
```bash
composer install
```

### 3. Install Node.js Dependencies
```bash
npm install
```

### 4. Environment Setup
```bash
cp .env.example .env
php artisan key:generate
```

### 5. Database Setup
```bash
php artisan migrate
php artisan db:seed
```

### 6. Storage Setup
```bash
php artisan storage:link
```

### 7. Build Assets
```bash
npm run build
```

---

## Configuration

### 1. Environment Variables

Edit `.env` file with your configuration:

```env
APP_NAME="Face Attendance System"
APP_ENV=local
APP_KEY=base64:your-app-key
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# FastAPI Service Configuration
FASTAPI_URL=http://localhost:8000
FASTAPI_SECRET=your-fastapi-secret-key

# Session Configuration
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Cache Configuration
CACHE_DRIVER=file
```

### 2. FastAPI Service Setup

The system requires a FastAPI service for face embedding extraction. Set up the service:

```bash
# Install FastAPI service dependencies
pip install fastapi uvicorn python-multipart opencv-python face-recognition

# Start FastAPI service
uvicorn main:app --host 0.0.0.0 --port 8000
```

### 3. Webcam Permissions

Ensure your browser has webcam access:
- **Chrome:** Allow camera access when prompted
- **Firefox:** Allow camera access when prompted
- **Safari:** Enable camera access in preferences

---

## Quick Testing

### 1. Start the Application
```bash
php artisan serve
```

The application will be available at `http://localhost:8000`

### 2. Create Admin User
```bash
php artisan tinker
```

In Tinker:
```php
$user = \App\Models\User::create([
    'name' => 'Admin User',
    'email' => 'admin@example.com',
    'password' => bcrypt('password123'),
    'email_verified_at' => now()
]);
```

### 3. Test Basic Functionality

#### A. Login Test
1. Navigate to `http://localhost:8000/login`
2. Enter credentials: `admin@example.com` / `password123`
3. Verify successful login and dashboard access

#### B. Employee Creation Test
1. Go to `/employees/create`
2. Fill in employee details:
   - Employee Code: `EMP001`
   - First Name: `John`
   - Last Name: `Doe`
   - Email: `john@example.com`
3. Save the employee
4. Verify employee appears in the list

#### C. Face Registration Test
1. Go to `/face-registration`
2. Fill in the form:
   - Employee Code: `EMP001`
   - Name: `John Doe`
   - Email: `john@example.com`
3. Allow webcam access
4. Click "Capture Face" when face is detected
5. Click "Register Face"
6. Verify success message

#### D. Face Attendance Test
1. Go to `/face-attendance`
2. Allow webcam access
3. Position face in camera view
4. Wait for face recognition
5. Click "Check In" or "Check Out"
6. Verify attendance is logged

---

## Troubleshooting

### Common Issues

#### 1. Webcam Not Working
**Problem:** Camera access denied or not detected

**Solutions:**
- Check browser permissions
- Ensure no other application is using the camera
- Try different browser
- Check camera hardware

#### 2. FastAPI Service Not Responding
**Problem:** Face registration fails with service error

**Solutions:**
- Verify FastAPI service is running on port 8000
- Check firewall settings
- Test FastAPI endpoint directly: `http://localhost:8000/docs`

#### 3. Database Connection Issues
**Problem:** Database errors or migration failures

**Solutions:**
- Ensure SQLite file exists: `database/database.sqlite`
- Check file permissions
- Run migrations: `php artisan migrate:fresh`

#### 4. Storage Permission Issues
**Problem:** File upload failures

**Solutions:**
- Check storage directory permissions
- Run: `php artisan storage:link`
- Ensure `storage/app/public` is writable

#### 5. CSRF Token Errors
**Problem:** Form submissions fail with CSRF errors

**Solutions:**
- Clear browser cache
- Check CSRF token in meta tag
- Verify session configuration

---

## Development Workflow

### 1. Making Changes
```bash
# Start development server
php artisan serve

# Watch for changes (in another terminal)
npm run dev

# Run tests
php artisan test
```

### 2. Database Changes
```bash
# Create migration
php artisan make:migration add_new_field_to_employees_table

# Run migrations
php artisan migrate

# Rollback if needed
php artisan migrate:rollback
```

### 3. Frontend Changes
```bash
# Watch for changes
npm run dev

# Build for production
npm run build
```

---

## Production Deployment

### 1. Environment Preparation
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=sqlite
DB_DATABASE=/path/to/production/database.sqlite

FASTAPI_URL=https://your-fastapi-service.com
FASTAPI_SECRET=your-production-secret
```

### 2. Build Assets
```bash
npm run build
```

### 3. Optimize Application
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 4. Set Permissions
```bash
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
```

---

## Testing Checklist

### Basic Functionality
- [ ] Application starts without errors
- [ ] Database connection works
- [ ] User can login/logout
- [ ] Employee CRUD operations work
- [ ] Face registration works
- [ ] Face attendance works
- [ ] Attendance logs are created

### Security Testing
- [ ] CSRF protection works
- [ ] Authentication required for protected routes
- [ ] File upload validation works
- [ ] SQL injection protection
- [ ] XSS protection

### Performance Testing
- [ ] Page load times acceptable
- [ ] Face detection responsive
- [ ] Database queries optimized
- [ ] File uploads work efficiently

### Browser Compatibility
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)

---

## Next Steps

### 1. Customization
- Modify UI components
- Add custom fields
- Implement additional features
- Configure email notifications

### 2. Integration
- Connect to HR systems
- Implement reporting
- Add mobile app support
- Integrate with time tracking

### 3. Scaling
- Set up load balancing
- Implement caching
- Optimize database
- Add monitoring

---

## Support

### Documentation
- **System Flows:** `docs/SYSTEM_FLOWS.md`
- **API Endpoints:** `docs/API_ENDPOINTS.md`
- **Database Schema:** `docs/DATABASE_SCHEMA.md`
- **Troubleshooting:** `docs/TROUBLESHOOTING.md`

### Getting Help
1. Check troubleshooting guide
2. Review system documentation
3. Check error logs: `storage/logs/laravel.log`
4. Test individual components
5. Verify configuration

### Common Commands
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Reset database
php artisan migrate:fresh --seed

# Check application status
php artisan about

# Generate new key
php artisan key:generate
```

---

## Conclusion

This quick start guide provides everything needed to get the Face Attendance System running quickly. For detailed information, refer to the comprehensive documentation in the `docs/` directory.

The system is designed to be user-friendly and robust, with proper error handling and security measures. Follow the testing checklist to ensure everything works correctly before deploying to production.
