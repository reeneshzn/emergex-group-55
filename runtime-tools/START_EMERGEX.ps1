$ErrorActionPreference = 'Stop'

$projectFolder = Split-Path $PSScriptRoot -Parent
$workspaceRoot = Split-Path $projectFolder -Parent
$runtimeState = Join-Path $workspaceRoot '.project_runtime'
$xamppRoot = Join-Path $runtimeState 'xampp'
$php = Join-Path $xamppRoot 'php\php.exe'
$mysqlServer = Join-Path $xamppRoot 'mysql\bin\mysqld.exe'
$mysqlClient = Join-Path $xamppRoot 'mysql\bin\mysql.exe'
$htdocs = Join-Path $xamppRoot 'htdocs'
$application = Join-Path $htdocs 'emergex'

foreach ($required in @($php, $mysqlServer, $mysqlClient, (Join-Path $application 'database\install.php'))) {
    if (-not (Test-Path -LiteralPath $required)) {
        throw "The local EMERGEX runtime is incomplete: $required"
    }
}

$databaseListening = Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue
if (-not $databaseListening) {
    $baseDir = Join-Path $xamppRoot 'mysql'
    $dataDir = Join-Path $baseDir 'data'
    $errorLog = Join-Path $runtimeState 'mariadb-3307.err.log'
    $arguments = "--no-defaults --basedir=`"$baseDir`" --datadir=`"$dataDir`" --port=3307 --bind-address=127.0.0.1 --log-error=`"$errorLog`" --standalone"
    $databaseProcess = Start-Process -FilePath $mysqlServer -ArgumentList $arguments -WorkingDirectory $xamppRoot -WindowStyle Hidden -PassThru
    Set-Content -LiteralPath (Join-Path $runtimeState 'mysql-3307.pid') -Value $databaseProcess.Id
}

$databaseReady = $false
for ($attempt = 0; $attempt -lt 30; $attempt++) {
    try {
        & $mysqlClient --protocol=TCP -h 127.0.0.1 -P 3307 -u root -N -e 'SELECT 1;' 2>$null | Out-Null
        if ($LASTEXITCODE -eq 0) { $databaseReady = $true; break }
    } catch {}
    Start-Sleep -Milliseconds 500
}
if (-not $databaseReady) { throw 'MariaDB did not become ready on port 3307.' }

$schemaExists = & $mysqlClient --protocol=TCP -h 127.0.0.1 -P 3307 -u root -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='emergex';"
if ([int]$schemaExists -lt 7) {
    & $php (Join-Path $application 'database\install.php')
    if ($LASTEXITCODE -ne 0) { throw 'The EMERGEX database installer failed.' }
}

$webListening = Get-NetTCPConnection -LocalPort 8080 -State Listen -ErrorAction SilentlyContinue
if (-not $webListening) {
    $arguments = "-S 127.0.0.1:8080 -t `"$htdocs`""
    $webProcess = Start-Process -FilePath $php -ArgumentList $arguments -WorkingDirectory $htdocs -WindowStyle Hidden -PassThru
    Set-Content -LiteralPath (Join-Path $runtimeState 'php-server.pid') -Value $webProcess.Id
}

$url = 'http://127.0.0.1:8080/emergex/public/'
$response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 10
if ($response.StatusCode -ne 200) { throw "EMERGEX returned HTTP $($response.StatusCode)." }

Write-Host "EMERGEX is running at $url"
Write-Host 'Demo accounts are listed in application\README.md.'

