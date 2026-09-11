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

    /** Serviço só de API: a raiz não deve anunciar o framework. */
    #[Test]
    public function a_raiz_nao_serve_pagina_do_framework(): void
    {
        $response = $this->get('/');

        $response->assertNotFound();
        $this->assertStringNotContainsString('Laravel', $response->getContent() ?: '');
    }
}
