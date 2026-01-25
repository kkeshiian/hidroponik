@echo off
title MQTT Listener - Hidroponik System
color 0A

echo ================================================
echo   MQTT Listener Service - Hidroponik System
echo ================================================
echo.
echo Starting MQTT Listener...
echo Press CTRL+C to stop the service
echo.

cd /d "%~dp0"

:loop
php artisan mqtt:listen

echo.
echo ================================================
echo Service stopped. Restarting in 5 seconds...
echo Press CTRL+C to cancel restart
echo ================================================
timeout /t 5
goto loop
