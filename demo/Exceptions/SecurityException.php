<?php

declare(strict_types=1);

namespace Demo\Exceptions;

use RuntimeException;

/**
 * Exceção lançada quando uma instrução SQL viola as regras de segurança do playground da demo.
 */
class SecurityException extends RuntimeException {}
