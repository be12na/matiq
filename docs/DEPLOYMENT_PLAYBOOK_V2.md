# META ADS TRACKER - DEPLOYMENT PLAYBOOK
## cPanel Shared Hosting

**Versi:** 1.0  
**Terakhir Update:** 2026-04-12  
**Status:** Production Ready

---

## DAFTAR ISI

1. [Prerequisites](#1-prerequisites)
2. [Environment Setup](#2-environment-setup)
3. [Pre-Deployment Checklist](#3-pre-deployment-checklist)
4. [Deployment Procedure](#4-deployment-procedure)
5. [Rollback Procedure](#5-rollback-procedure)
6. [Zero-Downtime Strategy](#6-zero-downtime-strategy)
7. [Post-Deployment Verification](#7-post-deployment-verification)
8. [Monitoring & Alerts](#8-monitoring--alerts)
9. [Load Testing](#9-load-testing)
10. [Troubleshooting](#10-troubleshooting)

---

## 1. PREREQUISITES

### 1.1 Access Requirements
- [ ] cPanel username & password
- [ ] FTP/SSH access ke server
- [ ] MySQL database credentials (root atau user dengan privileges)
- [ ] Domain DNS configured
- [ ] SSL certificate (recommended)

### 1.2 Local Requirements
- [ ] Git installed
- [ ] MySQL client installed
- [ ] rsync atau FTP client
- [ ] Text editor (VS Code / Nano / Vim)

---

## 2. ENVIRONMENT SETUP

### 2.1 Create Database di cPanel

1. Login ke cPanel → **MySQL Databases**
2. Create database: `matiq_ads`
3. Create user: `matiq_user` (use strong password)
4. Grant all privileges to user on database

### 2.2 Import Database Schema

```bash
# Via Command Line (SSH)
mysql -u matiq_user -p matiq_ads < database/mysql_schema.sql

# OR via phpMyAdmin
# 1. Select database
# 2. Import database/mysql_schema.sql
```

### 2.3 Environment Variables Setup

Create `public_html/.env` file:

```bash
# =============================================
# META ADS TRACKER - ENVIRONMENT VARIABLES
# =============================================

# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=matiq_ads
DB_USER=matiq_user
DB_PASS=<strong-random-password-min-16-chars>
DB_CHARSET=utf8mb4

# Application Settings
APP_URL=https://yourdomain.com
AUTH_TOKEN_TTL_HOURS=24

# Security (CHANGE THESE!)
PASSWORD_RESET_SECRET=<generate-with-openssl-rand-hex-32>

# Mailketing (Email Service)
MAILKETING_API_URL=https://api.mailketing.co.id/api/v1/send
MAILKETING_API_KEY=<your-api-key>
MAILKETING_SENDER=noreply@yourdomain.com
MAILKETING_FROM_NAME=MATIQ

# Starsender (WhatsApp Service)
STARSENDER_API_URL=https://api.starsender.online/api/send
STARSENDER_API_KEY=<your-api-key>
STARSENDER_TIMEOUT_MS=15000

# Notification Settings
NOTIFICATION_RETRY_MAX=3
NOTIFICATION_RETRY_DELAY_MS=1200

# Runtime Mode
PUBLIC_RUNTIME_MODE=production
```

### 2.4 Generate Secure Secrets

```bash
# Generate random password reset secret
openssl rand -hex 32

# Generate database password
openssl rand -hex 24
```

---

## 3. PRE-DEPLOYMENT CHECKLIST

### 3.1 Pre-Flight Checks

| # | Item | Status |
|---|------|--------|
| 1 | Database schema imported | [ ] |
| 2 | .env file configured | [ ] |
| 3 | SSL certificate active | [ ] |
| 4 | Backup terbaru dibuat | [ ] |
| 5 | Code sudah ditesting lokal | [ ] |
| 6 | Error logs di-clear | [ ] |

### 3.2 Create Backup

```bash
# Create timestamp
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Backup database
mysqldump -u matiq_user -p'password' matiq_ads > "backup_db_${TIMESTAMP}.sql"

# Backup files (jika sudah ada deployment sebelumnya)
tar -czf "backup_files_${TIMESTAMP}.tar.gz" -C /home/username public_html/
```

---

## 4. DEPLOYMENT PROCEDURE

### 4.1 Option A: Git Deployment (Recommended)

#### Step 1: Setup Git di cPanel

1. Login ke cPanel → **Git Version Control**
2. Create new repository
3. Repository path: `/home/username/matiq-ads.git`

#### Step 2: Configure Post-Receive Hook

Create `/home/username/matiq-ads.git/hooks/post-receive`:

```bash
#!/bin/bash
GIT_DIR=/home/username/matiq-ads.git
WORK_TREE=/home/username/public_html

while read oldrev newrev refname; do
    branch=$(echo $refname | sed 's|refs/heads/||')
    if [ "main" = "$branch" ]; then
        git --work-tree=$WORK_TREE --git-dir=$GIT_DIR checkout -f main
        echo "Deployed to production successfully"
    fi
done
```

#### Step 3: Add Remote & Push

```bash
# Local machine
git remote add production ssh://username@server/home/username/matiq-ads.git
git push production main
```

### 4.2 Option B: FTP/SFTP Deployment

```bash
# Using rsync (recommended)
rsync -avz --delete \
  --exclude='.git' \
  --exclude='.env' \
  --exclude='*.log' \
  --exclude='node_modules' \
  ./ username@server:/home/username/public_html/

# Or using filezilla/sftp
```

### 4.3 Option C: Manual Upload

1. Zip files: `zip -r deploy.zip . -x ".git/*" -x ".env"`
2. Upload via cPanel File Manager
3. Extract di `public_html/`
4. Move `.env` file ke `public_html/`

---

## 5. ROLLBACK PROCEDURE

### 5.1 Emergency Rollback (Less than 30 seconds)

**Kapan:** Website down atau error kritis

```bash
# 1. Restore database
mysql -u matiq_user -p'password' matiq_ads < backup_db_YYYYMMDD_HHMMSS.sql

# 2. Restore files
tar -xzf backup_files_YYYYMMDD_HHMMSS.tar.gz -C /home/username/

# 3. Clear opcache (jika ada)
rm -rf /home/username/public_html/opcache/*

# 4. Test
curl -I https://yourdomain.com/health
```

### 5.2 Planned Rollback (Less than 5 minutes)

**Kapan:** Ada issue setelah update tapi tidak emergency

```bash
# 1. Check git log
git log --oneline -10

# 2. Revert commit
git revert HEAD

# OR checkout specific version
git checkout <commit-hash>

# 3. Force push (HATI-HATI!)
git push production main --force

# 4. Verify
curl -X POST https://yourdomain.com/health
```

### 5.3 Database Rollback Only

**Kapan:** Hanya ada masalah di database

```bash
# 1. Restore specific table
mysql -u matiq_user -p'password' matiq_ads < backup_db_YYYYMMDD_HHMMSS.sql

# 2. Verify table data
mysql -u matiq_user -p'password' -e "SELECT COUNT(*) FROM users" matiq_ads
```

---

## 6. ZERO-DOWNTIME STRATEGY

### 6.1 Blue-Green Deployment

```bash
# 1. Create new directory
mkdir /home/username/public_html_new

# 2. Deploy to new directory
rsync -avz ./ /home/username/public_html_new/

# 3. Test new version
# Edit /home/username/public_html_new/.htaccess untuk sementara point ke new

# 4. Swap directories
mv /home/username/public_html /home/username/public_html_old
mv /home/username/public_html_new /home/username/public_html

# 5. If issue, rollback
mv /home/username/public_html_old /home/username/public_html
```

### 6.2 Database Migration Strategy

```bash
# 1. Add new column (nullable first)
ALTER TABLE users ADD COLUMN new_field VARCHAR(255) NULL;

# 2. Deploy code
# ...

# 3. Migrate data (bisa dalam background)
UPDATE users SET new_field = old_field WHERE new_field IS NULL;

# 4. Make NOT NULL after verification
ALTER TABLE users MODIFY COLUMN new_field VARCHAR(255) NOT NULL;
```

### 6.3 Feature Flags

Di `.env`:
```bash
FEATURE_NEW_IMPORT=true
FEATURE_AI=false
```

Di kode:
```php
if (isTruthy(envGet($env, 'FEATURE_NEW_IMPORT', 'false'))) {
    // New import logic
} else {
    // Old logic
}
```

---

## 7. POST-DEPLOYMENT VERIFICATION

### 7.1 Health Checks

```bash
# 1. API Health
curl -s https://yourdomain.com/health | jq .

# 2. Login endpoint
curl -X POST https://yourdomain.com/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"test123"}'

# 3. Database connection
mysql -u matiq_user -p'password' -e "SELECT 1" matiq_ads
```

### 7.2 Automated Tests

```bash
# Run PHP syntax check
find /home/username/public_html -name "*.php" -exec php -l {} \;

# Run import test
php -r "
require 'api/index.php';
echo 'API loads OK';
"
```

---

## 8. MONITORING & ALERTS

### 8.1 Error Log Monitoring

```bash
# Watch PHP errors
tail -f /home/username/logs/error.log

# Watch API errors
grep -i error /home/username/logs/error.log | tail -20
```

### 8.2 cPanel Resource Monitoring

- **CPU Usage:** cPanel → Metrics → CPU Usage
- **Memory:** cPanel → Metrics → Memory Usage
- **MySQL:** cPanel → Databases → Current Queries

### 8.3 Setup External Monitoring (Optional)

```bash
# UptimeRobot (free tier)
# Add monitor: https://yourdomain.com/health

# Health check script
#!/bin/bash
response=$(curl -s -o /dev/null -w "%{http_code}" https://yourdomain.com/health)
if [ "$response" != "200" ]; then
    echo "ALERT: Health check failed with code $response"
    # Send notification
fi
```

---

## 9. LOAD TESTING

### 9.1 Basic Load Test (100 Concurrent Users)

```bash
# Using Apache Bench (available on most servers)
ab -n 1000 -c 100 https://yourdomain.com/health

# Using k6 (recommended)
# File: load-test.js
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  vus: 100,
  duration: '1m',
};

export default function() {
  const res = http.get('https://yourdomain.com/health');
  check(res, { 'status was 200': (r) => r.status === 200 });
  sleep(1);
}
```

### 9.2 Performance Criteria

| Metric | Target | Threshold |
|--------|--------|-----------|
| Response Time (avg) | < 1s | < 3s |
| Response Time (p95) | < 2s | < 5s |
| Error Rate | < 1% | < 5% |
| CPU Usage | < 70% | < 90% |
| Memory Usage | < 80% | < 95% |

### 9.3 Load Test Specific Endpoints

```bash
# Auth endpoint
ab -n 500 -c 50 -p login.json -T application/json https://yourdomain.com/auth/login

# Snapshot endpoint (with auth)
TOKEN="your-token-here"
ab -n 200 -c 20 -H "Authorization: Bearer $TOKEN" https://yourdomain.com/app/snapshot
```

---

## 10. TROUBLESING

### 10.1 Common Issues

#### Issue: 500 Internal Server Error

```bash
# Check error log
tail -50 /home/username/logs/error.log

# Common causes:
# - PHP syntax error
# - Missing .env file
# - Database connection failed
# - Permission issues
```

#### Issue: Database Connection Failed

```bash
# Verify credentials
mysql -u matiq_user -p'password' -e "SHOW DATABASES;"

# Check MySQL service
systemctl status mysql

# Check max connections
mysql -u matiq_user -p'password' -e "SHOW VARIABLES LIKE 'max_connections';"
```

#### Issue: White Screen / Blank Page

```bash
# Enable error display (temporary)
# Di .htaccess:
php_flag display_errors On

# Common causes:
# - Memory limit exceeded
# - Timeout
# - Syntax error
```

#### Issue: Login Not Working

```bash
# Check sessions table
mysql -u matiq_user -p'password' -e "SELECT * FROM sessions LIMIT 5;" matiq_ads

# Check users table
mysql -u matiq_user -p'password' -e "SELECT id, email, is_active FROM users;" matiq_ads
```

### 10.2 Emergency Contacts

| Role | Contact |
|------|---------|
| cPanel Support | Via cPanel Chat |
| Database Admin | - |
| Lead Developer | - |

---

## 11. APPENDIX

### A. Useful Commands

```bash
# Restart PHP-FPM (if available)
systemctl restart php-fpm74

# Clear opcache
rm -rf /home/username/public_html/opcache/*

# Rebuild database indexes
mysqlcheck -u matiq_user -p --optimize --all-databases

# Check disk space
df -h

# Check inode usage
df -i
```

### B. File Structure Reference

```
public_html/
├── .env                    # Environment variables (NEVER commit!)
├── .htaccess               # Apache config
├── index.html              # Frontend entry
├── app-main.js             # Frontend app
├── api/
│   └── index.php           # API backend
├── runtime-config.js       # Public config
├── runtime-config.php      # PHP config
└── cpanel-public/          # Alternative entry
    └── api/
        └── index.php       # Alternative API
```

---

** Dokumen ini adalah confidential - Internal Use Only **

*End of Deployment Playbook*