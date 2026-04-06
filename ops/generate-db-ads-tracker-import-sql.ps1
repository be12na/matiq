param(
  [string]$XlsxPath = "templates/Db Ads Tracker.xlsx",
  [string]$OutputSqlPath = "database/mysql_import_db_ads_tracker.sql"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function SqlEscape([string]$value) {
  if ($null -eq $value) { return "" }
  return $value.Replace("'", "''")
}

function SqlText([string]$value) {
  if ([string]::IsNullOrWhiteSpace($value)) { return "NULL" }
  return "'" + (SqlEscape $value) + "'"
}

function SqlNumber([string]$value) {
  $v = [string]$value
  if ([string]::IsNullOrWhiteSpace($v)) { return "NULL" }
  $lv = $v.Trim().ToLowerInvariant()
  if ($lv -eq 'true' -or $lv -eq 'yes' -or $lv -eq 'y') { return '1' }
  if ($lv -eq 'false' -or $lv -eq 'no' -or $lv -eq 'n') { return '0' }
  $norm = $v.Trim().Replace(",", ".")
  $norm = [regex]::Replace($norm, "[^0-9.\-]", "")
  if ([string]::IsNullOrWhiteSpace($norm) -or $norm -eq "-" -or $norm -eq ".") { return "NULL" }
  if ($norm -match '^-?\d+\.0+$') {
    return ($norm -replace '\.0+$', '')
  }
  return $norm
}

function Parse-Xlsx {
  param([string]$Path)

  Add-Type -AssemblyName System.IO.Compression.FileSystem
  $zip = [System.IO.Compression.ZipFile]::OpenRead((Resolve-Path $Path))
  try {
    $wbEntry = $zip.GetEntry('xl/workbook.xml')
    if (-not $wbEntry) { throw "workbook.xml tidak ditemukan" }

    $wbDoc = New-Object System.Xml.XmlDocument
    $sr = New-Object System.IO.StreamReader($wbEntry.Open())
    $wbDoc.LoadXml($sr.ReadToEnd())
    $sr.Close()

    $ns = New-Object System.Xml.XmlNamespaceManager($wbDoc.NameTable)
    $ns.AddNamespace('x','http://schemas.openxmlformats.org/spreadsheetml/2006/main')
    $sheets = $wbDoc.SelectNodes('//x:sheets/x:sheet',$ns)

    $relsEntry = $zip.GetEntry('xl/_rels/workbook.xml.rels')
    if (-not $relsEntry) { throw "workbook rels tidak ditemukan" }

    $relsDoc = New-Object System.Xml.XmlDocument
    $sr2 = New-Object System.IO.StreamReader($relsEntry.Open())
    $relsDoc.LoadXml($sr2.ReadToEnd())
    $sr2.Close()

    $nsr = New-Object System.Xml.XmlNamespaceManager($relsDoc.NameTable)
    $nsr.AddNamespace('r','http://schemas.openxmlformats.org/package/2006/relationships')

    $shared = @()
    $ssEntry = $zip.GetEntry('xl/sharedStrings.xml')
    if ($ssEntry) {
      $ssDoc = New-Object System.Xml.XmlDocument
      $sr3 = New-Object System.IO.StreamReader($ssEntry.Open())
      $ssDoc.LoadXml($sr3.ReadToEnd())
      $sr3.Close()

      $nss = New-Object System.Xml.XmlNamespaceManager($ssDoc.NameTable)
      $nss.AddNamespace('x','http://schemas.openxmlformats.org/spreadsheetml/2006/main')
      $shared = $ssDoc.SelectNodes('//x:si',$nss) | ForEach-Object {
        ($_.SelectNodes('.//x:t',$nss) | ForEach-Object { $_.'#text' }) -join ''
      }
    }

    function Get-CellValue([System.Xml.XmlElement]$cell, [object[]]$sharedStrings, [System.Xml.XmlNamespaceManager]$nsw) {
      $type = $cell.GetAttribute('t')
      if ($type -eq 'inlineStr') {
        $inlineTextNodes = $cell.SelectNodes('.//x:is//x:t', $nsw)
        if ($inlineTextNodes -and $inlineTextNodes.Count -gt 0) {
          return ($inlineTextNodes | ForEach-Object { $_.'#text' }) -join ''
        }
        return ''
      }

      $vNode = $cell.SelectSingleNode('x:v', $nsw)
      if (-not $vNode) { return '' }
      $v = [string]$vNode.InnerText
      if ($type -eq 's') {
        $idx = 0
        if ([int]::TryParse($v, [ref]$idx)) {
          if ($idx -ge 0 -and $idx -lt $sharedStrings.Count) {
            return [string]$sharedStrings[$idx]
          }
        }
      }
      return $v
    }

    $result = [ordered]@{}

    foreach ($sheet in $sheets) {
      $name = $sheet.GetAttribute('name')
      $rid = $sheet.GetAttribute('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships')
      $rel = $relsDoc.SelectSingleNode("//r:Relationship[@Id='$rid']", $nsr)
      if (-not $rel) { continue }

      $target = $rel.GetAttribute('Target')
      if ($target -notlike 'worksheets/*') { continue }

      $entry = $zip.GetEntry('xl/' + $target)
      if (-not $entry) { continue }

      $wsDoc = New-Object System.Xml.XmlDocument
      $srw = New-Object System.IO.StreamReader($entry.Open())
      $wsDoc.LoadXml($srw.ReadToEnd())
      $srw.Close()

      $nsw = New-Object System.Xml.XmlNamespaceManager($wsDoc.NameTable)
      $nsw.AddNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main')
      $rows = $wsDoc.SelectNodes('//x:sheetData/x:row', $nsw)
      if (-not $rows -or $rows.Count -eq 0) { continue }

      $headerCells = $rows[0].SelectNodes('x:c', $nsw)
      $headers = @()
      foreach ($hc in $headerCells) {
        $headers += (Get-CellValue -cell $hc -sharedStrings $shared -nsw $nsw)
      }

      $data = New-Object System.Collections.Generic.List[object]
      for ($i = 1; $i -lt $rows.Count; $i++) {
        $cells = $rows[$i].SelectNodes('x:c', $nsw)
        if (-not $cells) { continue }
        $obj = [ordered]@{}
        $hasData = $false

        for ($ci = 0; $ci -lt $headers.Count; $ci++) {
          $header = [string]$headers[$ci]
          if ([string]::IsNullOrWhiteSpace($header)) { continue }
          $val = ''
          if ($ci -lt $cells.Count) {
            $val = [string](Get-CellValue -cell $cells[$ci] -sharedStrings $shared -nsw $nsw)
          }
          if (-not [string]::IsNullOrWhiteSpace($val)) { $hasData = $true }
          $obj[$header] = $val
        }

        if ($hasData) {
          $data.Add($obj)
        }
      }

      $result[$name] = [ordered]@{
        headers = $headers
        rows = $data
      }
    }

    return $result
  }
  finally {
    $zip.Dispose()
  }
}

$schemaSql = @"
-- Generated from templates/Db Ads Tracker.xlsx
-- Import into selected database in phpMyAdmin
SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS campaigns (
  id VARCHAR(64) NOT NULL,
  import_batch_id VARCHAR(64) DEFAULT NULL,
  period_label VARCHAR(64) DEFAULT NULL,
  campaign_name VARCHAR(255) NOT NULL,
  spend DECIMAL(18,2) NOT NULL DEFAULT 0,
  impressions BIGINT NOT NULL DEFAULT 0,
  ctr DECIMAL(10,4) NOT NULL DEFAULT 0,
  results DECIMAL(18,4) NOT NULL DEFAULT 0,
  revenue DECIMAL(18,2) NOT NULL DEFAULT 0,
  roas DECIMAL(18,6) NOT NULL DEFAULT 0,
  cpm DECIMAL(18,4) NOT NULL DEFAULT 0,
  reach BIGINT NOT NULL DEFAULT 0,
  freq DECIMAL(10,4) NOT NULL DEFAULT 0,
  atc DECIMAL(18,4) NOT NULL DEFAULT 0,
  cpa DECIMAL(18,4) NOT NULL DEFAULT 0,
  date_start VARCHAR(32) DEFAULT NULL,
  date_end VARCHAR(32) DEFAULT NULL,
  created_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_campaigns_import_batch (import_batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adsets (
  id VARCHAR(64) NOT NULL,
  import_batch_id VARCHAR(64) DEFAULT NULL,
  period_label VARCHAR(64) DEFAULT NULL,
  campaign_name VARCHAR(255) NOT NULL,
  adset_name VARCHAR(255) NOT NULL,
  spend DECIMAL(18,2) NOT NULL DEFAULT 0,
  impressions BIGINT NOT NULL DEFAULT 0,
  ctr DECIMAL(10,4) NOT NULL DEFAULT 0,
  results DECIMAL(18,4) NOT NULL DEFAULT 0,
  revenue DECIMAL(18,2) NOT NULL DEFAULT 0,
  roas DECIMAL(18,6) NOT NULL DEFAULT 0,
  cpm DECIMAL(18,4) NOT NULL DEFAULT 0,
  reach BIGINT NOT NULL DEFAULT 0,
  freq DECIMAL(10,4) NOT NULL DEFAULT 0,
  atc DECIMAL(18,4) NOT NULL DEFAULT 0,
  cpa DECIMAL(18,4) NOT NULL DEFAULT 0,
  date_start VARCHAR(32) DEFAULT NULL,
  date_end VARCHAR(32) DEFAULT NULL,
  created_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_adsets_import_batch (import_batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ads (
  id VARCHAR(64) NOT NULL,
  import_batch_id VARCHAR(64) DEFAULT NULL,
  period_label VARCHAR(64) DEFAULT NULL,
  campaign_name VARCHAR(255) NOT NULL,
  adset_name VARCHAR(255) NOT NULL,
  ad_name VARCHAR(255) NOT NULL,
  spend DECIMAL(18,2) NOT NULL DEFAULT 0,
  impressions BIGINT NOT NULL DEFAULT 0,
  ctr DECIMAL(10,4) NOT NULL DEFAULT 0,
  results DECIMAL(18,4) NOT NULL DEFAULT 0,
  revenue DECIMAL(18,2) NOT NULL DEFAULT 0,
  roas DECIMAL(18,6) NOT NULL DEFAULT 0,
  cpm DECIMAL(18,4) NOT NULL DEFAULT 0,
  reach BIGINT NOT NULL DEFAULT 0,
  freq DECIMAL(10,4) NOT NULL DEFAULT 0,
  atc DECIMAL(18,4) NOT NULL DEFAULT 0,
  cpa DECIMAL(18,4) NOT NULL DEFAULT 0,
  date_start VARCHAR(32) DEFAULT NULL,
  date_end VARCHAR(32) DEFAULT NULL,
  created_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_ads_import_batch (import_batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS thresholds (
  metric_key VARCHAR(32) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  rule_type ENUM('min','max') NOT NULL,
  value DECIMAL(18,6) NOT NULL DEFAULT 0,
  label VARCHAR(128) NOT NULL,
  PRIMARY KEY (metric_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notes (
  id VARCHAR(600) NOT NULL,
  entity_level ENUM('campaign','adset','ad') NOT NULL,
  entity_name VARCHAR(255) NOT NULL,
  note_text TEXT NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  key_name VARCHAR(128) NOT NULL,
  key_value TEXT,
  PRIMARY KEY (key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_logs (
  import_batch_id VARCHAR(64) NOT NULL,
  level ENUM('campaign','adset','ad') NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  row_count INT NOT NULL DEFAULT 0,
  imported_at DATETIME(3) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'ok',
  message TEXT,
  KEY idx_import_logs_batch (import_batch_id),
  KEY idx_import_logs_imported_at (imported_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id VARCHAR(64) NOT NULL,
  email VARCHAR(191) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  salt VARCHAR(64) NOT NULL,
  name VARCHAR(191) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  payment_status ENUM('LUNAS','PENDING','NONE') NOT NULL DEFAULT 'NONE',
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  last_login DATETIME(3) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions (
  token_id VARCHAR(128) NOT NULL,
  user_id VARCHAR(64) NOT NULL,
  email VARCHAR(191) NOT NULL,
  role ENUM('admin','user') NOT NULL,
  payment_status ENUM('LUNAS','PENDING','NONE') NOT NULL,
  created_at DATETIME(3) NOT NULL,
  expires_at DATETIME(3) NOT NULL,
  is_revoked TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (token_id),
  KEY idx_sessions_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS readme_meta (
  field_name VARCHAR(191) NOT NULL,
  field_value TEXT,
  PRIMARY KEY (field_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE TABLE campaigns;
TRUNCATE TABLE adsets;
TRUNCATE TABLE ads;
TRUNCATE TABLE thresholds;
TRUNCATE TABLE notes;
TRUNCATE TABLE settings;
TRUNCATE TABLE import_logs;
TRUNCATE TABLE users;
TRUNCATE TABLE sessions;
TRUNCATE TABLE readme_meta;
"@

$sheetMap = [ordered]@{
  campaigns = @{ table = 'campaigns'; cols = @('id','import_batch_id','period_label','campaign_name','spend','impressions','ctr','results','revenue','roas','cpm','reach','freq','atc','cpa','date_start','date_end','created_at'); num = @('spend','impressions','ctr','results','revenue','roas','cpm','reach','freq','atc','cpa') }
  adsets = @{ table = 'adsets'; cols = @('id','import_batch_id','period_label','campaign_name','adset_name','spend','impressions','ctr','results','revenue','roas','cpm','reach','freq','atc','cpa','date_start','date_end','created_at'); num = @('spend','impressions','ctr','results','revenue','roas','cpm','reach','freq','atc','cpa') }
  ads = @{ table = 'ads'; cols = @('id','import_batch_id','period_label','campaign_name','adset_name','ad_name','spend','impressions','ctr','results','revenue','roas','cpm','reach','freq','atc','cpa','date_start','date_end','created_at'); num = @('spend','impressions','ctr','results','revenue','roas','cpm','reach','freq','atc','cpa') }
  thresholds = @{ table = 'thresholds'; cols = @('metric_key','enabled','rule_type','value','label'); num = @('enabled','value') }
  notes = @{ table = 'notes'; cols = @('id','entity_level','entity_name','note_text','updated_at'); num = @() }
  settings = @{ table = 'settings'; cols = @('key_name','key_value'); num = @() }
  import_logs = @{ table = 'import_logs'; cols = @('import_batch_id','level','file_name','row_count','imported_at','status','message'); num = @('row_count') }
  users = @{ table = 'users'; cols = @('id','email','password_hash','salt','name','role','payment_status','created_at','updated_at','last_login','is_active'); num = @('is_active') }
  sessions = @{ table = 'sessions'; cols = @('token_id','user_id','email','role','payment_status','created_at','expires_at','is_revoked'); num = @('is_revoked') }
  README = @{ table = 'readme_meta'; cols = @('field','value'); num = @() }
}

$numericDefaults = @{
  thresholds = @{ enabled = '0'; value = '0' }
  users = @{ is_active = '1' }
  sessions = @{ is_revoked = '0' }
  import_logs = @{ row_count = '0' }
}

$data = Parse-Xlsx -Path $XlsxPath

$sqlOut = New-Object System.Text.StringBuilder
[void]$sqlOut.AppendLine($schemaSql)

foreach ($sheetName in $sheetMap.Keys) {
  if (-not $data.Contains($sheetName)) { continue }

  $conf = $sheetMap[$sheetName]
  $table = [string]$conf.table
  $cols = @($conf.cols)
  $numCols = @($conf.num)

  $insertCols = if ($sheetName -eq 'README') { @('field_name','field_value') } else { $cols }
  $rows = $data[$sheetName].rows
  if ($rows.Count -eq 0) { continue }

  foreach ($row in $rows) {
    $vals = @()
    foreach ($c in $cols) {
      $sourceCol = $c
      if ($sheetName -eq 'README') {
        if ($c -eq 'field') { $sourceCol = 'field' }
        if ($c -eq 'value') { $sourceCol = 'value' }
      }

      $raw = ''
      if ($row.Contains($sourceCol)) { $raw = [string]$row[$sourceCol] }

      if ($numCols -contains $c) {
        $numVal = SqlNumber $raw
        if ($numVal -eq 'NULL' -and $numericDefaults.ContainsKey($sheetName) -and $numericDefaults[$sheetName].ContainsKey($c)) {
          $numVal = [string]$numericDefaults[$sheetName][$c]
        }
        $vals += $numVal
      } else {
        $vals += (SqlText $raw)
      }
    }

    $colSql = ($insertCols | ForEach-Object { '`' + $_ + '`' }) -join ', '
    $valSql = $vals -join ', '
    [void]$sqlOut.AppendLine(('INSERT INTO `{0}` ({1}) VALUES ({2});' -f $table, $colSql, $valSql))
  }

  [void]$sqlOut.AppendLine("")
}

[void]$sqlOut.AppendLine("SET FOREIGN_KEY_CHECKS = 1;")

$outAbs = Join-Path (Get-Location) $OutputSqlPath
$sqlOut.ToString() | Out-File -FilePath $outAbs -Encoding utf8
Write-Host ("Generated SQL: " + $OutputSqlPath)
