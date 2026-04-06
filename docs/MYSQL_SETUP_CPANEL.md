# Setup MySQL di cPanel

File schema sudah disiapkan di:

- `database/mysql_schema.sql`

## Langkah import via cPanel (phpMyAdmin)

1. Login ke cPanel.
2. Buka **MySQL Databases**.
3. Buat database baru (opsional jika belum ada), contoh: `matiq_tracker`.
4. Buat user database, lalu assign ke database dengan privilege **ALL PRIVILEGES**.
5. Buka **phpMyAdmin**.
6. Pilih database target terlebih dahulu.
7. Tab **Import** -> pilih file `database/mysql_schema.sql`.
8. Klik **Go** dan pastikan tidak ada error.

Catatan: file schema tidak lagi berisi `CREATE DATABASE` atau `USE`, jadi import harus dilakukan setelah database target dipilih di phpMyAdmin.

## Tabel yang akan dibuat

- `campaigns`
- `adsets`
- `ads`
- `thresholds`
- `notes`
- `settings`
- `import_logs`
- `users`
- `sessions`
- `user_contacts`
- `notification_logs`
- `whatsapp_queue`

Schema sudah termasuk:

- index utama untuk query operasional,
- relasi `sessions.user_id -> users.id` (cascade),
- seed default untuk `thresholds`.

## Catatan penting integrasi

Runtime cPanel sekarang membaca/menulis langsung ke MySQL melalui `cpanel-public/api/index.php`.

Artinya:

- phpMyAdmin + MySQL adalah source of truth untuk mode cPanel,
- endpoint `/auth/*`, `/admin/*`, `/user/*`, dan `/app/*` tidak lagi relay ke Google Apps Script.

## Verifikasi cepat setelah import

Jalankan query berikut di phpMyAdmin:

```sql
SHOW TABLES;
SELECT * FROM thresholds;
```

Jika tabel muncul lengkap dan `thresholds` berisi 4 baris default, setup database berhasil.
