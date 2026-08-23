param(
    [Parameter(Mandatory = $true)]
    [string]$BackupFile,
    [switch]$ConfirmRestore
)

$ErrorActionPreference = 'Stop'
if (-not $ConfirmRestore) {
    throw 'Restore replaces the current EMERGEX database. Rerun with -ConfirmRestore after confirming the backup file.'
}

$resolvedBackup = (Resolve-Path -LiteralPath $BackupFile).Path
if ([IO.Path]::GetExtension($resolvedBackup) -ne '.sql') {
    throw 'The selected backup must be an SQL file.'
}

$projectFolder = Split-Path $PSScriptRoot -Parent
$workspaceRoot = Split-Path $projectFolder -Parent
$portableRoot = Join-Path $workspaceRoot '.project_runtime\xampp'
$standardRoot = 'C:\xampp'

if (Test-Path -LiteralPath (Join-Path $portableRoot 'mysql\bin\mysql.exe')) {
    $xamppRoot = $portableRoot
    $databasePort = 3307
} elseif (Test-Path -LiteralPath (Join-Path $standardRoot 'mysql\bin\mysql.exe')) {
    $xamppRoot = $standardRoot
    $databasePort = 3306
} else {
    throw 'XAMPP was not found. Install XAMPP or use the supplied workspace runtime.'
}

$mysql = Join-Path $xamppRoot 'mysql\bin\mysql.exe'
& $mysql --protocol=TCP --host=127.0.0.1 --port=$databasePort --user=root --execute='CREATE DATABASE IF NOT EXISTS emergex CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
if ($LASTEXITCODE -ne 0) { throw 'The EMERGEX database could not be prepared.' }

$sourcePath = $resolvedBackup.Replace('\', '/')
& $mysql --protocol=TCP --host=127.0.0.1 --port=$databasePort --user=root --database=emergex --execute="source $sourcePath"
if ($LASTEXITCODE -ne 0) { throw 'The EMERGEX database restore failed.' }

Write-Host "EMERGEX restored from: $resolvedBackup"

