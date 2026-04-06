# Import MySQL dari `Db Ads Tracker.xlsx`

Dokumen ini untuk mode import penuh agar isi MySQL sama dengan workbook `templates/Db Ads Tracker.xlsx`.

## File yang dipakai

- Source workbook: `templates/Db Ads Tracker.xlsx`
- Generator SQL: `ops/generate-db-ads-tracker-import-sql.ps1`
- Output SQL import: `database/mysql_import_db_ads_tracker.sql`

## Regenerate SQL dari workbook

Jalankan dari root project:

```powershell
./ops/generate-db-ads-tracker-import-sql.ps1 -XlsxPath "templates/Db Ads Tracker.xlsx" -OutputSqlPath "database/mysql_import_db_ads_tracker.sql"
```

## Import ke phpMyAdmin (database baru)

1. Buat database baru di cPanel, contoh: `egxvvhji_matiq_import`.
2. Masuk phpMyAdmin, pilih database tersebut.
3. Tab **Import**.
4. Pilih file `database/mysql_import_db_ads_tracker.sql`.
5. Klik **Go**.

## Hasil import

SQL ini akan:

- membuat tabel sesuai workbook (`campaigns`, `adsets`, `ads`, `thresholds`, `notes`, `settings`, `import_logs`, `users`, `sessions`, `readme_meta`),
- truncate isi tabel,
- lalu insert data hasil ekstraksi workbook.

## Verifikasi cepat

Jalankan query berikut di phpMyAdmin:

```sql
SHOW TABLES;
SELECT COUNT(*) FROM campaigns;
SELECT COUNT(*) FROM adsets;
SELECT COUNT(*) FROM ads;
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM sessions;
```

Jika row count sesuai data workbook, import berhasil.
