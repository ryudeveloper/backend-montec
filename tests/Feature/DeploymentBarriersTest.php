<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Barreiras contra exposição de arquivo sensível pela web.
 *
 * A proteção principal é de configuração: o Document Root do subdomínio aponta
 * para `public/`, e o resto do projeto fica fora do alcance da web. Isso depende
 * de UM acerto no painel da hospedagem.
 *
 * Estes arquivos são a rede embaixo disso. Se alguém apontar o Document Root
 * para a raiz do projeto — num redeploy, numa migração de servidor, por engano —
 * sem eles a URL `/.env` entrega credenciais de banco, de SMTP e da API, e
 * `/storage/app/private/resumes/` entrega os currículos dos candidatos.
 *
 * O teste existe porque estes arquivos são invisíveis no dia a dia: ninguém
 * percebe que sumiram até o dia em que fariam falta.
 */
final class DeploymentBarriersTest extends TestCase
{
    private function contents(string $relative): string
    {
        $path = dirname(__DIR__, 2).'/'.$relative;

        $this->assertFileExists($path, "A barreira {$relative} não existe.");

        return (string) file_get_contents($path);
    }

    /** @return list<array{string}> */
    public static function barriers(): array
    {
        return [['.htaccess'], ['storage/.htaccess']];
    }

    #[Test]
    #[DataProvider('barriers')]
    public function a_barreira_nega_tudo(string $file): void
    {
        $contents = $this->contents($file);

        // Apache 2.4 e 2.2: hospedagem compartilhada ainda roda as duas.
        $this->assertStringContainsString('Require all denied', $contents);
        $this->assertStringContainsString('Deny from all', $contents);
        $this->assertStringContainsString('Options -Indexes', $contents);
    }

    /** Sem HTTPS o cookie `secure` não viaja e o login falha em silêncio. */
    #[Test]
    public function o_document_root_forca_https(): void
    {
        $contents = $this->contents('public/.htaccess');

        $this->assertStringContainsString('RewriteCond %{HTTPS} !=on', $contents);
        // Proxy-aware: sem isto, CDN na frente cria laço de redirecionamento.
        $this->assertStringContainsString('X-Forwarded-Proto', $contents);
    }

    /**
     * O Let's Encrypt valida o domínio buscando um arquivo em http PURO, sob
     * /.well-known/acme-challenge/. Redirecionado para https, o AutoSSL do
     * cPanel falha na conferência local e não emite nada.
     *
     * Sem certificado válido o portal não abre: o cabeçalho HSTS deste mesmo
     * arquivo faz o navegador recusar certificado inválido sem oferecer
     * exceção. E a renovação acontece a cada 90 dias, sozinha — quem remover
     * esta linha não vê efeito nenhum hoje, e derruba o portal em três meses.
     */
    #[Test]
    public function a_validacao_do_certificado_nao_e_redirecionada(): void
    {
        $contents = $this->contents('public/.htaccess');

        $this->assertMatchesRegularExpression(
            '/RewriteCond\s+%\{REQUEST_URI\}\s+!\^\/\\\.well-known\/acme-challenge\//',
            $contents,
            'A exceção do ACME sumiu: a renovação do certificado vai falhar em silêncio.'
        );
    }

    /**
     * As páginas do portal passam pelo grupo `web`, que não tem o middleware de
     * cabeçalhos da API — elas dependem destes.
     */
    #[Test]
    public function o_document_root_define_cabecalhos_de_seguranca(): void
    {
        $contents = $this->contents('public/.htaccess');

        foreach ([
            'X-Content-Type-Options',
            'X-Frame-Options',
            'Referrer-Policy',
            'Strict-Transport-Security',
        ] as $header) {
            $this->assertStringContainsString($header, $contents, "Falta o cabeçalho {$header}.");
        }
    }

    /** Mesmo dentro de public/, estes arquivos nunca devem ser servidos. */
    #[Test]
    public function o_document_root_nega_arquivos_sensiveis(): void
    {
        $contents = $this->contents('public/.htaccess');

        foreach (['\.env', '\.sqlite', '\.log', 'composer\.'] as $pattern) {
            $this->assertStringContainsString($pattern, $contents);
        }
    }

    /** O empacotador precisa levar as três — e abortar se faltar alguma. */
    #[Test]
    public function o_empacotador_confere_as_barreiras(): void
    {
        $script = $this->contents('scripts/package-cpanel.sh');

        foreach (['.htaccess', 'storage/.htaccess', 'public/.htaccess'] as $barrier) {
            $this->assertStringContainsString("has '{$barrier}'", $script);
        }
    }
}
