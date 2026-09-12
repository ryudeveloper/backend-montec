<?php

declare(strict_types=1);

namespace App\Support\Mail;

/**
 * Exige que produção declare para onde vão as mensagens dos formulários.
 *
 * Estes destinatários já tiveram valor padrão: as caixas reais da empresa. Num
 * ambiente de teste, homologação ou demonstração, um único envio bastava para
 * despachar currículo de candidato e conteúdo de denúncia — dado pessoal, e no
 * caso da ouvidoria dado sensível sob promessa de confidencialidade — para uma
 * caixa que ninguém naquele contexto deveria alcançar.
 *
 * O que torna isso grave é o silêncio: a aplicação responde 201, o formulário
 * agradece, e nada em lugar nenhum registra que a mensagem saiu para o endereço
 * errado.
 *
 * Sem valor padrão o sistema não consegue errar sozinho. Ele para, e diz qual
 * variável falta.
 */
final class RecipientGuard
{
    /** Variável de ambiente correspondente a cada destinatário. */
    private const ENV_KEYS = [
        'sales' => 'MONTEC_MAIL_SALES',
        'careers' => 'MONTEC_MAIL_CAREERS',
        'whistleblower' => 'MONTEC_MAIL_WHISTLEBLOWER',
    ];

    /**
     * @param  array<string, string|null>  $recipients
     */
    public function assertConfigured(string $environment, array $recipients): void
    {
        if ($environment !== 'production') {
            return;
        }

        $missing = [];

        foreach (self::ENV_KEYS as $key => $envKey) {
            $value = trim((string) ($recipients[$key] ?? ''));

            if ($value === '' || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                $missing[] = $envKey;
            }
        }

        if ($missing === []) {
            return;
        }

        throw new UnconfiguredRecipientException(
            'Destinatário de formulário não configurado em produção: '
            .implode(', ', $missing)
            .'. Declare cada um no .env com um endereço válido. Não há valor padrão '
            .'de propósito: um padrão silencioso mandaria currículo e conteúdo de '
            .'denúncia para uma caixa que este ambiente não deveria alcançar.'
        );
    }
}
