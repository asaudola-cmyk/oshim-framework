<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Ui\Router\AppRouter;
use Oshim\Kernel\MicroKernel;
use Oshim\Kernel\RouteParameterExtractor;
use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Ledger\MerkleTree;
use Oshim\Ledger\Blockchain;

class M2RoutingLedgerChallengerTest extends TestCase
{
    /**
     * Adversarial stress test: AppRouter with malformed, deeply nested, multi-slashed URIs in resolve() and dispatch().
     */
    public function testAppRouterMalformedDeeplyNestedMultiSlashed(): void
    {
        $router = new AppRouter();
        $router->page('/', fn() => '<h1>Home</h1>', null, 'Home');
        $router->page('/about', fn() => '<h1>About</h1>', null, 'About');
        $router->page('/vps/[id]', fn($p) => "<h1>VPS {$p['id']}</h1>", null, 'VPS Detail');
        $router->page('/catalog/[category]/item/[itemId]', fn($p) => "Item: {$p['category']} / {$p['itemId']}", null, 'Item');

        // 1. Malformed and multi-slashed URIs in resolve() and dispatch()
        $malformedUris = [
            '',
            '/',
            '//',
            '///',
            '////',
            '/////unknown////',
            '////vps////42////',
            'http://',
            'http://:80',
            'http:///path',
            'https://example.com:80:80/path',
            '://malformed',
            '?foo=bar',
            '#fragment',
            '//?foo=bar#baz',
            'http://user:pass@host:8080/about?ref=nav#top',
            'http://example.com/about//',
            '/about///',
        ];

        foreach ($malformedUris as $uri) {
            $resolved = $router->resolve($uri);
            $this->assertTrue($resolved === null || is_array($resolved), "resolve('{$uri}') must return null or array");

            $dispatched = $router->dispatch($uri);
            $this->assertTrue($dispatched === null || $dispatched instanceof Response, "dispatch('{$uri}') must return null or Response");

            $req = Request::create('GET', $uri);
            $dispReq = $router->dispatch($req);
            $this->assertTrue($dispReq === null || $dispReq instanceof Response, "dispatch(Request) must return null or Response");
        }

        // 2. Deeply nested 500-level path
        $deeplyNested = '/' . implode('/', array_fill(0, 500, 'nest'));
        $this->assertNull($router->resolve($deeplyNested));
        $this->assertNull($router->dispatch($deeplyNested));

        // 3. Thousand slashes
        $thousandSlashes = str_repeat('/', 1000) . 'about' . str_repeat('/', 1000);
        $resSlashes = $router->resolve($thousandSlashes);
        $this->assertTrue($resSlashes === null || is_array($resSlashes));
        $dispSlashes = $router->dispatch($thousandSlashes);
        $this->assertTrue($dispSlashes === null || $dispSlashes instanceof Response);

        // 4. Huge parameter (50KB)
        $hugeParam = str_repeat('B', 50000);
        $hugeUri = '/vps/' . $hugeParam;
        $resolvedHuge = $router->resolve($hugeUri);
        $this->assertNotNull($resolvedHuge);
        $this->assertSame($hugeParam, $resolvedHuge['params']['id']);
        $dispHuge = $router->dispatch($hugeUri);
        $this->assertInstanceOf(Response::class, $dispHuge);
        $this->assertSame(200, $dispHuge->getStatusCode());

        // 5. URL-decoding in dynamic routes
        $rEncoded = $router->resolve('/catalog/cloud%20storage/item/backup%2301%26save');
        $this->assertNotNull($rEncoded);
        $this->assertSame('cloud storage', $rEncoded['params']['category']);
        $this->assertSame('backup#01&save', $rEncoded['params']['itemId']);

        // 6. Dispatch soft navigation
        $dispSoft = $router->dispatch(new Request('GET', '/about', ['X-Oshim-Soft-Nav' => '1']));
        $this->assertInstanceOf(Response::class, $dispSoft);
        $this->assertSame(200, $dispSoft->getStatusCode());
    }

