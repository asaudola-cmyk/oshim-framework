<?php
declare(strict_types=1);

namespace Oshim\Epp\Exceptions;

/**
 * Thrown when an object does not exist in the registry (code 2303).
 */
class EppObjectNotFoundException extends EppResponseException
{
}
