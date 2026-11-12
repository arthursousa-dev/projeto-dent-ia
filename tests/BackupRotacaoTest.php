<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

/** P0.5: a rotação guarda 7 backups diários e 4 semanais e só apaga o que sobra. */
final class BackupRotacaoTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../scripts/backup.php';
    }

    /** Um backup por dia, terminando em 12/11/2026 (quinta-feira), do mais novo para o mais antigo. */
    private function diarios(int $dias): array
    {
        $nomes = [];
        for ($i = 0; $i < $dias; $i++) $nomes[] = 'auto_dentia_' . date('Ymd', strtotime("2026-11-12 -$i days")) . '_020000.dump';
        return $nomes;
    }

    public function test_sessenta_dias_viram_sete_diarios_e_quatro_semanais(): void
    {
        $todos = $this->diarios(60);
        $ficam = array_values(array_diff($todos, backupsParaApagar($todos)));

        $this->assertCount(11, $ficam);
        $this->assertSame(array_slice($todos, 0, 7), array_slice($ficam, 0, 7), 'os 7 dias mais recentes ficam');
        // Os 4 semanais são de semanas diferentes, todas anteriores aos diários.
        $semanas = array_map(fn($n) => date('o-W', strtotime(substr($n, 12, 8))), array_slice($ficam, 7));
        $this->assertCount(4, array_unique($semanas));
        $this->assertLessThan($ficam[6], $ficam[7]);
    }

    public function test_dois_backups_no_mesmo_dia_guardam_so_o_mais_novo(): void
    {
        $apagar = backupsParaApagar(['auto_dentia_20261112_020000.dump', 'auto_dentia_20261112_180000.dump']);
        $this->assertSame(['auto_dentia_20261112_020000.dump'], $apagar);
    }

    public function test_poucos_backups_e_arquivos_manuais_nunca_sao_apagados(): void
    {
        $this->assertSame([], backupsParaApagar($this->diarios(7)));
        $this->assertSame([], backupsParaApagar(['dentia_20261027_172215.dump', 'dentia_antes_migracao_20261112_041339.dump']));
    }
}
