<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Áreas do portal.
 *
 * As rotas declaram ÁREA, não papel. A diferença importa desde que `admin`
 * passou a abrir tudo: com papel na rota, um administrador levaria 403 em
 * `EnsureRole:hr` mesmo tendo permissão. Amarrando a rota à área, o mapa
 * papel → área vive num lugar só (User) e a rota não precisa saber dele.
 */
enum PortalArea: string
{
    case Resumes = 'resumes';
    case Reports = 'reports';
    case Audit = 'audit';
}
