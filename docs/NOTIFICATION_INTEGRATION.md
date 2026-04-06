# Integrasi Mailketing + Starsender

Dokumen ini menjelaskan konfigurasi notifikasi untuk:

- email verifikasi pendaftaran,
- email notifikasi login,
- WhatsApp konfirmasi akun (queue + retry).

## 1) Script Properties yang wajib

Set di Apps Script -> Project Settings -> Script properties:

- `MAILKETING_API_URL`
- `MAILKETING_API_KEY`
- `MAILKETING_SENDER` (contoh: `no-reply@domainanda.com`)
- `STARSENDER_API_URL`
- `STARSENDER_API_KEY`

Opsional:

- `STARSENDER_DEVICE_ID`
- `MAILKETING_TIMEOUT_MS` (default `15000`)
- `STARSENDER_TIMEOUT_MS` (default `15000`)
- `NOTIFICATION_RETRY_MAX` (default `3`)
- `NOTIFICATION_RETRY_DELAY_MS` (default `1200`)
- `APP_PUBLIC_URL` (untuk membentuk link verifikasi di email)

## 2) Data storage tambahan

Sistem membuat sheet berikut otomatis:

- `user_contacts`
- `notification_logs`
- `whatsapp_queue`

## 3) Flow notifikasi

### Register

1. Validasi email/password/nama/nomor WhatsApp.
2. User dibuat.
3. `user_contacts` di-upsert.
4. Email verifikasi pendaftaran dikirim via Mailketing (retry).
5. Pesan WhatsApp konfirmasi di-enqueue ke `whatsapp_queue`.
6. Queue diproses langsung 1 item (best effort), sisanya bisa diproses manual dari admin dashboard.

### Login

1. Validasi email/password.
2. Session token dibuat.
3. Email notifikasi login dikirim via Mailketing (retry).

## 4) Admin monitoring

Dashboard Admin menampilkan:

- ringkasan success/failed email,
- ringkasan WA sent/pending/retry,
- log pengiriman terbaru,
- tombol manual `Proses Queue WhatsApp`.

Endpoint admin tambahan:

- `GET /admin/notifications` -> status queue + log
- `POST /admin/notifications` dengan `{ "process_queue": true, "max_items": 10 }` -> proses queue

## 5) Retry + queue behavior

- Email: retry sinkron dengan backoff (`NOTIFICATION_RETRY_MAX`, `NOTIFICATION_RETRY_DELAY_MS`).
- WA: retry asinkron berbasis queue (`pending` -> `retry` -> `failed/sent`).
- Kondisi retry: timeout, `429`, atau `5xx`.

## 6) Test skenario yang harus dijalankan

### A. Timeout koneksi

1. Set `MAILKETING_TIMEOUT_MS=1` (sementara).
2. Coba register/login.
3. Pastikan error tidak merusak auth flow, dan `notification_logs` mencatat failed + attempt.

### B. Invalid API key

1. Isi `MAILKETING_API_KEY` atau `STARSENDER_API_KEY` dengan nilai salah.
2. Coba register/login.
3. Pastikan status `failed` tercatat, plus retry berjalan sesuai aturan.

### C. Format nomor WhatsApp salah

1. Register dengan nomor invalid (contoh: `0812-ABCD`).
2. Pastikan API register menolak request dengan error validasi.

### D. Queue processing

1. Register user valid (nomor WA valid).
2. Cek ada row baru di `whatsapp_queue`.
3. Klik `Proses Queue WhatsApp` di Admin.
4. Verifikasi status berubah (`sent` / `retry` / `failed`) dan log tercatat.

### E. Self-test internal

Admin dapat menjalankan action:

- `notification_self_test`

Self-test ini memverifikasi:

- invalid phone rejection,
- valid phone acceptance,
- retry helper behavior.
