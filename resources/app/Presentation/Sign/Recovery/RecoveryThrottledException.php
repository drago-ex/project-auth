<?php

declare(strict_types=1);

namespace App\Presentation\Sign\Recovery;

use RuntimeException;


/**
 * Thrown when a recovery code is requested too often or after too many failed attempts.
 */
class RecoveryThrottledException extends RuntimeException
{
}
