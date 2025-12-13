# Face Attendance System - Troubleshooting Guide

## Overview

This guide provides solutions to common issues encountered when using the Face Attendance System. It covers installation, configuration, runtime, and performance problems.

---

## Installation Issues

### 1. Composer Installation Failures

**Problem:** `composer install` fails with dependency errors

**Symptoms:**
- PHP version compatibility errors
- Memory limit exceeded
- Network connection issues

**Solutions:**

#### PHP Version Issues
```bash
# Check PHP version
php --version

# Update PHP to 8.2+ (Ubuntu/Debian)
sudo apt update
sudo apt install php8.2 php8.2-cli php8.2-fpm php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip

# Update PHP to 8.2+ (CentOS/RHEL)
sudo yum install php82 php82-cli php82-fpm php82-mysql php82-xml php82-mbstring php82-curl php82-zip
```

#### Memory Limit Issues
```bash
# Increase memory limit
php -d memory_limit=2G /usr/local/bin/composer install

# Or update php.ini
echo "memory_limit = 2G" >> /etc/php/8.2/cli/php.ini
```

#### Network Issues
```bash
# Use different Composer repository
composer config -g repo.packagist composer https://packagist.org

# Clear Composer cache
composer clear-cache
```

### 2. Node.js Installation Issues

**Problem:** `npm install` fails or Node.js not found

**Symptoms:**
- "node: command not found"
- npm package installation errors
- Permission denied errors

**Solutions:**

#### Install Node.js
```bash
# Using Node Version Manager (recommended)
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash
source ~/.bashrc
nvm install 18
nvm use 18

# Or install directly (Ubuntu/Debian)
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs

# Or install directly (CentOS/RHEL)
curl -fsSL https://rpm.nodesource.com/setup_18.x | sudo bash -
sudo yum install -y nodejs
```

#### Fix npm Permissions
```bash
# Fix npm permissions
sudo chown -R $(whoami) ~/.npm
sudo chown -R $(whoami) /usr/local/lib/node_modules
```

### 3. Database Setup Issues

**Problem:** Database migration failures or SQLite errors

**Symptoms:**
- "Database connection failed"
- Migration errors
- Permission denied on database file

**Solutions:**

#### SQLite Installation
```bash
# Install SQLite (Ubuntu/Debian)
sudo apt install sqlite3

# Install SQLite (CentOS/RHEL)
sudo yum install sqlite
```

#### Database Permissions
```bash
# Fix database file permissions
chmod 664 database/database.sqlite
chown www-data:www-data database/database.sqlite

# Or create new database
touch database/database.sqlite
chmod 664 database/database.sqlite
php artisan migrate
```

#### Migration Issues
```bash
# Reset migrations
php artisan migrate:fresh

# Or rollback and re-run
php artisan migrate:rollback
php artisan migrate
```

---

## Configuration Issues

### 1. Environment Configuration

**Problem:** Application fails to start or configuration errors

**Symptoms:**
- "APP_KEY not set" error
- Database connection errors
- Service configuration failures

**Solutions:**

#### Generate Application Key
```bash
# Generate new application key
php artisan key:generate

# Or manually set in .env
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
```

#### Database Configuration
```env
# Check .env file
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# Ensure database file exists
touch database/database.sqlite
chmod 664 database/database.sqlite
```

#### FastAPI Configuration
```env
# FastAPI service URL
FASTAPI_URL=http://localhost:8000
FASTAPI_SECRET=your-secret-key

# Test FastAPI connection
curl http://localhost:8000/health
```

### 2. Storage Configuration

**Problem:** File upload failures or storage errors

**Symptoms:**
- "Storage link failed"
- "Permission denied" on storage
- File upload errors

**Solutions:**

#### Create Storage Link
```bash
# Create storage link
php artisan storage:link

# Check if link exists
ls -la public/storage

# Fix permissions
chmod -R 755 storage/
chmod -R 755 public/storage/
```

#### Storage Permissions
```bash
# Fix storage permissions
sudo chown -R www-data:www-data storage/
sudo chmod -R 755 storage/

# Fix public storage permissions
sudo chown -R www-data:www-data public/storage/
sudo chmod -R 755 public/storage/
```

---

## Runtime Issues

