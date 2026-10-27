# Klyval — one-shot Supabase import.
#
# Usage (Windows PowerShell):
#   cd C:\Users\maxin\Downloads\klyval
#   .\deploy\supabase-import.ps1
# Optional upload of media files into the Storage bucket:
#   .\deploy\supabase-import.ps1 -Storage
#
# You need from the Supabase dashboard (project mbviyrwasfywxhuehfzf):
#   1. Database connection string  (Connect → "Direct" tab, password filled in)
#   2. service_role key            (Settings → API → service_role) — only needed for -Storage

param([switch]$Storage)

$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)

if (-not $env:DB_URI) { $env:DB_URI = Read-Host 'Paste the postgres connection string' }
$env:APP_ENV = 'local'; $env:APP_KEY = 'local-run-key'; $env:APP_TZ = 'UTC'; $env:APP_URL = 'http://localhost:3000'
$php = "$env:LOCALAPPDATA\klyval-tools\php83\php.exe"

Write-Host "== importing data ==" -ForegroundColor Cyan
& $php scripts\migrate-data\import-dump.php legacy-dump.sql
if ($LASTEXITCODE -ne 0) { throw "data import failed" }
& $php scripts\migrate-data\verify-import.php
if ($LASTEXITCODE -ne 0) { throw "verification failed" }

if ($Storage) {
    Write-Host "== uploading media to Storage ==" -ForegroundColor Cyan
    if (-not $env:SUPABASE_URL) { $env:SUPABASE_URL = Read-Host 'SUPABASE_URL (https://....supabase.co)' }
    if (-not $env:SUPABASE_SERVICE_ROLE) { $env:SUPABASE_SERVICE_ROLE = Read-Host 'SUPABASE_SERVICE_ROLE (service_role key)' }
    & $php qa\upload-media.php
    if ($LASTEXITCODE -ne 0) { throw "storage upload failed" }
}
Write-Host "DONE" -ForegroundColor Green
