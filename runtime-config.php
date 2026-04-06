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
$docRoot = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
if ($docRoot !== '') {
  $candidates[] = rtrim($docRoot, '/\\') . '/.env';
}
$cwd = getcwd();
if (is_string($cwd) && $cwd !== '') {
  $candidates[] = rtrim($cwd, '/\\') . '/.env';
}
$scriptFile = (string)($_SERVER['SCRIPT_FILENAME'] ?? '');
if ($scriptFile !== '') {
  $candidates[] = dirname($scriptFile) . '/.env';
  $candidates[] = dirname($scriptFile, 2) . '/.env';
}
$candidates[] = dirname(__DIR__) . '/.env';
$candidates[] = dirname(__DIR__, 2) . '/.env';
$candidates[] = dirname(__DIR__) . '/../.env';
$candidates[] = dirname(__DIR__) . '/../../.env';
$candidates[] = getenv('HOME') ? rtrim((string)getenv('HOME'), '/\\') . '/.env' : '';

foreach ($candidates as $path) {
  if (!is_string($path) || $path === '' || !is_file($path)) {
    continue;
  }

  $parsed = parse_ini_file($path, false, INI_SCANNER_RAW);
  if (is_array($parsed)) {
    $env = $parsed;
    break;
  }

  $raw = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if (is_array($raw)) {
    $fallbackEnv = [];
    foreach ($raw as $line) {
      $line = trim((string)$line);
      if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
        continue;
      }
      $parts = explode('=', $line, 2);
      if (count($parts) !== 2) {
        continue;
      }
      $key = trim($parts[0]);
      $value = trim($parts[1]);
      if ($key !== '') {
        $fallbackEnv[$key] = $value;
      }
    }
    if ($fallbackEnv !== []) {
      $env = $fallbackEnv;
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
