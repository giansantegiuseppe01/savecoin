<#
  SaveCoin - inicio automatico del servidor en Windows.

  Crea una tarea programada que arranca "php artisan serve" cada vez que se enciende la PC,
  sin iniciar sesion y sin abrir WAMP ni escribir comandos. Si el servidor se cae, Windows lo
  vuelve a levantar al minuto. Tambien abre el puerto en el Firewall de Windows.

  Uso (PowerShell COMO ADMINISTRADOR, desde la carpeta del proyecto):
      powershell -ExecutionPolicy Bypass -File .\scripts\windows\instalar-inicio-automatico.ps1
      powershell -ExecutionPolicy Bypass -File .\scripts\windows\instalar-inicio-automatico.ps1 -Puerto 8080
      powershell -ExecutionPolicy Bypass -File .\scripts\windows\instalar-inicio-automatico.ps1 -Quitar

  Antes de instalarlo, cierre cualquier "php artisan serve" que tenga abierto (usa el mismo puerto).
#>
#Requires -RunAsAdministrator
param(
    [switch]$Quitar,
    [int]$Puerto = 8000
)

$ErrorActionPreference = 'Stop'
$nombreTarea = 'SaveCoin Servidor'
$nombreRegla = "SaveCoin puerto $Puerto"
$raiz = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path

if ($Quitar) {
    Stop-ScheduledTask -TaskName $nombreTarea -ErrorAction SilentlyContinue
    Unregister-ScheduledTask -TaskName $nombreTarea -Confirm:$false -ErrorAction SilentlyContinue
    Remove-NetFirewallRule -DisplayName $nombreRegla -ErrorAction SilentlyContinue
    Write-Host 'Inicio automatico eliminado.'
    return
}

if (-not (Test-Path (Join-Path $raiz 'artisan'))) {
    throw "No encuentro el archivo artisan en $raiz. Ejecute el script desde el proyecto SaveCoin."
}

$php = (Get-Command php -ErrorAction SilentlyContinue).Source
if (-not $php) {
    throw 'No encuentro php.exe. Pruebe en esta misma ventana con: php -v. Si no funciona, agregue la carpeta de PHP (por ejemplo C:\wamp64\bin\php\php8.4.x) al PATH de Windows.'
}

Write-Host "Proyecto : $raiz"
Write-Host "PHP      : $php"
Write-Host "Puerto   : $Puerto"

# Se guarda la ruta completa de php.exe: la tarea corre como SYSTEM y no usa el PATH del usuario.
# Se ejecuta a traves de cmd para guardar lo que imprime el servidor (incluidos los errores)
# en storage\logs\servidor.log: si no arranca, ahi se ve el motivo.
$comando = "/c `"`"$php`" artisan serve --host=0.0.0.0 --port=$Puerto >> storage\logs\servidor.log 2>&1`""
$accion = New-ScheduledTaskAction -Execute 'cmd.exe' -Argument $comando -WorkingDirectory $raiz
$disparador = New-ScheduledTaskTrigger -AtStartup
$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
$config = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1) `
    -ExecutionTimeLimit ([TimeSpan]::Zero) `
    -MultipleInstances IgnoreNew

Register-ScheduledTask -TaskName $nombreTarea -Action $accion -Trigger $disparador `
    -Principal $principal -Settings $config -Force | Out-Null

if (-not (Get-NetFirewallRule -DisplayName $nombreRegla -ErrorAction SilentlyContinue)) {
    New-NetFirewallRule -DisplayName $nombreRegla -Direction Inbound -Protocol TCP `
        -LocalPort $Puerto -Action Allow | Out-Null
}

Start-ScheduledTask -TaskName $nombreTarea
Start-Sleep -Seconds 5

Write-Host ''
Write-Host 'Listo. El servidor ya esta corriendo y arrancara solo cada vez que encienda la PC.'
Write-Host "Prueba: abra http://localhost:$Puerto en el navegador."
Write-Host ''
Write-Host 'Recomendado para que no se apague solo: Configuracion > Sistema > Energia > Suspension = Nunca (con corriente).'
Write-Host 'Si no arranca, el motivo queda en: storage\logs\servidor.log'
Write-Host 'Para quitarlo: repita el comando con -Quitar'
