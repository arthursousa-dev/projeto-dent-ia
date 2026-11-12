#!/usr/bin/env bash
# Roda todas as verificações antes de aceitar uma mudança: sintaxe, análise estática, PHPUnit e os três scripts.
# Uso (Git Bash, na pasta do projeto):  bash scripts/verificar_tudo.sh [banco_de_teste]
# Banco padrão: dentia_limpo_teste (crie com: php scripts/criar_banco_teste.php). Nunca aponte para o dentia.
# Sai com código diferente de zero se qualquer etapa falhar.
cd "$(dirname "$0")/.." || exit 1
export DB_NAME="${1:-dentia_limpo_teste}"
case "$DB_NAME" in *_teste|*_test) ;; *) echo "Recusado: $DB_NAME não é banco de teste."; exit 1 ;; esac
PHP="${PHP:-php}"
falhas=0
etapa() { # etapa "nome" comando...
    local nome="$1"; shift
    if "$@" > /tmp/dentia_etapa.log 2>&1; then echo "[OK]     $nome"
    else echo "[FALHOU] $nome"; tail -15 /tmp/dentia_etapa.log; falhas=$((falhas + 1)); fi
}
sintaxe() { local erro=0; for f in *.php includes/*.php scripts/*.php tests/*.php app/Config/*.php; do "$PHP" -l "$f" > /dev/null 2>&1 || { echo "$f"; erro=1; }; done; return $erro; }

echo "Banco de teste: $DB_NAME"
etapa "sintaxe (php -l)" sintaxe
etapa "análise estática (PHPStan nível 5)" "$PHP" vendor/bin/phpstan analyse --memory-limit=1G --no-progress
etapa "PHPUnit" "$PHP" vendor/bin/phpunit
etapa "testar_fluxos_railway.php" "$PHP" scripts/testar_fluxos_railway.php
etapa "testar_melhorias.php" "$PHP" scripts/testar_melhorias.php
etapa "testar_seguranca.php" "$PHP" scripts/testar_seguranca.php
rm -f /tmp/dentia_etapa.log
[ "$falhas" -eq 0 ] && echo "TUDO VERDE" || echo "$falhas etapa(s) com falha"
exit "$falhas"
