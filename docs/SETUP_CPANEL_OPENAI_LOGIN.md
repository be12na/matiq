# Setup cPanel + OpenAI Browser Login API

Dokumen ini menjelaskan cara deploy aplikasi ke cPanel dan mengaktifkan login browser OpenAI.

## 1) Deploy ke cPanel

### Upload File

1. **Siapkan folder di komputer lokal:**
   - Buat folder baru, contoh: `matiq-deploy`
   - Copy file berikut ke folder tersebut:
     - `index.html`
     - `app-main.js` (sudah dihapus opsi Gemini & Claude)
     - `runtime-config.js`
     - `.htaccess` (dari folder `docs/`)
     - `api/index.php` (dari folder `cpanel-public/`)
     - `runtime-config.php` (dari folder `cpanel-public/`)

2. **Upload ke cPanel:**
   - Login ke cPanel → File Manager
   - Buka `public_html` (atau folder domain anda)
   - Upload semua file di atas

### Konfigurasi Database

1. **Buat database di cPanel:**
   - Menu `MySQL Database` → Buat database baru
   - Buat user database dan password
   - Assign user ke database dengan privilege ALL

2. **Buat file `.env` di luar public_html:**
   - Di File Manager, navigasi ke `/home/<username>/`
   - Buat file baru `.env`
   - Isi dengan:
     ```
     DB_HOST=localhost
     DB_NAME=nama_database_anda
     DB_USER=nama_user_database
     DB_PASS=password_database_anda
     ```

### Verifikasi

1. Buka `https://domain-anda/health` - harus muncul `{"ok":true,...}`
2. Buka `https://domain-anda/` - harus muncul halaman login aplikasi

---

## 2) Setup OpenAI Browser Login (OAuth)

### Penjelasan

Aplikasi menggunakan OpenAI OAuth via browser session. Pengguna login ke OpenAI melalui browser, lalu aplikasi menggunakan secure relay untuk mengakses API OpenAI tanpa perlu API key manual.

### Konfigurasi di Aplikasi

1. Buka aplikasi di browser
2. Klik **Settings** (icon gear)
3. Pada **AI Provider**, pilih **OpenAI**
4. Klik tombol **Login Browser**
   - Akan terbuka halaman login OpenAI di tab baru
   - Login dengan akun OpenAI anda
   - Setelah login berhasil, kembali ke aplikasi
5. Klik **Validasi Session** untuk memastikan koneksi aktif
6. Isi **Model** (opsional) - contoh: `gpt-4o-mini`
7. Klik **Simpan**

### Catatan Penting

- Session login browser bersifat sementara - anda mungkin perlu login ulang setelah beberapa waktu
- Jika session expired, klik **Validasi Session** dulu, jika gagal berarti perlu **Login Browser** ulang
- Tombol **Putuskan** untuk logout dari OpenAI dan kembali ke mode default

### Troubleshooting

**Login Browser tidak membuka halaman OpenAI:**
- Pastikan popup blocker tidak memblokir jendela baru
- Coba klik ulang tombol dengan allow popup

**Validasi Session gagal:**
- Session mungkin expired - klik Login Browser ulang
- Cek koneksi internet

**Error "Provider not supported":**
- Pastikan sudah memilih provider OpenAI di dropdown
- Refresh halaman aplikasi

---

## 3) Setelah Setup Selesai

1. Register user pertama → otomatis menjadi admin
2. Setup database sudah otomatis membuat tabel jika belum ada
3. Aplikasi siap digunakan untuk tracking Meta Ads

---

## 4) File yang Diubah

- `app-main.js` - Dihapus pilihan provider Gemini dan Claude, sekarang hanya:
  - Builtin (tanpa API key)
  - OpenAI (dengan browser OAuth)