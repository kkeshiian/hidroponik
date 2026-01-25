@echo off
title MQTT Listener Service Installer
color 0B

echo ================================================
echo   Install MQTT Listener as Windows Service
echo ================================================
echo.
echo This script will install MQTT Listener as a Windows service
echo that runs automatically when your computer starts.
echo.
echo Prerequisites:
echo - NSSM (Non-Sucking Service Manager) must be downloaded
echo - Download from: https://nssm.cc/download
echo - Extract nssm.exe to this folder or add to PATH
echo.
pause

set SERVICE_NAME=HidroponikMQTTListener
set PROJECT_DIR=%~dp0
set PHP_PATH=C:\laragon\bin\php\php-8.2.4-nts-Win32-vs16-x64\php.exe

echo.
echo Detecting PHP path...
if not exist "%PHP_PATH%" (
    echo PHP not found at: %PHP_PATH%
    echo Please update PHP_PATH in this script with your PHP installation path
    echo.
    echo Common Laragon PHP paths:
    dir /b C:\laragon\bin\php 2>nul
    echo.
    pause
    exit /b 1
)

echo PHP found: %PHP_PATH%
echo Project directory: %PROJECT_DIR%
echo.

echo Installing service...
nssm install %SERVICE_NAME% "%PHP_PATH%" "artisan mqtt:listen"
nssm set %SERVICE_NAME% AppDirectory "%PROJECT_DIR%"
nssm set %SERVICE_NAME% DisplayName "Hidroponik MQTT Listener"
nssm set %SERVICE_NAME% Description "Background service to listen MQTT messages and save to database"
nssm set %SERVICE_NAME% Start SERVICE_AUTO_START
nssm set %SERVICE_NAME% AppStdout "%PROJECT_DIR%storage\logs\mqtt-listener.log"
nssm set %SERVICE_NAME% AppStderr "%PROJECT_DIR%storage\logs\mqtt-listener-error.log"
nssm set %SERVICE_NAME% AppRotateFiles 1
nssm set %SERVICE_NAME% AppRotateOnline 1
nssm set %SERVICE_NAME% AppRotateBytes 1048576

echo.
echo ================================================
echo Service installed successfully!
echo ================================================
echo.
echo To start the service, run:
echo   nssm start %SERVICE_NAME%
echo.
echo To stop the service:
echo   nssm stop %SERVICE_NAME%
echo.
echo To remove the service:
echo   nssm remove %SERVICE_NAME% confirm
echo.
echo To check service status:
echo   nssm status %SERVICE_NAME%
echo.
pause

echo.
echo Do you want to start the service now? (Y/N)
set /p START_NOW=
if /i "%START_NOW%"=="Y" (
    nssm start %SERVICE_NAME%
    echo Service started!
) else (
    echo Service not started. Start it manually when ready.
)

echo.
pause
