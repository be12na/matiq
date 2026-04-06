# Integrasi Email Mailketing (Spec Resmi API)

Dokumen ini mengunci kontrak integrasi email berdasarkan dokumentasi resmi:

- https://mailketing.co.id/docs/send-email-via-api/

Fokus dokumen ini adalah mode backend PHP/MySQL pada cPanel (bukan Apps Script).

## 1) Endpoint resmi Mailketing

- URL: `https://api.mailketing.co.id/api/v1/send`
- Method: `POST`
- Body format: `application/x-www-form-urlencoded` (sesuai contoh resmi cURL PHP)

Parameter request yang wajib:

- `api_token` (API token akun Mailketing)
- `from_name` (nama pengirim)
- `from_email` (email pengirim terverifikasi)
- `recipient` (email tujuan)
- `subject` (judul email)
- `content` (isi email, plain text atau HTML)

Parameter opsional:

- `attach1`
- `attach2`
- `attach3`

Catatan dokumen resmi menyebut attachment sebagai direct URL file, dan size file mengikuti batas Mailketing saat request diproses.

## 2) Response resmi Mailketing

Contoh response sukses:

```json
{"status":"success","response":"Mail Sent"}
```

Contoh response gagal dari docs resmi:

- `User Not Found or Wrong API Token`
- `Access Denied, Invalid Token`
- `Unknown Sender, Please Add your Sender Email at Add Domain Menu`
- `Empty From Name`
- `No Credits, Please Top Up`
- `Empty Recipient, Please Add recipient address`
- `Blacklisted`
- `Empty Subject, Please Add Subject Email`
- `Empty Content, Please Add Email Content`

## 3) Konfigurasi .env (server cPanel)

Minimal konfigurasi untuk email API:

- `MAILKETING_API_URL=https://api.mailketing.co.id/api/v1/send`
- `MAILKETING_API_KEY=...`
- `MAILKETING_FROM_NAME=...`
- `MAILKETING_SENDER=sender@domain-verified.com`

Opsional:

- `MAILKETING_TIMEOUT_MS=15000`
- `NOTIFICATION_RETRY_MAX=3`
- `NOTIFICATION_RETRY_DELAY_MS=1200`

Semua variabel di atas harus disimpan di `.env` server (di luar source control).

## 4) Mapping payload backend -> Mailketing

Backend wajib mengirim payload ke Mailketing dengan mapping berikut:

- `api_token` <- `MAILKETING_API_KEY`
- `from_name` <- `MAILKETING_FROM_NAME`
- `from_email` <- `MAILKETING_SENDER`
- `recipient` <- email user target
- `subject` <- subject notifikasi
- `content` <- isi notifikasi

## 5) Validasi integrasi produksi

Checklist verifikasi:

1. Request ke endpoint Mailketing menggunakan `POST` dan body form-url-encoded.
2. `from_email` sudah terdaftar/terverifikasi di akun Mailketing.
3. API key valid dan memiliki credit.
4. Saat sukses, status log internal disimpan sebagai `sent`.
5. Saat gagal (`status=failed`), backend menyimpan error message dari field `response` untuk debug.

## 6) Troubleshooting cepat

- Jika muncul `Unknown Sender`: verifikasi domain/sender di dashboard Mailketing.
- Jika muncul `No Credits`: isi ulang credit Mailketing.
- Jika muncul `Invalid Token`: cek nilai `MAILKETING_API_KEY`.
- Jika email tidak terkirim tapi API sukses: periksa reputasi recipient dan status blacklist.
