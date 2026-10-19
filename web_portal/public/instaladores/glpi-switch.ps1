# ==============================================================================
# 🔄 VALLE SECO ITAM - CONMUTADOR DE ENTORNO (WINDOWS)
# Cambia el servidor destino de GLPI-Agent entre Desarrollo y Producción
# ==============================================================================

[CmdletBinding()]
param(
    [ValidateSet("dev", "prod")]
    [string]$Target = "prod"
)

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "[!] ERROR: Requiere ejecutarse como Administrador." -ForegroundColor Red
    Exit 1
}

$ServerUrl = if ($Target -eq "prod") {
    "http://10.20.23.252/api/v1/inventory/receiver/d8f58b76e1039c94f58a741362d2bc4a896f610e72c842b15cf4817d23a50e96"
} else {
    "http://10.20.23.221/api/v1/inventory/receiver/d8f58b76e1039c94f58a741362d2bc4a896f610e72c842b15cf4817d23a50e96"
}

Write-Host "[*] Conmutando agente GLPI hacia: $Target ($ServerUrl)..." -ForegroundColor Cyan

$regPaths = @(
    "HKLM:\SOFTWARE\GLPI-Agent",
    "HKLM:\SOFTWARE\WOW6432Node\GLPI-Agent"
)

foreach ($path in $regPaths) {
    if (Test-Path $path) {
        Set-ItemProperty -Path $path -Name "server" -Value $ServerUrl -Force
    }
}

Restart-Service -Name "glpi-agent" -Force -ErrorAction SilentlyContinue

$agentBatPaths = @(
    "C:\Program Files\GLPI-Agent\glpi-agent.bat",
    "C:\Program Files (x86)\GLPI-Agent\glpi-agent.bat"
)
foreach ($bat in $agentBatPaths) {
    if (Test-Path $bat) {
        Start-Process -FilePath $bat -ArgumentList "--force" -WindowStyle Hidden
        break
    }
}

Write-Host "[+] Agente GLPI conmutado exitosamente a $Target." -ForegroundColor Green
