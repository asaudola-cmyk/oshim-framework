<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use Oshim\Testing\TestCase;
use Oshim\Autoloader;

class AutoloaderTest extends TestCase
{
    public function testAutoloaderIsRegistered(): void
    {
        $this->assertTrue(Autoloader::isRegistered());
        $namespaces = Autoloader::getNamespaces();

        $this->assertArrayHasKey('Oshim\\', $namespaces);
        $this->assertArrayHasKey('App\\', $namespaces);
        $this->assertArrayHasKey('Tests\\', $namespaces);
    }

    public function testAutoloaderLoadsExistingCoreClass(): void
    {
        $this->assertTrue(class_exists(\Oshim\Container\Container::class));
        $this->assertTrue(class_exists(\Oshim\Http\Request::class));
        $this->assertTrue(class_exists(\Oshim\Database\DB::class));
        $this->assertTrue(class_exists(\Oshim\Async\Async::class));
        $this->assertTrue(class_exists(\Oshim\Security\Cipher::class));
    }

    public function testAutoloaderRejectsPathTraversal(): void
    {
        $this->assertFalse(Autoloader::loadClass('Oshim\\..\\..\\etc\\passwd'));
        $this->assertFalse(Autoloader::loadClass("Oshim\\Container\0Evil"));
    }

    public function testAutoloaderReturnsFalseForNonExistentClass(): void
    {
        $this->assertFalse(class_exists('Oshim\\NonExistentClassFooBar123', true));
    }

    public function testAutoloaderCustomNamespaceRegistration(): void
    {
        Autoloader::addNamespace('CustomModule\\', __DIR__);
        $namespaces = Autoloader::getNamespaces();
        $this->assertArrayHasKey('CustomModule\\', $namespaces);
    }
}
