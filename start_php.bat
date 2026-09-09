@echo off
cd /d "%~dp0"
if not exist .env copy .env.example .env
set PHP_EXE=php
where php >nul 2>nul
if errorlevel 1 if exist "D:\XAMPP\php\php.exe" set PHP_EXE=D:\XAMPP\php\php.exe
"%PHP_EXE%" -S 127.0.0.1:8080 -t public public/index.php
