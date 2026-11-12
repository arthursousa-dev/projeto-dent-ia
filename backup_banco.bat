@echo off
rem Gera backup do banco em backups\ (formato custom do pg_dump, restaurado com pg_restore).
cd /d "%~dp0"
for /f "usebackq eol=# tokens=1,* delims==" %%a in (".env") do set "%%a=%%b"
set "PGHOST=%DB_HOST%"
set "PGPORT=%DB_PORT%"
set "PGUSER=%DB_USER%"
set "PGPASSWORD=%DB_PASS%"
if not exist backups mkdir backups
for /f %%t in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd_HHmmss"') do set "CARIMBO=%%t"
pg_dump -Fc -d "%DB_NAME%" -f "backups\%DB_NAME%_%CARIMBO%.dump" || exit /b 1
echo Backup salvo em backups\%DB_NAME%_%CARIMBO%.dump
