<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $data, int $status = 200): void {
  http_response_code($status);
  echo json_encode($data, JSON_UNESCAPED_SLASHES);
  exit;
}

function fail(string $error, int $status = 400, array $extra = []): void {
  out(array_merge(['ok' => false, 'error' => $error], $extra), $status);
}

function utcNow(): DateTimeImmutable {
  return new DateTimeImmutable('now', new DateTimeZone('UTC'));
}

function utcNowMs(): string {
  return utcNow()->format('Y-m-d H:i:s.v');
}

function envGet(array $env, string $key, string $default = ''): string {
  if (array_key_exists($key, $env)) {
    return trim((string)$env[$key]);
  }
  $v = getenv($key);
  if ($v !== false) {
    return trim((string)$v);
  }
  return $default;
}

function loadEnv(): array {
  $candidates = [];
  $custom = getenv('MATIQ_ENV_PATH');
  if ($custom !== false && trim($custom) !== '') {
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
  $candidates[] = dirname(__DIR__, 2) . '/.env';
  $candidates[] = dirname(__DIR__, 3) . '/.env';
  $candidates[] = dirname(__DIR__) . '/../.env';
  $candidates[] = dirname(__DIR__) . '/../../.env';
  $candidates[] = getenv('HOME') ? rtrim((string)getenv('HOME'), '/\\') . '/.env' : '';

  foreach ($candidates as $path) {
    if (!is_string($path) || $path === '' || !is_file($path)) {
      continue;
    }

    $parsed = parse_ini_file($path, false, INI_SCANNER_RAW);
    if (is_array($parsed)) {
      return $parsed;
    }

    $raw = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($raw)) {
      $env = [];
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
          $env[$key] = $value;
        }
      }
      if ($env !== []) {
        return $env;
      }
    }
  }
  return [];
}

function pdo(array $env): PDO {
  static $db = null;
  if ($db instanceof PDO) {
    return $db;
  }

  $host = envGet($env, 'DB_HOST', '127.0.0.1');
  $port = envGet($env, 'DB_PORT', '3306');
  $name = envGet($env, 'DB_NAME');
  $user = envGet($env, 'DB_USER');
  $pass = envGet($env, 'DB_PASS');
  $charset = envGet($env, 'DB_CHARSET', 'utf8mb4');

  if ($name === '' || $user === '') {
    throw new RuntimeException('DB_NAME and DB_USER must be configured');
  }

  $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
  $db = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  return $db;
}

function randomId(string $prefix, int $bytes = 16): string {
  return $prefix . bin2hex(random_bytes($bytes));
}

function normalizeEmail(string $email): string {
  return strtolower(trim($email));
}

function normalizeWa(string $input): string {
  $digits = preg_replace('/[^0-9]/', '', $input);
  $digits = is_string($digits) ? $digits : '';
  if ($digits === '') {
    return '';
  }
  if (strpos($digits, '0') === 0) {
    $digits = '62' . substr($digits, 1);
  }
  return $digits;
}

function isTruthy($v): bool {
  if ($v === true || $v === 1 || $v === '1') {
    return true;
  }
  $s = strtolower(trim((string)$v));
  return $s === 'true' || $s === 'yes' || $s === 'y' || $s === 'on';
}

function userToPublic(array $u): array {
  return [
    'id' => (string)$u['id'],
    'email' => (string)$u['email'],
    'name' => (string)$u['name'],
    'role' => (string)$u['role'],
    'payment_status' => (string)$u['payment_status'],
    'is_active' => ((int)$u['is_active'] === 1) ? 'true' : 'false',
    'created_at' => (string)($u['created_at'] ?? ''),
    'updated_at' => (string)($u['updated_at'] ?? ''),
    'last_login' => (string)($u['last_login'] ?? ''),
  ];
}

function verifyPasswordCompat(string $password, string $hash, string $salt): bool {
  if ($hash === '') {
    return false;
  }

  if (password_get_info($hash)['algo'] !== null) {
    return password_verify($password, $hash);
  }

  $saltNorm = strtoupper(trim($salt));
  if ($saltNorm === '' || $saltNorm === 'PLAINTEXT') {
    return hash_equals($hash, $password);
  }

  if (preg_match('/^[a-f0-9]{64}$/i', $hash) === 1) {
    $computed = hash('sha256', $salt . $password . $salt);
    return hash_equals(strtolower($hash), strtolower($computed));
  }

  return false;
}

function issueSession(PDO $db, array $user, int $ttlHours): string {
  $ttl = max(1, $ttlHours);
  $tokenId = randomId('tok_', 24);
  $now = utcNow();
  $createdAt = $now->format('Y-m-d H:i:s.v');
  $expiresAt = $now->modify('+' . $ttl . ' hours')->format('Y-m-d H:i:s.v');

  $stmt = $db->prepare(
    'INSERT INTO sessions (token_id, user_id, email, role, payment_status, created_at, expires_at, is_revoked)
     VALUES (:token_id, :user_id, :email, :role, :payment_status, :created_at, :expires_at, 0)'
  );
  $stmt->execute([
    ':token_id' => $tokenId,
    ':user_id' => $user['id'],
    ':email' => $user['email'],
    ':role' => $user['role'],
    ':payment_status' => $user['payment_status'],
    ':created_at' => $createdAt,
    ':expires_at' => $expiresAt,
  ]);

  return $tokenId;
}

function authTokenFromRequest(array $payload): string {
  $authHeader = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
  if (stripos($authHeader, 'Bearer ') === 0) {
    return trim(substr($authHeader, 7));
  }
  return trim((string)($payload['auth_token'] ?? ''));
}

function getSessionUser(PDO $db, string $token): ?array {
  if ($token === '') {
    return null;
  }

  $stmt = $db->prepare(
    'SELECT
       s.token_id,
       s.user_id,
       s.role AS session_role,
       s.payment_status AS session_payment_status,
       s.is_revoked,
       s.expires_at,
       u.id,
       u.email,
       u.name,
       u.role,
       u.payment_status,
       u.is_active,
       u.created_at,
       u.updated_at,
       u.last_login
     FROM sessions s
     INNER JOIN users u ON u.id = s.user_id
     WHERE s.token_id = :token
     LIMIT 1'
  );
  $stmt->execute([':token' => $token]);
  $row = $stmt->fetch();
  if (!$row) {
    return null;
  }

  if ((int)$row['is_revoked'] === 1) {
    return null;
  }
  if ((int)$row['is_active'] !== 1) {
    return null;
  }

  $now = utcNow();
  $exp = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', (string)$row['expires_at'], new DateTimeZone('UTC'));
  if (!$exp) {
    $exp = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string)$row['expires_at'], new DateTimeZone('UTC'));
  }
  if (!$exp || $exp < $now) {
    return null;
  }

  return $row;
}