    /**
     * Adversarial stress test: MicroKernel routing parameter extraction with scalar type casting, mismatched names, and missing arguments.
     */
    public function testMicroKernelRoutingParameterExtractionAndTypeCasting(): void
    {
        $req = new Request('GET', '/test');

        // 1. Scalar type coercion: int, float, bool, string
        $args = RouteParameterExtractor::resolveArgs(
            fn(Request $r, int $id, float $price, bool $active, string $name) => [$id, $price, $active, $name],
            $req,
            ['id' => '789', 'price' => '12.99', 'active' => 'true', 'name' => 'ClusterNode']
        );
        $this->assertSame($req, $args[0]);
        $this->assertSame(789, $args[1]);
        $this->assertSame(12.99, $args[2]);
        $this->assertTrue($args[3]);
        $this->assertSame('ClusterNode', $args[4]);

        // 2. Boolean variations
        $boolMap = [
            '1' => true, 'true' => true, 'TRUE' => true, 'yes' => true, 'YES' => true, 'on' => true, 'ON' => true,
            '0' => false, 'false' => false, 'no' => false, 'off' => false, 'other' => false, '' => false
        ];
        foreach ($boolMap as $raw => $expected) {
            $res = RouteParameterExtractor::resolveArgs(fn(bool $flag) => $flag, $req, ['flag' => $raw]);
            $this->assertSame($expected, $res[0], "Coercing '{$raw}' failed");
        }

        // 3. Negative numbers and scientific notation
        $numArgs = RouteParameterExtractor::resolveArgs(
            fn(int $negI, float $negF, float $sciF) => [$negI, $negF, $sciF],
            $req,
            ['negI' => '-2048', 'negF' => '-99.95', 'sciF' => '2.5e-2']
        );
        $this->assertSame(-2048, $numArgs[0]);
        $this->assertSame(-99.95, $numArgs[1]);
        $this->assertSame(0.025, $numArgs[2]);

        // 4. Positional fallback on mismatched parameter names
        $posArgs = RouteParameterExtractor::resolveArgs(
            fn(int $expectedId, string $expectedName) => [$expectedId, $expectedName],
            $req,
            ['mismatched_key_1' => '54321', 'mismatched_key_2' => 'FallbackName']
        );
        $this->assertSame(54321, $posArgs[0]);
        $this->assertSame('FallbackName', $posArgs[1]);

        // 5. Mixed exact matching and positional fallback
        $mixedArgs = RouteParameterExtractor::resolveArgs(
            fn(string $exactName, int $posVal) => [$exactName, $posVal],
            $req,
            ['random_key' => '333', 'exactName' => 'ExactMatch']
        );
        $this->assertSame('ExactMatch', $mixedArgs[0]);
        $this->assertSame(333, $mixedArgs[1]);

        // 6. Missing arguments with defaults and nullable types
        $defaultArgs = RouteParameterExtractor::resolveArgs(
            fn(int $id, string $role = 'operator', ?float $bonus = null) => [$id, $role, $bonus],
            $req,
            ['id' => '55']
        );
        $this->assertSame(55, $defaultArgs[0]);
        $this->assertSame('operator', $defaultArgs[1]);
        $this->assertNull($defaultArgs[2]);

        // 7. MicroKernel routing execution with HTTP methods and optional parameters
        $kernel = MicroKernel::create();
        $kernel->get('/user/{id}', fn(Request $r, int $id) => Response::json(['user' => $id, 'has' => $r->has('id')]));
        $kernel->post('/items/{category}/{id}', fn(string $category, string $id) => "ITEM {$category}:{$id}");
        $kernel->get('/search/{query}', fn(string $query) => "QUERY: {$query}");
        $kernel->get('/profile/{section?}', fn(?string $section = 'overview') => "SEC: " . ($section ?? 'none'));

        $resGet = $kernel->handle(new Request('GET', '/user/9001'));
        $this->assertSame(200, $resGet->statusCode());
        $dataGet = json_decode((string)$resGet->body(), true);
        $this->assertSame(9001, $dataGet['user']);
        $this->assertTrue($dataGet['has']);

        $resPost = $kernel->handle(new Request('POST', '/items/hardware/server-01'));
        $this->assertSame(200, $resPost->statusCode());
        $this->assertSame('ITEM hardware:server-01', (string)$resPost->body());

        $resSearch = $kernel->handle(new Request('GET', '/search/hello%20world%21'));
        $this->assertSame('QUERY: hello world!', (string)$resSearch->body());

        $resOptPresent = $kernel->handle(new Request('GET', '/profile/security'));
        $this->assertSame('SEC: security', (string)$resOptPresent->body());

        $resOptAbsent = $kernel->handle(new Request('GET', '/profile'));
        $this->assertSame('SEC: overview', (string)$resOptAbsent->body());

        $res404 = $kernel->handle(new Request('GET', '/non/existent'));
        $this->assertSame(404, $res404->statusCode());

        $resMethodMismatch = $kernel->handle(new Request('DELETE', '/user/9001'));
        $this->assertSame(404, $resMethodMismatch->statusCode());
    }

