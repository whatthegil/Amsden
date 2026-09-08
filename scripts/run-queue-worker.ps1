<#
Runs `php artisan queue:work` in a self-healing loop so the OCR job (and any
future queued jobs) keep processing in the background. Restarts automatically
if the worker exits, whether from a crash or its own --max-time recycle.

Registered as a logon-triggered Scheduled Task ("AmsdenLaravelQueueWorker")
so it starts automatically whenever this user logs in.
#>

$ErrorActionPreference = "Continue"

$projectDir = "C:\Users\emman\Music\amsden-laravel"
$logFile    = Join-Path $projectDir "storage\logs\queue-worker.log"

# The PHP binary is resolved at run time rather than pinned to a path. The 8.5
# upgrade retired the XAMPP build this used to point at, and a dead hardcoded
# path turns the self-healing loop below into a self-repeating failure. An
# explicit PHP_BINARY wins; otherwise take whatever php.exe is on PATH.
function Resolve-PhpExe {
    if ($env:PHP_BINARY -and (Test-Path -LiteralPath $env:PHP_BINARY)) { return $env:PHP_BINARY }
    $cmd = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    return $null
}

Set-Location -LiteralPath $projectDir

if (-not (Test-Path (Split-Path $logFile))) {
    New-Item -ItemType Directory -Path (Split-Path $logFile) -Force | Out-Null
}

while ($true) {
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"

    $phpExe = Resolve-PhpExe
    if (-not $phpExe) {
        # Back off harder than the usual restart: without a PHP binary every
        # iteration fails instantly, and a 5s loop would flood the log.
        Add-Content -Path $logFile -Value "[$timestamp] No php.exe found - set PHP_BINARY or add PHP to PATH - retrying in 60s" -Encoding ASCII
        Start-Sleep -Seconds 60
        continue
    }

    Add-Content -Path $logFile -Value "[$timestamp] Starting queue:work ($phpExe)" -Encoding ASCII

    # Run via cmd.exe's native ">>" redirection rather than a PowerShell
    # pipeline. A piped "| Add-Content" keeps the file open exclusively for
    # as long as queue:work runs (i.e. almost always), locking the log out
    # of any other reader the whole time. cmd's ">>" uses plain Win32 append
    # semantics with shared-read access, and writes php's ASCII console
    # output byte-for-byte with no PowerShell re-encoding (which previously
    # corrupted it into letter-spaced garbage).
    $cmdLine = "`"$phpExe`" artisan queue:work --sleep=3 --tries=3 --max-time=3600 >> `"$logFile`" 2>&1"
    & cmd.exe /c $cmdLine

    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Add-Content -Path $logFile -Value "[$timestamp] queue:work exited (code $LASTEXITCODE) - restarting in 5s" -Encoding ASCII
    Start-Sleep -Seconds 5
}
