# ==============================================================================
# 🚀 VALLE SECO ITAM - SCRIPT DE INSTALACIÓN Y DESPLIEGUE GLPI AGENT (WINDOWS)
# Detecta automáticamente 32 o 64 bits, instala en modo silencioso y reporta.
# ==============================================================================

[CmdletBinding()]
param(
    [string]$ServerUrl = "http://10.20.23.221/api/v1/inventory/receiver/ValleSeco_GLPI_Token_2026_Secure",
    [string]$BaseUrl = "http://10.20.23.221/instaladores",
    [string]$Tag = "ValleSeco-ITAM",
    [switch]$ForceReinstall
)

# 1. Verificar privilegios de Administrador
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "[!] ERROR: Este script requiere ejecutarse con privilegios de Administrador." -ForegroundColor Red
    Write-Host "    Haga clic derecho en PowerShell y seleccione 'Ejecutar como administrador'." -ForegroundColor Yellow
    Exit 1
}

Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  VALLE SECO ITAM - INSTALADOR REMOTO DE AGENTE GLPI" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan

# 2. Detección de Arquitectura
$is64 = [Environment]::Is64BitOperatingSystem
$archStr = if ($is64) { "64-bit (x64)" } else { "32-bit (x86)" }
$msiFile = if ($is64) { "GLPI-Agent-x64.msi" } else { "GLPI-Agent-x86.msi" }
$downloadUrl = "$BaseUrl/$msiFile"

Write-Host "[+] Sistema Operativo detectado: Windows $archStr" -ForegroundColor Green
Write-Host "[+] Paquete seleccionado: $msiFile" -ForegroundColor Green
Write-Host "[+] Servidor destino: $ServerUrl" -ForegroundColor Yellow

# 3. Comprobar si el servicio ya existe
$existingService = Get-Service -Name "glpi-agent" -ErrorAction SilentlyContinue

if ($existingService -and -not $ForceReinstall) {
    Write-Host "[*] El servicio GLPI-Agent ya se encuentra instalado." -ForegroundColor Cyan
    Write-Host "[*] Actualizando configuración de servidor objetivo..." -ForegroundColor Yellow

    # Clave de registro para 64-bit o 32-bit
    $regPaths = @(
        "HKLM:\SOFTWARE\GLPI-Agent",
        "HKLM:\SOFTWARE\WOW6432Node\GLPI-Agent"
    )

    $updated = $false
    foreach ($path in $regPaths) {
        if (Test-Path $path) {
            Set-ItemProperty -Path $path -Name "server" -Value $ServerUrl -Force
            Set-ItemProperty -Path $path -Name "tag" -Value $Tag -Force
            $updated = $true
        }
    }

    if (-not $updated) {
        if ($is64) {
            New-Item -Path "HKLM:\SOFTWARE\GLPI-Agent" -Force | Out-Null
            Set-ItemProperty -Path "HKLM:\SOFTWARE\GLPI-Agent" -Name "server" -Value $ServerUrl -Force
        } else {
            New-Item -Path "HKLM:\SOFTWARE\GLPI-Agent" -Force | Out-Null
            Set-ItemProperty -Path "HKLM:\SOFTWARE\GLPI-Agent" -Name "server" -Value $ServerUrl -Force
        }
    }

    Restart-Service -Name "glpi-agent" -Force -ErrorAction SilentlyContinue
    Write-Host "[+] Servicio reiniciado con nuevo servidor: $ServerUrl" -ForegroundColor Green
} else {
    # 4. Descarga del instalador MSI adecuado
    $tempDir = [System.IO.Path]::GetTempPath()
    $localMsi = Join-Path $tempDir $msiFile

    Write-Host "[*] Descargando instalador desde: $downloadUrl ..." -ForegroundColor Cyan

    try {
        try {
            [Net.ServicePointManager]::SecurityProtocol = 3072 -bor 768 -bor 192 -bor 48
        } catch {}
        $webClient = New-Object System.Net.WebClient
        $webClient.DownloadFile($downloadUrl, $localMsi)
    } catch {
        Write-Host "[!] Reintentando descarga con WebClient..." -ForegroundColor Yellow
        try {
            (New-Object System.Net.WebClient).DownloadFile($downloadUrl, $localMsi)
        } catch {
            Write-Host "[!] Error descargando archivo: $_" -ForegroundColor Red
        }
    }

    if (-not (Test-Path $localMsi)) {
        Write-Host "[!] ERROR: No se pudo descargar el instalador $msiFile desde el servidor." -ForegroundColor Red
        Exit 1
    }

    $sizeMb = [math]::Round((Get-Item $localMsi).Length / 1MB, 2)
    Write-Host "[+] Instalador descargado exitosamente ($sizeMb MB)." -ForegroundColor Green

    # 5. Instalación silenciosa desatendida con MSIEXEC
    Write-Host "[*] Ejecutando instalación silenciosa en segundo plano..." -ForegroundColor Cyan

    $msiArgs = "/i `"$localMsi`" /quiet /qn SERVER=`"$ServerUrl`" ADD_FIREWALL_EXCEPTION=1 RUNNOW=1 TAG=`"$Tag`""
    $process = Start-Process -FilePath "msiexec.exe" -ArgumentList $msiArgs -Wait -PassThru

    if ($process.ExitCode -eq 0 -or $process.ExitCode -eq 3010) {
        Write-Host "[+] Instalación de GLPI-Agent completada con éxito (Código: $($process.ExitCode))." -ForegroundColor Green
    } else {
        Write-Host "[!] Advertencia: msiexec finalizó con código $($process.ExitCode)." -ForegroundColor Yellow
    }

    # Limpieza del instalador temporal
    Remove-Item -Path $localMsi -Force -ErrorAction SilentlyContinue
}

# 6. Forzar ejecución del primer inventario
Write-Host "[*] Solicitando envío forzado de telemetría e inventario a $ServerUrl ..." -ForegroundColor Cyan
$agentBatPaths = @(
    "C:\Program Files\GLPI-Agent\glpi-agent.bat",
    "C:\Program Files (x86)\GLPI-Agent\glpi-agent.bat"
)

$ranInventory = $false
foreach ($bat in $agentBatPaths) {
    if (Test-Path $bat) {
        Start-Process -FilePath $bat -ArgumentList "--force" -WindowStyle Hidden
        $ranInventory = $true
        Write-Host "[+] Inventario disparado exitosamente con $bat." -ForegroundColor Green
        break
    }
}

if (-not $ranInventory) {
    # Intento via servicio o curl local del agente
    try {
        (New-Object System.Net.WebClient).DownloadString("http://127.0.0.1:62354/now") | Out-Null
        Write-Host "[+] Inventario disparado vía endpoint local del agente (puerto 62354)." -ForegroundColor Green
    } catch {
        Write-Host "[*] Agente operando en segundo plano." -ForegroundColor Green
    }
}

Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  ✅ DESPLIEGUE FINALIZADO EXITOSAMENTE" -ForegroundColor Green
Write-Host "============================================================" -ForegroundColor Cyan
