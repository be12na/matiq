<?php
declare(strict_types=1);

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$envPath = dirname(__DIR__) . '/.env';
$env = is_file($envPath) ? parse_ini_file($envPath, false, INI_SCANNER_RAW) : [];

$cfg = [
  'gasWebAppUrl' => (string)($env['PUBLIC_GAS_WEB_APP_URL'] ?? ''),
  'dbTargetSheetId' => (string)($env['PUBLIC_DB_TARGET_SHEET_ID'] ?? ''),
  'authFallbackApiBase' => (string)($env['PUBLIC_AUTH_FALLBACK_API_BASE'] ?? ''),
  'defaultApiBase' => (string)($env['PUBLIC_DEFAULT_API_BASE'] ?? ''),
  'disableLiveSync' => (string)($env['PUBLIC_DISABLE_LIVE_SYNC'] ?? 'false'),
];

echo 'window.__MATIQ_PUBLIC_CONFIG__=' . json_encode($cfg, JSON_UNESCAPED_SLASHES) . ';';