### 1. Webcam Access Problems

**Problem:** Camera not working or access denied

**Symptoms:**
- "Camera not found" error
- "Permission denied" for camera
- Black video feed

**Solutions:**

#### Browser Permissions
- **Chrome:** Click camera icon in address bar → Allow
- **Firefox:** Click camera icon in address bar → Allow
- **Safari:** Safari → Preferences → Websites → Camera → Allow

#### Hardware Issues
```bash
# Check camera hardware (Linux)
lsusb | grep -i camera
ls /dev/video*

# Test camera with ffmpeg
ffmpeg -f v4l2 -i /dev/video0 -t 5 test.mp4

# Check camera permissions
groups $USER
sudo usermod -a -G video $USER
```

#### Application Issues
```javascript
// Check camera access in browser console
navigator.mediaDevices.getUserMedia({ video: true })
  .then(stream => console.log('Camera access granted'))
  .catch(err => console.error('Camera access denied:', err));
```

### 2. Face Recognition Issues

**Problem:** Face detection or recognition not working

**Symptoms:**
- "No face detected" errors
- Low confidence scores
- Recognition failures

**Solutions:**

#### Face Detection Setup
```javascript
// Check face-api.js models loading
console.log('Models loaded:', faceapi.nets);

// Verify model files exist
// Check: public/FaceApi/models/ directory
```

#### Lighting and Positioning
- Ensure good lighting on face
- Face should be centered in camera view
- Avoid backlighting or shadows
- Maintain appropriate distance (1-2 feet)

#### Model Configuration
```javascript
// Adjust detection parameters
const detectionOptions = {
  minConfidence: 0.5,
  inputSize: 416
};

// Adjust recognition threshold
const MATCH_THRESHOLD = 0.45; // Lower = more lenient
```

### 3. FastAPI Service Issues

**Problem:** FastAPI service not responding or errors

**Symptoms:**
- "FastAPI service error" messages
- Connection timeout errors
- Embedding extraction failures

**Solutions:**

#### Service Status
```bash
# Check if FastAPI is running
ps aux | grep uvicorn
netstat -tlnp | grep 8000

# Start FastAPI service
uvicorn main:app --host 0.0.0.0 --port 8000 --reload
```

#### Service Configuration
```python
# Check FastAPI configuration
# Ensure main.py exists and is configured correctly
# Verify dependencies are installed
pip install fastapi uvicorn python-multipart opencv-python face-recognition
```

#### Network Issues
```bash
# Test FastAPI endpoint
curl http://localhost:8000/health

# Check firewall settings
sudo ufw status
sudo ufw allow 8000

# Test from Laravel
php artisan tinker
Http::get('http://localhost:8000/health');
```

---

## Performance Issues

### 1. Slow Page Loading

**Problem:** Application loads slowly or times out

**Symptoms:**
- Long page load times
- Timeout errors
- High server resource usage

**Solutions:**

#### Optimize Assets
```bash
# Build optimized assets
npm run build

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

#### Database Optimization
```bash
# Optimize database
php artisan optimize

# Check database size
du -h database/database.sqlite

# Vacuum database
sqlite3 database/database.sqlite "VACUUM;"
```

#### Server Configuration
```bash
# Increase PHP memory limit
echo "memory_limit = 512M" >> /etc/php/8.2/fpm/php.ini

# Increase max execution time
echo "max_execution_time = 300" >> /etc/php/8.2/fpm/php.ini

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

### 2. High Memory Usage

**Problem:** Application consumes too much memory

**Symptoms:**
- Memory limit exceeded errors
- Slow performance
- Server crashes

**Solutions:**

#### PHP Configuration
```bash
# Increase memory limit
echo "memory_limit = 1G" >> /etc/php/8.2/fpm/php.ini

# Optimize PHP settings
echo "opcache.enable=1" >> /etc/php/8.2/fpm/php.ini
echo "opcache.memory_consumption=256" >> /etc/php/8.2/fpm/php.ini
```

#### Application Optimization
```php
// Optimize face template loading
// Load only necessary fields
FaceTemplate::select('id', 'employee_id', 'embedding')
    ->with('employee:id,emp_code,first_name,last_name')
    ->get();

// Use chunking for large datasets
FaceTemplate::chunk(100, function ($templates) {
    // Process templates in batches
});
```

