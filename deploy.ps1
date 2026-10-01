<#
.SYNOPSIS
    Deploy the Car Booking Notification System to the intranet IIS server.

.DESCRIPTION
    Builds the Vue admin SPA into Laravel's public/ folder, installs production
    composer dependencies into a staging copy, then mirrors the result to
    Z:\Intranet\PHP84\GA\car_rev_api.

    The destination .env is treated as the source of truth: it is backed up and
    left alone on every deploy after the first one. Pass -OverwriteEnv to push
    .env.production over it (APP_KEY is carried over).

.PARAMETER Destination
    Production folder. Defaults to Z:\Intranet\PHP84\GA\car_rev_api.

.PARAMETER SkipFrontendBuild
    Reuse the existing fontend/car_rev_font/dist instead of rebuilding.

.PARAMETER SkipComposer
    Copy the local vendor/ as-is instead of running composer install --no-dev.
    Faster, but ships dev dependencies.

.PARAMETER OverwriteEnv
    Replace the destination .env with .env.production (existing APP_KEY kept).

.PARAMETER Mirror
    Delete files at the destination that no longer exist in the build.
    .env and storage/ are never touched.

.PARAMETER Force
    Do not ask for confirmation before writing to the destination.

.PARAMETER DryRun
    Build and stage everything, print what would be copied, but write nothing
    to the destination.

.PARAMETER KeepStaging
    Leave the staging folder behind for inspection.

.EXAMPLE
    .\deploy.ps1 -DryRun

.EXAMPLE
    .\deploy.ps1 -Force
#>
[CmdletBinding()]
param(
    [string]$Destination = 'Z:\Intranet\PHP84\GA\car_rev_api',
    [switch]$SkipFrontendBuild,
    [switch]$SkipComposer,
    [switch]$OverwriteEnv,
    [switch]$Mirror,
    [switch]$Force,
    [switch]$DryRun,
    [switch]$KeepStaging
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

$script:StepNo = 0

function Write-Step {
    param([string]$Message)
    $script:StepNo++
    Write-Host ''
    Write-Host ("[{0}] {1}" -f $script:StepNo, $Message) -ForegroundColor Cyan
}

function Write-Info {
    param([string]$Message)
    Write-Host ("    {0}" -f $Message) -ForegroundColor Gray
}

function Write-Ok {
    param([string]$Message)
    Write-Host ("    OK  {0}" -f $Message) -ForegroundColor Green
}

function Write-Warn {
    param([string]$Message)
    Write-Host ("    !!  {0}" -f $Message) -ForegroundColor Yellow
}

function Stop-Deploy {
    param([string]$Message)
    Write-Host ''
    Write-Host ("DEPLOY FAILED: {0}" -f $Message) -ForegroundColor Red
    exit 1
}

# Writes UTF-8 without a BOM. Set-Content -Encoding utf8 adds one on Windows
# PowerShell 5.1, and a BOM in .env corrupts the first key.
function Set-Utf8File {
    param([string]$Path, [string]$Content)
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $Content, $utf8NoBom)
}

function Get-Utf8File {
    param([string]$Path)
    return [System.IO.File]::ReadAllText($Path, [System.Text.Encoding]::UTF8)
}

function Invoke-External {
    param(
        [string]$Command,
        [string[]]$Arguments,
        [string]$WorkingDirectory,
        [string]$What
    )

    Write-Info ("> {0} {1}" -f $Command, ($Arguments -join ' '))

    $previous = Get-Location
    try {
        if ($WorkingDirectory) { Set-Location -LiteralPath $WorkingDirectory }
        & $Command @Arguments
        if ($LASTEXITCODE -ne 0) {
            Stop-Deploy ("{0} exited with code {1}" -f $What, $LASTEXITCODE)
        }
    }
    finally {
        Set-Location -LiteralPath $previous
    }
}

# robocopy uses exit codes 0-7 for success, 8+ for failure.
function Invoke-Robocopy {
    param(
        [string]$Source,
        [string]$Target,
        [string[]]$ExtraArguments = @(),
        [string]$What
    )

    $arguments = @($Source, $Target, '/E', '/R:2', '/W:2', '/NFL', '/NDL', '/NJH', '/NJS', '/NP')
    $arguments += $ExtraArguments

    & robocopy.exe @arguments | Out-Null
    $code = $LASTEXITCODE
    if ($code -ge 8) {
        Stop-Deploy ("robocopy failed for {0} (exit code {1})" -f $What, $code)
    }
}

