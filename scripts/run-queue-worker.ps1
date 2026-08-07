<#
Runs `php artisan queue:work` in a self-healing loop so the OCR job (and any
future queued jobs) keep processing in the background. Restarts automatically
if the worker exits, whether from a crash or its own --max-time recycle.

Registered as a logon-triggered Scheduled Task ("AmsdenLaravelQueueWorker")
so it starts automatically whenever this user logs in.
#>

$ErrorActionPreference = "Continue"

$projectDir = "C:\Users\emman\Music\amsden-laravel"
$phpExe     = "C:\xampp\php\php.exe"
$logFile    = Join-Path $projectDir "storage\logs\queue-worker.log"

Set-Location -LiteralPath $projectDir

if (-not (Test-Path (Split-Path $logFile))) {
    New-Item -ItemType Directory -Path (Split-Path $logFile) -Force | Out-Null
}

while ($true) {
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Add-Content -Path $logFile -Value "[$timestamp] Starting queue:work" -Encoding ASCII

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
