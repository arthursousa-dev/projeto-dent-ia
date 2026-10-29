@echo off
rem Encerra o servidor PHP do DENT IA que estiver escutando na porta configurada.
cd /d "%~dp0"
set "APP_PORT=8000"
if exist .env for /f "usebackq eol=# tokens=1,* delims==" %%a in (".env") do set "%%a=%%b"
set "ENCONTRADO="
for /f "tokens=5" %%p in ('netstat -ano ^| findstr /r /c:":%APP_PORT% .*LISTENING"') do (
    tasklist /fi "PID eq %%p" | findstr /i "php.exe" >nul && (taskkill /pid %%p /f >nul & set "ENCONTRADO=1")
)
if defined ENCONTRADO (echo DENT IA parado.) else (echo Nenhum servidor PHP do DENT IA na porta %APP_PORT%.)
