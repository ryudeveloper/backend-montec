<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos de segurança nas respostas de API.
 *
 * `Referrer-Policy: no-referrer` (mais restritivo que o do site): estes endpoints
 * recebem dado sensível, e não há motivo para o navegador contar de onde veio.
 * A resposta também não deve ser cacheada em lugar nenhum — o corpo confirma
 * recebimento de currículo e de denúncia.
 */
final class ApiSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Cache-Control', 'no-store, max-age=0');
        // A API só devolve JSON: nenhuma política de conteúdo é necessária além
        // de proibir que a resposta seja tratada como documento.
        $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");

        $this->hideServerSignature($response);

        return $response;
    }

    /**
     * Remove a assinatura do servidor.
     *
     * `X-Powered-By` é injetado pelo PHP no nível do SAPI (`expose_php=On`),
     * DEPOIS de o Symfony montar a resposta — tirá-lo só do objeto de resposta
     * não resolve, e o teste unitário passa enquanto o servidor real vaza a
     * versão exata do PHP. Daí o `header_remove`.
     *
     * A solução definitiva é `expose_php=Off` no php.ini, que também cobre as
     * respostas que nem chegam ao Laravel (erro 500 do próprio PHP, por
     * exemplo). Isto aqui é a rede de proteção para quando o ini não é nosso.
     */
    private function hideServerSignature(Response $response): void
    {
        $response->headers->remove('X-Powered-By');

        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }
    }
}
