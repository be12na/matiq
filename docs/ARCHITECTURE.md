# Ad Campaign Tracker - Arsitektur MVP

## Struktur Folder

```txt
index.html        # Entry point frontend static
app-main.js       # Frontend app logic (React UMD)
runtime-config.js # Config publik default lintas environment

gas/
  Code.gs        # Entry point doGet/doPost
  Api.gs         # Dispatcher action API + wrapper UI
  Parser.gs      # CSV parser + flexible header mapping
  Analyzer.gs    # Safe metrics + diagnosis + alert + brief + hierarchy
  Notifications.gs # Integrasi Mailketing + Starsender (retry + WA queue + logging)
  Sheets.gs      # Inisialisasi schema Sheets + CRUD helper
  Ai.gs          # Integrasi ke Cloudflare Worker AI proxy
  App.html       # UI internal Apps Script HTML Service

functions/
  auth/*         # Cloudflare Pages Functions auth gateway
  user/*         # Cloudflare Pages Functions user gateway
  admin/*        # Cloudflare Pages Functions admin gateway
  health.js      # Health endpoint untuk Pages Functions

cpanel-public/
  .htaccess      # Rewrite + security headers + SPA fallback
  runtime-config.php
  api/index.php  # Gateway PHP untuk shared hosting cPanel

docs/
  ARCHITECTURE.md
  SETUP.md
  DEPLOY_CPANEL_SHARED_HOSTING.md
  SAMPLE_RESPONSES.json
  IMPORT_TEMPLATE.csv
```

## Kontrak Data Utama

### Entities
- campaign, adset, ad
- Semua entity disajikan sebagai objek normalisasi dengan:
  - `level`, `name`, `metrics`, `status`, `priority`, `diagnosis`, `action`, `alerts`, `note`

### Metrics Aman
- `roas = revenue / spend`
- `cpm = (spend / impressions) * 1000`
- `cpa = spend / results`
- `clicks = impressions * ctr / 100` (jika clicks tidak ada)
- `atcRate = atc / clicks * 100`
- `conversionRate = results / atc * 100`
- Semua pembagian aman (default 0 saat denominator 0)

## API Actions (Apps Script)

Semua POST via `doPost` payload JSON:

```json
{ "action": "import_csv", "...": "payload" }
```

GET snapshot:
- `GET ?action=snapshot`

### Action List
- `bootstrap`
- `import_csv`
- `snapshot`
- `save_thresholds`
- `save_note`
- `save_settings`
- `reset_data`
- `compare_periods`
- `ask_ai`

### Error Shape

```json
{ "ok": false, "error": "message" }
```

### Success Shape

```json
{ "ok": true, "data": { } }
```

### Snapshot Payload

- Snapshot dipakai sebagai hot-path untuk render dashboard/live frontend.
- Payload utama berisi `kpi`, `entities`, `hierarchy`, `thresholds`, `notes`, dan `settings`.
- `import_logs` sengaja tidak ikut dimuat penuh pada snapshot live untuk menurunkan overhead baca Google Sheets. Jika field ini muncul, nilainya kosong kecuali ada kebutuhan debug khusus.

## Data Flow Ringkas

1. User upload CSV per level dari tab Import.
2. UI kirim CSV text ke `uiImportCsv`.
3. `Parser.gs` normalisasi header + parsing angka aman.
4. `Sheets.gs` simpan ke `campaigns/adsets/ads`; penulisan `import_logs` hanya aktif jika `ENABLE_IMPORT_LOG_SHEET=true`.
5. Tab lain ambil `uiSnapshot` lalu render KPI/rekomendasi/alert/hierarki/analitik.
6. Tab AI kirim pertanyaan ke `uiAskAi`.
7. `Ai.gs` kirim ringkasan data ke Cloudflare Worker (`/ai/analyze`) dengan token internal.

## Batasan MVP
- Satu spreadsheet sebagai source of truth.
- Tanpa static assets terpisah.
- Tanpa frontend framework berat.
- Tanpa backend tambahan selain Apps Script + Worker.
