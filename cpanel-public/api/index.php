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
  
  // Clear any cached prepared statements after schema changes
  try {
    $db->exec('FLUSH TABLES');
  } catch (Exception $e) {
    // Ignore flush errors, connection is still valid
  }
  
  return $db;
}

function ensureNotificationTables(PDO $db): void {
  static $ensured = false;
  if ($ensured) {
    return;
  }

  $db->exec(
    "CREATE TABLE IF NOT EXISTS user_contacts (
      user_id VARCHAR(64) NOT NULL,
      email VARCHAR(191) NOT NULL,
      phone_number VARCHAR(32) NOT NULL,
      is_whatsapp_opt_in TINYINT(1) NOT NULL DEFAULT 1,
      updated_at DATETIME(3) NOT NULL,
      PRIMARY KEY (user_id),
      KEY idx_user_contacts_phone (phone_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  );

  $db->exec(
    "CREATE TABLE IF NOT EXISTS notification_logs (
      id VARCHAR(64) NOT NULL,
      event_type VARCHAR(64) NOT NULL,
      channel ENUM('email','whatsapp') NOT NULL,
      recipient VARCHAR(191) NOT NULL,
      status ENUM('sent','failed','queued','retry') NOT NULL,
      attempt INT NOT NULL DEFAULT 1,
      provider VARCHAR(64) NOT NULL,
      http_status VARCHAR(16) DEFAULT NULL,
      error_message VARCHAR(500) DEFAULT NULL,
      response_excerpt TEXT,
      queue_id VARCHAR(64) DEFAULT NULL,
      user_id VARCHAR(64) DEFAULT NULL,
      created_at DATETIME(3) NOT NULL,
      updated_at DATETIME(3) NOT NULL,
      PRIMARY KEY (id),
      KEY idx_notification_logs_created_at (created_at),
      KEY idx_notification_logs_channel_status (channel,status),
      KEY idx_notification_logs_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  );

  $db->exec(
    "CREATE TABLE IF NOT EXISTS whatsapp_queue (
      queue_id VARCHAR(64) NOT NULL,
      user_id VARCHAR(64) DEFAULT NULL,
      email VARCHAR(191) DEFAULT NULL,
      phone_number VARCHAR(32) NOT NULL,
      message_type VARCHAR(64) NOT NULL,
      message_payload LONGTEXT,
      status ENUM('pending','retry','sent','failed') NOT NULL DEFAULT 'pending',
      attempt_count INT NOT NULL DEFAULT 0,
      max_attempts INT NOT NULL DEFAULT 3,
      next_retry_at DATETIME(3) NOT NULL,
      last_error VARCHAR(500) DEFAULT NULL,
      provider_message_id VARCHAR(128) DEFAULT NULL,
      created_at DATETIME(3) NOT NULL,
      updated_at DATETIME(3) NOT NULL,
      PRIMARY KEY (queue_id),
      KEY idx_whatsapp_queue_status_retry (status,next_retry_at),
      KEY idx_whatsapp_queue_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  );

  $ensured = true;
}

function randomId(string $prefix, int $bytes = 16): string {
  return $prefix . bin2hex(random_bytes($bytes));
}

function normalizeEmail(string $email): string {
  return strtolower(trim($email));
}

function getDomain(): string {
  $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  return $protocol . '://' . $host;
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
    'mailketing_list_id' => (string)($u['mailketing_list_id'] ?? ''),
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

  $insertSessionStmt = $db->prepare(
    'INSERT INTO sessions (token_id, user_id, email, role, payment_status, created_at, expires_at, is_revoked)
     VALUES (:token_id, :user_id, :email, :role, :payment_status, :created_at, :expires_at, 0)'
  );
  $insertSessionStmt->execute([
    ':token_id' => $tokenId,
    ':user_id' => $user['id'],
    ':email' => $user['email'],
    ':role' => $user['role'],
    ':payment_status' => $user['payment_status'],
    ':created_at' => $createdAt,
    ':expires_at' => $expiresAt,
  ]);
  $insertSessionStmt = null;

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

  $getSessionStmt = $db->prepare(
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
  $getSessionStmt->execute([':token' => $token]);
  $row = $getSessionStmt->fetch();
  $getSessionStmt = null;
  
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

function starsenderDelaySeconds(array $env): int {
  $delayMs = (int)envGet($env, 'NOTIFICATION_RETRY_DELAY_MS', '1200');
  if ($delayMs < 1000) {
    $delayMs = 1000;
  }
  return (int)max(1, ceil($delayMs / 1000));
}

function sendWhatsappViaStarsender(array $env, array $queueItem): array {
  $apiUrl = trim(envGet($env, 'STARSENDER_API_URL', 'https://api.starsender.online/api/send'));
  $apiKey = trim(envGet($env, 'STARSENDER_API_KEY', ''));
  $timeoutMs = (int)envGet($env, 'STARSENDER_TIMEOUT_MS', '15000');
  if ($timeoutMs < 1000) {
    $timeoutMs = 15000;
  }

  if ($apiKey === '') {
    return [
      'ok' => false,
      'http_status' => 0,
      'provider_message_id' => '',
      'error' => 'STARSENDER_API_KEY belum dikonfigurasi',
      'response_excerpt' => '',
    ];
  }

  $to = trim((string)($queueItem['phone_number'] ?? ''));
  if ($to === '') {
    return [
      'ok' => false,
      'http_status' => 0,
      'provider_message_id' => '',
      'error' => 'Nomor WhatsApp kosong',
      'response_excerpt' => '',
    ];
  }

  $payload = [];
  $rawPayload = (string)($queueItem['message_payload'] ?? '');
  if ($rawPayload !== '') {
    $decoded = json_decode($rawPayload, true);
    if (is_array($decoded)) {
      $payload = $decoded;
    }
  }

  $messageType = strtolower(trim((string)($payload['messageType'] ?? 'text')));
  if (!in_array($messageType, ['text', 'media'], true)) {
    $messageType = 'text';
  }
  $body = trim((string)($payload['body'] ?? ($payload['message'] ?? '')));
  $file = trim((string)($payload['file'] ?? ''));
  if ($messageType === 'text' && $body === '') {
    $body = 'Notifikasi MATIQ';
  }
  if ($messageType === 'media' && $file === '') {
    return [
      'ok' => false,
      'http_status' => 0,
      'provider_message_id' => '',
      'error' => 'messageType media membutuhkan field file URL',
      'response_excerpt' => '',
    ];
  }

  $requestBody = [
    'messageType' => $messageType,
    'to' => $to,
  ];
  if ($body !== '') {
    $requestBody['body'] = $body;
  }
  if ($file !== '') {
    $requestBody['file'] = $file;
  }
  if (isset($payload['delay'])) {
    $requestBody['delay'] = (int)$payload['delay'];
  }
  if (isset($payload['schedule'])) {
    $requestBody['schedule'] = (int)$payload['schedule'];
  }

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT_MS => $timeoutMs,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($requestBody, JSON_UNESCAPED_SLASHES),
    CURLOPT_HTTPHEADER => [
      'Content-Type:application/json',
      'Authorization: ' . $apiKey,
    ],
  ]);

  $responseRaw = curl_exec($ch);
  $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);

  $decoded = [];
  if (is_string($responseRaw) && $responseRaw !== '') {
    $json = json_decode($responseRaw, true);
    if (is_array($json)) {
      $decoded = $json;
    }
  }

  $providerMessageId = '';
  if (isset($decoded['data']) && is_array($decoded['data'])) {
    $providerMessageId = (string)($decoded['data']['id'] ?? ($decoded['data']['messageId'] ?? ''));
  }

  $success = ($httpStatus >= 200 && $httpStatus < 300 && ($decoded['success'] ?? false) === true);
  $responseMessage = (string)($decoded['message'] ?? '');
  $responseExcerpt = is_string($responseRaw) ? substr($responseRaw, 0, 450) : '';
  $err = '';
  if (!$success) {
    if ($curlError !== '') {
      $err = 'cURL: ' . $curlError;
    } elseif ($responseMessage !== '') {
      $err = $responseMessage;
    } else {
      $err = 'Starsender request gagal';
    }
  }

  return [
    'ok' => $success,
    'http_status' => $httpStatus,
    'provider_message_id' => $providerMessageId,
    'error' => $err,
    'response_excerpt' => $responseExcerpt,
  ];
}

