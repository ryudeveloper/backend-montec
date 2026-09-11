<?php

declare(strict_types=1);

namespace App\Domain\Screening;

use RuntimeException;

/** Currículo que não dá para ler. A mensagem é segura para exibir ao RH. */
final class UnreadableResumeException extends RuntimeException {}
