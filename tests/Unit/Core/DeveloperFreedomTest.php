<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use Oshim\Testing\TestCase;

class DeveloperFreedomTest extends TestCase
{
    public function testGlobalFacadesWithoutNamespaceImports(): void
    {
        // 1. Global DB Facade without `use Oshim\Database\DB`
        $this->assertTrue(class_exists('DB'));
        $query = \DB::table('test_table');
        $this->assertSame('SELECT * FROM "test_table"', $query->toSql());

        // 2. Global Response Facade without imports
        $this->assertTrue(class_exists('Response'));
        $res = \Response::json(['status' => 'success', 'freedom' => 100]);
        $this->assertSame(200, $res->getStatusCode());
        $this->assertStringContainsString('"freedom":100', $res->getContent());

        // 3. Global AI Facade
        $this->assertTrue(class_exists('AI'));
        $this->assertTrue(class_exists('Html'));
        $div = \Html::div(['class' => 'w-[200px]'], 'Custom Shop');
        $this->assertStringContainsString('<div class="w-[200px]">Custom Shop</div>', (string)$div);
    }

    public function testUniversalGlobalHelpers(): void
    {
        $this->assertTrue(function_exists('db'));
        $this->assertTrue(function_exists('response'));
        $this->assertTrue(function_exists('ai'));

        $jsonRes = response(['order_id' => 'ORD-999'], 201);
        $this->assertSame(201, $jsonRes->getStatusCode());
        $this->assertStringContainsString('ORD-999', $jsonRes->getContent());

        $builder = db('orders');
        $this->assertSame('SELECT * FROM "orders"', $builder->toSql());
    }

    public function testDynamicCustomDirectoryDiscovery(): void
    {
        // Create custom dynamic shop module directory
        $customDir = dirname(__DIR__, 3) . '/shop';
        @mkdir($customDir . '/Models', 0755, true);

        $customClassCode = <<<'PHP'
<?php
declare(strict_types=1);

namespace Shop\Models;

class CustomProduct
{
    public function getName(): string
    {
        return 'Sovereign Freedom T-Shirt';
    }
}
PHP;
        file_put_contents($customDir . '/Models/CustomProduct.php', $customClassCode);

        // Autoloader should automatically discover Shop\ namespace on the fly!
        $this->assertTrue(class_exists('Shop\Models\CustomProduct'));
        $prod = new \Shop\Models\CustomProduct();
        $this->assertSame('Sovereign Freedom T-Shirt', $prod->getName());

        // Clean up temporary custom test files
        @unlink($customDir . '/Models/CustomProduct.php');
        @rmdir($customDir . '/Models');
        @rmdir($customDir);
    }
}