function sendEmailViaMailketing(array $env, string $recipient, string $subject, string $content): array {
  $apiUrl = trim(envGet($env, 'MAILKETING_API_URL', 'https://api.mailketing.co.id/api/v1/send'));
  $apiToken = trim(envGet($env, 'MAILKETING_API_KEY', ''));
  $fromName = trim(envGet($env, 'MAILKETING_FROM_NAME', 'MATIQ'));
  $fromEmail = trim(envGet($env, 'MAILKETING_SENDER', ''));
  $timeoutMs = (int)envGet($env, 'MAILKETING_TIMEOUT_MS', '15000');
  if ($timeoutMs < 1000) {
    $timeoutMs = 15000;
  }

  if ($apiToken === '' || $fromEmail === '') {
    return [
      'ok' => false,
      'http_status' => 0,
      'error' => 'MAILKETING_API_KEY atau MAILKETING_SENDER belum dikonfigurasi',
      'response_excerpt' => '',
    ];
  }

  $payload = [
    'api_token' => $apiToken,
    'from_name' => $fromName !== '' ? $fromName : 'MATIQ',
    'from_email' => $fromEmail,
    'recipient' => trim($recipient),
    'subject' => trim($subject),
    'content' => $content,
  ];

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT_MS => $timeoutMs,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => http_build_query($payload),
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/x-www-form-urlencoded',
    ],
  ]);

  $responseRaw = curl_exec($ch);
  $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);

  $decoded = [];
  if (is_string($responseRaw) && $responseRaw !== '') {
    $json = json_decode($responseRaw, true);
    if (is_array($json)) {
      $decoded = $json;
    }
  }

  $isSuccess = ($httpStatus >= 200 && $httpStatus < 300 && strtolower((string)($decoded['status'] ?? '')) === 'success');
  $respMsg = (string)($decoded['response'] ?? '');
  $respExcerpt = is_string($responseRaw) ? substr($responseRaw, 0, 450) : '';
  $err = '';
  if (!$isSuccess) {
    if ($curlError !== '') {
      $err = 'cURL: ' . $curlError;
    } elseif ($respMsg !== '') {
      $err = $respMsg;
    } else {
      $err = 'Mailketing request gagal';
    }
  }

  return [
    'ok' => $isSuccess,
    'http_status' => $httpStatus,
    'error' => $err,
    'response_excerpt' => $respExcerpt,
  ];
}

