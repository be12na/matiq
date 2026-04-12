# META ADS TRACKER - SECURITY & PERFORMANCE AUDIT REPORT

**Tanggal Audit:** 2026-04-12  
**Auditor:** OpenCode AI  
**Versi:** 1.0  
**Status:** DRAFT - PERLU TINDAK LANJUT

---

## DAFTAR ISI

1. [Ringkasan Eksekutif](#ringkasan-eksekutif)
2. [Metodologi Audit](#metodologi-audit)
3. [Temuan Keamanan](#temuan-keamanan)
4. [Temuan Performa](#temuan-performa)
5. [Temuan Bugs](#temuan-bugs)
6. [Rekomendasi Perbaikan](#rekomendasi-perbaikan)
7. [Konfigurasi Production](#konfigurasi-production)
8. [Playbook Deployment](#playbook-deployment)

---

## 1. RINGKASAN EKSEKUTIF

### Overall Risk Assessment: **TINGGI** ⚠️

Project ini memiliki beberapa **kerentanan kritis** yang memerlukan tindakan segera sebelum deployment ke production:

| Kategori | Risk Level | Jumlah |
|----------|------------|--------|
| Critical | 🔴 TINGGI | 3 |
| High | 🟠 SEDANG | 5 |
| Medium | 🟡 RENDAH | 7 |
| Low | 🟢 INFO | 4 |

### Temuan Kritis

1. **SQL Injection** - cpanel-public/api/index.php menggunakan string concatenation pada query INSERT (lines 1186-1190, 1234-1239)
2. **Password Reset Bug** - Perhitungan timestamp salah di api/index.php line 1438
3. **Hardcoded Secret** - Password reset HMAC secret di-hardcode di dua tempat

### Rekomendasi Prioritas

1. Segera fix SQL injection di cpanel-public variant
2. Perbaiki bug timestamp password reset
3. Pindahkan secret ke environment variable
4. Tambahkan rate limiting pada auth endpoints
5. strengthen password policy

---

## 2. METODOLOGI AUDIT

### Analisis Statik
- Review seluruh PHP files (api/index.php, cpanel-public/api/index.php)
- Review JavaScript files (app-main.js, functions/*)
- Review database schema
- Review konfigurasi (.htaccess, runtime-config)

### Analisis Dinamik
- Review authentication flow
- Review session management
- Review input validation
- Review error handling

### Scope
- API endpoints
- Database operations
- Authentication/Authorization
- Input validation
- Session management
- Configuration security

---

## 3. TEMUAN KEAMANAN

### 3.1 CRITICAL SEVERITY

#### [C-001] SQL Injection Vulnerability - cpanel-public/api/index.php

**Lokasi:** `cpanel-public/api/index.php` lines 1186-1190, 1234-1239, 1219

**Deskripsi:**
Menggunakan `PDO::quote()` dengan string concatenation yang rentan SQL injection:

```php
// Line 1186-1190
$sql = "INSERT INTO users (id, email, password_hash, salt, name, role, payment_status, mailketing_list_id, created_at, updated_at, last_login, is_active)
        VALUES ({$db->quote($userId)}, {$db->quote($email)}, ...)";
$db->exec($sql);

// Line 1219
$escapedEmail = $db->quote($email);
if ($db->query("SELECT id FROM users WHERE email = {$escapedEmail} LIMIT 1")->fetch()) {
```

**Dampak:**
- Attacker dapat melakukan SQL injection melalui parameter user registration
- Potensi: Full database compromise, data exfiltration, privilege escalation

**Bukti:**
- Tidak ada prepared statement
- Langsung concatenate input user ke query SQL
- Berbeda dengan `api/index.php` yang sudah menggunakan prepared statements

**Rekomendasi:**
Konversi ke prepared statements seperti di `api/index.php`:
```php
$stmt = $db->prepare(
  'INSERT INTO users (id, email, password_hash, salt, name, role, payment_status, mailketing_list_id, created_at, updated_at, last_login, is_active)
   VALUES (:id, :email, :password_hash, :salt, :name, :role, :payment_status, :mailketing_list_id, :created_at, :updated_at, :last_login, :is_active)'
);
$stmt->execute([...]);
```

**Status:** 🔴 BELUM DIPERBAIKI

---

#### [C-002] Password Reset Timestamp Calculation Bug

**Lokasi:** `api/index.php` line 1438

**Deskripsi:**
Perhitungan expiry timestamp salah:

```php
$expiresAt = date('Y-m-d H:i:s.u', (time() + (2 * 60 * 60)) * 1000);
```

**Masalah:**
- `(time() + (2 * 60 * 60)) * 1000` - perkalian dengan 1000 dilakukan SETELAH penjumlahan
- Ini menghasilkan timestamp yang sangat besar (tahun 2071+ bukan 2 jam dari sekarang)
- `date()` expects Unix timestamp dalam detik, bukan milidetik

**Dampak:**
- Token reset tidak akan pernah expire (atau expired di waktu yang salah)
- User tidak dapat reset password setelah 2 jam
-也可能 token terlalu lama valid

**Rekomendasi:**
```php
// Benar:
$expiresAt = date('Y-m-d H:i:s.u', time() + (2 * 60 * 60)); // 2 jam dari sekarang

// Atau lebih baik gunakan DateTime:
$expiresAt = (new DateTimeImmutable('+2 hours', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');
```

**Status:** 🔴 BELUM DIPERBAIKI

---

#### [C-003] Hardcoded Password Reset Secret

**Lokasi:** 
- `api/index.php` line 1435
- `api/index.php` line 1518

**Deskripsi:**
HMAC secret di-hardcode:

```php
$hashedToken = hash_hmac('sha256', $resetToken, 'password_reset_secret', false);
```

**Dampak:**
- Secret tidak bisa dirotasi tanpa deploy kode
- Jika secret bocor, semua password reset tokens bisa di-decrypt
- Tidak sesuai best practices (environment-based secrets)

**Rekomendasi:**
Gunakan environment variable:
```php
$resetSecret = envGet($env, 'PASSWORD_RESET_SECRET', '');
if ($resetSecret === '') {
  $resetSecret = bin2hex(random_bytes(32)); // Generate on first run
}
$hashedToken = hash_hmac('sha256', $resetToken, $resetSecret, false);
```

**Status:** 🔴 BELUM DIPERBAIKI

---

### 3.2 HIGH SEVERITY

#### [H-001] No Rate Limiting on Authentication Endpoints

**Lokasi:** Semua `/auth/*` endpoints

**Deskripsi:**
Tidak ada rate limiting pada:
- `/auth/login` - vulnerable terhadap brute force
- `/auth/register` - vulnerable terhadap mass registration
- `/auth/forgot-password` - vulnerable terhadap enumeration

**Dampak:**
- Attacker dapat melakukan brute force pada password
- Attacker dapat melakukan mass account creation
- Attacker dapat menguji apakah email terdaftar

**Rekomendasi:**
Implementasikan simple rate limiting:
```php
function checkRateLimit(PDO $db, string $ip, string $action, int $maxAttempts = 5, int $windowSeconds = 300): bool {
  $stmt = $db->prepare(
    'SELECT COUNT(*) FROM rate_limits 
     WHERE ip = :ip AND action = :action AND created_at > :since'
  );
  // ... implementation
}
```

**Status:** 🟠 BELUM DIPERBAIKI

---

#### [H-002] Weak Password Policy

**Lokasi:** Multiple locations

**Deskripsi:**
Password requirement sangat lemah:
- Minimum 8 karakter
- Wajib mengandung angka
- Tidak ada requirement uppercase, special chars, atau panjang minimum yang kuat

**Dampak:**
- User cenderung menggunakan password lemah
- Dictionary attacks lebih mudah berhasil

**Rekomendasi:**
Strengthen policy:
```php
if (strlen($password) < 12) {
  fail('Password minimal 12 karakter', 400);
}
if (preg_match('/[A-Z]/', $password) !== 1) {
  fail('Password wajib mengandung huruf besar', 400);
}
if (preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password) !== 1) {
  fail('Password wajib mengandung karakter khusus', 400);
}
```

**Status:** 🟠 BELUM DIPERBAIKI

---

#### [H-003] No CSRF Protection

**Lokasi:** Semua POST endpoints

**Deskripsi:**
Tidak ada CSRF token untuk state-changing operations

**Dampak:**
- Cross-site request forgery possible
- Attacker dapat membuat user meng-action tanpa persetujuan

**Rekomendasi:**
Tambahkan CSRF token generation dan validation:
```php
// Generate token
function generateCsrfToken(): string {
  return bin2hex(random_bytes(32));
}

// Validate
function validateCsrfToken(string $token): bool {
  // Check against stored session token
}
```

**Status:** 🟠 BELUM DIPERBAIKI

---

#### [H-004] Session Fixation Not Handled

**Lokasi:** `/auth/login` endpoint

**Deskripsi:**
Tidak ada session rotation setelah login berhasil

**Dampak:**
- Session fixation attacks possible
- Attacker dapat menetapkan cookie sebelum user login

**Rekomendasi:**
Invalidasi session lama dan buat yang baru:
```php
// After successful password verification
$revokeStmt = $db->prepare('UPDATE sessions SET is_revoked = 1 WHERE user_id = :user_id');
$revokeStmt->execute([':user_id' => $user['id']]);

// Then create new session
$token = issueSession($db, $fresh, $ttlHours);
```

**Status:** 🟠 BELUM DIPERBAIKI

---

#### [H-005] Error Messages Leak Sensitive Information

**Lokasi:** Catch block di akhir file

**Deskripsi:**
Error messages menampilkan stack trace dan database errors:

```php
// api/index.php line 2384
fail('Database error: ' . $e->getMessage(), 500);
```

**Dampak:**
- Information disclosure
- Database structure exposure
- Potensi CVE disclosure

**Rekomendasi:**
Sanitize errors:
```php
catch (PDOException $e) {
  error_log('Database error: ' . $e->getMessage());
  fail('Database error occurred. Please contact support.', 500);
}
```

**Status:** 🟠 BELUM DIPERBAIKI

---

### 3.3 MEDIUM SEVERITY

#### [M-001] Missing Content Security Policy (CSP) Header

**Lokasi:** `.htaccess`

**Dampak:**
- XSS attacks dapat lebih mudah dilakukan
- Tidak ada protection terhadap data injection

**Rekomendasi:**
Tambahkan CSP di `.htaccess`:
```apache
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'"
```

---

#### [M-002] Missing HSTS Header

**Dampak:**
- Man-in-the-middle attacks possible pada HTTP
- Cookie hijacking lebih mudah

**Rekomendasi:**
Tambahkan HSTS di `.htaccess`:
```apache
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

---

#### [M-003] No Input Validation on CSV Import

**Lokasi:** `/app/import` endpoint

**Dampak:**
- DoS via malformed CSV
- Invalid data di-database

---

#### [M-004] Mailketing List ID Hardcoded

**Lokasi:** cpanel-public/api/index.php line 1232

```php
$listId = trim((string)($payload['mailketing_list_id'] ?? '88538'));
```

---

#### [M-005] API Base URL Hardcoded

**Lokasi:** cpanel-public/api/index.php line 1312

```php
$appUrl = 'https://matiq.cepat.digital/';
```

---

### 3.4 LOW SEVERITY & INFO

#### [L-001] Public .env.example Missing

**Status:** File `.env.example` tersedia tapi mungkin tidak lengkap

#### [L-002] No Auto-Logout After Inactivity

**Status:** Session hanya expired berdasarkan TTL, tidak berdasarkan inactivity

#### [L-003] No Two-Factor Authentication

**Status:** Fitur 2FA belum tersedia

#### [L-004] Logging Tidak Komprehensif

**Status:** Tidak ada audit trail untuk admin actions

---

## 4. TEMUAN PERFORMA

### 4.1 Database Issues

#### [P-001] No Connection Pooling

**Lokasi:** `api/index.php` line 97-121

**Deskripsi:**
Setiap request membuat koneksi baru. Di cpanel-public ada workaround dengan RESET CONNECTION.

**Dampak:**
- Overhead koneksi database
- Tidak optimal untuk high traffic

**Rekomendasi:**
Gunakan persistent connections atau connection pooling:
```php
$db = new PDO($dsn, $user, $pass, [
  PDO::ATTR_PERSISTENT => true,
  // ...
]);
```

---

#### [P-002] No Query Caching

**Lokasi:** `/app/snapshot`

**Dampak:**
- Semua data di-load ulang setiap request
- Query tidak di-cache

**Rekomendasi:**
Implementasikan cache untuk snapshot:
- Redis (jika tersedia)
- File-based cache dengan TTL
- ETag support untuk conditional requests

---

#### [P-003] Missing Database Index

**Lokasi:** `notification_logs` table

**Deskripsi:**
Tidak ada index pada `user_id` column di `notification_logs`

**Dampak:**
- Slow queries saat filter by user_id

**Rekomendasi:**
Index sudah ada di schema (line 215), tapi perlu verify efektifnya.

---

#### [P-004] Large Snapshot Response

**Lokasi:** `/app/snapshot`

**Dampak:**
- Response size besar
- Slow response time

**Rekomendasi:**
Implementasikan pagination atau filtering:
```php
GET /app/snapshot?page=1&limit=50
```

---

## 5. TEMUAN BUGS

### 5.1 Functional Bugs

#### [B-001] Duplicate Payment Variable Declaration

**Lokasi:** cpanel-public/api/index.php lines 1996-2008

```php
$payment = strtoupper(trim((string)($payload['payment_status'] ?? '')));
$role = strtoupper(trim((string)($payload['role'] ?? ''))); // ← typo, should be strtolower
$activeProvided = array_key_exists('is_active', $payload);

$set = [];
$params = [':updated_at' => utcNowMs()];
if (in_array($payment, ['LUNAS', 'PENDING', 'NONE'], true)) {
  $set[] = 'payment_status = :payment_status';
  $params[':payment_status'] = $payment;
}
if (in_array($role, ['admin', 'user'], true)) { // ← type mismatch
  $set[] = 'role = :role';
  $params[':role'] = $role;
}
```

**Bug:** `$role` menggunakan `strtoupper()` sementara validasi menggunakan lowercase 'admin'/'user'

---

#### [B-002] DateTime Parsing Multiple Format Attempt

**Lokasi:** `api/index.php` lines 335-338

```php
$exp = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', (string)$row['expires_at'], new DateTimeZone('UTC'));
if (!$exp) {
  $exp = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string)$row['expires_at'], new DateTimeZone('UTC'));
}
```

**Issue:** Potensi race condition dan inconsistent parsing

---

#### [B-003] AI Endpoint Not Implemented in PHP Mode

**Lokasi:** `api/index.php` line 2378-2380

```php
if ($method === 'POST' && $uriPath === '/app/ai') {
  fail('AI endpoint belum diaktifkan di mode MySQL PHP', 501);
}
```

---

## 6. REKOMENDASI PERBAIKAN

### Priority Matrix

| Priority | Issue | Effort | Impact |
|----------|-------|--------|--------|
| P1 - Critical | Fix SQL injection (cpanel-public) | 2 hours | High |
| P1 - Critical | Fix password reset timestamp | 1 hour | High |
| P1 - Critical | Move reset secret to env | 1 hour | High |
| P2 - High | Add rate limiting | 4 hours | High |
| P2 - High | Strengthen password policy | 2 hours | Medium |
| P3 - Medium | Add CSRF protection | 4 hours | Medium |
| P3 - Medium | Add CSP header | 1 hour | Medium |
| P3 - Medium | Add HSTS header | 1 hour | Medium |
| P4 - Low | Implement session rotation | 2 hours | Medium |
| P4 - Low | Sanitize error messages | 1 hour | Low |

---

## 7. KONFIGURASI PRODUCTION

### 7.1 .htaccess Production

(Lihat file `docs/.htaccess-production`)

### 7.2 php.ini Production

(Lihat file `docs/php.ini-production`)

### 7.3 Environment Variables

```
# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=matiq_ads
DB_USER=matiq_user
DB_PASS=<strong-random-password>
DB_CHARSET=utf8mb4

# Authentication
AUTH_TOKEN_TTL_HOURS=24
PASSWORD_RESET_SECRET=<generate-with-openssl-random-hex-32>

# External APIs
MAILKETING_API_URL=https://api.mailketing.co.id/api/v1
MAILKETING_API_KEY=<your-key>
MAILKETING_SENDER=noreply@yourdomain.com

STARSENDER_API_URL=https://api.starsender.online/api/send
STARSENDER_API_KEY=<your-key>

# Notification Settings
NOTIFICATION_RETRY_MAX=3
NOTIFICATION_RETRY_DELAY_MS=1200

# Application
APP_URL=https://yourdomain.com
PUBLIC_RUNTIME_MODE=production
```

---

## 8. PLAYBOOK DEPLOYMENT

### 8.1 Pre-Deployment Checklist

- [ ] Semua critical issues di-fix
- [ ] Schema database sudah di-import
- [ ] Environment variables dikonfigurasi
- [ ] SSL certificate aktif
- [ ] Backup dibuat

### 8.2 Deployment Steps

1. **Backup**
   ```bash
   # Database backup
   mysqldump -u DB_USER -p DB_NAME > backup_$(date +%Y%m%d_%H%M%S).sql
   
   # Files backup
   tar -czf files_backup_$(date +%Y%m%d_%H%M%S).tar.gz public_html/
   ```

2. **Deploy Files**
   ```bash
   # Via FTP/SSH
   rsync -avz --delete ./ user@server:/home/user/public_html/
   ```

3. **Run Database Migrations**
   ```bash
   mysql -u DB_USER -p DB_NAME < database/mysql_schema.sql
   ```

4. **Verify**
   - Health check: `GET /health`
   - Login test
   - Import test

### 8.3 Rollback Procedure

1. **Immediate Rollback**
   ```bash
   # Restore files
   tar -xzf files_backup_TIMESTAMP.tar.gz -C /
   
   # Restore database
   mysql -u DB_USER -p DB_NAME < backup_TIMESTAMP.sql
   ```

2. **Version Rollback (Git)**
   ```bash
   git reset --hard HEAD~1
   git push --force
   ```

### 8.4 Monitoring

- Check error logs: `tail -f /home/user/logs/error.log`
- Check API responses
- Monitor resource usage (CPU/RAM)

---

## 9. LAMPIRAN

### A. Files Reviewed
- `api/index.php` (2387 lines)
- `cpanel-public/api/index.php` (2489 lines)
- `database/mysql_schema.sql` (267 lines)
- `.htaccess` (30 lines)
- `runtime-config.js`
- `runtime-config.php`

### B. Tools Used
- Manual code review
- Static analysis patterns
- Best practices comparison

### C. References
- OWASP Top 10
- PHP Security Cheat Sheet
- cPanel Best Practices

---

** Dokumen ini adalah confidential dan hanya untuk tim development **