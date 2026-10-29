<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

final class PaginacaoTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
    }

    public function test_fatia_a_pagina_pedida(): void
    {
        $_GET['pagina'] = '2';
        $pag = paginarLista(range(1, 45), 20);

        $this->assertSame(range(21, 40), $pag['itens']);
        $this->assertSame(['pagina' => 2, 'total_paginas' => 3, 'total' => 45], array_diff_key($pag, ['itens' => 1]));
    }

    public function test_pagina_fora_do_intervalo_vira_a_primeira_ou_a_ultima(): void
    {
        $_GET['pagina'] = '99';
        $this->assertSame(3, paginarLista(range(1, 45), 20)['pagina']);
        $_GET['pagina'] = 'abc';
        $this->assertSame(1, paginarLista(range(1, 45), 20)['pagina']);
        $this->assertSame(1, paginarLista([], 20)['total_paginas']);
    }

    public function test_controles_preservam_a_busca_e_escapam_o_html(): void
    {
        $_GET = ['busca' => 'ana"><script>', 'pagina' => '2'];
        $html = controlesPaginacao(paginarLista(range(1, 45), 20));

        $this->assertStringContainsString('aria-current="page">2<', $html);
        $this->assertStringContainsString('busca=ana%22%3E%3Cscript%3E&amp;pagina=3', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertSame('', controlesPaginacao(paginarLista(range(1, 5), 20)), 'uma página só não mostra controles');
    }
}
