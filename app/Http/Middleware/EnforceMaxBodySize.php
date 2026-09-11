<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Recusa corpo acima do teto antes de qualquer parse.
 *
 * O PHP carrega o corpo inteiro em memória: um punhado de requisições de dezenas
 * de MB derruba o processo sem precisar de nenhuma falha de lógica. O currículo
 * em base64 infla ~33%, então o teto tem de acomodar isso e nada mais.
 *
 * Isto é defesa em profundidade, não a única: `post_max_size` no PHP e limite no
 * servidor web continuam valendo — este middleware é o que ainda responde JSON
 * com mensagem útil em vez de derrubar a conexão.
 */
final class EnforceMaxBodySize
{
    public function handle(Request $request, Closure $next): Response
    {
        $limit = $this->limitInBytes();
        $declared = (int) $request->server('CONTENT_LENGTH', 0);

        // Content-Length primeiro: rejeita sem sequer ler o corpo do socket.
        if ($declared > $limit) {
            return $this->tooLarge();
        }

        // Cliente pode mentir no Content-Length (ou omitir em chunked): confere
        // o tamanho real do que chegou.
        if (strlen($request->getContent()) > $limit) {
            return $this->tooLarge();
        }

        return $next($request);
    }

    private function limitInBytes(): int
    {
        /** @var int $resumeMax */
        $resumeMax = config('montec.resume.max_bytes');

        // Base64 infla 4/3. A folga cobre os campos de texto do formulário.
        return (int) ceil($resumeMax * 4 / 3) + 256 * 1024;
    }

    private function tooLarge(): Response
    {
        return response()->json([
            'message' => 'O conteúdo enviado é grande demais. Verifique o tamanho do arquivo.',
        ], 413);
    }
}
