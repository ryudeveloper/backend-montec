<?php

declare(strict_types=1);

namespace App\Domain\JobApplication;

use RuntimeException;

/** Currículo recusado na fronteira. A mensagem é segura para exibir ao usuário. */
final class InvalidResumeException extends RuntimeException {}
