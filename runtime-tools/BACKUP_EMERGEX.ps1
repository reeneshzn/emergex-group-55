param(
    [string]$DestinationDirectory
)

$ErrorActionPreference = 'Stop'

$projectFolder = Split-Path $PSScriptRoot -Parent
$workspaceRoot = Split-Path $projectFolder -Parent
$portableRoot = Join-Path $workspaceRoot '.project_runtime\xampp'
$standardRoot = 'C:\xampp'

if (Test-Path -LiteralPath (Join-Path $portableRoot 'mysql\bin\mysqldump.exe')) {
    $xamppRoot = $portableRoot
    $databasePort = 3307
} elseif (Test-Path -LiteralPath (Join-Path $standardRoot 'mysql\bin\mysqldump.exe')) {
    $xamppRoot = $standardRoot
    $databasePort = 3306
} else {
    throw 'XAMPP was not found. Install XAMPP or use the supplied workspace runtime.'
}

$dumpTool = Join-Path $xamppRoot 'mysql\bin\mysqldump.exe'
if (-not $DestinationDirectory) {
    $DestinationDirectory = Join-Path $projectFolder 'backups'
}
New-Item -ItemType Directory -Force -Path $DestinationDirectory | Out-Null
$destination = Join-Path (Resolve-Path -LiteralPath $DestinationDirectory).Path ("emergex-{0}.sql" -f (Get-Date -Format 'yyyyMMdd-HHmmss'))

& $dumpTool --protocol=TCP --host=127.0.0.1 --port=$databasePort --user=root --single-transaction --routines --triggers --result-file=$destination emergex
if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $destination)) {
    throw 'The EMERGEX database backup failed.'
}

$item = Get-Item -LiteralPath $destination
Write-Host "Backup created: $($item.FullName)"
Write-Host "Size: $($item.Length) bytes"

