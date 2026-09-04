<?php
declare(strict_types=1);

namespace Oshim\Container;

use Oshim\Container\Exceptions\ContainerExceptionInterface;
use Oshim\Container\Exceptions\NotFoundExceptionInterface;

/**
 * PSR-11 compliant Container Interface.
 */
interface ContainerInterface
{
    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     * @throws NotFoundExceptionInterface No entry was found for **this** identifier.
     * @throws ContainerExceptionInterface Error while retrieving the entry.
     * @return mixed Entry.
     */
    public function get(string $id): mixed;

    /**
     * Returns true if the container can return an entry for the given identifier.
     *
     * @param string $id Identifier of the entry to look for.
     * @return bool
     */
    public function has(string $id): bool;
}