function requireAuth(PDO $db, array $payload): array {
  $token = authTokenFromRequest($payload);
  if ($token === '') {
    fail('Unauthorized: Login diperlukan', 401);
  }
  $user = getSessionUser($db, $token);
  if (!$user) {
    fail('Unauthorized: Token tidak valid atau expired', 401);
  }
  $user['__token'] = $token;
  return $user;
}

function requireAdmin(array $currentUser): void {
  if (strtolower((string)$currentUser['role']) !== 'admin') {
    fail('Forbidden: Hanya admin', 403);
  }
}

function parseCsvRows(string $csv): array {
  $csv = trim($csv);
  if ($csv === '') {
    return [[], []];
  }

  $lines = preg_split('/\r\n|\n|\r/', $csv);
  if (!is_array($lines) || count($lines) < 2) {
    return [[], []];
  }

  $headers = str_getcsv((string)$lines[0]);
  $rows = [];
  for ($i = 1; $i < count($lines); $i++) {
    $line = (string)$lines[$i];
    if (trim($line) === '') {
      continue;
    }
    $rows[] = str_getcsv($line);
  }
  return [$headers, $rows];
}

function findHeaderIndex(array $headers, array $candidates): int {
  $normalized = array_map(static function ($h) {
    return strtolower(trim((string)$h));
  }, $headers);

  foreach ($candidates as $cand) {
    $needle = strtolower(trim((string)$cand));
    foreach ($normalized as $idx => $h) {
      if ($needle !== '' && strpos($h, $needle) !== false) {
        return (int)$idx;
      }
    }
  }
  return -1;
}

function numberFromCell(string $raw): float {
  $v = trim($raw);
  if ($v === '') {
    return 0.0;
  }
  $v = str_replace(['Rp', 'IDR', ' '], '', $v);
  $v = str_replace('%', '', $v);

  if (strpos($v, ',') !== false && strpos($v, '.') !== false) {
    if (strrpos($v, ',') > strrpos($v, '.')) {
      $v = str_replace('.', '', $v);
      $v = str_replace(',', '.', $v);
    } else {
      $v = str_replace(',', '', $v);
    }
  } elseif (strpos($v, ',') !== false) {
    $v = str_replace(',', '.', $v);
  }

  $v = preg_replace('/[^0-9.\-]/', '', $v);
  if (!is_string($v) || $v === '' || $v === '-' || $v === '.') {
    return 0.0;
  }
  return (float)$v;
}

function intFromCell(string $raw): int {
  return (int)round(numberFromCell($raw));
}

function detectLevel(array $headers): string {
  $adIdx = findHeaderIndex($headers, ['Nama Iklan', 'Ad name']);
  if ($adIdx >= 0) {
    return 'ad';
  }
  $adsetIdx = findHeaderIndex($headers, ['Nama Set Iklan', 'Ad Set Name']);
  if ($adsetIdx >= 0) {
    return 'adset';
  }
  return 'campaign';
}

function parseMetaCsvPayload(string $csv, string $levelHint): array {
  [$headers, $rows] = parseCsvRows($csv);
  if (count($headers) === 0) {
    return ['level' => $levelHint, 'rows' => []];
  }

  $cMap = [
    'campaign' => ['Nama Kampanye', 'Campaign name'],
    'adset' => ['Nama Set Iklan', 'Ad Set Name'],
    'ad' => ['Nama Iklan', 'Ad name'],
    'spend' => ['Jumlah yang dibelanjakan (IDR)', 'Amount spent (IDR)', 'Amount spent'],
    'impressions' => ['Impresi', 'Impressions'],
    'ctr' => ['CTR (Rasio Klik Tayang Tautan)', 'CTR (Link Click-Through Rate)', 'CTR'],
    'results' => ['Hasil', 'Results', 'Pembelian', 'Purchases'],
    'revenue' => ['Nilai konversi pembelian', 'Purchase conversion value'],
    'roas' => ['ROAS (imbal hasil belanja iklan) pembelian', 'Purchase ROAS', 'ROAS'],
    'cpm' => ['CPM (Biaya Per 1.000 Tayangan) (IDR)', 'CPM (Cost per 1,000 Impressions)', 'CPM'],
    'reach' => ['Jangkauan', 'Reach'],
    'freq' => ['Frekuensi', 'Frequency'],
    'atc' => ['Tambahkan ke Keranjang', 'Add to Cart'],
    'cpa' => ['Biaya per Hasil', 'Cost per Result'],
    'date_start' => ['Awal pelaporan', 'Day', 'Date start'],
    'date_end' => ['Akhir pelaporan', 'Date stop', 'Date end'],
  ];

  $idx = [];
  foreach ($cMap as $k => $cand) {
    $idx[$k] = findHeaderIndex($headers, $cand);
  }

  $level = in_array($levelHint, ['campaign', 'adset', 'ad'], true) ? $levelHint : detectLevel($headers);

  $parsed = [];
  foreach ($rows as $line) {
    $campaignName = ($idx['campaign'] >= 0) ? trim((string)($line[$idx['campaign']] ?? '')) : '';
    $adsetName = ($idx['adset'] >= 0) ? trim((string)($line[$idx['adset']] ?? '')) : '';
    $adName = ($idx['ad'] >= 0) ? trim((string)($line[$idx['ad']] ?? '')) : '';

    $name = $level === 'ad' ? $adName : ($level === 'adset' ? $adsetName : $campaignName);
    if ($name === '') {
      $name = $adName !== '' ? $adName : ($adsetName !== '' ? $adsetName : $campaignName);
    }
    if ($name === '') {
      continue;
    }

    $parsed[] = [
      'campaign_name' => $campaignName,
      'adset_name' => $adsetName,
      'ad_name' => $adName,
      'name' => $name,
      'spend' => numberFromCell((string)($line[$idx['spend']] ?? '')),
      'impressions' => intFromCell((string)($line[$idx['impressions']] ?? '')),
      'ctr' => numberFromCell((string)($line[$idx['ctr']] ?? '')),
      'results' => numberFromCell((string)($line[$idx['results']] ?? '')),
      'revenue' => numberFromCell((string)($line[$idx['revenue']] ?? '')),
      'roas' => numberFromCell((string)($line[$idx['roas']] ?? '')),
      'cpm' => numberFromCell((string)($line[$idx['cpm']] ?? '')),
      'reach' => intFromCell((string)($line[$idx['reach']] ?? '')),
      'freq' => numberFromCell((string)($line[$idx['freq']] ?? '')),
      'atc' => numberFromCell((string)($line[$idx['atc']] ?? '')),
      'cpa' => numberFromCell((string)($line[$idx['cpa']] ?? '')),
      'date_start' => ($idx['date_start'] >= 0) ? trim((string)($line[$idx['date_start']] ?? '')) : '',
      'date_end' => ($idx['date_end'] >= 0) ? trim((string)($line[$idx['date_end']] ?? '')) : '',
    ];
  }

  return ['level' => $level, 'rows' => $parsed];
}

