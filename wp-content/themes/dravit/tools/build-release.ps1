<#
.SYNOPSIS
    Dravit Theme Release Builder — Step 11.4
.DESCRIPTION
    Runs all quality gates then packages dravit-theme.zip
    for production deployment.
.NOTES
    Exit 0 = success, Exit 1 = failure
#>

$ErrorActionPreference = 'Stop'

$ThemeRoot = Split-Path -Parent $PSScriptRoot
$ZipPath  = Join-Path $ThemeRoot 'dravit-theme.zip'

# ── Colors ──────────────────────────────────────────────
function Write-Step  { param([string]$Msg) Write-Host "`n▶ $Msg" -ForegroundColor Cyan }
function Write-Pass  { param([string]$Msg) Write-Host "  ✓ $Msg" -ForegroundColor Green }
function Write-Fail  { param([string]$Msg) Write-Host "  ✗ $Msg" -ForegroundColor Red }
function Write-Warn  { param([string]$Msg) Write-Host "  ⚠ $Msg" -ForegroundColor Yellow }

# ── Helper: Run command and exit on failure ─────────────
function Invoke-Step {
    param(
        [string]$Label,
        [string]$Command,
        [string]$WorkDir = $ThemeRoot
    )

    Write-Step $Label

    Push-Location $WorkDir
    try {
        Invoke-Expression $Command
        if ($LASTEXITCODE -and $LASTEXITCODE -ne 0) {
            Write-Fail "$Label failed (exit code $LASTEXITCODE)"
            exit 1
        }
        Write-Pass "$Label passed"
    }
    catch {
        Write-Fail "$Label failed: $_"
        exit 1
    }
    finally {
        Pop-Location
    }
}

# ════════════════════════════════════════════════════════
#  QUALITY GATES
# ════════════════════════════════════════════════════════

Write-Host "`n╔══════════════════════════════════════╗" -ForegroundColor Magenta
Write-Host "║   DRAVIT THEME — Release Builder     ║" -ForegroundColor Magenta
Write-Host "╚══════════════════════════════════════╝" -ForegroundColor Magenta

# 1. PHP Tests
Invoke-Step 'PHPUnit Tests'    'composer test'

# 2. JS Tests
Invoke-Step 'Vitest Tests'     'npm test -- --run'

# 3. Lint (ESLint + Stylelint)
Invoke-Step 'Lint'             'npm run lint'

# 4. Vite Build
Invoke-Step 'Vite Build'       'npm run build'

