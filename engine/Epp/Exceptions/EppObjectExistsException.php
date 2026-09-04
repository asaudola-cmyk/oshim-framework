<?php
declare(strict_types=1);

namespace Oshim\Epp\Exceptions;

/**
 * Thrown when an object already exists in the registry (code 2302).
 */
class EppObjectExistsException extends EppResponseException
{
}
