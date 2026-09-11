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
| o CORS nem entra em jogo. Isto cobre o caso de a API subir em domínio próprio
| (ex.: api.montecmococa.com.br).
*/
$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('MONTEC_CORS_ORIGINS', 'https://www.montecmococa.com.br')),
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