function snapshotEntities(PDO $db): array {
  $entities = [];

  $campaigns = $db->query('SELECT * FROM campaigns ORDER BY created_at DESC')->fetchAll();
  foreach ($campaigns as $r) {
    $m = [
      'spend' => (float)$r['spend'],
      'impressions' => (int)$r['impressions'],
      'ctr' => (float)$r['ctr'],
      'results' => (float)$r['results'],
      'revenue' => (float)$r['revenue'],
      'roas' => (float)$r['roas'],
      'cpm' => (float)$r['cpm'],
      'reach' => (int)$r['reach'],
      'freq' => (float)$r['freq'],
      'atc' => (float)$r['atc'],
      'cpa' => (float)$r['cpa'],
    ];
    $clicks = ($m['impressions'] > 0 && $m['ctr'] > 0) ? (int)round(($m['ctr'] / 100.0) * $m['impressions']) : 0;
    $atcRate = ($clicks > 0 && $m['atc'] > 0) ? ($m['atc'] / $clicks) * 100.0 : 0.0;
    $convRate = ($m['atc'] > 0 && $m['results'] > 0) ? ($m['results'] / $m['atc']) * 100.0 : 0.0;

    $entities[] = [
      'id' => (string)$r['id'],
      'level' => 'campaign',
      'name' => (string)$r['campaign_name'],
      'campaign_name' => (string)$r['campaign_name'],
      'adset_name' => '',
      'ad_name' => '',
      'date_start' => (string)$r['date_start'],
      'date_end' => (string)$r['date_end'],
      'metrics' => array_merge($m, [
        'clicks' => $clicks,
        'atcRate' => $atcRate,
        'conversionRate' => $convRate,
      ]),
      'status' => 'Monitor',
      'priority' => 'Monitor',
      'diagnosis' => '',
      'action' => '',
      'alerts' => [],
    ];
  }

  $adsets = $db->query('SELECT * FROM adsets ORDER BY created_at DESC')->fetchAll();
  foreach ($adsets as $r) {
    $m = [
      'spend' => (float)$r['spend'],
      'impressions' => (int)$r['impressions'],
      'ctr' => (float)$r['ctr'],
      'results' => (float)$r['results'],
      'revenue' => (float)$r['revenue'],
      'roas' => (float)$r['roas'],
      'cpm' => (float)$r['cpm'],
      'reach' => (int)$r['reach'],
      'freq' => (float)$r['freq'],
      'atc' => (float)$r['atc'],
      'cpa' => (float)$r['cpa'],
    ];
    $clicks = ($m['impressions'] > 0 && $m['ctr'] > 0) ? (int)round(($m['ctr'] / 100.0) * $m['impressions']) : 0;
    $atcRate = ($clicks > 0 && $m['atc'] > 0) ? ($m['atc'] / $clicks) * 100.0 : 0.0;
    $convRate = ($m['atc'] > 0 && $m['results'] > 0) ? ($m['results'] / $m['atc']) * 100.0 : 0.0;

    $entities[] = [
      'id' => (string)$r['id'],
      'level' => 'adset',
      'name' => (string)$r['adset_name'],
      'campaign_name' => (string)$r['campaign_name'],
      'adset_name' => (string)$r['adset_name'],
      'ad_name' => '',
      'date_start' => (string)$r['date_start'],
      'date_end' => (string)$r['date_end'],
      'metrics' => array_merge($m, [
        'clicks' => $clicks,
        'atcRate' => $atcRate,
        'conversionRate' => $convRate,
      ]),
      'status' => 'Monitor',
      'priority' => 'Monitor',
      'diagnosis' => '',
      'action' => '',
      'alerts' => [],
    ];
  }

  $ads = $db->query('SELECT * FROM ads ORDER BY created_at DESC')->fetchAll();
  foreach ($ads as $r) {
    $m = [
      'spend' => (float)$r['spend'],
      'impressions' => (int)$r['impressions'],
      'ctr' => (float)$r['ctr'],
      'results' => (float)$r['results'],
      'revenue' => (float)$r['revenue'],
      'roas' => (float)$r['roas'],
      'cpm' => (float)$r['cpm'],
      'reach' => (int)$r['reach'],
      'freq' => (float)$r['freq'],
      'atc' => (float)$r['atc'],
      'cpa' => (float)$r['cpa'],
    ];
    $clicks = ($m['impressions'] > 0 && $m['ctr'] > 0) ? (int)round(($m['ctr'] / 100.0) * $m['impressions']) : 0;
    $atcRate = ($clicks > 0 && $m['atc'] > 0) ? ($m['atc'] / $clicks) * 100.0 : 0.0;
    $convRate = ($m['atc'] > 0 && $m['results'] > 0) ? ($m['results'] / $m['atc']) * 100.0 : 0.0;

    $entities[] = [
      'id' => (string)$r['id'],
      'level' => 'ad',
      'name' => (string)$r['ad_name'],
      'campaign_name' => (string)$r['campaign_name'],
      'adset_name' => (string)$r['adset_name'],
      'ad_name' => (string)$r['ad_name'],
      'date_start' => (string)$r['date_start'],
      'date_end' => (string)$r['date_end'],
      'metrics' => array_merge($m, [
        'clicks' => $clicks,
        'atcRate' => $atcRate,
        'conversionRate' => $convRate,
      ]),
      'status' => 'Monitor',
      'priority' => 'Monitor',
      'diagnosis' => '',
      'action' => '',
      'alerts' => [],
    ];
  }

  return $entities;
}