### 3. Database Performance

**Problem:** Slow database queries or high load

**Symptoms:**
- Slow page loads
- Database connection timeouts
- High CPU usage

**Solutions:**

#### Query Optimization
```sql
-- Add missing indexes
CREATE INDEX idx_attendance_employee_date ON attendance_logs(employee_id, logged_at);
CREATE INDEX idx_employees_active_code ON employees(active, emp_code);

-- Optimize queries
EXPLAIN QUERY PLAN SELECT * FROM attendance_logs WHERE employee_id = 1;
```

#### Database Maintenance
```bash
# Analyze database
sqlite3 database/database.sqlite "ANALYZE;"

# Reindex database
sqlite3 database/database.sqlite "REINDEX;"

# Vacuum database
sqlite3 database/database.sqlite "VACUUM;"
```

---

## Security Issues

### 1. Authentication Problems

**Problem:** Login failures or session issues

**Symptoms:**
- "Invalid credentials" errors
- Session timeout issues
- CSRF token errors

**Solutions:**

#### User Account Issues
```bash
# Reset user password
php artisan tinker
$user = User::where('email', 'admin@example.com')->first();
$user->password = bcrypt('newpassword');
$user->save();
```

#### Session Configuration
```env
# Check session configuration
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
```

#### CSRF Issues
```html
<!-- Ensure CSRF token is included -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<!-- Include in forms -->
<input type="hidden" name="_token" value="{{ csrf_token() }}">
```

### 2. File Upload Security

**Problem:** File upload failures or security issues

**Symptoms:**
- "File too large" errors
- "Invalid file type" errors
- Upload security warnings

**Solutions:**

#### File Size Limits
```php
// Increase upload limits in php.ini
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
```

#### File Type Validation
```php
// Validate file types
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($file->getMimeType(), $allowedTypes)) {
    return response()->json(['error' => 'Invalid file type'], 422);
}
```

---

## Browser Compatibility

### 1. JavaScript Errors

**Problem:** JavaScript errors or functionality not working

**Symptoms:**
- Console errors
- Face detection not working
- Form submissions failing

**Solutions:**

#### Browser Compatibility
- **Chrome:** Latest version recommended
- **Firefox:** Latest version supported
- **Safari:** Version 14+ required
- **Edge:** Latest version supported

#### JavaScript Debugging
```javascript
// Check for errors in browser console
console.error('Error details:', error);

// Verify face-api.js loading
console.log('face-api loaded:', typeof faceapi);

// Test camera access
navigator.mediaDevices.getUserMedia({ video: true })
  .then(stream => console.log('Camera OK'))
  .catch(err => console.error('Camera Error:', err));
```

### 2. CSS/UI Issues

**Problem:** Styling problems or layout issues

**Symptoms:**
- Broken layouts
- Missing styles
- Responsive design issues

**Solutions:**

#### Asset Building
```bash
# Rebuild CSS assets
npm run build

# Clear browser cache
# Hard refresh: Ctrl+F5 (Windows) or Cmd+Shift+R (Mac)
```

#### Tailwind CSS Issues
```bash
# Check Tailwind configuration
cat tailwind.config.js

# Rebuild Tailwind
npm run dev
```

---

## Log Analysis

### 1. Laravel Logs

**Problem:** Application errors or debugging needed

**Solutions:**

#### Check Laravel Logs
```bash
# View recent logs
tail -f storage/logs/laravel.log

# Search for specific errors
grep "ERROR" storage/logs/laravel.log
grep "Exception" storage/logs/laravel.log
```

#### Enable Debug Mode
```env
# Enable debug mode for development
APP_DEBUG=true
LOG_LEVEL=debug
```

### 2. Browser Console

**Problem:** Frontend errors or debugging needed

**Solutions:**

#### Browser Developer Tools
- **Chrome:** F12 → Console tab
- **Firefox:** F12 → Console tab
- **Safari:** Develop → Show Web Inspector

#### Common Console Commands
```javascript
// Check for JavaScript errors
console.error('Error message');

// Test API endpoints
fetch('/api/health').then(r => r.json()).then(console.log);

// Check face-api.js status
console.log('Models:', faceapi.nets);
```

