# Install CKPN Queue Worker sebagai Windows Service menggunakan NSSM
# Jalankan script ini sebagai Administrator
# Download NSSM dulu dari: https://nssm.cc/download

param(
    [string]$NssmPath = "C:\nssm\nssm.exe"
)

$ServiceName  = "CkpnQueueWorker"
$AppDir       = "D:\laragon\www\CKPN_FINAL"
$BatchFile    = "$AppDir\scripts\queue-worker.bat"
$LogDir       = "$AppDir\storage\logs"
$StdoutLog    = "$LogDir\queue-worker-stdout.log"
$StderrLog    = "$LogDir\queue-worker-stderr.log"

# Pastikan NSSM ada
if (-not (Test-Path $NssmPath)) {
    Write-Host "ERROR: NSSM tidak ditemukan di $NssmPath" -ForegroundColor Red
    Write-Host "Download dari https://nssm.cc/download lalu extract nssm.exe ke C:\nssm\" -ForegroundColor Yellow
    exit 1
}

# Hapus service lama jika ada
$existing = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
if ($existing) {
    Write-Host "Menghapus service lama '$ServiceName'..."
    & $NssmPath stop $ServiceName confirm
    & $NssmPath remove $ServiceName confirm
}

Write-Host "Menginstall service '$ServiceName'..."

# Install service
& $NssmPath install $ServiceName "cmd.exe" "/c `"$BatchFile`""

# Konfigurasi service
& $NssmPath set $ServiceName AppDirectory       $AppDir
& $NssmPath set $ServiceName DisplayName        "CKPN Queue Worker"
& $NssmPath set $ServiceName Description        "Laravel queue:work untuk job perhitungan CKPN"
& $NssmPath set $ServiceName Start              SERVICE_AUTO_START

# Logging stdout/stderr ke file
& $NssmPath set $ServiceName AppStdout          $StdoutLog
& $NssmPath set $ServiceName AppStderr          $StderrLog
& $NssmPath set $ServiceName AppRotateFiles     1
& $NssmPath set $ServiceName AppRotateBytes     10485760   # rotate tiap 10 MB

# Auto-restart jika crash, throttle 5 detik agar tidak loop terlalu cepat
& $NssmPath set $ServiceName AppThrottle        5000
& $NssmPath set $ServiceName AppRestartDelay    3000

# Jalankan service
Write-Host "Menjalankan service..."
& $NssmPath start $ServiceName

$svc = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
if ($svc -and $svc.Status -eq "Running") {
    Write-Host "Service '$ServiceName' berhasil berjalan." -ForegroundColor Green
    Write-Host "Log stdout : $StdoutLog"
    Write-Host "Log stderr : $StderrLog"
    Write-Host ""
    Write-Host "Perintah berguna:"
    Write-Host "  Stop    : nssm stop $ServiceName"
    Write-Host "  Start   : nssm start $ServiceName"
    Write-Host "  Restart : nssm restart $ServiceName"
    Write-Host "  Hapus   : nssm remove $ServiceName confirm"
} else {
    Write-Host "Service tidak berjalan, cek log di $StderrLog" -ForegroundColor Red
}
