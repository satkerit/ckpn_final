@echo off
title CKPN Queue Worker
cd /d D:\laragon\www\CKPN_FINAL

:loop
echo [%date% %time%] Starting queue worker...
php artisan queue:work database --queue=ckpn-calculation,default --sleep=3 --tries=3 --timeout=600 --memory=512 --max-jobs=500
echo [%date% %time%] Worker stopped. Restarting in 5 seconds...
timeout /t 5 /nobreak >nul
goto loop
