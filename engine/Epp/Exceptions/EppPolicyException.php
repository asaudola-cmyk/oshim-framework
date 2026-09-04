<?php
declare(strict_types=1);

namespace Oshim\Epp\Exceptions;

/**
 * Thrown when an operation violates registry policy (codes 2105, 2106, 2306, 2308).
 */
class EppPolicyException extends EppResponseException
{
}
