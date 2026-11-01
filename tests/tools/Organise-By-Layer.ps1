# Groups the suite files into folders named for the test level they
# mostly cover, and writes a README into each giving the counts.
#
# Run it by double-clicking "Organise By Layer.cmd" in this folder.
# Safe to run twice - files already in place are left alone.
#
# NOTE ON ENCODING
# This file is deliberately plain ASCII. Windows PowerShell 5.1 reads a
# .ps1 as ANSI unless it carries a byte-order mark, so a stray em dash or
# curly quote arrives as garbage bytes and the script fails to parse
# before it can run a single line. Keep it to ASCII.
#
# WHY THE FOLDER IS NOT THE WHOLE TRUTH
# About a third of the tests belong to two levels at once. A test proving
# a deactivated listing vanishes from search is integration AND
# regression. A folder holds a file once, so each file sits under the
# level it mostly covers and keeps its own markers. The markers remain
# the accurate record:
#
#     pytest -m integration
#
# The README in each folder names the other levels its tests cover.

$ErrorActionPreference = 'Stop'

try {

$tests  = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$suites = Join-Path $tests 'suites'

if (-not (Test-Path $suites)) {
    throw "No suites folder at $suites - run this from the tests\tools folder."
}

Write-Host ''
Write-Host '  Organising the suite by test level' -ForegroundColor Cyan
Write-Host '  ----------------------------------' -ForegroundColor Cyan
Write-Host ''

$plan = @(
  @{ File='test_smoke.py';      Folder='00-smoke';       Primary='Smoke';
     Covers='Proves the suite is wired to the application. Run first; if it fails, every other result is meaningless.' }

  @{ File='test_campuses.py';   Folder='01-ui';          Primary='UI';
     Covers='Also integration - several tests change data and require the page to follow.' }

  @{ File='test_behaviour.py';  Folder='01-ui';          Primary='UI';
     Covers='Accessibility, responsive layout and fallback. Also integration and functional.' }

  @{ File='test_search.py';     Folder='02-functional';  Primary='Functional';
     Covers='Also integration (results compared against the listings table) and security (the clamped rent parameter).' }

  @{ File='test_listings.py';   Folder='02-functional';  Primary='Functional';
     Covers='Also integration and security.' }

  @{ File='test_shortlist.py';  Folder='02-functional';  Primary='Functional';
     Covers='Also UI and end-to-end.' }

  @{ File='test_validation.py'; Folder='02-functional';  Primary='Functional';
     Covers='The viewing-request rules. Three tests here are expected to fail: DEF-01, DEF-02, DEF-03.' }

  @{ File='test_calculator.py'; Folder='03-logic';       Primary='Logic';
     Covers='Pure calculation - the cost estimator. Also functional and UI.' }

  @{ File='test_submission.py'; Folder='04-integration'; Primary='Integration';
     Covers='The write path. Also functional and UI.' }

  @{ File='test_security.py';   Folder='05-security';    Primary='Security';
     Covers='CSRF, injection, escaping, disclosure. Two tests here are expected to fail: DEF-04, DEF-05.' }
)

# --- move the files --------------------------------------------------

$moved = 0
$already = 0

foreach ($item in $plan) {
    $target = Join-Path $suites $item.Folder
    if (-not (Test-Path $target)) {
        New-Item -ItemType Directory -Path $target | Out-Null
    }

    $from = Join-Path $suites $item.File
    $to   = Join-Path $target $item.File

    if (Test-Path $to) {
        Write-Host ('  already placed : ' + $item.Folder + '\' + $item.File) -ForegroundColor DarkGray
        $already++
        continue
    }
    if (-not (Test-Path $from)) {
        Write-Host ('  not found      : ' + $item.File) -ForegroundColor Yellow
        continue
    }

    Move-Item -LiteralPath $from -Destination $to
    Write-Host ('  moved          : ' + $item.Folder + '\' + $item.File) -ForegroundColor Green
    $moved++
}

# --- write a README into each folder ---------------------------------
#
# Counting "def test_" is close enough for a README. The exact per-level
# figures come from layer_index.py, which reads the markers.

Write-Host ''
Write-Host '  Writing a README into each folder...'

$known = $plan | ForEach-Object { $_.Folder } | Select-Object -Unique

$folders = Get-ChildItem -Path $suites -Directory |
           Where-Object { $known -contains $_.Name } |
           Sort-Object Name

foreach ($folder in $folders) {

    $files = Get-ChildItem -Path $folder.FullName -Filter 'test_*.py' -File
    if ($files.Count -eq 0) { continue }

    $entry   = $plan | Where-Object { $_.Folder -eq $folder.Name } | Select-Object -First 1
    $primary = if ($entry) { $entry.Primary } else { $folder.Name }
    $marker  = $folder.Name -replace '^\d+-', ''

    $total = 0
    $rows  = New-Object System.Collections.Generic.List[string]

    foreach ($f in $files) {
        $text = Get-Content $f.FullName -Raw
        $n = ([regex]::Matches($text, '(?m)^\s*def\s+test_')).Count
        $total = $total + $n

        $note = $plan | Where-Object { $_.File -eq $f.Name } | Select-Object -First 1
        $covers = if ($note) { $note.Covers } else { '' }

        $rows.Add('| `' + $f.Name + '` | ' + $n + ' | ' + $covers + ' |')
    }

    $out = New-Object System.Collections.Generic.List[string]
    $out.Add('# ' + $primary + ' - ' + $total + ' test functions')
    $out.Add('')
    $out.Add('Part of the EMERGEX test suite. Run this level on its own with:')
    $out.Add('')
    $out.Add('    pytest -m ' + $marker)
    $out.Add('')
    $out.Add('That command uses the markers rather than this folder, so it')
    $out.Add('returns every test at this level including any filed elsewhere.')
    $out.Add('')
    $out.Add('| File | Test functions | Also covers |')
    $out.Add('| --- | ---: | --- |')
    foreach ($r in $rows) { $out.Add($r) }
    $out.Add('')
    $out.Add('## Why the folder is not the whole picture')
    $out.Add('')
    $out.Add('About a third of the tests belong to two levels at once. A test')
    $out.Add('proving a deactivated listing vanishes from search is integration')
    $out.Add('AND regression. A folder holds a file once, so each file sits')
    $out.Add('under the level it mostly covers and keeps its own markers.')
    $out.Add('')
    $out.Add('The markers are the accurate record. For the full breakdown see')
    $out.Add('reports/layer-index.md, regenerated with:')
    $out.Add('')
    $out.Add('    python tools/layer_index.py')

    Set-Content -Path (Join-Path $folder.FullName 'README.md') -Value $out -Encoding UTF8

    Write-Host ('    ' + $folder.Name.PadRight(18) + $total + ' test functions')
}

Write-Host ''
Write-Host "  $moved moved, $already already in place." -ForegroundColor Green
Write-Host ''
Write-Host '  Check nothing broke:' -ForegroundColor Cyan
Write-Host '    pytest --collect-only -q'

}
catch {
    Write-Host ''
    Write-Host '  Something went wrong:' -ForegroundColor Red
    Write-Host ('  ' + $_.Exception.Message)
    Write-Host ''
    Write-Host ('  at line ' + $_.InvocationInfo.ScriptLineNumber + ': ' +
                $_.InvocationInfo.Line.Trim()) -ForegroundColor DarkGray
    Write-Host ''
    Write-Host '  Nothing is broken. Files already moved are where they should'
    Write-Host '  be, and re-running picks up where this stopped.'
}

Write-Host ''
Read-Host '  Press Enter to close'
