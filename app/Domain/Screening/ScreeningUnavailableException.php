<?php

declare(strict_types=1);

namespace App\Domain\Screening;

use RuntimeException;

/** Triagem indisponível. A mensagem é segura para exibir ao RH. */
final class ScreeningUnavailableException extends RuntimeException {}
