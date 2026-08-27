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
    throw 'XAMPP was not found. Nothing was stopped.'
}

$runtimeState = Join-Path $env:LOCALAPPDATA 'EMERGEX\runtime-state'
$xamppRootLower = $xamppRoot.ToLowerInvariant()
$processIds = [System.Collections.Generic.HashSet[int]]::new()

foreach ($pidFile in @('php-server.pid', 'mysql-server.pid')) {
    $path = Join-Path $runtimeState $pidFile
    if (-not (Test-Path -LiteralPath $path)) { continue }
    $savedId = 0
    if ([int]::TryParse((Get-Content -LiteralPath $path -Raw).Trim(), [ref]$savedId)) {
        [void]$processIds.Add($savedId)
    }
}

$stopped = 0
foreach ($candidateId in $processIds) {
    $process = Get-Process -Id $candidateId -ErrorAction SilentlyContinue
    if (-not $process -or -not $process.Path) { continue }
    if (-not $process.Path.ToLowerInvariant().StartsWith($xamppRootLower)) { continue }
    Stop-Process -Id $candidateId -Force
    Write-Host "Stopped $($process.ProcessName) (PID $candidateId)."
    $stopped++
}

foreach ($pidFile in @('php-server.pid', 'mysql-server.pid')) {
    $path = Join-Path $runtimeState $pidFile
    if (Test-Path -LiteralPath $path) { Remove-Item -LiteralPath $path -Force }
}

if ($stopped -eq 0) {
    Write-Host 'No launcher-owned EMERGEX services were running.'
    Write-Host 'Services started separately in the XAMPP Control Panel were left running.'
} else {
    Write-Host 'Launcher-owned EMERGEX services are stopped.'
}
