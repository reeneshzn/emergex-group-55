$ErrorActionPreference = 'Stop'

$projectFolder = Split-Path $PSScriptRoot -Parent
$workspaceRoot = Split-Path $projectFolder -Parent
$runtimeState = Join-Path $workspaceRoot '.project_runtime'
$xamppRoot = (Join-Path $runtimeState 'xampp').ToLowerInvariant()
$processIds = [System.Collections.Generic.HashSet[int]]::new()

foreach ($pidFile in @('php-server.pid', 'mysql-3307.pid')) {
    $path = Join-Path $runtimeState $pidFile
    if (-not (Test-Path -LiteralPath $path)) { continue }
    $savedId = 0
    if ([int]::TryParse((Get-Content -LiteralPath $path -Raw).Trim(), [ref]$savedId)) {
        [void]$processIds.Add($savedId)
    }
}

foreach ($port in @(8080, 3307)) {
    Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue | ForEach-Object {
        [void]$processIds.Add([int]$_.OwningProcess)
    }
}

$stopped = 0
foreach ($candidateId in $processIds) {
    $process = Get-Process -Id $candidateId -ErrorAction SilentlyContinue
    if (-not $process -or -not $process.Path) { continue }
    if (-not $process.Path.ToLowerInvariant().StartsWith($xamppRoot)) { continue }
    Stop-Process -Id $candidateId -Force
    Write-Host "Stopped $($process.ProcessName) (PID $candidateId)."
    $stopped++
}

foreach ($pidFile in @('php-server.pid', 'mysql-3307.pid')) {
    $path = Join-Path $runtimeState $pidFile
    if (Test-Path -LiteralPath $path) { Remove-Item -LiteralPath $path -Force }
}

if ($stopped -eq 0) {
    Write-Host 'EMERGEX was already stopped.'
} else {
    Write-Host 'EMERGEX local services are stopped.'
}
