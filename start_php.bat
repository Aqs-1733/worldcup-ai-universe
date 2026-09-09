@echo off
cd /d "%~dp0"
if not exist .env copy .env.example .env
php -S 127.0.0.1:8080 -t public public/index.php
