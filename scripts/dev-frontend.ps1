$ErrorActionPreference = 'Stop'

$root = Split-Path -Path $PSScriptRoot -Parent
Set-Location (Join-Path $root 'frontend')

Write-Host "Frontend startet auf http://127.0.0.1:5173"
npm.cmd run dev -- --host 127.0.0.1 --port 5173

