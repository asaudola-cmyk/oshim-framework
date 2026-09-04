<?php
declare(strict_types=1);

namespace Oshim\Epp\Exceptions;

/**
 * Thrown when object status prohibits the requested operation (code 2304).
 */
class EppObjectStatusProhibitsException extends EppResponseException
{
}
