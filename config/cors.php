<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| CORS
|------------------------------------------------------------------------------
| Origem fechada por configuração. `*` aqui deixaria qualquer site postar nos
| formulários usando o navegador do visitante como ponte.
|
| O frontend atual chama /api/* em caminho relativo, ou seja, mesma origem — aí
| o CORS nem entra em jogo. Isto cobre o caso de a API subir em domínio próprio.
|
| Sem padrão: lista vazia recusa toda origem cruzada. Um padrão apontando para
| um domínio específico esconde o esquecimento, porque tudo continua respondendo
| — só que autorizando um site que não é o deste ambiente.
|
| A comparação é LITERAL: esquema, host e porta precisam bater exatamente.
| `https://site.com` e `https://www.site.com` são origens diferentes.
*/
$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('MONTEC_CORS_ORIGINS', '')),
)));

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['POST', 'OPTIONS'],
    'allowed_origins' => $origins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Accept', 'X-Requested-With'],
    'exposed_headers' => ['Retry-After'],
    'max_age' => 3600,
    // Sem cookie nem sessão nesses endpoints: não há credencial a enviar.
    'supports_credentials' => false,
];