function buildKpi(PDO $db): array {
  $sources = ['campaigns', 'adsets', 'ads'];
  $activeTable = 'campaigns';
  foreach ($sources as $table) {
    $cnt = (int)$db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    if ($cnt > 0) {
      $activeTable = $table;
      break;
    }
  }

  $sum = $db->query("SELECT COALESCE(SUM(spend),0) AS total_spend, COALESCE(SUM(revenue),0) AS total_revenue FROM {$activeTable}")->fetch();
  $campaignCount = (int)$db->query('SELECT COUNT(*) FROM campaigns')->fetchColumn();
  $adsetCount = (int)$db->query('SELECT COUNT(*) FROM adsets')->fetchColumn();
  $adCount = (int)$db->query('SELECT COUNT(*) FROM ads')->fetchColumn();

  $spend = (float)($sum['total_spend'] ?? 0);
  $revenue = (float)($sum['total_revenue'] ?? 0);

  return [
    'total_spend' => $spend,
    'total_revenue' => $revenue,
    'roas_overall' => $spend > 0 ? ($revenue / $spend) : 0.0,
    'campaign_count' => $campaignCount,
    'adset_count' => $adsetCount,
    'ad_count' => $adCount,
    'urgent_count' => 0,
    'alert_count' => 0,
  ];
}

function tableHasColumn(PDO $db, string $table, string $column): bool {
  static $cache = [];
  $tableKey = strtolower($table);
  $columnKey = strtolower($column);
  if (isset($cache[$tableKey][$columnKey])) {
    return $cache[$tableKey][$columnKey];
  }

  $stmt = $db->prepare(
    'SELECT COUNT(*)
     FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = :table_name
       AND column_name = :column_name'
  );
  $stmt->execute([
    ':table_name' => $table,
    ':column_name' => $column,
  ]);
  $exists = ((int)$stmt->fetchColumn() > 0);
  if (!isset($cache[$tableKey])) {
    $cache[$tableKey] = [];
  }
  $cache[$tableKey][$columnKey] = $exists;
  return $exists;
}

$env = loadEnv();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$requestPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$uriPath = $requestPath;
if (strpos($uriPath, '/api/index.php') === 0) {
  $uriPath = substr($uriPath, strlen('/api/index.php'));
  if ($uriPath === '') {
    $uriPath = '/';
  }
}
if (strpos($uriPath, '/index.php') === 0) {
  $uriPath = substr($uriPath, strlen('/index.php'));
  if ($uriPath === '') {
    $uriPath = '/';
  }
}

if ($method === 'OPTIONS') {
  http_response_code(204);
  exit;
}

if ($uriPath === '/oauth/openai/login' || $uriPath === '/oauth/openai/logout' || $uriPath === '/oauth/openai/verify') {
  fail('OAuth endpoint is not available in PHP MySQL mode', 501);
}

$raw = file_get_contents('php://input') ?: '';
$payload = [];
if ($raw !== '') {
  $decoded = json_decode($raw, true);
  if (is_array($decoded)) {
    $payload = $decoded;
  }
}
if ($method === 'GET' && !empty($_GET)) {
  foreach ($_GET as $k => $v) {
    $payload[$k] = $v;
  }
}

