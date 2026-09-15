@echo off
:: Queue worker for CKPN_FINAL Laravel application
:: Dijalankan oleh NSSM sebagai Windows Service
:: Ref: AGENTS.md - queue driver untuk job perhitungan batch

set PHP_BIN=D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe
set APP_DIR=D:\laragon\www\CKPN_FINAL

"%PHP_BIN%" "%APP_DIR%\artisan" queue:work --queue=ckpn-calculation,default --sleep=3 --tries=3 --timeout=600 --memory=512 --max-time=3600
