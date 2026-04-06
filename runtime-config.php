<?php
declare(strict_types=1);

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$env = [];
$candidates = [];
$custom = getenv('MATIQ_ENV_PATH');
if ($custom !== false && trim((string)$custom) !== '') {
  $candidates[] = trim((string)$custom);
}
$candidates[] = dirname(__DIR__) . '/.env';
$candidates[] = dirname(__DIR__, 2) . '/.env';

foreach ($candidates as $path) {
  if (is_file($path)) {
    $parsed = parse_ini_file($path, false, INI_SCANNER_RAW);
    if (is_array($parsed)) {
      $env = $parsed;
      break;
    }
  }
}

$cfg = [
  'gasWebAppUrl' => '',
  'dbTargetSheetId' => '',
  'authFallbackApiBase' => (string)($env['PUBLIC_AUTH_FALLBACK_API_BASE'] ?? ''),
  'defaultApiBase' => (string)($env['PUBLIC_DEFAULT_API_BASE'] ?? ''),
  'disableLiveSync' => (string)($env['PUBLIC_DISABLE_LIVE_SYNC'] ?? 'false'),
  'runtimeMode' => (string)($env['PUBLIC_RUNTIME_MODE'] ?? 'mysql'),
];

echo 'window.__MATIQ_PUBLIC_CONFIG__=' . json_encode($cfg, JSON_UNESCAPED_SLASHES) . ';';
