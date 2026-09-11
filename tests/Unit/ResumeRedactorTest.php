<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Screening\ResumeRedactor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Remove identificadores antes de o currículo sair para a IA.
 *
 * Duas razões, nesta ordem:
 *
 * 1. VIÉS. Nome, endereço e idade carregam sinais de gênero, origem e faixa
 *    etária. Um modelo que os lê pode correlacionar o que não tem relação com a
 *    vaga. O RH tem esses dados na tela ao lado — o modelo não precisa deles
 *    para avaliar experiência em solda.
 * 2. MINIMIZAÇÃO (LGPD). Só sai o necessário para a finalidade.
 *
 * A redação é imperfeita por natureza: nenhuma expressão regular pega tudo. Por
 * isso ela remove o que o sistema JÁ CONHECE do candidato (nome, e-mail,
 * telefone vindos do banco) e só então tenta padrões genéricos.
 */
final class ResumeRedactorTest extends TestCase
{
    private function redact(string $text, array $known = []): string
    {
        return (new ResumeRedactor)->redact($text, $known);
    }

    #[Test]
    public function remove_os_dados_que_o_sistema_ja_conhece(): void
    {
        $texto = "João Pereira da Silva\njoao.pereira@exemplo.com\n(19) 99888-7766\n\nSoldador com 8 anos.";

        $limpo = $this->redact($texto, [
            'name' => 'João Pereira da Silva',
            'email' => 'joao.pereira@exemplo.com',
            'phone' => '19998887766',
        ]);

        $this->assertStringNotContainsString('João Pereira', $limpo);
        $this->assertStringNotContainsString('joao.pereira@exemplo.com', $limpo);
        $this->assertStringNotContainsString('99888-7766', $limpo);
        // O que interessa para a vaga permanece.
        $this->assertStringContainsString('Soldador com 8 anos', $limpo);
    }

    /** O nome aparece de novo no corpo do texto, não só no topo. */
    #[Test]
    public function remove_todas_as_ocorrencias_do_nome(): void
    {
        $limpo = $this->redact(
            "João Pereira\n\nEu, João Pereira, atuei como soldador.",
            ['name' => 'João Pereira'],
        );

        $this->assertStringNotContainsString('João Pereira', $limpo);
        $this->assertStringContainsString('atuei como soldador', $limpo);
    }

    /** Partes do nome também vazam identidade — "Sr. Pereira". */
    #[Test]
    public function remove_partes_significativas_do_nome(): void
    {
        $limpo = $this->redact(
            'Referência: falar com Sr. Pereira no antigo emprego.',
            ['name' => 'João Pereira'],
        );

        $this->assertStringNotContainsString('Pereira', $limpo);
    }

    /** Preposição não é parte identificável e removê-la mutila o texto. */
    #[Test]
    public function nao_remove_preposicoes_do_nome(): void
    {
        $limpo = $this->redact(
            'Trabalhou na montagem de estruturas da planta.',
            ['name' => 'João da Silva'],
        );

        $this->assertStringContainsString('montagem de estruturas da planta', $limpo);
    }

    #[Test]
    public function remove_email_e_telefone_genericos(): void
    {
        $limpo = $this->redact('Contato: outro@dominio.com.br ou (11) 3456-7890.');

        $this->assertStringNotContainsString('outro@dominio.com.br', $limpo);
        $this->assertStringNotContainsString('3456-7890', $limpo);
    }

    #[Test]
    public function remove_cpf_e_rg(): void
    {
        $limpo = $this->redact('CPF 123.456.789-09 · RG 12.345.678-9');

        $this->assertStringNotContainsString('123.456.789-09', $limpo);
        $this->assertStringNotContainsString('12.345.678-9', $limpo);
    }

    #[Test]
    public function remove_data_de_nascimento_e_idade(): void
    {
        $limpo = $this->redact('Nascido em 14/03/1987, 38 anos, casado.');

        $this->assertStringNotContainsString('14/03/1987', $limpo);
        $this->assertStringNotContainsString('38 anos', $limpo);
    }

    /** Ano de experiência não é idade e precisa sobreviver. */
    #[Test]
    public function preserva_tempo_de_experiencia(): void
    {
        $limpo = $this->redact('8 anos de experiência em solda MIG. Atuou de 2015 a 2023.');

        $this->assertStringContainsString('8 anos de experiência', $limpo);
        $this->assertStringContainsString('2015 a 2023', $limpo);
    }

    #[Test]
    public function corta_texto_longo_demais(): void
    {
        $longo = str_repeat('solda ', 20000);

        $this->assertLessThanOrEqual(18000, strlen($this->redact($longo)));
    }
}
