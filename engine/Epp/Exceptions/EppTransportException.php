<?php
declare(strict_types=1);

namespace Oshim\Epp\Exceptions;

/**
 * Thrown on TCP/TLS socket connection, read, write, or timeout failures.
 */
class EppTransportException extends EppException
{
}
