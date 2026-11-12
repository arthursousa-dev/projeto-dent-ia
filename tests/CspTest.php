<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * P2.1: a CSP não tem 'unsafe-inline' em script-src. Para isso continuar valendo, nenhuma página pode
 * voltar a ter JavaScript em atributos (onclick=...), links javascript: ou bloco <script> sem nonce.
 */
final class CspTest extends TestCase
{
    /** Páginas e includes que geram HTML. */
    private static function arquivos(): array
    {
        return array_merge(glob(__DIR__ . '/../*.php'), glob(__DIR__ . '/../includes/*.php'));
    }

    public function test_script_src_nao_aceita_inline_em_nenhuma_das_tres_definicoes(): void
    {
        foreach (['router.php', '.htaccess', 'includes/bootstrap_sessao.php'] as $arquivo) {
            preg_match_all("/script-src[^;\"]*/", file_get_contents(__DIR__ . '/../' . $arquivo), $m);
            $this->assertNotEmpty($m[0], "$arquivo deveria definir script-src.");
            foreach ($m[0] as $diretiva) $this->assertStringNotContainsString('unsafe-inline', $diretiva, $arquivo);
        }
        $this->assertStringContainsString("'nonce-", file_get_contents(__DIR__ . '/../includes/bootstrap_sessao.php'));
    }

    public function test_nenhuma_pagina_tem_javascript_em_atributo_ou_link(): void
    {
        $achados = [];
        foreach (self::arquivos() as $arquivo) {
            $codigo = file_get_contents($arquivo);
            if (preg_match_all('/\son[a-z]+\s*=\s*["\'][^"\']*["\']/i', $codigo, $m)) $achados[basename($arquivo)] = $m[0];
            if (preg_match('/href\s*=\s*["\']\s*javascript:/i', $codigo)) $achados[basename($arquivo)][] = 'href="javascript:"';
        }
        $this->assertSame([], $achados, 'Use addEventListener num bloco <script nonce> ou em js/*.js.');
    }

    public function test_todo_bloco_script_tem_nonce_arquivo_ou_e_so_dados(): void
    {
        $semNonce = [];
        foreach (self::arquivos() as $arquivo) {
            // Bloco de código = a tag de abertura sozinha na linha. (Comentário que cita a tag, <script src> e
            // <script type="application/json"> com os dados na mesma linha não entram.)
            preg_match_all('/^[ \t]*(<script\b[^>]*>)[ \t]*\r?$/mi', file_get_contents($arquivo), $m);
            foreach ($m[1] as $tag) {
                if (!str_contains($tag, 'cspNonce()')) $semNonce[] = basename($arquivo) . ': ' . $tag;
            }
        }
        $this->assertSame([], $semNonce, 'Bloco <script> sem nonce="<?= cspNonce() ?>" é bloqueado pelo navegador.');
    }
}
