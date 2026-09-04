<?php
declare(strict_types=1);

namespace Oshim\Epp\Exceptions;

/**
 * Thrown on EPP authentication or authorization failure (codes 2200, 2201, 2202, 2501).
 */
class EppAuthException extends EppResponseException
{
}
