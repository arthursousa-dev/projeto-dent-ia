@echo off
rem Inicia o DENT IA com o servidor embutido do PHP. Feche esta janela (ou Ctrl+C) para parar.
cd /d "%~dp0"
if not exist .env (
    copy .env.example .env >nul
    echo Arquivo .env criado. Preencha DB_PASS com a senha do PostgreSQL e execute de novo.
    pause
    exit /b 1
)
set "APP_HOST=127.0.0.1"
set "APP_PORT=8000"
for /f "usebackq eol=# tokens=1,* delims==" %%a in (".env") do set "%%a=%%b"

sc query postgresql-x64-18 | findstr /c:"RUNNING" >nul || (
    echo O servico do PostgreSQL ^(postgresql-x64-18^) nao esta em execucao. Inicie-o em services.msc.
    pause
    exit /b 1
)

rem HTTPS: o Caddy (ferramentas\caddy.exe) fica na frente do PHP e atende em https://localhost.
set "HTTPS_ATIVO="
if exist ferramentas\caddy.exe (
    ferramentas\caddy.exe start --config Caddyfile >nul 2>&1
    netstat -ano | findstr /r /c:"127.0.0.1:443 .*LISTENING" >nul && set "HTTPS_ATIVO=1"
)

echo.
if defined HTTPS_ATIVO (
    echo DENT IA com HTTPS:         https://localhost
    echo   Aviso de "site nao seguro"? Rode uma vez: ferramentas\caddy.exe trust
) else (
    echo HTTPS nao subiu ^(falta ferramentas\caddy.exe ou a porta 443 esta ocupada^).
)
echo DENT IA sem HTTPS:         http://localhost:%APP_PORT%
if "%APP_HOST%"=="0.0.0.0" (
    echo Em outros dispositivos da rede, use um destes enderecos:
    for /f "tokens=2 delims=:" %%i in ('ipconfig ^| findstr /c:"IPv4"') do for /f "tokens=*" %%j in ("%%i") do echo    http://%%j:%APP_PORT%
)
echo.
php -S %APP_HOST%:%APP_PORT% router.php
if exist ferramentas\caddy.exe ferramentas\caddy.exe stop >nul 2>&1
