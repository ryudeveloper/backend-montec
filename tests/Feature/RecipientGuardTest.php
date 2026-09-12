<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Mail\RecipientGuard;
use App\Support\Mail\UnconfiguredRecipientException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Para onde vai a candidatura e para onde vai a denúncia não pode ter valor
 * padrão.
 *
 * A configuração antes caía nas caixas reais da empresa quando o .env não
 * declarava os destinatários. Num ambiente de teste isso entrega currículo de
 * candidato e conteúdo de denúncia — dado pessoal sensível, sob LGPD — a uma
 * caixa que ninguém naquele contexto deveria alcançar, e sem que nada no
 * sistema indique que aconteceu.
 *
 * Falhar no boot é ruidoso e chato. É também a única forma de o erro aparecer
 * antes de a primeira mensagem sair.
 */
final class RecipientGuardTest extends TestCase
{
    /** @var array<string, string> */
    private const COMPLETE = [
        'sales' => 'comercial@exemplo.test',
        'careers' => 'rh@exemplo.test',
        'whistleblower' => 'ouvidoria@exemplo.test',
    ];

    #[Test]
    public function em_producao_recusa_destinatario_ausente(): void
    {
        $this->expectException(UnconfiguredRecipientException::class);
        $this->expectExceptionMessageMatches('/MONTEC_MAIL_CAREERS/');

        (new RecipientGuard)->assertConfigured('production', [
            ...self::COMPLETE,
            'careers' => '',
        ]);
    }

    #[Test]
    public function em_producao_recusa_destinatario_so_com_espacos(): void
    {
        $this->expectException(UnconfiguredRecipientException::class);

        (new RecipientGuard)->assertConfigured('production', [
            ...self::COMPLETE,
            'whistleblower' => '   ',
        ]);
    }

    #[Test]
    public function em_producao_recusa_endereco_invalido(): void
    {
        // Um endereço malformado falha no envio — e a mensagem se perde em
        // silêncio, que é exatamente o que este guard existe para impedir.
        $this->expectException(UnconfiguredRecipientException::class);

        (new RecipientGuard)->assertConfigured('production', [
            ...self::COMPLETE,
            'sales' => 'nao-e-um-email',
        ]);
    }

    #[Test]
    public function aponta_todos_os_ausentes_de_uma_vez(): void
    {
        $this->expectExceptionMessageMatches('/MONTEC_MAIL_SALES.*MONTEC_MAIL_WHISTLEBLOWER/s');

        (new RecipientGuard)->assertConfigured('production', [
            'sales' => '',
            'careers' => 'rh@exemplo.test',
            'whistleblower' => '',
        ]);
    }

    #[Test]
    public function aceita_configuracao_completa(): void
    {
        (new RecipientGuard)->assertConfigured('production', self::COMPLETE);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Em desenvolvimento o guard sai de cena: quem roda a suíte ou sobe o
     * servidor local não precisa declarar três endereços para ver uma tela.
     */
    #[Test]
    public function fora_de_producao_nao_interfere(): void
    {
        (new RecipientGuard)->assertConfigured('local', [
            'sales' => '',
            'careers' => '',
            'whistleblower' => '',
        ]);

        $this->expectNotToPerformAssertions();
    }
}
