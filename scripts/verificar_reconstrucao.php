<?php
// Compatibilidade: teste unificado de todos os módulos, sem persistir dados.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__.'/testar_fluxos_railway.php';
