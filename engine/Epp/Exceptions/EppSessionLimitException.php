<?php
declare(strict_types=1);

namespace Oshim\Epp\Exceptions;

/**
 * Thrown when registry session limit is exceeded (code 2502).
 */
class EppSessionLimitException extends EppResponseException
{
}
