$ErrorActionPreference = 'Stop'

$root = Split-Path -Path $PSScriptRoot -Parent
Set-Location $root

$phpCmd = Get-Command php -ErrorAction SilentlyContinue
if (-not $phpCmd) {
  throw "PHP wurde nicht gefunden. Bitte neues Terminal öffnen oder PATH prüfen."
}

$phpPath = $phpCmd.Source
$phpDir = Split-Path -Path $phpPath -Parent
$extDir = Join-Path $phpDir 'ext'
$caFile = Join-Path $root 'backend\certs\cacert.pem'

if (-not (Test-Path $caFile)) {
  throw "CA-Datei fehlt: $caFile"
}

$args = @(
  '-d', "extension_dir=$extDir",
  '-d', 'extension=php_pdo_mysql.dll',
  '-d', 'extension=php_curl.dll',
  '-d', 'extension=php_zip.dll',
  '-d', 'extension=php_mbstring.dll',
  '-d', "curl.cainfo=$caFile",
  '-d', "openssl.cafile=$caFile",
  '-S', '127.0.0.1:8000',
  '-t', (Join-Path $root 'backend\public')
)

Write-Host "Backend startet auf http://127.0.0.1:8000"
& $phpPath @args

