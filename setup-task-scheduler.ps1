# Setup CKPN Queue Worker sebagai Windows Scheduled Task
# Jalankan script ini sebagai Administrator sekali saja
# Usage: Right-click -> Run with PowerShell (as Administrator)

$taskName = "CKPN_QueueWorker"
$batFile  = "D:\laragon\www\CKPN_FINAL\queue-worker.bat"

# Hapus task lama jika ada
if (Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue) {
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
    Write-Host "Task lama '$taskName' dihapus."
}

# Action: jalankan batch script (minimized, bukan hidden agar bisa dipantau)
$action  = New-ScheduledTaskAction -Execute "cmd.exe" -Argument "/c `"$batFile`""

# Trigger: jalankan saat Windows startup
$trigger = New-ScheduledTaskTrigger -AtStartup

# Settings: restart jika gagal, jalankan terus
$settings = New-ScheduledTaskSettingsSet `
    -ExecutionTimeLimit (New-TimeSpan -Hours 0) `
    -RestartCount 99 `
    -RestartInterval (New-TimeSpan -Minutes 1) `
    -MultipleInstances IgnoreNew

# Principal: jalankan sebagai user saat ini, hanya ketika user login
$principal = New-ScheduledTaskPrincipal `
    -UserId $env:USERNAME `
    -LogonType Interactive `
    -RunLevel Highest

Register-ScheduledTask `
    -TaskName $taskName `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Principal $principal `
    -Description "CKPN Queue Worker - auto restart on Windows startup" | Out-Null

Write-Host "Task '$taskName' berhasil didaftarkan."
Write-Host "Queue worker akan otomatis jalan saat Windows startup."
Write-Host ""
Write-Host "Untuk menjalankan sekarang tanpa restart:"
Write-Host "  Start-ScheduledTask -TaskName '$taskName'"
Write-Host ""
Write-Host "Untuk menghentikan:"
Write-Host "  Stop-ScheduledTask -TaskName '$taskName'"
