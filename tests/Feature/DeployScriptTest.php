<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * O roteiro de deploy por git tem uma diferença sutil em relação ao pacote zip:
 * o git não versiona diretório vazio.
 *
 * Os diretórios de storage/framework sobrevivem ao clone porque cada um carrega
 * um .gitignore. O de currículos não carrega nenhum arquivo — num servidor novo
 * ele simplesmente não existe, e o disco `resumes` está configurado com
 * 'throw' => true.
 *
 * O sintoma é ruim de diagnosticar: tudo funciona, o portal abre, os outros dois
 * formulários respondem — e só o envio de currículo devolve 500, na cara do
 * candidato, com mensagem que não diz o que faltou.
 */
final class DeployScriptTest extends TestCase
{
    private function deployScript(): string
    {
        $path = dirname(__DIR__, 2).'/scripts/deploy.sh';

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** @return list<array{string}> */
    public static function runtimeDirectories(): array
    {
        return [
            ['storage/app/private/resumes'],
            ['storage/logs'],
            ['storage/framework/cache/data'],
            ['storage/framework/sessions'],
            ['storage/framework/views'],
            ['bootstrap/cache'],
        ];
    }

    #[Test]
    #[DataProvider('runtimeDirectories')]
    public function o_deploy_cria_os_diretorios_de_escrita(string $dir): void
    {
        $this->assertStringContainsString(
            $dir,
            $this->deployScript(),
            "O deploy não garante {$dir}. Num clone novo ele não existe e a escrita falha."
        );
    }

    /**
     * Criar não basta: em cPanel o processo do PHP roda como o usuário da conta,
     * e sem bit de escrita o Laravel falha no primeiro log.
     */
    #[Test]
    public function o_deploy_ajusta_as_permissoes_de_escrita(): void
    {
        $this->assertMatchesRegularExpression(
            '/chmod\s+-R\s+\S+\s+storage\s+bootstrap\/cache/',
            $this->deployScript()
        );
    }
}