---

## Emergency Recovery

### 1. System Recovery

**Problem:** Complete system failure or data loss

**Solutions:**

#### Database Recovery
```bash
# Restore from backup
cp backup_20240115_120000.db database/database.sqlite
chmod 664 database/database.sqlite

# Verify data integrity
sqlite3 database/database.sqlite "PRAGMA integrity_check;"
```

#### Application Recovery
```bash
# Reset application
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Rebuild assets
npm run build

# Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
```

### 2. Data Recovery

**Problem:** Lost data or corrupted records

**Solutions:**

#### Employee Data Recovery
```sql
-- Recover soft-deleted employees
UPDATE employees SET deleted_at = NULL WHERE deleted_at IS NOT NULL;

-- Verify employee data
SELECT COUNT(*) FROM employees WHERE active = 1;
```

#### Attendance Data Recovery
```sql
-- Check attendance log integrity
SELECT COUNT(*) FROM attendance_logs;
SELECT COUNT(*) FROM attendance_logs WHERE employee_id IS NULL;

-- Fix orphaned records
UPDATE attendance_logs SET employee_id = (
    SELECT id FROM employees WHERE emp_code = attendance_logs.emp_code LIMIT 1
) WHERE employee_id IS NULL;
```

---

## Prevention Strategies

### 1. Regular Maintenance

**Daily Tasks:**
- Check application logs
- Monitor system performance
- Verify backup completion

**Weekly Tasks:**
- Database optimization
- Cache cleanup
- Security updates

**Monthly Tasks:**
- Full system backup
- Performance analysis
- Security audit

### 2. Monitoring Setup

**System Monitoring:**
```bash
# Monitor disk space
df -h

# Monitor memory usage
free -h

# Monitor CPU usage
top

# Monitor database size
du -h database/database.sqlite
```

**Application Monitoring:**
```bash
# Monitor Laravel logs
tail -f storage/logs/laravel.log

# Monitor web server logs
tail -f /var/log/nginx/error.log

# Monitor PHP-FPM logs
tail -f /var/log/php8.2-fpm.log
```

### 3. Backup Strategy

**Automated Backups:**
```bash
#!/bin/bash
# backup.sh
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups"
DB_FILE="database/database.sqlite"

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup database
sqlite3 $DB_FILE ".backup $BACKUP_DIR/db_backup_$DATE.db"

# Backup storage
tar -czf $BACKUP_DIR/storage_backup_$DATE.tar.gz storage/

# Cleanup old backups (keep 30 days)
find $BACKUP_DIR -name "*.db" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
```

**Cron Job Setup:**
```bash
# Add to crontab
0 2 * * * /path/to/backup.sh
```

---

## Getting Help

### 1. Documentation Resources

- **System Flows:** `docs/SYSTEM_FLOWS.md`
- **API Endpoints:** `docs/API_ENDPOINTS.md`
- **Database Schema:** `docs/DATABASE_SCHEMA.md`
- **Quick Start:** `docs/QUICK_START.md`

### 2. Debugging Tools

**Laravel Debugging:**
```bash
# Enable debug mode
php artisan config:cache
php artisan route:list
php artisan queue:work --verbose
```

**Browser Debugging:**
```javascript
// Enable verbose logging
localStorage.setItem('debug', 'face-api:*');

// Test API connectivity
fetch('/api/health').then(r => r.json()).then(console.log);
```

### 3. Community Support

- **Laravel Documentation:** https://laravel.com/docs
- **Face-api.js Documentation:** https://github.com/justadudewhohacks/face-api.js
- **FastAPI Documentation:** https://fastapi.tiangolo.com/

---

## Conclusion

This troubleshooting guide covers the most common issues encountered with the Face Attendance System. For issues not covered here, refer to the comprehensive documentation or seek additional support.

Key points to remember:
- **Check logs first:** Always start with application and system logs
- **Verify configuration:** Ensure all environment variables are set correctly
- **Test components:** Isolate issues by testing individual components
- **Monitor performance:** Keep an eye on system resources and performance
- **Regular maintenance:** Implement preventive measures to avoid issues

For additional support or reporting bugs, please refer to the project documentation or contact the development team.
