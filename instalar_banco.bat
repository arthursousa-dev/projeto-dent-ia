@echo off
rem Cria o banco local e carrega schema + dados de demonstracao. Nao apaga nada: para se o banco ja existir.
cd /d "%~dp0"
if not exist .env (echo Crie o .env a partir do .env.example primeiro. & exit /b 1)
for /f "usebackq eol=# tokens=1,* delims==" %%a in (".env") do set "%%a=%%b"
set "PGHOST=%DB_HOST%"
set "PGPORT=%DB_PORT%"
set "PGUSER=%DB_USER%"
set "PGPASSWORD=%DB_PASS%"

createdb "%DB_NAME%" || (
    echo Nao foi possivel criar o banco "%DB_NAME%". Se ele ja existe, nada foi alterado.
    exit /b 1
)
psql -v ON_ERROR_STOP=1 -q -d "%DB_NAME%" -f database\schema.sql || exit /b 1
psql -v ON_ERROR_STOP=1 -q -d "%DB_NAME%" -f database\seed.sql || exit /b 1
psql -v ON_ERROR_STOP=1 -q -d "%DB_NAME%" -c "TRUNCATE auditoria_banco" || exit /b 1
rem (a carga inicial do seed nao entra no registro de alteracoes)
echo Banco "%DB_NAME%" criado com o schema e os dados de demonstracao.
