<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HealthCheckTest extends TestCase
{
    #[Test]
    public function o_health_check_responde(): void
    {
        $this->get('/up')->assertOk();
    }

    /**
     * Endereço inexistente não deve anunciar o framework.
     *
     * O caminho era a raiz, até ela passar a encaminhar para o portal. Precisa
     * ser um endereço que nenhuma rota atende — é aí que a página de erro
     * padrão do framework apareceria.
     */
    #[Test]
    public function endereco_inexistente_nao_serve_pagina_do_framework(): void
    {
        $response = $this->get('/nao-existe-em-lugar-nenhum');

        $response->assertNotFound();
        $this->assertStringNotContainsString('Laravel', $response->getContent() ?: '');
    }
}
