# Setup MySQL di cPanel

File schema sudah disiapkan di:

- `database/mysql_schema.sql`

## Langkah import via cPanel (phpMyAdmin)

1. Login ke cPanel.
2. Buka **MySQL Databases**.
3. Buat database baru (opsional jika belum ada), contoh: `matiq_tracker`.
4. Buat user database, lalu assign ke database dengan privilege **ALL PRIVILEGES**.
5. Buka **phpMyAdmin**.
6. Pilih database target.
7. Tab **Import** -> pilih file `database/mysql_schema.sql`.
8. Klik **Go** dan pastikan tidak ada error.

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

Saat ini logic utama aplikasi masih membaca/menulis ke Google Sheets (`gas/*.gs`), bukan langsung ke MySQL.

Artinya:

- MySQL ini sudah siap sebagai database production,
- tetapi perpindahan runtime ke MySQL butuh layer repository/query di backend (belum diubah pada tahap ini agar contract existing tetap aman).

## Verifikasi cepat setelah import

Jalankan query berikut di phpMyAdmin:

```sql
SHOW TABLES;
SELECT * FROM thresholds;
```

Jika tabel muncul lengkap dan `thresholds` berisi 4 baris default, setup database berhasil.