function logNotification(
  PDO $db,
  string $eventType,
  string $channel,
  string $recipient,
  string $status,
  int $attempt,
  string $provider,
  int $httpStatus,
  string $errorMessage,
  string $responseExcerpt,
  string $queueId,
  string $userId
): void {
  try {
    $stmt = $db->prepare(
      'INSERT INTO notification_logs (id, event_type, channel, recipient, status, attempt, provider, http_status, error_message, response_excerpt, queue_id, user_id, created_at, updated_at)
       VALUES (:id, :event_type, :channel, :recipient, :status, :attempt, :provider, :http_status, :error_message, :response_excerpt, :queue_id, :user_id, :created_at, :updated_at)'
    );
    $now = utcNowMs();
    $stmt->execute([
      ':id' => randomId('nlog_', 12),
      ':event_type' => $eventType,
      ':channel' => $channel,
      ':recipient' => $recipient,
      ':status' => $status,
      ':attempt' => $attempt,
      ':provider' => $provider,
      ':http_status' => $httpStatus > 0 ? (string)$httpStatus : null,
      ':error_message' => $errorMessage !== '' ? substr($errorMessage, 0, 500) : null,
      ':response_excerpt' => $responseExcerpt !== '' ? $responseExcerpt : null,
      ':queue_id' => $queueId !== '' ? $queueId : null,
      ':user_id' => $userId !== '' ? $userId : null,
      ':created_at' => $now,
      ':updated_at' => $now,
    ]);
  } catch (Throwable $e) {
    // Logging errors should not break main notification flow.
  }
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

if ($uriPath === '/oauth/openai/login' || $uriPath === '/oauth/openai/verify') {
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
  try {
    ensureNotificationTables($db);
  } catch (Throwable $e) {
    // Non-fatal: auth flow must remain available even when schema bootstrap fails.
  }

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
      'INSERT INTO users (id, email, password_hash, salt, name, role, payment_status, mailketing_list_id, created_at, updated_at, last_login, is_active)
       VALUES (:id, :email, :password_hash, :salt, :name, :role, :payment_status, :mailketing_list_id, :created_at, :updated_at, :last_login, 1)'
    );
    $stmt->execute([
      ':id' => $userId,
      ':email' => $email,
      ':password_hash' => $hash,
      ':salt' => $salt,
      ':name' => $name !== '' ? $name : 'Admin',
      ':role' => 'admin',
      ':payment_status' => 'LUNAS',
      ':mailketing_list_id' => null,
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
    $maxAttempts = max(1, (int)envGet($env, 'NOTIFICATION_RETRY_MAX', '3'));
    $waQueueItem = null;

    try {
      $db->beginTransaction();
      $listId = trim((string)($payload['mailketing_list_id'] ?? ''));
      $ins = $db->prepare(
        'INSERT INTO users (id, email, password_hash, salt, name, role, payment_status, mailketing_list_id, created_at, updated_at, last_login, is_active)
         VALUES (:id, :email, :password_hash, :salt, :name, :role, :payment_status, :mailketing_list_id, :created_at, :updated_at, :last_login, 1)'
      );
      $ins->execute([
        ':id' => $userId,
        ':email' => $email,
        ':password_hash' => $hash,
        ':salt' => $salt,
        ':name' => $name,
        ':role' => 'user',
        ':payment_status' => 'NONE',
        ':mailketing_list_id' => $listId !== '' ? $listId : null,
        ':created_at' => $now,
        ':updated_at' => $now,
        ':last_login' => null,
      ]);

      try {
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
      } catch (Throwable $e) {
        // Do not block registration if contact table is unavailable.
      }

      try {
        $waBody = "Halo {$name}! 🎉 Selamat, akun MATIQ kamu udah siap! \n\nLogin di sini pake email {$email} dan mulai pantau campaign Meta Ads-mu. Dashboard ini bakal jadi temen terbaik buat ambil keputusan yang lebih smart. \n\nSiap? Mari kita scale! 🚀";
        $waPayload = [
          'messageType' => 'text',
          'to' => $wa,
          'body' => $waBody,
        ];
        $queueId = randomId('waq_', 12);
        $qins = $db->prepare(
          'INSERT INTO whatsapp_queue (queue_id, user_id, email, phone_number, message_type, message_payload, status, attempt_count, max_attempts, next_retry_at, created_at, updated_at)
           VALUES (:queue_id, :user_id, :email, :phone_number, :message_type, :message_payload, :status, :attempt_count, :max_attempts, :next_retry_at, :created_at, :updated_at)'
        );
        $qins->execute([
          ':queue_id' => $queueId,
          ':user_id' => $userId,
          ':email' => $email,
          ':phone_number' => $wa,
          ':message_type' => 'welcome_register',
          ':message_payload' => json_encode($waPayload, JSON_UNESCAPED_SLASHES),
          ':status' => 'pending',
          ':attempt_count' => 0,
          ':max_attempts' => $maxAttempts,
          ':next_retry_at' => $now,
          ':created_at' => $now,
          ':updated_at' => $now,
        ]);

        $waQueueItem = [
          'queue_id' => $queueId,
          'user_id' => $userId,
          'email' => $email,
          'phone_number' => $wa,
          'message_payload' => json_encode($waPayload, JSON_UNESCAPED_SLASHES),
          'attempt_count' => 0,
          'max_attempts' => $maxAttempts,
        ];
      } catch (Throwable $e) {
        $waQueueItem = null;
      }

      $db->commit();
    } catch (Throwable $txnErr) {
      try {
        $db->rollback();
      } catch (Throwable $e) {
        // Ignore rollback errors
      }
      fail('Gagal membuat akun: ' . $txnErr->getMessage(), 500);
      return;
    }

    $appUrl = trim(envGet($env, 'APP_URL', ''));
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $loginHref = $appUrl !== '' ? rtrim($appUrl, '/') : '';
    $welcomeSubject = 'Selamat Datang di MATIQ';
    $welcomeContent = '<div style="font-family:Segoe UI,Arial,sans-serif;font-size:14px;line-height:1.6;color:#222">'
      . '<p>Halo <strong>' . $safeName . '</strong>,</p>'
      . '<p>Pendaftaran akun MATIQ Anda berhasil dengan email <strong>' . $safeEmail . '</strong>.</p>'
      . '<p>Silakan login untuk mulai menggunakan dashboard.</p>'
      . ($loginHref !== '' ? '<p><a href="' . htmlspecialchars($loginHref, ENT_QUOTES, 'UTF-8') . '">Buka Halaman Login</a></p>' : '')
      . '<p>Terima kasih.</p>'
      . '</div>';

    $emailRes = sendEmailViaMailketing($env, $email, $welcomeSubject, $welcomeContent);
    logNotification(
      $db,
      'auth_register_welcome',
      'email',
      $email,
      $emailRes['ok'] ? 'sent' : 'failed',
      1,
      'mailketing',
      (int)($emailRes['http_status'] ?? 0),
      (string)($emailRes['error'] ?? ''),
      (string)($emailRes['response_excerpt'] ?? ''),
      '',
      $userId
    );

    if (is_array($waQueueItem)) {
      $attempt = 1;
      $waRes = sendWhatsappViaStarsender($env, $waQueueItem);
      if ($waRes['ok']) {
        $fields = [
          'status = :status',
          'attempt_count = :attempt_count',
          'updated_at = :updated_at',
        ];
        $params = [
          ':status' => 'sent',
          ':attempt_count' => $attempt,
          ':updated_at' => utcNowMs(),
          ':queue_id' => (string)$waQueueItem['queue_id'],
        ];
        if (tableHasColumn($db, 'whatsapp_queue', 'provider_message_id')) {
          $fields[] = 'provider_message_id = :provider_message_id';
          $params[':provider_message_id'] = (string)($waRes['provider_message_id'] ?? '');
        }
        if (tableHasColumn($db, 'whatsapp_queue', 'last_error')) {
          $fields[] = 'last_error = :last_error';
          $params[':last_error'] = null;
        }
        $upd = $db->prepare('UPDATE whatsapp_queue SET ' . implode(', ', $fields) . ' WHERE queue_id = :queue_id');
        $upd->execute($params);

        logNotification(
          $db,
          'auth_register_welcome',
          'whatsapp',
          (string)$waQueueItem['phone_number'],
          'sent',
          $attempt,
          'starsender',
          (int)($waRes['http_status'] ?? 0),
          '',
          (string)($waRes['response_excerpt'] ?? ''),
          (string)$waQueueItem['queue_id'],
          $userId
        );
      } else {
        $nextStatus = ((int)$waQueueItem['max_attempts'] > 1) ? 'retry' : 'failed';
        $fields = [
          'status = :status',
          'attempt_count = :attempt_count',
          'updated_at = :updated_at',
        ];
        $params = [
          ':status' => $nextStatus,
          ':attempt_count' => $attempt,
          ':updated_at' => utcNowMs(),
          ':queue_id' => (string)$waQueueItem['queue_id'],
        ];
        if (tableHasColumn($db, 'whatsapp_queue', 'last_error')) {
          $fields[] = 'last_error = :last_error';
          $params[':last_error'] = substr((string)($waRes['error'] ?? 'Unknown error'), 0, 500);
        }
        if ($nextStatus === 'retry' && tableHasColumn($db, 'whatsapp_queue', 'next_retry_at')) {
          $fields[] = 'next_retry_at = :next_retry_at';
          $params[':next_retry_at'] = utcNow()->modify('+' . starsenderDelaySeconds($env) . ' seconds')->format('Y-m-d H:i:s.v');
        }
        $upd = $db->prepare('UPDATE whatsapp_queue SET ' . implode(', ', $fields) . ' WHERE queue_id = :queue_id');
        $upd->execute($params);

        logNotification(
          $db,
          'auth_register_welcome',
          'whatsapp',
          (string)$waQueueItem['phone_number'],
          $nextStatus,
          $attempt,
          'starsender',
          (int)($waRes['http_status'] ?? 0),
          (string)($waRes['error'] ?? ''),
          (string)($waRes['response_excerpt'] ?? ''),
          (string)$waQueueItem['queue_id'],
          $userId
        );
      }
    }

    out([
      'ok' => true,
      'message' => 'Pendaftaran berhasil. Silakan login untuk melanjutkan.',
      'redirect_to' => 'login',
    ]);
  }

  if ($method === 'POST' && $uriPath === '/auth/login') {
    $email = normalizeEmail((string)($payload['email'] ?? ''));
    $password = (string)($payload['password'] ?? '');

    if ($email === '' || $password === '') {
      fail('Email dan password wajib diisi', 400);
    }

    $selectStmt = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $selectStmt->execute([':email' => $email]);
    $user = $selectStmt->fetch();
    $selectStmt = null;
    
    if (!$user) {
      fail('Email atau password salah', 401);
    }
    if ((int)$user['is_active'] !== 1) {
      fail('Akun tidak aktif', 403);
    }

    if (!verifyPasswordCompat($password, (string)$user['password_hash'], (string)$user['salt'])) {
      fail('Email atau password salah', 401);
    }

    $updateStmt = $db->prepare('UPDATE users SET last_login = :last_login, updated_at = :updated_at WHERE id = :id');
    $now = utcNowMs();
    $updateStmt->execute([':last_login' => $now, ':updated_at' => $now, ':id' => $user['id']]);
    $updateStmt = null;

    $freshSelectStmt = $db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $freshSelectStmt->execute([':id' => $user['id']]);
    $fresh = $freshSelectStmt->fetch();
    $freshSelectStmt = null;
    
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
    $revokeStmt = $db->prepare('UPDATE sessions SET is_revoked = 1 WHERE token_id = :token_id');
    $revokeStmt->execute([':token_id' => $current['__token']]);
    $revokeStmt = null;
    out(['ok' => true]);
  }

  if ($method === 'POST' && $uriPath === '/auth/forgot-password') {
    $email = normalizeEmail((string)($payload['email'] ?? ''));
    
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      fail('Format email tidak valid', 400);
    }

    $userStmt = $db->prepare('SELECT id, email, name FROM users WHERE email = :email LIMIT 1');
    $userStmt->execute([':email' => $email]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
      // For security, don't reveal if email exists - just say success
      out(['ok' => true, 'message' => 'Jika email terdaftar, Anda akan menerima link reset password.']);
      return;
    }

    // Generate reset token
    $resetToken = bin2hex(random_bytes(32));
    $hashedToken = hash_hmac('sha256', $resetToken, 'password_reset_secret', false);
    $tokenId = randomId('prt_', 16);
    $now = utcNowMs();
    $expiresAt = date('Y-m-d H:i:s.u', (time() + (2 * 60 * 60)) * 1000); // 2 hours from now

    // Invalidate old tokens for this user
    $invalidateStmt = $db->prepare('UPDATE password_reset_tokens SET is_used = 1 WHERE user_id = :user_id AND is_used = 0');
    $invalidateStmt->execute([':user_id' => $user['id']]);

    // Insert new reset token
    $insertStmt = $db->prepare(
      'INSERT INTO password_reset_tokens (token_id, user_id, email, token, expires_at, is_used, created_at)
       VALUES (:token_id, :user_id, :email, :token, :expires_at, 0, :created_at)'
    );
    $insertStmt->execute([
      ':token_id' => $tokenId,
      ':user_id' => $user['id'],
      ':email' => $email,
      ':token' => $hashedToken,
      ':expires_at' => $expiresAt,
      ':created_at' => $now,
    ]);

    // Try to get user contact for WhatsApp
    $contactStmt = $db->prepare('SELECT phone_number FROM user_contacts WHERE user_id = :user_id LIMIT 1');
    $contactStmt->execute([':user_id' => $user['id']]);
    $contact = $contactStmt->fetch(PDO::FETCH_ASSOC);

    $resetLink = getDomain() . '/reset-password?token=' . $resetToken;
    $waBody = "Halo {$user['name']}! 🔐 Kami terima request untuk reset password MATIQ kamu. \n\nTap link ini buat buat password baru: \n{$resetLink} \n\n⏱️ Link berlaku 2 jam doang! Kalo bukan kamu yang request, abaikan aja pesan ini, aman kok.";

    // Queue WhatsApp message if contact exists
    if ($contact && $contact['phone_number']) {
      try {
        $waPayload = [
          'messageType' => 'text',
          'to' => $contact['phone_number'],
          'body' => $waBody,
        ];
        $queueId = randomId('waq_', 12);
        $maxAttempts = max(1, (int)envGet($env, 'NOTIFICATION_RETRY_MAX', '3'));
        $qins = $db->prepare(
          'INSERT INTO whatsapp_queue (queue_id, user_id, email, phone_number, message_type, message_payload, status, attempt_count, max_attempts, next_retry_at, created_at, updated_at)
           VALUES (:queue_id, :user_id, :email, :phone_number, :message_type, :message_payload, :status, :attempt_count, :max_attempts, :next_retry_at, :created_at, :updated_at)'
        );
        $qins->execute([
          ':queue_id' => $queueId,
          ':user_id' => $user['id'],
          ':email' => $email,
          ':phone_number' => $contact['phone_number'],
          ':message_type' => 'password_reset',
          ':message_payload' => json_encode($waPayload, JSON_UNESCAPED_SLASHES),
          ':status' => 'pending',
          ':attempt_count' => 0,
          ':max_attempts' => $maxAttempts,
          ':next_retry_at' => $now,
          ':created_at' => $now,
          ':updated_at' => $now,
        ]);
      } catch (Throwable $e) {
        // Log but don't fail - WhatsApp is optional
        error_log('Failed to queue password reset WhatsApp: ' . $e->getMessage());
      }
    }

    out(['ok' => true, 'message' => 'Jika email terdaftar, Anda akan menerima link reset password.']);
  }

  if ($method === 'POST' && $uriPath === '/auth/reset-password') {
    $resetToken = (string)($payload['token'] ?? '');
    $newPassword = (string)($payload['password'] ?? '');

    if (!$resetToken) {
      fail('Token tidak ditemukan', 400);
    }
    if (!$newPassword) {
      fail('Password baru wajib diisi', 400);
    }
    if (strlen($newPassword) < 8 || preg_match('/[0-9]/', $newPassword) !== 1) {
      fail('Password minimal 8 karakter dan wajib mengandung angka', 400);
    }

    // Hash the provided token to compare with stored hash
    $hashedToken = hash_hmac('sha256', $resetToken, 'password_reset_secret', false);
    
    // Find the reset token
    $tokenStmt = $db->prepare(
      'SELECT token_id, user_id, email, expires_at, is_used 
       FROM password_reset_tokens 
       WHERE token = :token LIMIT 1'
    );
    $tokenStmt->execute([':token' => $hashedToken]);
    $resetRecord = $tokenStmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetRecord) {
      fail('Link reset tidak valid atau telah kadaluarsa', 400);
    }

    // Check if token is expired
    $expiryTime = strtotime($resetRecord['expires_at']);
    if ($expiryTime < time()) {
      fail('Link reset telah kadaluarsa. Silakan minta link reset baru.', 400);
    }

    // Check if token already used
    if ($resetRecord['is_used']) {
      fail('Link reset telah digunakan. Silakan minta link reset baru.', 400);
    }

    // Update password
    $salt = bin2hex(random_bytes(16));
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $now = utcNowMs();

    $updateStmt = $db->prepare(
      'UPDATE users SET password_hash = :password_hash, salt = :salt, updated_at = :updated_at 
       WHERE id = :user_id'
    );
    $updateStmt->execute([
      ':password_hash' => $newHash,
      ':salt' => $salt,
      ':user_id' => $resetRecord['user_id'],
      ':updated_at' => $now,
    ]);

    // Mark token as used
    $usedStmt = $db->prepare(
      'UPDATE password_reset_tokens SET is_used = 1, used_at = :used_at 
       WHERE token_id = :token_id'
    );
    $usedStmt->execute([
      ':token_id' => $resetRecord['token_id'],
      ':used_at' => $now,
    ]);

    // Invalidate all active sessions for this user (require re-login)
    $revokeStmt = $db->prepare('UPDATE sessions SET is_revoked = 1 WHERE user_id = :user_id AND is_revoked = 0');
    $revokeStmt->execute([':user_id' => $resetRecord['user_id']]);

    out(['ok' => true, 'message' => 'Password berhasil direset. Silakan login dengan password baru Anda.']);
  }

  if ($method === 'GET' && ($uriPath === '/oauth/openai/start' || $uriPath === '/oauth/openai/login')) {
    $returnTo = trim((string)($payload['return_to'] ?? '/'));
    if ($returnTo === '' || $returnTo[0] !== '/') {
      $returnTo = '/';
    }
    $sep = (strpos($returnTo, '?') === false) ? '?' : '&';
    $target = $returnTo . $sep
      . 'oauth_provider=openai'
      . '&oauth_status=error'
      . '&oauth_error=' . rawurlencode('OpenAI OAuth belum dikonfigurasi di mode PHP MySQL');
    header('Location: ' . $target, true, 302);
    exit;
  }

  if ($method === 'GET' && ($uriPath === '/oauth/openai/status' || $uriPath === '/oauth/openai/verify')) {
    $oauthUser = requireAuth($db, $payload);
    out([
      'ok' => true,
      'connected' => false,
      'provider' => 'openai',
      'mode' => 'php-mysql',
      'message' => 'OpenAI OAuth belum dikonfigurasi di mode ini',
      'user_id' => (string)($oauthUser['id'] ?? ''),
    ]);
  }

  if ($method === 'POST' && $uriPath === '/oauth/openai/logout') {
    requireAuth($db, $payload);
    out([
      'ok' => true,
      'connected' => false,
      'provider' => 'openai',
      'message' => 'Session OpenAI OAuth diputus',
    ]);
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
    $listId = trim((string)($payload['mailketing_list_id'] ?? ''));

    $stmt = $db->prepare(
      'INSERT INTO users (id, email, password_hash, salt, name, role, payment_status, mailketing_list_id, created_at, updated_at, last_login, is_active)
       VALUES (:id, :email, :password_hash, :salt, :name, :role, :payment_status, :mailketing_list_id, :created_at, :updated_at, :last_login, 1)'
    );
    $stmt->execute([
      ':id' => $id,
      ':email' => $email,
      ':password_hash' => $hash,
      ':salt' => $salt,
      ':name' => $name,
      ':role' => $role,
      ':payment_status' => $payment,
      ':mailketing_list_id' => $listId !== '' ? $listId : null,
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
      "SELECT queue_id, user_id, email, phone_number, message_payload, status, attempt_count, max_attempts
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
      $attempt = ((int)($it['attempt_count'] ?? 0)) + 1;
      $maxAttempts = max(1, (int)($it['max_attempts'] ?? 3));
      $recipient = (string)($it['phone_number'] ?? '');
      $res = sendWhatsappViaStarsender($env, $it);
      $nowRun = utcNowMs();

      if ($res['ok']) {
        $fields = [
          'status = :status',
          'attempt_count = :attempt_count',
          'updated_at = :updated_at',
        ];
        $params = [
          ':status' => 'sent',
          ':attempt_count' => $attempt,
          ':updated_at' => $nowRun,
          ':queue_id' => $qid,
        ];
        if (tableHasColumn($db, 'whatsapp_queue', 'provider_message_id')) {
          $fields[] = 'provider_message_id = :provider_message_id';
          $params[':provider_message_id'] = (string)($res['provider_message_id'] ?? '');
        }
        if (tableHasColumn($db, 'whatsapp_queue', 'last_error')) {
          $fields[] = 'last_error = :last_error';
          $params[':last_error'] = null;
        }
        $upd = $db->prepare('UPDATE whatsapp_queue SET ' . implode(', ', $fields) . ' WHERE queue_id = :queue_id');
        $upd->execute($params);
        $sent++;

        logNotification(
          $db,
          'whatsapp_queue_process',
          'whatsapp',
          $recipient,
          'sent',
          $attempt,
          'starsender',
          (int)($res['http_status'] ?? 0),
          '',
          (string)($res['response_excerpt'] ?? ''),
          $qid,
          (string)($it['user_id'] ?? '')
        );
        continue;
      }

      $canRetry = $attempt < $maxAttempts;
      $nextStatus = $canRetry ? 'retry' : 'failed';
      if ($canRetry) {
        $retried++;
      } else {
        $failed++;
      }

      $fields = [
        'status = :status',
        'attempt_count = :attempt_count',
        'updated_at = :updated_at',
      ];
      $params = [
        ':status' => $nextStatus,
        ':attempt_count' => $attempt,
        ':updated_at' => $nowRun,
        ':queue_id' => $qid,
      ];
      if (tableHasColumn($db, 'whatsapp_queue', 'last_error')) {
        $fields[] = 'last_error = :last_error';
        $params[':last_error'] = substr((string)($res['error'] ?? 'Unknown error'), 0, 500);
      }
      if ($canRetry && tableHasColumn($db, 'whatsapp_queue', 'next_retry_at')) {
        $fields[] = 'next_retry_at = :next_retry_at';
        $params[':next_retry_at'] = utcNow()->modify('+' . starsenderDelaySeconds($env) . ' seconds')->format('Y-m-d H:i:s.v');
      }
      $upd = $db->prepare('UPDATE whatsapp_queue SET ' . implode(', ', $fields) . ' WHERE queue_id = :queue_id');
      $upd->execute($params);

      logNotification(
        $db,
        'whatsapp_queue_process',
        'whatsapp',
        $recipient,
        $nextStatus,
        $attempt,
        'starsender',
        (int)($res['http_status'] ?? 0),
        (string)($res['error'] ?? ''),
        (string)($res['response_excerpt'] ?? ''),
        $qid,
        (string)($it['user_id'] ?? '')
      );
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

    if (array_key_exists('mailketing_list_id', $payload)) {
      $listId = trim((string)$payload['mailketing_list_id']);
      $fields[] = 'mailketing_list_id = :mailketing_list_id';
      $params[':mailketing_list_id'] = $listId !== '' ? $listId : null;
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
      $role = strtolower(trim((string)($payload['role'] ?? '')));
    $activeProvided = array_key_exists('is_active', $payload);

    $set = [];
    $params = [':updated_at' => utcNowMs()];
    if (in_array($payment, ['LUNAS', 'PENDING', 'NONE'], true)) {
      $set[] = 'payment_status = :payment_status';
      $params[':payment_status'] = $payment;
    }
      if (in_array($role, ['admin', 'user'], true)) {
        $set[] = 'role = :role';
        $params[':role'] = $role;
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

  if ($method === 'POST' && $uriPath === '/app/clear-data') {
    // Clear all campaign data and notes from database
    $errors = [];
    $deleted = [];

    // Try to clear notes
    try {
      $result = $db->exec('DELETE FROM notes WHERE 1=1');
      $deleted[] = "notes ({$result} rows)";
    } catch (Throwable $e) {
      $errors[] = 'notes: ' . $e->getMessage();
    }

    // Try to clear campaigns, adsets, ads
    try {
      $c1 = $db->exec('DELETE FROM campaigns WHERE 1=1');
      $c2 = $db->exec('DELETE FROM adsets WHERE 1=1');
      $c3 = $db->exec('DELETE FROM ads WHERE 1=1');
      $deleted[] = "campaigns ({$c1}), adsets ({$c2}), ads ({$c3}) rows";
    } catch (Throwable $e) {
      $errors[] = 'campaign tables: ' . $e->getMessage();
    }

    // Verify deletion
    $campaignCount = 0;
    $adsetCount = 0;
    $adCount = 0;
    try {
      $campaignCount = (int)$db->query('SELECT COUNT(*) FROM campaigns')->fetchColumn();
      $adsetCount = (int)$db->query('SELECT COUNT(*) FROM adsets')->fetchColumn();
      $adCount = (int)$db->query('SELECT COUNT(*) FROM ads')->fetchColumn();
    } catch (Throwable $e) {
      // Ignore verification error
    }

    $verified = ($campaignCount === 0 && $adsetCount === 0 && $adCount === 0);

    if (count($errors) > 0) {
      fail(
        'Gagal clear beberapa data: ' . implode('; ', $errors) . '. Hubungi admin.',
        400,
        ['cleared' => $deleted, 'remaining' => ['campaigns' => $campaignCount, 'adsets' => $adsetCount, 'ads' => $adCount]]
      );
    }

    out([
      'ok' => true,
      'message' => 'Data berhasil direset.',
      'deleted' => $deleted,
      'verified' => $verified,
      'remaining' => ['campaigns' => $campaignCount, 'adsets' => $adsetCount, 'ads' => $adCount],
    ]);
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