function Test-CommandExists {
    param([string]$Name)
    return [bool](Get-Command $Name -ErrorAction SilentlyContinue)
}

# ---------------------------------------------------------------------------
# Paths
# ---------------------------------------------------------------------------

$Root = $PSScriptRoot
$ApiSource = Join-Path $Root 'backend\car_rev_api'
$FontSource = Join-Path $Root 'fontend\car_rev_font'
$EnvTemplate = Join-Path $ApiSource '.env.production'
$DistDir = Join-Path $FontSource 'dist'
$Stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$Staging = Join-Path $env:TEMP ("car_rev_deploy-{0}" -f $Stamp)

Write-Host ''
Write-Host '===========================================================' -ForegroundColor White
Write-Host ' Car Booking Notification System - production deploy' -ForegroundColor White
Write-Host '===========================================================' -ForegroundColor White
Write-Host ("  Source      : {0}" -f $Root)
Write-Host ("  Destination : {0}" -f $Destination)
Write-Host ("  Staging     : {0}" -f $Staging)
if ($DryRun) { Write-Host '  Mode        : DRY RUN (destination is not written)' -ForegroundColor Yellow }
if ($Mirror) { Write-Host '  Mode        : MIRROR (stale files at destination are deleted)' -ForegroundColor Yellow }

# ---------------------------------------------------------------------------
# 1. Preflight
# ---------------------------------------------------------------------------

Write-Step 'Preflight checks'

foreach ($dir in @($ApiSource, $FontSource)) {
    if (-not (Test-Path -LiteralPath $dir)) { Stop-Deploy ("missing source folder: {0}" -f $dir) }
}
if (-not (Test-Path -LiteralPath $EnvTemplate)) { Stop-Deploy ("missing env template: {0}" -f $EnvTemplate) }

$required = @('php', 'robocopy')
if (-not $SkipComposer) { $required += 'composer' }
if (-not $SkipFrontendBuild) { $required += 'npm' }

foreach ($tool in $required) {
    if (-not (Test-CommandExists $tool)) { Stop-Deploy ("'{0}' was not found on PATH" -f $tool) }
    Write-Ok ("{0} found" -f $tool)
}

$destParent = Split-Path -Parent $Destination
if (-not (Test-Path -LiteralPath $destParent)) {
    Stop-Deploy ("destination parent is not reachable: {0} (is the Z: drive mapped?)" -f $destParent)
}
Write-Ok ("destination parent reachable: {0}" -f $destParent)

$destEnv = Join-Path $Destination '.env'
$destEnvExists = Test-Path -LiteralPath $destEnv
$isFirstDeploy = -not (Test-Path -LiteralPath (Join-Path $Destination 'artisan'))
if ($isFirstDeploy) {
    Write-Info 'first deploy to this folder'
} else {
    Write-Info 'existing installation detected'
}

# ---------------------------------------------------------------------------
# 2. Confirm
# ---------------------------------------------------------------------------

if (-not $Force -and -not $DryRun) {
    Write-Step 'Confirmation'
    Write-Host ("    About to deploy to PRODUCTION: {0}" -f $Destination) -ForegroundColor Yellow
    $answer = Read-Host '    Type YES to continue'
    if ($answer -cne 'YES') { Stop-Deploy 'cancelled by operator' }
}

# ---------------------------------------------------------------------------
# 3. Build the Vue admin SPA
# ---------------------------------------------------------------------------

if ($SkipFrontendBuild) {
    Write-Step 'Frontend build (skipped)'
    if (-not (Test-Path -LiteralPath (Join-Path $DistDir 'index.html'))) {
        Stop-Deploy 'no existing dist/index.html to reuse; drop -SkipFrontendBuild'
    }
    Write-Warn 'reusing the existing dist/ folder'
} else {
    Write-Step 'Building the Vue admin SPA (mode=production)'

    if (-not (Test-Path -LiteralPath (Join-Path $FontSource 'node_modules'))) {
        Write-Info 'node_modules missing, running npm ci'
        Invoke-External -Command 'npm' -Arguments @('ci') -WorkingDirectory $FontSource -What 'npm ci'
    }

    if (Test-Path -LiteralPath $DistDir) { Remove-Item -LiteralPath $DistDir -Recurse -Force }

    Invoke-External -Command 'npm' -Arguments @('run', 'build') -WorkingDirectory $FontSource -What 'npm run build'

    if (-not (Test-Path -LiteralPath (Join-Path $DistDir 'index.html'))) {
        Stop-Deploy 'the build produced no dist/index.html'
    }
    Write-Ok 'dist/ built'
}