# 5. Budget Check (graceful skip if script missing)
$BudgetScript = Join-Path $ThemeRoot 'tools\check-budgets.mjs'
if (Test-Path $BudgetScript) {
    Invoke-Step 'Budget Check'  "node `"$BudgetScript`""
}
else {
    Write-Step 'Budget Check'
    Write-Warn 'tools/check-budgets.mjs not found — skipping'
}

# ════════════════════════════════════════════════════════
#  PACKAGE
# ════════════════════════════════════════════════════════

Write-Step 'Creating dravit-theme.zip'

# Remove previous zip
if (Test-Path $ZipPath) {
    Remove-Item $ZipPath -Force
}

# ── Directories to EXCLUDE (relative, no trailing slash) ──
$ExcludeDirs = @(
    'node_modules'
    'vendor'
    'assets\src'
    'tests'
    'tools'
    'docs'
    '.agents'
    '.git'
    '.github'
    '.vscode'
)

# ── Root files to EXCLUDE (glob patterns matched against filename) ──
$ExcludeFiles = @(
    'phpunit.xml.dist'
    'phpstan.neon.dist'
    'phpcs.xml.dist'
    'composer.json'
    'composer.lock'
    'package.json'
    'package-lock.json'
    'vite.config.js'
    '.editorconfig'
    '.gitignore'
    '.phpunit.result.cache'
)

# File-name patterns (matched with -like)
$ExcludePatterns = @(
    '*.md'
    '.eslintrc*'
    '.stylelintrc*'
    'eslint.config.*'
)

# ── Collect files to include ──────────────────────────
$AllFiles = Get-ChildItem -Path $ThemeRoot -Recurse -File

$IncludeFiles = [System.Collections.Generic.List[System.IO.FileInfo]]::new()

foreach ($f in $AllFiles) {
    $RelPath = $f.FullName.Substring($ThemeRoot.Length + 1)

    # Check excluded directories
    $Skip = $false
    foreach ($dir in $ExcludeDirs) {
        if ($RelPath.StartsWith($dir + '\', [System.StringComparison]::OrdinalIgnoreCase) -or
            $RelPath -eq $dir) {
            $Skip = $true
            break
        }
    }
    if ($Skip) { continue }

    # Check excluded exact filenames (root-level)
    $FileName = $f.Name
    if ($FileName -in $ExcludeFiles) { continue }

    # Check excluded patterns
    $PatternMatch = $false
    foreach ($pat in $ExcludePatterns) {
        if ($FileName -like $pat) {
            $PatternMatch = $true
            break
        }
    }
    if ($PatternMatch) { continue }

    $IncludeFiles.Add($f)
}

Write-Pass "Collected $($IncludeFiles.Count) files"

# ── Build zip using .NET ZipFile ──────────────────────
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$ZipStream = [System.IO.File]::Create($ZipPath)
$Archive   = [System.IO.Compression.ZipArchive]::new($ZipStream, [System.IO.Compression.ZipArchiveMode]::Create)

try {
    foreach ($f in $IncludeFiles) {
        $RelPath  = $f.FullName.Substring($ThemeRoot.Length + 1)
        # Zip entries use forward slashes
        $EntryName = "dravit/$($RelPath -replace '\\','/')"
        $Entry     = $Archive.CreateEntry($EntryName, [System.IO.Compression.CompressionLevel]::Optimal)

        $EntryStream = $Entry.Open()
        try {
            $FileStream = [System.IO.File]::OpenRead($f.FullName)
            try {
                $FileStream.CopyTo($EntryStream)
            }
            finally {
                $FileStream.Dispose()
            }
        }
        finally {
            $EntryStream.Dispose()
        }
    }
}
finally {
    $Archive.Dispose()
    $ZipStream.Dispose()
}

Write-Pass "Zip created: $ZipPath"

# ════════════════════════════════════════════════════════
#  VERIFICATION
# ════════════════════════════════════════════════════════

Write-Step 'Verifying zip contents'

$ZipStream2 = [System.IO.File]::OpenRead($ZipPath)
$Archive2   = [System.IO.Compression.ZipArchive]::new($ZipStream2, [System.IO.Compression.ZipArchiveMode]::Read)

try {
    $Entries    = $Archive2.Entries
    $EntryCount = $Entries.Count

    # Forbidden path prefixes inside the zip (after "dravit/" prefix)
    $ForbiddenPrefixes = @(
        'dravit/node_modules/'
        'dravit/vendor/'
        'dravit/assets/src/'
        'dravit/tests/'
        'dravit/tools/'
        'dravit/.git/'
        'dravit/.agents/'
        'dravit/docs/'
    )

    $Violations = [System.Collections.Generic.List[string]]::new()

    foreach ($entry in $Entries) {
        foreach ($prefix in $ForbiddenPrefixes) {
            if ($entry.FullName.StartsWith($prefix, [System.StringComparison]::OrdinalIgnoreCase)) {
                $Violations.Add($entry.FullName)
                break
            }
        }
    }
}
finally {
    $Archive2.Dispose()
    $ZipStream2.Dispose()
}

# ── Results ───────────────────────────────────────────
$ZipSize     = (Get-Item $ZipPath).Length
$ZipSizeKB   = [math]::Round($ZipSize / 1024, 1)
$ZipSizeMB   = [math]::Round($ZipSize / 1048576, 2)

Write-Host ""
Write-Host "╔══════════════════════════════════════╗" -ForegroundColor Magenta
Write-Host "║         BUILD SUMMARY                ║" -ForegroundColor Magenta
Write-Host "╠══════════════════════════════════════╣" -ForegroundColor Magenta
Write-Host "║  Files in zip : $($EntryCount.ToString().PadLeft(6))               ║" -ForegroundColor White
Write-Host "║  Zip size     : $("$ZipSizeMB MB".PadLeft(6).PadRight(18)) ║" -ForegroundColor White

if ($Violations.Count -gt 0) {
    Write-Host "║  Status       : FAIL                 ║" -ForegroundColor Red
    Write-Host "╚══════════════════════════════════════╝" -ForegroundColor Magenta
    Write-Host ""
    Write-Fail "Found $($Violations.Count) forbidden entries:"
    foreach ($v in $Violations) {
        Write-Host "    - $v" -ForegroundColor Red
    }
    Write-Host ""
    Remove-Item $ZipPath -Force
    Write-Fail 'Zip deleted. Fix violations and retry.'
    exit 1
}

Write-Host "║  Status       : PASS ✓               ║" -ForegroundColor Green
Write-Host "╚══════════════════════════════════════╝" -ForegroundColor Magenta
Write-Host ""
Write-Pass "Release ready: $ZipPath ($ZipSizeMB MB)"
exit 0
