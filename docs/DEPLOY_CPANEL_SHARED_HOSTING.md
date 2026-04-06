# Deploy ke cPanel Shared Hosting (Aman + Production-Friendly)

Dokumen ini menyiapkan project agar bisa live di cPanel biasa (Apache + PHP), tanpa Node runtime, dengan backend native PHP + MySQL.

## Ringkasan arsitektur

- Frontend static: `index.html` + `app-main.js`
- API gateway: PHP router (`cpanel-public/api/index.php`)
- Source of truth data + business logic: MySQL (via phpMyAdmin + API PHP)

## 1) File yang dipakai untuk live di cPanel

Copy isi folder `cpanel-public/` ke document root domain (`public_html` atau docroot custom):

- `.htaccess`
- `api/index.php`
- `runtime-config.php`

Copy juga file frontend:

- `index.html`
- `app-main.js`
- `runtime-config.js`

## 2) Simpan environment secara aman

1. Buat file env di luar web root, contoh:
   - `/home/<cpanel-user>/.env`
2. Isi dari template `.env.example`.
3. Minimal wajib:
   - `DB_HOST`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`
4. Opsional:
   - `DB_PORT`
   - `DB_CHARSET`
   - `AUTH_TOKEN_TTL_HOURS`

`runtime-config.php` membaca variabel publik dari file env ini lalu expose ke browser sebagai `window.__MATIQ_PUBLIC_CONFIG__`.
Pada mode cPanel, `.htaccess` me-rewrite `runtime-config.js` -> `runtime-config.php`, jadi frontend tetap memanggil `runtime-config.js` dengan kompatibilitas lintas environment.

## 3) Routing yang harus aktif

`.htaccess` sudah mengarahkan route ini ke PHP gateway:

- `/health`
- `/auth/*`
- `/admin/*`
- `/user/*`
- `/app/*`
- `/oauth/*`

Semua route lain fallback ke `index.html` (SPA fallback).

## 4) Endpoint API (mode MySQL)

- `POST /auth/register`
- `POST /auth/login`
- `POST /auth/verify`
- `POST /auth/logout`
- `POST /auth/create-first-admin`
- `GET /admin/users`
- `POST /admin/users`
- `POST /admin/user`
- `POST /admin/user/delete`
- `POST /admin/user/reset-password`
- `POST /admin/users/bulk-status`
- `GET /admin/stats`
- `POST /admin/notifications`
- `GET /user/profile`
- `POST /user/profile`
- `POST /user/change-password`
- `GET /app/snapshot`
- `POST /app/import`
- `POST /app/save-note`
- `POST /app/ai` (saat ini mengembalikan `501`)

Catatan: `/oauth/openai/*` dikembalikan `501` pada mode gateway PHP ini, karena backend OAuth dedicated belum diimplementasi di repo ini.

## 5) Uji setelah upload

1. Cek health:
   - `GET https://<domain-anda>/health` -> harus `200` dan `{"ok":true,...}`
2. Cek auth:
   - `POST /auth/login`
3. Cek snapshot:
   - `GET /app/snapshot` (dengan bearer token valid)
4. Cek UI:
   - Hard refresh halaman utama, pastikan tidak ada 404 untuk route API utama

## 6) Verifikasi manual (wajib saat deploy pertama)

- Pastikan `mod_rewrite` aktif di hosting.
- Pastikan file env benar-benar di luar public directory.
- Pastikan permission file aman (umum: file `644`, folder `755`).
- Jika domain dipasang di subfolder (bukan root), sesuaikan `RewriteBase` di `.htaccess`.

## 7) Cleanup yang aman dilakukan

Sudah dibersihkan artefak non-runtime berikut:

- `token-usage-output.txt`
- `creative` (placeholder kosong)

Tidak ada logic bisnis GAS/API contract yang diubah.
Runtime cPanel saat ini berjalan native MySQL tanpa relay ke GAS.
