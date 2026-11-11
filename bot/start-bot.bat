@echo off
cd /d "%~dp0"
if not exist config.json (echo Copy config.example.json to config.json and fill it first & pause & exit)
if not exist node_modules (echo Installing packages... & call npm install)
node index.js
echo.
echo === BOT STOPPED ===
pause
