# Deploy ke cPanel Shared Hosting (Aman + Production-Friendly)

Dokumen ini menyiapkan project agar bisa live di cPanel biasa (Apache + PHP), tanpa Node runtime dan tanpa mengubah logic bisnis yang berjalan di GAS.

## Ringkasan arsitektur

- Frontend static: `index.html` + `app-main.js`
- API gateway: PHP router (`cpanel-public/api/index.php`)
- Source of truth data + business logic: Google Apps Script (`gas/*.gs`)

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
   - `GAS_WEB_APP_URL`
   - `DB_TARGET_SHEET_ID`
4. Untuk protected action (`/app/*`, `/admin/*`, `/user/*`), isi:
   - `INTERNAL_API_TOKEN`

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

## 4) Endpoint mapping PHP -> GAS action

- `POST /auth/register` -> `register`
- `POST /auth/login` -> `login`
- `POST /auth/verify` -> `verify_token`
- `POST /auth/logout` -> `logout`
- `POST /auth/create-first-admin` -> `create_first_admin`
- `GET /admin/users` -> `list_users`
- `POST /admin/users` -> `list_users` atau `create_user` (jika payload ada `password`)
- `GET /admin/user` -> `get_user`
- `POST /admin/user` -> `update_user`
- `POST /admin/user/delete` -> `delete_user`
- `POST /admin/user/reset-password` -> `reset_user_password`
- `POST /admin/users/bulk-status` -> `bulk_update_status`
- `GET|POST /admin/stats` -> `get_user_stats`
- `GET /admin/notifications` -> `get_notification_status`
- `POST /admin/notifications` -> `get_notification_status` atau `process_whatsapp_queue` (jika payload `process_queue=true`)
- `GET /user/profile` -> `get_profile`
- `POST /user/profile` -> `update_profile`
- `POST /user/change-password` -> `change_password`
- `GET /app/snapshot` -> `snapshot`
- `POST /app/import` -> `import_csv`
- `POST /app/save-note` -> `save_note`
- `POST /app/ai` -> `ask_ai`

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
- Pastikan `allow_url_fopen` aktif (dipakai `file_get_contents` untuk call GAS).
- Pastikan file env benar-benar di luar public directory.
- Pastikan permission file aman (umum: file `644`, folder `755`).
- Jika domain dipasang di subfolder (bukan root), sesuaikan `RewriteBase` di `.htaccess`.

## 7) Cleanup yang aman dilakukan

Sudah dibersihkan artefak non-runtime berikut:

- `token-usage-output.txt`
- `creative` (placeholder kosong)

Tidak ada logic bisnis GAS/API contract yang diubah.
