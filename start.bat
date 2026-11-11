@echo off
cd /d "%~dp0"

rem ---- PHP path (change it if yours is different) ----
set PHP=C:\xampp_lite_8_5\apps\php\php.exe
if not exist "%PHP%" set PHP=C:\xampp\php\php.exe
if not exist "%PHP%" (echo php.exe introuvable - change the PHP path in start.bat & pause & exit)
for %%I in ("%PHP%") do set PHPDIR=%%~dpI
(
echo extension_dir="%PHPDIR%ext"
echo extension=curl
echo extension=openssl
echo extension=pdo_sqlite
echo extension=sqlite3
echo extension=mbstring
echo extension=fileinfo
echo date.timezone=Africa/Tunis
echo file_uploads=On
echo upload_max_filesize=20M
echo post_max_size=25M
) > "%~dp0php.ini"

rem ---- 1) Tailscale Funnel (public link, stays on in the background) ----
set TS=C:\Program Files\Tailscale\tailscale.exe
if exist "%TS%" ("%TS%" funnel --bg 8000) else (echo Tailscale not found - skipping the public link)

rem ---- 2) Discord bot in its own window ----
if exist "%~dp0bot\config.json" start "NOVA Bot" /d "%~dp0bot" cmd /k start-bot.bat

rem ---- 3) The website ----
echo.
echo Website running on http://127.0.0.1:8000  (keep this window open)
"%PHP%" -c "%~dp0php.ini" -S 127.0.0.1:8000 "%~dp0router.php"
echo.
echo === SERVER STOPPED ===
pause