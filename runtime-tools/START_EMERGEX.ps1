$ErrorActionPreference = 'Stop'

$projectFolder = Split-Path $PSScriptRoot -Parent
$workspaceRoot = Split-Path $projectFolder -Parent
$portableXampp = Join-Path $workspaceRoot '.project_runtime\xampp'
$standardXampp = 'C:\xampp'

if (Test-Path -LiteralPath (Join-Path $portableXampp 'php\php.exe')) {
    $xamppRoot = $portableXampp
} elseif (Test-Path -LiteralPath (Join-Path $standardXampp 'php\php.exe')) {
    $xamppRoot = $standardXampp
} else {
    throw 'XAMPP was not found. Install XAMPP in C:\xampp or supply the portable .project_runtime folder.'
}

$runtimeState = Join-Path $env:LOCALAPPDATA 'EMERGEX\runtime-state'
New-Item -ItemType Directory -Path $runtimeState -Force | Out-Null

$php = Join-Path $xamppRoot 'php\php.exe'
$mysqlServer = Join-Path $xamppRoot 'mysql\bin\mysqld.exe'
$mysqlClient = Join-Path $xamppRoot 'mysql\bin\mysql.exe'
$htdocs = Join-Path $xamppRoot 'htdocs'
$application = Join-Path $projectFolder 'application'
$hostedApplication = Join-Path $htdocs 'emergex'

foreach ($required in @($php, $mysqlServer, $mysqlClient, (Join-Path $application 'database\install.php'))) {
    if (-not (Test-Path -LiteralPath $required)) {
        throw "The local EMERGEX setup is incomplete: $required"
    }
}

if (-not (Test-Path -LiteralPath $hostedApplication)) {
    New-Item -ItemType Junction -Path $hostedApplication -Target $application | Out-Null
} else {
    $hostedItem = Get-Item -LiteralPath $hostedApplication -Force
    $hostedTarget = @($hostedItem.Target) | Select-Object -First 1
    $correctTarget = $false
    if ($hostedItem.LinkType -and $hostedTarget) {
        $resolvedHostedTarget = (Resolve-Path -LiteralPath $hostedTarget).Path.TrimEnd('\')
        $resolvedApplication = (Resolve-Path -LiteralPath $application).Path.TrimEnd('\')
        $correctTarget = $resolvedHostedTarget -ieq $resolvedApplication
    }
    if (-not $correctTarget) {
        throw "$hostedApplication already exists but is not linked to $application. Rename the existing folder, then run START EMERGEX again."
    }
}

function Test-DatabaseConnection {
    param([int]$Port)
    try {
        & $mysqlClient --protocol=TCP -h 127.0.0.1 -P $Port -u root -N -e 'SELECT 1;' 2>$null | Out-Null
        return $LASTEXITCODE -eq 0
    } catch {
        return $false
    }
}

$databasePort = 0
foreach ($candidatePort in @(3307, 3306)) {
    if (Test-DatabaseConnection -Port $candidatePort) {
        $databasePort = $candidatePort
        break
    }
}

if ($databasePort -eq 0) {
    $databasePort = 3307
    $baseDir = Join-Path $xamppRoot 'mysql'
    $dataDir = Join-Path $baseDir 'data'
    $errorLog = Join-Path $runtimeState 'mariadb-3307.err.log'
    $arguments = "--no-defaults --basedir=`"$baseDir`" --datadir=`"$dataDir`" --port=$databasePort --bind-address=127.0.0.1 --log-error=`"$errorLog`" --standalone"
    $databaseProcess = Start-Process -FilePath $mysqlServer -ArgumentList $arguments -WorkingDirectory $xamppRoot -WindowStyle Hidden -PassThru
    Set-Content -LiteralPath (Join-Path $runtimeState 'mysql-server.pid') -Value $databaseProcess.Id

    $databaseReady = $false
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        if (Test-DatabaseConnection -Port $databasePort) {
            $databaseReady = $true
            break
        }
        Start-Sleep -Milliseconds 500
    }
    if (-not $databaseReady) {
        throw "MariaDB did not become ready on port $databasePort. Check $errorLog."
    }
}

$env:EMERGEX_DB_PORT = [string]$databasePort
$schemaExists = & $mysqlClient --protocol=TCP -h 127.0.0.1 -P $databasePort -u root -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='emergex';"
if ($LASTEXITCODE -ne 0) { throw "Could not inspect the EMERGEX database on port $databasePort." }
if ([int]$schemaExists -lt 7) {
    & $php (Join-Path $application 'database\install.php')
    if ($LASTEXITCODE -ne 0) { throw 'The EMERGEX database installer failed.' }
}

$url = 'http://127.0.0.1:8080/emergex/public/'
$webReady = $false
try {
    $existingResponse = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 3
    $webReady = $existingResponse.StatusCode -eq 200
} catch {}

if (-not $webReady) {
    $portInUse = Get-NetTCPConnection -LocalPort 8080 -State Listen -ErrorAction SilentlyContinue
    if ($portInUse) {
        throw 'Port 8080 is being used by another program. Stop that program and run START EMERGEX again.'
    }
    $arguments = "-S 127.0.0.1:8080 -t `"$htdocs`""
    $webProcess = Start-Process -FilePath $php -ArgumentList $arguments -WorkingDirectory $htdocs -WindowStyle Hidden -PassThru
    Set-Content -LiteralPath (Join-Path $runtimeState 'php-server.pid') -Value $webProcess.Id
}

$response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 10
if ($response.StatusCode -ne 200) { throw "EMERGEX returned HTTP $($response.StatusCode)." }

Write-Host "EMERGEX is running at $url"
Write-Host "XAMPP: $xamppRoot"
Write-Host "MariaDB port: $databasePort"
Write-Host 'Demo accounts are listed in application\README.md.'
