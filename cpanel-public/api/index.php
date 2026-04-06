<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$envPath = dirname(__DIR__, 2) . '/.env';
$env = is_file($envPath) ? parse_ini_file($envPath, false, INI_SCANNER_RAW) : [];

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

function out(array $data, int $status = 200): void {
  http_response_code($status);
  echo json_encode($data, JSON_UNESCAPED_SLASHES);
  exit;
}

function inferStatus(string $message, int $fallback = 400): int {
  $msg = strtolower($message);
  if ($msg === '') return $fallback;
  if (strpos($msg, 'unauthorized') !== false || strpos($msg, 'login diperlukan') !== false || strpos($msg, 'token') !== false) return 401;
  if (strpos($msg, 'forbidden') !== false || strpos($msg, 'hanya admin') !== false || strpos($msg, 'akses ditolak') !== false) return 403;
  if (strpos($msg, 'not found') !== false || strpos($msg, 'tidak ditemukan') !== false) return 404;
  return $fallback;
}

if ($method === 'OPTIONS') {
  http_response_code(204);
  exit;
}

$routes = [
  'GET /health' => ['health', false, false],

  'POST /auth/register' => ['register', false, false],
  'POST /auth/login' => ['login', false, false],
  'POST /auth/verify' => ['verify_token', false, false],
  'POST /auth/logout' => ['logout', false, false],
  'POST /auth/create-first-admin' => ['create_first_admin', false, false],

  'GET /admin/users' => ['list_users', true, true],
  'POST /admin/users' => ['list_users', true, true],
  'GET /admin/user' => ['get_user', true, true],
  'POST /admin/user' => ['update_user', true, true],
  'POST /admin/user/delete' => ['delete_user', true, true],
  'POST /admin/user/reset-password' => ['reset_user_password', true, true],
  'POST /admin/users/bulk-status' => ['bulk_update_status', true, true],
  'GET /admin/stats' => ['get_user_stats', true, true],
  'POST /admin/stats' => ['get_user_stats', true, true],
  'GET /admin/notifications' => ['get_notification_status', true, true],
  'POST /admin/notifications' => ['get_notification_status', true, true],

  'GET /user/profile' => ['get_profile', true, true],
  'POST /user/profile' => ['update_profile', true, true],
  'POST /user/change-password' => ['change_password', true, true],

  'GET /app/snapshot' => ['snapshot', true, true],
  'POST /app/import' => ['import_csv', true, true],
  'POST /app/save-note' => ['save_note', true, true],
  'POST /app/ai' => ['ask_ai', true, true],
];

$routeKey = $method . ' ' . $uriPath;
if (!isset($routes[$routeKey])) {
  if (strpos($uriPath, '/oauth/openai/') === 0) {
    out(['ok' => false, 'error' => 'OAuth endpoint is not available in PHP gateway mode'], 501);
  }
  out(['ok' => false, 'error' => 'Route not found'], 404);
}

[$action, $requireAuth, $includeInternal] = $routes[$routeKey];

if ($uriPath === '/health') {
  out(['ok' => true, 'service' => 'cpanel-php-gateway']);
}

$gasUrl = trim((string)($env['GAS_WEB_APP_URL'] ?? ''));
if ($gasUrl === '') out(['ok' => false, 'error' => 'GAS_WEB_APP_URL is not configured'], 500);

$raw = file_get_contents('php://input') ?: '';
$payload = [];
if ($raw !== '') {
  $parsed = json_decode($raw, true);
  if (is_array($parsed)) $payload = $parsed;
}

if ($method === 'GET' && !empty($_GET)) {
  foreach ($_GET as $k => $v) {
    $payload[$k] = $v;
  }
}

if ($routeKey === 'POST /admin/users' && isset($payload['password']) && trim((string)$payload['password']) !== '') {
  $action = 'create_user';
}
if ($routeKey === 'POST /admin/notifications' && !empty($payload['process_queue'])) {
  $action = 'process_whatsapp_queue';
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$bearer = '';
if (stripos($authHeader, 'Bearer ') === 0) {
  $bearer = trim(substr($authHeader, 7));
}
if ($bearer !== '' && empty($payload['auth_token'])) {
  $payload['auth_token'] = $bearer;
}

if ($requireAuth && empty($payload['auth_token'])) {
  out(['ok' => false, 'error' => 'Unauthorized: Login diperlukan'], 401);
}

$payload['action'] = $action;

$dbSheet = trim((string)($env['DB_TARGET_SHEET_ID'] ?? ''));
if ($dbSheet !== '' && empty($payload['db_target_sheet_id'])) {
  $payload['db_target_sheet_id'] = $dbSheet;
}

if ($includeInternal) {
  $internal = trim((string)($env['INTERNAL_API_TOKEN'] ?? ''));
  if ($internal !== '' && empty($payload['internal_token'])) {
    $payload['internal_token'] = $internal;
  }
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' => "Content-Type: application/json\r\n",
    'content' => json_encode($payload, JSON_UNESCAPED_SLASHES),
    'timeout' => 40,
    'ignore_errors' => true,
  ],
]);

$responseText = @file_get_contents($gasUrl, false, $ctx);
if ($responseText === false) {
  out(['ok' => false, 'error' => 'Failed to reach GAS endpoint'], 502);
}

$httpCode = 200;
if (isset($http_response_header) && is_array($http_response_header)) {
  foreach ($http_response_header as $line) {
    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
      $httpCode = (int)$m[1];
      break;
    }
  }
}

$json = json_decode($responseText, true);
if (!is_array($json)) {
  out([
    'ok' => false,
    'error' => 'Invalid JSON response from GAS endpoint',
    'status' => $httpCode,
  ], 502);
}

if (isset($json['ok']) && $json['ok'] === false) {
  $status = ($httpCode >= 200 && $httpCode < 300)
    ? inferStatus((string)($json['error'] ?? ''), 400)
    : $httpCode;
  out($json, $status);
}

out($json, ($httpCode >= 200 && $httpCode < 300) ? 200 : $httpCode);