# The base path baked into index.html must match where IIS serves the app from,
# otherwise every asset 404s.
$indexHtml = Get-Utf8File (Join-Path $DistDir 'index.html')
$expectedBase = '/intranet/php84/GA/car_rev_api/public/assets/'
if ($indexHtml -notmatch [regex]::Escape($expectedBase)) {
    Write-Warn ("dist/index.html does not reference {0}" -f $expectedBase)
    Write-Warn 'check VITE_BASE_PATH in fontend/car_rev_font/.env.production'
} else {
    Write-Ok 'asset base path matches the production URL'
}

# ---------------------------------------------------------------------------
# 4. Stage the Laravel application
# ---------------------------------------------------------------------------

Write-Step 'Staging the Laravel application'

New-Item -ItemType Directory -Path $Staging -Force | Out-Null

foreach ($dir in @('app', 'config', 'database', 'public', 'resources', 'routes')) {
    Invoke-Robocopy -Source (Join-Path $ApiSource $dir) -Target (Join-Path $Staging $dir) -What $dir
}

# bootstrap/cache holds locally generated caches; regenerate them at the destination.
Invoke-Robocopy -Source (Join-Path $ApiSource 'bootstrap') -Target (Join-Path $Staging 'bootstrap') `
    -ExtraArguments @('/XD', (Join-Path $ApiSource 'bootstrap\cache')) -What 'bootstrap'

foreach ($file in @('artisan', 'composer.json', 'composer.lock')) {
    Copy-Item -LiteralPath (Join-Path $ApiSource $file) -Destination (Join-Path $Staging $file) -Force
}

$writableDirs = @(
    'bootstrap\cache',
    'storage\app\private',
    'storage\app\public',
    'storage\framework\cache\data',
    'storage\framework\sessions',
    'storage\framework\views',
    'storage\logs'
)
foreach ($dir in $writableDirs) {
    New-Item -ItemType Directory -Path (Join-Path $Staging $dir) -Force | Out-Null
}
Write-Ok 'application files staged'

# composer scripts boot Laravel, which wants an .env present.
Copy-Item -LiteralPath $EnvTemplate -Destination (Join-Path $Staging '.env') -Force

# ---------------------------------------------------------------------------
# 5. Production dependencies
# ---------------------------------------------------------------------------

if ($SkipComposer) {
    Write-Step 'Composer dependencies (copying the local vendor/)'
    $localVendor = Join-Path $ApiSource 'vendor'
    if (-not (Test-Path -LiteralPath $localVendor)) { Stop-Deploy 'no local vendor/ to copy' }
    Write-Warn 'shipping dev dependencies; drop -SkipComposer for a clean production install'
    Invoke-Robocopy -Source $localVendor -Target (Join-Path $Staging 'vendor') -What 'vendor'
} else {
    Write-Step 'Composer dependencies (install --no-dev)'
    Invoke-External -Command 'composer' `
        -Arguments @('install', '--no-dev', '--optimize-autoloader', '--no-interaction', '--no-progress') `
        -WorkingDirectory $Staging -What 'composer install'
}
Write-Ok 'vendor/ ready'

# ---------------------------------------------------------------------------
# 6. Drop the SPA into public/
# ---------------------------------------------------------------------------

Write-Step 'Placing the SPA inside public/'

Invoke-Robocopy -Source $DistDir -Target (Join-Path $Staging 'public') -What 'dist -> public'

foreach ($file in @('index.php', 'index.html', 'web.config')) {
    if (-not (Test-Path -LiteralPath (Join-Path $Staging "public\$file"))) {
        Stop-Deploy ("public/{0} is missing from the staged build" -f $file)
    }
}
Write-Ok 'public/ contains index.php (API), index.html (SPA) and web.config'

# ---------------------------------------------------------------------------
# 7. Copy to the destination
# ---------------------------------------------------------------------------

Write-Step 'Copying to the destination'

if ($DryRun) {
    Write-Warn 'dry run: listing what would be copied, nothing is written'
    & robocopy.exe $Staging $Destination '/E' '/L' '/NJH' '/NJS' '/XF' '.env' | Out-Host
    Write-Host ''
    Write-Host 'Dry run finished. Staging kept at:' -ForegroundColor Yellow
    Write-Host ("  {0}" -f $Staging) -ForegroundColor Yellow
    exit 0
}

New-Item -ItemType Directory -Path $Destination -Force | Out-Null

# storage/ and bootstrap/cache at the destination hold live logs, sessions and
# caches. Never overwrite or delete them from the build.
$protectedDirs = @(
    (Join-Path $Staging 'storage'),
    (Join-Path $Staging 'bootstrap\cache')
)

$copyArgs = @('/XF', '.env', '/XD') + $protectedDirs
if ($Mirror) { $copyArgs += '/MIR' }

Invoke-Robocopy -Source $Staging -Target $Destination -ExtraArguments $copyArgs -What 'staging -> destination'
Write-Ok 'application files copied'

foreach ($dir in $writableDirs) {
    New-Item -ItemType Directory -Path (Join-Path $Destination $dir) -Force | Out-Null
}
Write-Ok 'writable folders ensured (storage/, bootstrap/cache)'

# ---------------------------------------------------------------------------
# 8. Environment file
# ---------------------------------------------------------------------------

Write-Step 'Configuring .env at the destination'

if ($destEnvExists) {
    $backup = Join-Path $Destination (".env.bak.{0}" -f $Stamp)
    Copy-Item -LiteralPath $destEnv -Destination $backup -Force
    Write-Ok ("backed up existing .env -> {0}" -f (Split-Path -Leaf $backup))
}

if (-not $destEnvExists -or $OverwriteEnv) {
    $envContent = Get-Utf8File $EnvTemplate

    # Carry the production APP_KEY across an -OverwriteEnv so sessions and
    # encrypted payloads survive the deploy.
    if ($destEnvExists) {
        $existing = Get-Utf8File $destEnv
        $match = [regex]::Match($existing, '(?m)^APP_KEY=(.*)$')
        if ($match.Success -and $match.Groups[1].Value.Trim()) {
            $envContent = [regex]::Replace(
                $envContent,
                '(?m)^APP_KEY=.*$',
                ('APP_KEY=' + $match.Groups[1].Value.Trim())
            )
            Write-Ok 'kept the existing APP_KEY'
        }
    }

    Set-Utf8File -Path $destEnv -Content $envContent
    Write-Ok '.env written from .env.production'
} else {
    Write-Info '.env already present, left untouched (use -OverwriteEnv to replace it)'
}

# Generated here rather than with `php artisan key:generate` because artisan
# cannot bootstrap against the mapped drive (see step 9). Same recipe Laravel
# uses: 32 random bytes, base64 encoded, for the AES-256 cipher.
$destEnvContent = Get-Utf8File $destEnv
$keyMatch = [regex]::Match($destEnvContent, '(?m)^APP_KEY=(.*)$')
if (-not $keyMatch.Success -or -not $keyMatch.Groups[1].Value.Trim()) {
    $bytes = New-Object byte[] 32
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $newKey = 'base64:' + [Convert]::ToBase64String($bytes)

    if ($keyMatch.Success) {
        $destEnvContent = [regex]::Replace($destEnvContent, '(?m)^APP_KEY=.*$', ('APP_KEY=' + $newKey))
    } else {
        $destEnvContent = $destEnvContent.TrimEnd() + "`r`nAPP_KEY=$newKey`r`n"
    }

    Set-Utf8File -Path $destEnv -Content $destEnvContent
    Write-Ok 'APP_KEY generated'
} else {
    Write-Ok 'APP_KEY already set'
}

# ---------------------------------------------------------------------------
# 9. Framework caches
# ---------------------------------------------------------------------------

# Artisan is deliberately NOT run against the destination:
#   * PHP's is_writable() returns false for a mapped network drive even when
#     writing works, so Laravel refuses to bootstrap from here.
#   * anything cached from this machine would bake "Z:\..." paths into the
#     files the web server reads, and the server sees those folders under a
#     different drive letter.
# Ship the two discovery manifests (they hold class names only, no paths) and
# leave the rest for the server to build at runtime. This matches how the other
# Laravel services under PHP84 are deployed.

Write-Step 'Framework caches'

$destCache = Join-Path $Destination 'bootstrap\cache'
foreach ($manifest in @('packages.php', 'services.php')) {
    $staged = Join-Path $Staging "bootstrap\cache\$manifest"
    if (Test-Path -LiteralPath $staged) {
        Copy-Item -LiteralPath $staged -Destination (Join-Path $destCache $manifest) -Force
        Write-Ok ("shipped bootstrap/cache/{0}" -f $manifest)
    } else {
        Write-Warn ("bootstrap/cache/{0} was not generated during staging" -f $manifest)
    }
}

# A cached config or route file left behind by an earlier deploy would shadow
# the .env we just wrote.
foreach ($stale in @('config.php', 'events.php', 'routes-v7.php')) {
    $stalePath = Join-Path $destCache $stale
    if (Test-Path -LiteralPath $stalePath) {
        Remove-Item -LiteralPath $stalePath -Force
        Write-Ok ("removed stale bootstrap/cache/{0}" -f $stale)
    }
}

$compiledViews = Join-Path $Destination 'storage\framework\views'
Get-ChildItem -LiteralPath $compiledViews -Filter '*.php' -ErrorAction SilentlyContinue |
    Remove-Item -Force -ErrorAction SilentlyContinue
Write-Ok 'compiled blade views cleared'

# ---------------------------------------------------------------------------
# 10. Post-deploy checks
# ---------------------------------------------------------------------------

Write-Step 'Post-deploy checks'

$m = [regex]::Match($destEnvContent, '(?m)^NOTI_APPROVAL_URL=(.*)$')
if (-not $m.Success -or -not $m.Groups[1].Value.Trim()) {
    # config/notification.php falls back to the portal, so the mail button still renders.
    Write-Info 'NOTI_APPROVAL_URL is empty, mail button links to https://e.summitautogroup.com/'
}

$m = [regex]::Match($destEnvContent, '(?m)^NOTI_MAIL_CC=(.*)$')
if (-not $m.Success -or -not $m.Groups[1].Value.Trim()) {
    Write-Warn 'NOTI_MAIL_CC is empty at the destination'
}

$appUrl = ''
$appUrlMatch = [regex]::Match($destEnvContent, '(?m)^APP_URL=(.*)$')
if ($appUrlMatch.Success) {
    $appUrl = $appUrlMatch.Groups[1].Value.Trim().TrimEnd('/')
    $healthUrl = "$appUrl/up"
    Write-Info ("GET {0}" -f $healthUrl)
    try {
        $response = Invoke-WebRequest -Uri $healthUrl -UseBasicParsing -TimeoutSec 20
        if ($response.StatusCode -eq 200) {
            Write-Ok 'health check passed'
        } else {
            Write-Warn ("health check returned HTTP {0}" -f $response.StatusCode)
        }
    }
    catch {
        Write-Warn ("health check could not reach the server: {0}" -f $_.Exception.Message)
        Write-Warn 'this is expected if the deploy machine cannot browse the intranet URL'
    }
}

# ---------------------------------------------------------------------------
# 11. Cleanup
# ---------------------------------------------------------------------------

if ($KeepStaging) {
    Write-Step 'Staging kept'
    Write-Info $Staging
} else {
    Write-Step 'Cleaning up staging'
    Remove-Item -LiteralPath $Staging -Recurse -Force -ErrorAction SilentlyContinue
    Write-Ok 'staging removed'
}

Write-Host ''
Write-Host '===========================================================' -ForegroundColor Green
Write-Host ' DEPLOY COMPLETE' -ForegroundColor Green
Write-Host '===========================================================' -ForegroundColor Green
Write-Host ("  Admin UI : {0}/" -f $appUrl)
Write-Host ("  API      : {0}/api/v1/plants" -f $appUrl)
Write-Host ("  Health   : {0}/up" -f $appUrl)
Write-Host ''

# robocopy leaves a non-zero $LASTEXITCODE behind even on success, so be
# explicit about the script's own exit code.
exit 0