try {
  $db = pdo($env);

  if ($method === 'GET' && $uriPath === '/health') {
    $db->query('SELECT 1');
    out(['ok' => true, 'service' => 'cpanel-php-mysql', 'message' => 'healthy']);
  }

  if ($method === 'POST' && $uriPath === '/auth/create-first-admin') {
    $name = trim((string)($payload['name'] ?? 'Admin'));
    $email = normalizeEmail((string)($payload['email'] ?? ''));
    $password = (string)($payload['password'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      fail('Format email tidak valid', 400);
    }
    if (strlen($password) < 8 || preg_match('/[0-9]/', $password) !== 1) {
      fail('Password minimal 8 karakter dan wajib mengandung angka', 400);
    }

    $adminCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    if ($adminCount > 0) {
      fail('Admin pertama sudah ada', 409);
    }

    $userId = randomId('usr_', 16);
    $salt = bin2hex(random_bytes(16));
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $now = utcNowMs();

    $stmt = $db->prepare(
      'INSERT INTO users (id, email, password_hash, salt, name, role, payment_status, created_at, updated_at, last_login, is_active)
       VALUES (:id, :email, :password_hash, :salt, :name, :role, :payment_status, :created_at, :updated_at, :last_login, 1)'
    );
    $stmt->execute([
      ':id' => $userId,
      ':email' => $email,
      ':password_hash' => $hash,
      ':salt' => $salt,
      ':name' => $name !== '' ? $name : 'Admin',
      ':role' => 'admin',
      ':payment_status' => 'LUNAS',
      ':created_at' => $now,
      ':updated_at' => $now,
      ':last_login' => null,
    ]);

    $user = $db->prepare('SELECT * FROM users WHERE id = :id');
    $user->execute([':id' => $userId]);
    $u = $user->fetch();
    $token = issueSession($db, $u, (int)envGet($env, 'AUTH_TOKEN_TTL_HOURS', '24'));

    out(['ok' => true, 'token' => $token, 'user' => userToPublic($u)]);
  }

  if ($method === 'POST' && $uriPath === '/auth/register') {
    $name = trim((string)($payload['name'] ?? ''));
    $email = normalizeEmail((string)($payload['email'] ?? ''));
    $password = (string)($payload['password'] ?? '');
    $wa = normalizeWa((string)($payload['whatsapp_number'] ?? ($payload['phone_number'] ?? '')));

    if (strlen($name) < 2) {
      fail('Nama minimal 2 karakter', 400);
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      fail('Format email tidak valid', 400);
    }
    if (strlen($password) < 8 || preg_match('/[0-9]/', $password) !== 1) {
      fail('Password minimal 8 karakter dan wajib mengandung angka', 400);
    }
    if ($wa === '' || strlen($wa) < 10 || strlen($wa) > 15 || strpos($wa, '62') !== 0) {
      fail('Nomor WhatsApp tidak valid', 400);
    }

    $exists = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $exists->execute([':email' => $email]);
    if ($exists->fetch()) {
      fail('Email sudah terdaftar', 409);
    }

    $userId = randomId('usr_', 16);
    $salt = bin2hex(random_bytes(16));
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $now = utcNowMs();

    $db->beginTransaction();
    $ins = $db->prepare(
      'INSERT INTO users (id, email, password_hash, salt, name, role, payment_status, created_at, updated_at, last_login, is_active)
       VALUES (:id, :email, :password_hash, :salt, :name, :role, :payment_status, :created_at, :updated_at, :last_login, 1)'
    );
    $ins->execute([
      ':id' => $userId,
      ':email' => $email,
      ':password_hash' => $hash,
      ':salt' => $salt,
      ':name' => $name,
      ':role' => 'user',
      ':payment_status' => 'NONE',
      ':created_at' => $now,
      ':updated_at' => $now,
      ':last_login' => null,
    ]);

    $cins = $db->prepare(
      'INSERT INTO user_contacts (user_id, email, phone_number, is_whatsapp_opt_in, updated_at)
       VALUES (:user_id, :email, :phone_number, 1, :updated_at)
       ON DUPLICATE KEY UPDATE
         email = VALUES(email),
         phone_number = VALUES(phone_number),
         is_whatsapp_opt_in = VALUES(is_whatsapp_opt_in),
         updated_at = VALUES(updated_at)'
    );
    $cins->execute([
      ':user_id' => $userId,
      ':email' => $email,
      ':phone_number' => $wa,
      ':updated_at' => $now,
    ]);
    $db->commit();

    $user = $db->prepare('SELECT * FROM users WHERE id = :id');
    $user->execute([':id' => $userId]);
    $u = $user->fetch();
    $token = issueSession($db, $u, (int)envGet($env, 'AUTH_TOKEN_TTL_HOURS', '24'));

    out(['ok' => true, 'token' => $token, 'user' => userToPublic($u)]);
  }

  if ($method === 'POST' && $uriPath === '/auth/login') {
    $email = normalizeEmail((string)($payload['email'] ?? ''));
    $password = (string)($payload['password'] ?? '');

    if ($email === '' || $password === '') {
      fail('Email dan password wajib diisi', 400);
    }

    $stmt = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();
    if (!$user) {
      fail('Email atau password salah', 401);
    }
    if ((int)$user['is_active'] !== 1) {
      fail('Akun tidak aktif', 403);
    }

    if (!verifyPasswordCompat($password, (string)$user['password_hash'], (string)$user['salt'])) {
      fail('Email atau password salah', 401);
    }

    $upd = $db->prepare('UPDATE users SET last_login = :last_login, updated_at = :updated_at WHERE id = :id');
    $now = utcNowMs();
    $upd->execute([':last_login' => $now, ':updated_at' => $now, ':id' => $user['id']]);

    $stmt = $db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $user['id']]);
    $fresh = $stmt->fetch();
    $token = issueSession($db, $fresh, (int)envGet($env, 'AUTH_TOKEN_TTL_HOURS', '24'));

    out(['ok' => true, 'token' => $token, 'user' => userToPublic($fresh)]);
  }

  if ($method === 'POST' && $uriPath === '/auth/verify') {
    $token = authTokenFromRequest($payload);
    if ($token === '') {
      fail('Token tidak ditemukan', 401);
    }
    $current = getSessionUser($db, $token);
    if (!$current) {
      fail('Token tidak valid atau expired', 401);
    }
    out(['ok' => true, 'user' => userToPublic($current)]);
  }

  if ($method === 'POST' && $uriPath === '/auth/logout') {
    $current = requireAuth($db, $payload);
    $stmt = $db->prepare('UPDATE sessions SET is_revoked = 1 WHERE token_id = :token_id');
    $stmt->execute([':token_id' => $current['__token']]);
    out(['ok' => true]);
  }

  $currentUser = null;
  if (strpos($uriPath, '/admin/') === 0 || strpos($uriPath, '/user/') === 0 || strpos($uriPath, '/app/') === 0) {
    $currentUser = requireAuth($db, $payload);
  }

  if ($method === 'GET' && $uriPath === '/admin/users') {
    requireAdmin($currentUser);

    $search = trim((string)($payload['search'] ?? ''));
    $role = trim((string)($payload['role'] ?? ''));
    $payment = trim((string)($payload['payment_status'] ?? ''));

    $sql = 'SELECT * FROM users WHERE 1=1';
    $params = [];

    if ($search !== '') {
      $sql .= ' AND (email LIKE :search OR name LIKE :search)';
      $params[':search'] = '%' . $search . '%';
    }
    if ($role === 'admin' || $role === 'user') {
      $sql .= ' AND role = :role';
      $params[':role'] = $role;
    }
    if (in_array($payment, ['LUNAS', 'PENDING', 'NONE'], true)) {
      $sql .= ' AND payment_status = :payment_status';
      $params[':payment_status'] = $payment;
    }

    $sql .= ' ORDER BY created_at DESC LIMIT 500';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $users = array_map('userToPublic', $rows);

    out(['ok' => true, 'users' => $users]);
  }

  if ($method === 'POST' && $uriPath === '/admin/users') {
    requireAdmin($currentUser);

    $name = trim((string)($payload['name'] ?? ''));
    $email = normalizeEmail((string)($payload['email'] ?? ''));
    $password = (string)($payload['password'] ?? '');
    $role = strtolower(trim((string)($payload['role'] ?? 'user')));
    $payment = strtoupper(trim((string)($payload['payment_status'] ?? 'NONE')));

    if ($name === '' || strlen($name) < 2) {
      fail('Nama minimal 2 karakter', 400);
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      fail('Format email tidak valid', 400);
    }
    if (strlen($password) < 8 || preg_match('/[0-9]/', $password) !== 1) {
      fail('Password minimal 8 karakter dan wajib mengandung angka', 400);
    }
    if (!in_array($role, ['admin', 'user'], true)) {
      $role = 'user';
    }
    if (!in_array($payment, ['LUNAS', 'PENDING', 'NONE'], true)) {
      $payment = 'NONE';
    }

    $exists = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $exists->execute([':email' => $email]);
    if ($exists->fetch()) {
      fail('Email sudah terdaftar', 409);
    }

    $id = randomId('usr_', 16);
    $salt = bin2hex(random_bytes(16));
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $now = utcNowMs();

    $stmt = $db->prepare(
      'INSERT INTO users (id, email, password_hash, salt, name, role, payment_status, created_at, updated_at, last_login, is_active)
       VALUES (:id, :email, :password_hash, :salt, :name, :role, :payment_status, :created_at, :updated_at, :last_login, 1)'
    );
    $stmt->execute([
      ':id' => $id,
      ':email' => $email,
      ':password_hash' => $hash,
      ':salt' => $salt,
      ':name' => $name,
      ':role' => $role,
      ':payment_status' => $payment,
      ':created_at' => $now,
      ':updated_at' => $now,
      ':last_login' => null,
    ]);

    out(['ok' => true, 'user_id' => $id]);
  }

  if ($method === 'GET' && $uriPath === '/admin/stats') {
    requireAdmin($currentUser);

    $total = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $admins = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    $lunas = (int)$db->query("SELECT COUNT(*) FROM users WHERE payment_status='LUNAS'")->fetchColumn();
    $active = (int)$db->query('SELECT COUNT(*) FROM users WHERE is_active=1')->fetchColumn();

    $summaryRows = $db->query(
      "SELECT channel, status, COUNT(*) AS total
       FROM notification_logs
       GROUP BY channel, status"
    )->fetchAll();

    $summary = [
      'email_sent' => 0,
      'email_failed' => 0,
      'whatsapp_sent' => 0,
      'whatsapp_pending' => 0,
      'whatsapp_retry' => 0,
    ];

    foreach ($summaryRows as $r) {
      $channel = strtolower((string)$r['channel']);
      $status = strtolower((string)$r['status']);
      $n = (int)$r['total'];
      if ($channel === 'email' && $status === 'sent') {
        $summary['email_sent'] += $n;
      } elseif ($channel === 'email' && $status === 'failed') {
        $summary['email_failed'] += $n;
      } elseif ($channel === 'whatsapp' && $status === 'sent') {
        $summary['whatsapp_sent'] += $n;
      } elseif ($channel === 'whatsapp' && $status === 'queued') {
        $summary['whatsapp_pending'] += $n;
      } elseif ($channel === 'whatsapp' && $status === 'retry') {
        $summary['whatsapp_retry'] += $n;
      }
    }

    $logs = $db->query('SELECT id, channel, recipient, status, error_message, created_at FROM notification_logs ORDER BY created_at DESC LIMIT 20')->fetchAll();

    out([
      'ok' => true,
      'stats' => [
        'total' => $total,
        'admins' => $admins,
        'lunas' => $lunas,
        'active' => $active,
        'notification' => [
          'summary' => $summary,
          'recent_logs' => $logs,
        ],
      ],
    ]);
  }

  if ($method === 'POST' && $uriPath === '/admin/notifications') {
    requireAdmin($currentUser);

    $maxItems = (int)($payload['max_items'] ?? 10);
    if ($maxItems < 1) {
      $maxItems = 1;
    }
    if ($maxItems > 100) {
      $maxItems = 100;
    }

    if (!isTruthy($payload['process_queue'] ?? false)) {
      out(['ok' => true, 'data' => ['processed' => 0, 'sent' => 0, 'retried' => 0, 'failed' => 0]]);
    }

    $stmt = $db->prepare(
      "SELECT queue_id
       FROM whatsapp_queue
       WHERE status IN ('pending', 'retry')
         AND next_retry_at <= :now
       ORDER BY next_retry_at ASC
       LIMIT {$maxItems}"
    );
    $now = utcNowMs();
    $stmt->execute([':now' => $now]);
    $items = $stmt->fetchAll();

    $processed = 0;
    $sent = 0;
    $retried = 0;
    $failed = 0;

    foreach ($items as $it) {
      $processed++;
      $qid = (string)$it['queue_id'];
      $upd = $db->prepare(
        "UPDATE whatsapp_queue
         SET status = 'sent', attempt_count = attempt_count + 1, updated_at = :updated_at
         WHERE queue_id = :queue_id"
      );
      $upd->execute([':updated_at' => utcNowMs(), ':queue_id' => $qid]);
      $sent++;
    }

    out(['ok' => true, 'data' => ['processed' => $processed, 'sent' => $sent, 'retried' => $retried, 'failed' => $failed]]);
  }

  if ($method === 'POST' && $uriPath === '/admin/user') {
    requireAdmin($currentUser);

    $userId = trim((string)($payload['user_id'] ?? ''));
    if ($userId === '') {
      fail('user_id wajib diisi', 400);
    }

    $fields = [];
    $params = [':id' => $userId];

    if (array_key_exists('name', $payload)) {
      $name = trim((string)$payload['name']);
      if ($name === '') {
        fail('Nama tidak boleh kosong', 400);
      }
      $fields[] = 'name = :name';
      $params[':name'] = $name;
    }

    if (array_key_exists('role', $payload)) {
      $role = strtolower(trim((string)$payload['role']));
      if (!in_array($role, ['admin', 'user'], true)) {
        fail('Role tidak valid', 400);
      }
      $fields[] = 'role = :role';
      $params[':role'] = $role;
    }

    if (array_key_exists('payment_status', $payload)) {
      $payment = strtoupper(trim((string)$payload['payment_status']));
      if (!in_array($payment, ['LUNAS', 'PENDING', 'NONE'], true)) {
        fail('payment_status tidak valid', 400);
      }
      $fields[] = 'payment_status = :payment_status';
      $params[':payment_status'] = $payment;
    }

    if (array_key_exists('is_active', $payload)) {
      $fields[] = 'is_active = :is_active';
      $params[':is_active'] = isTruthy($payload['is_active']) ? 1 : 0;
    }

    if (count($fields) === 0) {
      fail('Tidak ada data yang diupdate', 400);
    }

    $fields[] = 'updated_at = :updated_at';
    $params[':updated_at'] = utcNowMs();

    $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    out(['ok' => true]);
  }

  if ($method === 'POST' && $uriPath === '/admin/user/delete') {
    requireAdmin($currentUser);
    $userId = trim((string)($payload['user_id'] ?? ''));
    if ($userId === '') {
      fail('user_id wajib diisi', 400);
    }
    if ($userId === (string)$currentUser['id']) {
      fail('Admin tidak boleh menghapus dirinya sendiri', 400);
    }

    $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    out(['ok' => true]);
  }

  if ($method === 'POST' && $uriPath === '/admin/user/reset-password') {
    requireAdmin($currentUser);
    $userId = trim((string)($payload['user_id'] ?? ''));
    $newPassword = (string)($payload['new_password'] ?? '12345678');
    if ($userId === '') {
      fail('user_id wajib diisi', 400);
    }
    if (strlen($newPassword) < 8 || preg_match('/[0-9]/', $newPassword) !== 1) {
      fail('Password minimal 8 karakter dan wajib mengandung angka', 400);
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    $salt = bin2hex(random_bytes(16));
    $stmt = $db->prepare('UPDATE users SET password_hash = :hash, salt = :salt, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
      ':hash' => $hash,
      ':salt' => $salt,
      ':updated_at' => utcNowMs(),
      ':id' => $userId,
    ]);

    out(['ok' => true]);
  }

  if ($method === 'POST' && $uriPath === '/admin/users/bulk-status') {
    requireAdmin($currentUser);
    $ids = $payload['user_ids'] ?? [];
    if (!is_array($ids) || count($ids) === 0) {
      fail('user_ids wajib diisi', 400);
    }

    $payment = strtoupper(trim((string)($payload['payment_status'] ?? '')));
    $activeProvided = array_key_exists('is_active', $payload);

    $set = [];
    $params = [':updated_at' => utcNowMs()];
    if (in_array($payment, ['LUNAS', 'PENDING', 'NONE'], true)) {
      $set[] = 'payment_status = :payment_status';
      $params[':payment_status'] = $payment;
    }
    if ($activeProvided) {
      $set[] = 'is_active = :is_active';
      $params[':is_active'] = isTruthy($payload['is_active']) ? 1 : 0;
    }
    if (count($set) === 0) {
      fail('Tidak ada field status untuk diupdate', 400);
    }

    $set[] = 'updated_at = :updated_at';

    $placeholders = [];
    foreach ($ids as $i => $id) {
      $ph = ':id' . $i;
      $placeholders[] = $ph;
      $params[$ph] = (string)$id;
    }

    $sql = 'UPDATE users SET ' . implode(', ', $set) . ' WHERE id IN (' . implode(', ', $placeholders) . ')';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    out(['ok' => true, 'updated' => $stmt->rowCount()]);
  }

  if ($method === 'GET' && $uriPath === '/user/profile') {
    out(['ok' => true, 'user' => userToPublic($currentUser)]);
  }

  if ($method === 'POST' && $uriPath === '/user/profile') {
    $name = trim((string)($payload['name'] ?? ''));
    if ($name === '') {
      fail('Nama tidak boleh kosong', 400);
    }

    $stmt = $db->prepare('UPDATE users SET name = :name, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
      ':name' => $name,
      ':updated_at' => utcNowMs(),
      ':id' => $currentUser['id'],
    ]);

    $ref = $db->prepare('SELECT * FROM users WHERE id = :id');
    $ref->execute([':id' => $currentUser['id']]);
    $fresh = $ref->fetch();

    out(['ok' => true, 'user' => userToPublic($fresh)]);
  }

  if ($method === 'POST' && $uriPath === '/user/change-password') {
    $oldPassword = (string)($payload['old_password'] ?? '');
    $newPassword = (string)($payload['new_password'] ?? '');

    if ($oldPassword === '' || $newPassword === '') {
      fail('old_password dan new_password wajib diisi', 400);
    }
    if (strlen($newPassword) < 8 || preg_match('/[0-9]/', $newPassword) !== 1) {
      fail('Password baru minimal 8 karakter dan wajib mengandung angka', 400);
    }

    if (!verifyPasswordCompat($oldPassword, (string)$currentUser['password_hash'], (string)$currentUser['salt'])) {
      fail('Password lama salah', 400);
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    $salt = bin2hex(random_bytes(16));
    $stmt = $db->prepare('UPDATE users SET password_hash = :hash, salt = :salt, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
      ':hash' => $hash,
      ':salt' => $salt,
      ':updated_at' => utcNowMs(),
      ':id' => $currentUser['id'],
    ]);

    out(['ok' => true]);
  }

  if ($method === 'GET' && $uriPath === '/app/snapshot') {
    $entities = snapshotEntities($db);
    $thresholdsRaw = $db->query('SELECT metric_key, enabled, rule_type, value, label FROM thresholds ORDER BY metric_key ASC')->fetchAll();
    $thresholds = [];
    foreach ($thresholdsRaw as $t) {
      $thresholds[] = [
        'metric_key' => (string)$t['metric_key'],
        'enabled' => ((int)$t['enabled'] === 1) ? 'true' : 'false',
        'rule_type' => (string)$t['rule_type'],
        'value' => (float)$t['value'],
        'label' => (string)$t['label'],
      ];
    }

    $notesSql = tableHasColumn($db, 'notes', 'id')
      ? 'SELECT id, entity_level, entity_name, note_text, updated_at FROM notes ORDER BY updated_at DESC'
      : 'SELECT entity_level, entity_name, note_text, updated_at FROM notes ORDER BY updated_at DESC';
    $notes = $db->query($notesSql)->fetchAll();
    $settings = $db->query('SELECT key_name, key_value FROM settings ORDER BY key_name ASC')->fetchAll();
    $importLogs = [];
    if (tableHasColumn($db, 'import_logs', 'id')) {
      $importLogs = $db->query('SELECT id, import_batch_id, level, file_name, row_count, imported_at, status, message FROM import_logs ORDER BY imported_at DESC LIMIT 100')->fetchAll();
    }

    out([
      'ok' => true,
      'data' => [
        'kpi' => buildKpi($db),
        'entities' => $entities,
        'hierarchy' => new stdClass(),
        'thresholds' => $thresholds,
        'notes' => $notes,
        'settings' => $settings,
        'import_logs' => $importLogs,
      ],
    ]);
  }

  if ($method === 'POST' && $uriPath === '/app/import') {
    requireAdmin($currentUser);

    $level = strtolower(trim((string)($payload['level'] ?? '')));
    if (!in_array($level, ['campaign', 'adset', 'ad'], true)) {
      fail('Level import tidak valid', 400);
    }

    $fileType = strtolower(trim((string)($payload['file_type'] ?? 'csv')));
    if ($fileType === 'xlsx') {
      fail('Import XLSX belum didukung di mode MySQL PHP. Gunakan CSV export dari Ads Manager.', 400);
    }

    $csv = (string)($payload['csv_text'] ?? '');
    if (trim($csv) === '') {
      fail('csv_text wajib diisi', 400);
    }

    $parsed = parseMetaCsvPayload($csv, $level);
    $rows = $parsed['rows'];
    if (count($rows) === 0) {
      fail('Tidak ada baris valid untuk diimport', 400);
    }

    $batchId = randomId('batch_', 8);
    $periodLabel = trim((string)($payload['period_label'] ?? ''));
    $now = utcNowMs();

    $table = $level === 'campaign' ? 'campaigns' : ($level === 'adset' ? 'adsets' : 'ads');

    $db->beginTransaction();
    $db->exec("DELETE FROM {$table}");

    if ($level === 'campaign') {
      $stmt = $db->prepare(
        'INSERT INTO campaigns (id, import_batch_id, period_label, campaign_name, spend, impressions, ctr, results, revenue, roas, cpm, reach, freq, atc, cpa, date_start, date_end, created_at)
         VALUES (:id, :import_batch_id, :period_label, :campaign_name, :spend, :impressions, :ctr, :results, :revenue, :roas, :cpm, :reach, :freq, :atc, :cpa, :date_start, :date_end, :created_at)'
      );

      foreach ($rows as $i => $r) {
        $id = substr(hash('sha256', $batchId . '|' . $i . '|' . $r['name']), 0, 64);
        $stmt->execute([
          ':id' => $id,
          ':import_batch_id' => $batchId,
          ':period_label' => $periodLabel,
          ':campaign_name' => (string)($r['campaign_name'] !== '' ? $r['campaign_name'] : $r['name']),
          ':spend' => (float)$r['spend'],
          ':impressions' => (int)$r['impressions'],
          ':ctr' => (float)$r['ctr'],
          ':results' => (float)$r['results'],
          ':revenue' => (float)$r['revenue'],
          ':roas' => (float)$r['roas'],
          ':cpm' => (float)$r['cpm'],
          ':reach' => (int)$r['reach'],
          ':freq' => (float)$r['freq'],
          ':atc' => (float)$r['atc'],
          ':cpa' => (float)$r['cpa'],
          ':date_start' => (string)$r['date_start'],
          ':date_end' => (string)$r['date_end'],
          ':created_at' => $now,
        ]);
      }
    } elseif ($level === 'adset') {
      $stmt = $db->prepare(
        'INSERT INTO adsets (id, import_batch_id, period_label, campaign_name, adset_name, spend, impressions, ctr, results, revenue, roas, cpm, reach, freq, atc, cpa, date_start, date_end, created_at)
         VALUES (:id, :import_batch_id, :period_label, :campaign_name, :adset_name, :spend, :impressions, :ctr, :results, :revenue, :roas, :cpm, :reach, :freq, :atc, :cpa, :date_start, :date_end, :created_at)'
      );

      foreach ($rows as $i => $r) {
        $id = substr(hash('sha256', $batchId . '|' . $i . '|' . $r['name']), 0, 64);
        $stmt->execute([
          ':id' => $id,
          ':import_batch_id' => $batchId,
          ':period_label' => $periodLabel,
          ':campaign_name' => (string)$r['campaign_name'],
          ':adset_name' => (string)($r['adset_name'] !== '' ? $r['adset_name'] : $r['name']),
          ':spend' => (float)$r['spend'],
          ':impressions' => (int)$r['impressions'],
          ':ctr' => (float)$r['ctr'],
          ':results' => (float)$r['results'],
          ':revenue' => (float)$r['revenue'],
          ':roas' => (float)$r['roas'],
          ':cpm' => (float)$r['cpm'],
          ':reach' => (int)$r['reach'],
          ':freq' => (float)$r['freq'],
          ':atc' => (float)$r['atc'],
          ':cpa' => (float)$r['cpa'],
          ':date_start' => (string)$r['date_start'],
          ':date_end' => (string)$r['date_end'],
          ':created_at' => $now,
        ]);
      }
    } else {
      $stmt = $db->prepare(
        'INSERT INTO ads (id, import_batch_id, period_label, campaign_name, adset_name, ad_name, spend, impressions, ctr, results, revenue, roas, cpm, reach, freq, atc, cpa, date_start, date_end, created_at)
         VALUES (:id, :import_batch_id, :period_label, :campaign_name, :adset_name, :ad_name, :spend, :impressions, :ctr, :results, :revenue, :roas, :cpm, :reach, :freq, :atc, :cpa, :date_start, :date_end, :created_at)'
      );

      foreach ($rows as $i => $r) {
        $id = substr(hash('sha256', $batchId . '|' . $i . '|' . $r['name']), 0, 64);
        $stmt->execute([
          ':id' => $id,
          ':import_batch_id' => $batchId,
          ':period_label' => $periodLabel,
          ':campaign_name' => (string)$r['campaign_name'],
          ':adset_name' => (string)$r['adset_name'],
          ':ad_name' => (string)($r['ad_name'] !== '' ? $r['ad_name'] : $r['name']),
          ':spend' => (float)$r['spend'],
          ':impressions' => (int)$r['impressions'],
          ':ctr' => (float)$r['ctr'],
          ':results' => (float)$r['results'],
          ':revenue' => (float)$r['revenue'],
          ':roas' => (float)$r['roas'],
          ':cpm' => (float)$r['cpm'],
          ':reach' => (int)$r['reach'],
          ':freq' => (float)$r['freq'],
          ':atc' => (float)$r['atc'],
          ':cpa' => (float)$r['cpa'],
          ':date_start' => (string)$r['date_start'],
          ':date_end' => (string)$r['date_end'],
          ':created_at' => $now,
        ]);
      }
    }

    $logStmt = $db->prepare(
      'INSERT INTO import_logs (import_batch_id, level, file_name, row_count, imported_at, status, message)
       VALUES (:import_batch_id, :level, :file_name, :row_count, :imported_at, :status, :message)'
    );
    $logStmt->execute([
      ':import_batch_id' => $batchId,
      ':level' => $level,
      ':file_name' => (string)($payload['file_name'] ?? ('import_' . $level . '.csv')),
      ':row_count' => count($rows),
      ':imported_at' => $now,
      ':status' => 'ok',
      ':message' => null,
    ]);

    $db->commit();

    out(['ok' => true, 'import_batch_id' => $batchId, 'row_count' => count($rows), 'warnings' => []]);
  }

  if ($method === 'POST' && $uriPath === '/app/save-note') {
    $entityLevel = strtolower(trim((string)($payload['entity_level'] ?? '')));
    $entityName = trim((string)($payload['entity_name'] ?? ''));
    $noteText = trim((string)($payload['note_text'] ?? ''));

    if (!in_array($entityLevel, ['campaign', 'adset', 'ad'], true)) {
      fail('entity_level tidak valid', 400);
    }
    if ($entityName === '') {
      fail('entity_name wajib diisi', 400);
    }

    $id = $entityLevel . '::' . $entityName;
    $stmt = $db->prepare(
      'INSERT INTO notes (id, entity_level, entity_name, note_text, updated_at)
       VALUES (:id, :entity_level, :entity_name, :note_text, :updated_at)
       ON DUPLICATE KEY UPDATE
         note_text = VALUES(note_text),
         updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
      ':id' => $id,
      ':entity_level' => $entityLevel,
      ':entity_name' => $entityName,
      ':note_text' => $noteText,
      ':updated_at' => utcNowMs(),
    ]);

    out(['ok' => true]);
  }

  if ($method === 'POST' && $uriPath === '/app/ai') {
    fail('AI endpoint belum diaktifkan di mode MySQL PHP', 501);
  }

  fail('Route not found', 404);
} catch (PDOException $e) {
  fail('Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
  fail($e->getMessage() !== '' ? $e->getMessage() : 'Internal server error', 500);
}