    /**
     * Adversarial stress test: MerkleTree proof verification under corrupted transaction blocks, altered leaves, empty blocks, single-leaf trees, and out-of-bounds indices.
     */
    public function testMerkleTreeCryptographicLedgerAdversarialProofs(): void
    {
        // 1. Empty tree
        $emptyTree = new MerkleTree([]);
        $this->assertSame(hash('sha256', ''), $emptyTree->getRoot());
        $this->assertSame([], $emptyTree->getLeaves());

        try {
            $emptyTree->getProof(0);
            $this->fail("Expected OutOfBoundsException for empty tree getProof(0)");
        } catch (\OutOfBoundsException $e) {
            $this->assertStringContainsString('out of bounds', $e->getMessage());
        }

        $this->assertFalse(MerkleTree::verifyProof('', [], ''));
        $this->assertFalse(MerkleTree::verifyProof(hash('sha256', 'a'), [], ''));
        $this->assertFalse(MerkleTree::verifyProof('', [], hash('sha256', 'a')));

        // 2. Single-leaf tree
        $singleTree = new MerkleTree(['single_transaction']);
        $singleLeaf = $singleTree->getLeaves()[0];
        $singleRoot = $singleTree->getRoot();
        $this->assertSame($singleLeaf, $singleRoot);
        $this->assertSame([], $singleTree->getProof(0));
        $this->assertTrue(MerkleTree::verifyProof($singleLeaf, [], $singleRoot));
        $this->assertFalse(MerkleTree::verifyProof(hash('sha256', 'tampered'), [], $singleRoot));

        foreach ([-1, 1, 99] as $badIndex) {
            try {
                $singleTree->getProof($badIndex);
                $this->fail("Expected OutOfBoundsException for index {$badIndex}");
            } catch (\OutOfBoundsException $e) {
                $this->assertStringContainsString('out of bounds', $e->getMessage());
            }
        }

        // 3. Multi-size trees (both power-of-two and odd: 2, 3, 5, 7, 8, 9, 17, 33)
        foreach ([2, 3, 5, 7, 8, 9, 17, 33] as $size) {
            $txs = array_map(fn($k) => ['id' => $k, 'val' => "payload_{$k}"], range(0, $size - 1));
            $tree = new MerkleTree($txs);
            $root = $tree->getRoot();
            $leaves = $tree->getLeaves();

            $this->assertCount($size, $leaves);
            $this->assertSame(64, strlen($root));

            for ($i = 0; $i < $size; $i++) {
                $proof = $tree->getProof($i);
                $this->assertTrue(MerkleTree::verifyProof($leaves[$i], $proof, $root), "Tree size {$size} leaf {$i} must verify");

                // Tampered leaf hash must fail
                $this->assertFalse(MerkleTree::verifyProof(hash('sha256', "tampered_{$i}"), $proof, $root));

                // Corrupted proof step hash must fail
                if (!empty($proof)) {
                    $corruptProof = $proof;
                    $corruptProof[0]['hash'] = hash('sha256', 'corrupted');
                    $this->assertFalse(MerkleTree::verifyProof($leaves[$i], $corruptProof, $root));

                    // Inverted position must fail if sibling hash != current leaf hash
                    if ($proof[0]['hash'] !== $leaves[$i]) {
                        $invProof = $proof;
                        $invProof[0]['position'] = ($invProof[0]['position'] === 'left') ? 'right' : 'left';
                        $this->assertFalse(MerkleTree::verifyProof($leaves[$i], $invProof, $root));
                    }

                    // Truncated proof must fail
                    $truncProof = $proof;
                    array_pop($truncProof);
                    $this->assertFalse(MerkleTree::verifyProof($leaves[$i], $truncProof, $root));
                }

                // Cross-proof must fail
                if ($size > 1) {
                    $wrongProof = $tree->getProof(($i + 1) % $size);
                    $this->assertFalse(MerkleTree::verifyProof($leaves[$i], $wrongProof, $root));
                }
            }

            // Bounds checking
            try {
                $tree->getProof(-1);
                $this->fail("Expected OutOfBoundsException for -1");
            } catch (\OutOfBoundsException $e) {
                $this->assertStringContainsString('out of bounds', $e->getMessage());
            }

            try {
                $tree->getProof($size);
                $this->fail("Expected OutOfBoundsException for size {$size}");
            } catch (\OutOfBoundsException $e) {
                $this->assertStringContainsString('out of bounds', $e->getMessage());
            }
        }

        // 4. Malformed proof structures
        $dummy = hash('sha256', 'dummy');
        $this->assertFalse(MerkleTree::verifyProof($dummy, [['invalid' => true]], $dummy));
        $this->assertFalse(MerkleTree::verifyProof($dummy, [['position' => 'left']], $dummy));
        $this->assertFalse(MerkleTree::verifyProof($dummy, [['hash' => $dummy]], $dummy));
        $this->assertFalse(MerkleTree::verifyProof($dummy, [['position' => 'unknown', 'hash' => $dummy]], $dummy));
        $this->assertFalse(MerkleTree::verifyProof($dummy, ['non_array'], $dummy));

        // 5. Blockchain Ledger tampering detection
        $chain = new Blockchain(difficulty: 1);
        $chain->record(['tx' => 1, 'amount' => 100]);
        $chain->minePending();
        $chain->record(['tx' => 2, 'amount' => 200]);
        $chain->minePending();
        $this->assertTrue($chain->isValid());

        // Alter transaction in block 1
        $tamperedData1 = $chain->toArray();
        $tamperedData1['chain'][1]['transactions'][0]['amount'] = 999999;
        $tmpFile1 = tempnam(sys_get_temp_dir(), 'adv_ledger_t1_');
        file_put_contents($tmpFile1, json_encode($tamperedData1));
        $loaded1 = Blockchain::loadFromFile($tmpFile1);
        $this->assertFalse($loaded1->isValid(), "Chain with modified transaction must be invalid");
        @unlink($tmpFile1);

        // Inject fraudulent transaction into block 2
        $tamperedData2 = $chain->toArray();
        $tamperedData2['chain'][2]['transactions'][] = ['tx' => 99, 'fraud' => true];
        $tmpFile2 = tempnam(sys_get_temp_dir(), 'adv_ledger_t2_');
        file_put_contents($tmpFile2, json_encode($tamperedData2));
        $loaded2 = Blockchain::loadFromFile($tmpFile2);
        $this->assertFalse($loaded2->isValid(), "Chain with injected transaction must be invalid");
        @unlink($tmpFile2);

        // Break previous hash linkage
        $tamperedData3 = $chain->toArray();
        $tamperedData3['chain'][2]['previous_hash'] = hash('sha256', 'broken_link');
        $tmpFile3 = tempnam(sys_get_temp_dir(), 'adv_ledger_t3_');
        file_put_contents($tmpFile3, json_encode($tamperedData3));
        $loaded3 = Blockchain::loadFromFile($tmpFile3);
        $this->assertFalse($loaded3->isValid(), "Chain with broken hash link must be invalid");
        @unlink($tmpFile3);
    }
}
