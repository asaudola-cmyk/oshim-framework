<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Ui\Router\AppRouter;
use Oshim\Kernel\MicroKernel;
use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Http\Router\Router;
use Oshim\Ledger\MerkleTree;
use Oshim\Ledger\Blockchain;
use Oshim\Compiler\StandalonePackager;
use Oshim\Cli\Commands\PackStandaloneCommand;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\Ai\Vector\VectorStore;
use Oshim\Ai\Rag\HybridSearchEngine;
use Oshim\Ai\Rag\RagPipeline;
use Oshim\Ai\Embedding\TfIdfEmbedder;
use Oshim\Ui\Css\TailwindJitCompiler;

class AdversarialAuditStressTest extends TestCase
{
    /**
     * Adversarial stress testing for MicroKernel parameter extraction, routing edge cases, and type coercion.
     */
    public function testMicroKernelRoutingAndParameterExtractionEdgeCases(): void
    {
        // 1. AppRouter Malformed URI Handling
        $appRouter = new AppRouter();
        $malformedUris = [
            '////unknown',
            'http://:80',
            'http:///path',
            '//?foo=bar',
            'http://',
            '///',
            '://test',
            '',
            '/',
            'https://example.com:80:80/path',
        ];
        foreach ($malformedUris as $uri) {
            $resolved = $appRouter->resolve($uri);
            $this->assertTrue($resolved === null || is_array($resolved));
            $res = $appRouter->dispatch($uri);
            $this->assertTrue($res === null || $res instanceof Response);
        }

        // 2. MicroKernel Parameter Type Coercion (int, float, bool)
        $kernel = new MicroKernel();
        $kernel->get('/user/{id}', fn(Request $req, int $id) => ['user_id' => $id]);
        $kernel->get('/item/{price}', fn(Request $req, float $price) => ['price' => $price]);
        $kernel->get('/flag/{enabled}', fn(Request $req, bool $enabled) => ['active' => $enabled]);

        $resInt = $kernel->handle(new Request('GET', '/user/42'));
        $this->assertSame(200, $resInt->statusCode());
        $this->assertSame(42, json_decode((string)$resInt->body(), true)['user_id']);

        $resFloat = $kernel->handle(new Request('GET', '/item/19.99'));
        $this->assertSame(200, $resFloat->statusCode());
        $this->assertSame(19.99, json_decode((string)$resFloat->body(), true)['price']);

        $resBool = $kernel->handle(new Request('GET', '/flag/true'));
        $this->assertSame(200, $resBool->statusCode());
        $this->assertTrue(json_decode((string)$resBool->body(), true)['active']);

        // 3. Flexible Handler Signatures (Mismatched name, No Request, Zero args, Defaults)
        $k2 = new MicroKernel();
        $k2->get('/alias/{id}', fn(Request $r, string $userId) => 'uid:' . $userId);
        $k2->get('/standalone/{id}', fn(int $id) => 'id:' . $id);
        $k2->get('/static-match/{id}', fn() => 'matched');
        $k2->get('/default-val/{id}', fn(Request $r, string $id, string $role = 'guest') => $id . ':' . $role);

        $this->assertSame('uid:999', (string)$k2->handle(new Request('GET', '/alias/999'))->body());
        $this->assertSame('id:555', (string)$k2->handle(new Request('GET', '/standalone/555'))->body());
        $this->assertSame('matched', (string)$k2->handle(new Request('GET', '/static-match/123'))->body());
        $this->assertSame('888:guest', (string)$k2->handle(new Request('GET', '/default-val/888'))->body());

        // 4. Route Parameters Attached to Request Instance & Input Validation
        $k3 = new MicroKernel();
        $k3->get('/profile/{id}', function (Request $req, string $id) {
            $validated = $req->validate(['id' => 'required|numeric']);
            return [
                'routeParam' => $req->routeParam('id'),
                'validated'  => $validated['id'],
                'has'        => $req->has('id'),
            ];
        });
        $resProf = $k3->handle(new Request('GET', '/profile/777'));
        $this->assertSame(200, $resProf->statusCode());
        $dataProf = json_decode((string)$resProf->body(), true);
        $this->assertSame('777', $dataProf['routeParam']);
        $this->assertSame('777', $dataProf['validated']);
        $this->assertTrue($dataProf['has']);

        // 5. URL-Decoding of Route Parameters
        $k4 = new MicroKernel();
        $k4->get('/search/{query}', fn(Request $r, string $query) => $query);
        $resSearch = $k4->handle(new Request('GET', '/search/hello%20world%21'));
        $this->assertSame('hello world!', (string)$resSearch->body());

        // 6. Optional Parameters Support
        $k5 = new MicroKernel();
        $k5->get('/catalog/{category?}', fn(Request $r, ?string $category = null) => $category ?? 'all');
        $this->assertSame('laptops', (string)$k5->handle(new Request('GET', '/catalog/laptops'))->body());
        $this->assertSame('all', (string)$k5->handle(new Request('GET', '/catalog'))->body());

        // 7. Router Container Scalar Type Coercion
        $router = new Router();
        $router->get('/vps/{id}', fn(Request $req, int $id) => Response::json(['vps_id' => $id]));
        $resRouter = $router->dispatch(Request::create('GET', '/vps/4096'));
        $this->assertSame(200, $resRouter->getStatusCode());
        $this->assertSame(4096, json_decode($resRouter->getContent(), true)['vps_id']);

        // 8. Corrupted Query String Resilience
        $reqCorrupted = Request::create('GET', '/api/test?q=%ZZ&bad[]=1&bad[]=2&foo===&&');
        $this->assertSame('%ZZ', $reqCorrupted->query('q'));
        $this->assertSame(['1', '2'], $reqCorrupted->query('bad'));
    }

    /**
     * Adversarial stress testing for MerkleTree proof verification and corrupted blockchain blocks.
     */
    public function testMerkleTreeProofVerificationUnderCorruptedBlocks(): void
    {
        // 1. Multi-transaction Merkle Tree proof generation & verification
        $txs = [
            ['id' => 'tx_001', 'amount' => 100, 'sender' => 'Alice', 'recipient' => 'Bob'],
            ['id' => 'tx_002', 'amount' => 200, 'sender' => 'Bob', 'recipient' => 'Charlie'],
            ['id' => 'tx_003', 'amount' => 300, 'sender' => 'Charlie', 'recipient' => 'Dave'],
            ['id' => 'tx_004', 'amount' => 400, 'sender' => 'Dave', 'recipient' => 'Eve'],
        ];
        $tree = new MerkleTree($txs);
        $root = $tree->getRoot();
        $leaves = $tree->getLeaves();

        $this->assertCount(4, $leaves);
        $this->assertSame(64, strlen($root));

        // Verify valid proofs for all leaves
        for ($i = 0; $i < 4; $i++) {
            $proof = $tree->getProof($i);
            $this->assertNotEmpty($proof);
            $this->assertTrue(MerkleTree::verifyProof($leaves[$i], $proof, $root), "Proof for leaf {$i} must verify");
        }

        // 2. Corrupted leaf verification must fail
        $tamperedLeaf = hash('sha256', 'forged_transaction_payload');
        $this->assertFalse(MerkleTree::verifyProof($tamperedLeaf, $tree->getProof(0), $root));

        // 3. Corrupted proof step hash must fail
        $corruptedHashProof = $tree->getProof(0);
        $corruptedHashProof[0]['hash'] = str_repeat('0', 64);
        $this->assertFalse(MerkleTree::verifyProof($leaves[0], $corruptedHashProof, $root));

        // 4. Inverted proof node position ('left' <-> 'right') must fail
        $invertedProof = $tree->getProof(0);
        $invertedProof[0]['position'] = ($invertedProof[0]['position'] === 'left') ? 'right' : 'left';
        $this->assertFalse(MerkleTree::verifyProof($leaves[0], $invertedProof, $root));

        // 5. Truncated proof must fail
        $truncatedProof = $tree->getProof(0);
        array_pop($truncatedProof);
        $this->assertFalse(MerkleTree::verifyProof($leaves[0], $truncatedProof, $root));

        // 6. Cross-leaf proof transposition must fail
        $this->assertFalse(MerkleTree::verifyProof($leaves[0], $tree->getProof(1), $root));

        // 7. Single-leaf Merkle Tree
        $singleTree = new MerkleTree(['lone_transaction']);
        $singleLeaf = $singleTree->getLeaves()[0];
        $singleRoot = $singleTree->getRoot();
        $this->assertSame($singleLeaf, $singleRoot);
        $this->assertTrue(MerkleTree::verifyProof($singleLeaf, $singleTree->getProof(0), $singleRoot));
        $this->assertFalse(MerkleTree::verifyProof($tamperedLeaf, $singleTree->getProof(0), $singleRoot));

        // 8. Odd number of transactions (3 and 5 leaves)
        foreach ([3, 5] as $count) {
            $oddTxs = array_map(fn($k) => "tx_{$k}", range(1, $count));
            $oddTree = new MerkleTree($oddTxs);
            $oddLeaves = $oddTree->getLeaves();
            $oddRoot = $oddTree->getRoot();
            for ($k = 0; $k < $count; $k++) {
                $p = $oddTree->getProof($k);
                $this->assertTrue(MerkleTree::verifyProof($oddLeaves[$k], $p, $oddRoot), "Odd ({$count}) leaf {$k} must verify");
            }
        }

        // 9. Empty transactions tree
        $emptyTree = new MerkleTree([]);
        $this->assertSame(hash('sha256', ''), $emptyTree->getRoot());
        $this->assertFalse(MerkleTree::verifyProof('', [], ''));

        // 10. Malformed proof arrays should safely fail
        $this->assertFalse(MerkleTree::verifyProof($leaves[0], [['corrupt' => true]], $root));
        $this->assertFalse(MerkleTree::verifyProof($leaves[0], ['non_array_step'], $root));

        // 11. Bounds checking in getProof()
        try {
            $tree->getProof(-1);
            $this->fail("Expected OutOfBoundsException for negative index");
        } catch (\OutOfBoundsException $e) {
            $this->assertStringContainsString('out of bounds', $e->getMessage());
        }

        try {
            $tree->getProof(999);
            $this->fail("Expected OutOfBoundsException for out of range index");
        } catch (\OutOfBoundsException $e) {
            $this->assertStringContainsString('out of bounds', $e->getMessage());
        }

        // 12. Corrupted block detection in Blockchain
        $blockchain = new Blockchain(difficulty: 1);
        $blockchain->record(['from' => 'Alice', 'to' => 'Bob', 'amount' => 50]);
        $blockchain->record(['from' => 'Bob', 'to' => 'Charlie', 'amount' => 25]);
        $blockchain->minePending();
        $this->assertTrue($blockchain->isValid());

        // Tamper transaction amount in serialized chain
        $chainData = $blockchain->toArray();
        $chainData['chain'][1]['transactions'][0]['amount'] = 999999;
        $tmpFile = tempnam(sys_get_temp_dir(), 'adv_ledger_tamper_');
        file_put_contents($tmpFile, json_encode($chainData));
        $loaded = Blockchain::loadFromFile($tmpFile);
        $this->assertFalse($loaded->isValid(), "Altered transaction in block must invalidate blockchain");
        @unlink($tmpFile);
    }

    /**
     * Adversarial stress testing for StandalonePackager with circular class dependencies.
     */
    public function testStandalonePackagerWithCircularClassDependencies(): void
    {
        $tmpDir = sys_get_temp_dir() . '/adv_pack_test_' . uniqid();
        mkdir($tmpDir . '/engine/CycleA', 0777, true);
        mkdir($tmpDir . '/engine/CycleB', 0777, true);

        // 1. Direct 2-Node Circular Dependency (ClassA <-> ClassB)
        file_put_contents($tmpDir . '/engine/CycleA/NodeA.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace Oshim\CycleA;
use Oshim\CycleB\NodeB;
class NodeA {
    public function getB(): NodeB { return new NodeB(); }
    public function name(): string { return 'NodeA'; }
}
PHP
        );

        file_put_contents($tmpDir . '/engine/CycleB/NodeB.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace Oshim\CycleB;
use Oshim\CycleA\NodeA;
class NodeB {
    public function getA(): NodeA { return new NodeA(); }
    public function name(): string { return 'NodeB'; }
}
PHP
        );

        // App script exercising mutual circular calls
        $sourceFile = $tmpDir . '/app_circ.php';
        $bundleFile = $tmpDir . '/bundle_circ.php';
        file_put_contents($sourceFile, <<<'PHP'
<?php
use Oshim\CycleA\NodeA;
$a = new NodeA();
echo $a->name() . '->' . $a->getB()->name() . '->' . $a->getB()->getA()->name();
PHP
        );

        $packager = new StandalonePackager($tmpDir . '/engine');
        $result = $packager->compile($sourceFile, $bundleFile);

        $this->assertSame('COMPILED_SUCCESS', $result['status']);
        $this->assertTrue(is_file($bundleFile));
        $this->assertContains('Oshim\CycleA\NodeA', $result['classes_bundled']);
        $this->assertContains('Oshim\CycleB\NodeB', $result['classes_bundled']);

        // Execute bundle in isolated process
        $output = shell_exec(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($bundleFile));
        $this->assertSame('NodeA->NodeB->NodeA', trim((string)$output));

        // 2. 5-Node Circular Lattice Chain (N0 -> N1 -> N2 -> N3 -> N4 -> N0)
        mkdir($tmpDir . '/engine/Lattice', 0777, true);
        for ($i = 0; $i < 5; $i++) {
            $next = ($i + 1) % 5;
            $code = "<?php\ndeclare(strict_types=1);\nnamespace Oshim\\Lattice;\nuse Oshim\\Lattice\\Node{$next};\nclass Node{$i} {\n    public function next(): Node{$next} { return new Node{$next}(); }\n    public function id(): int { return {$i}; }\n}\n";
            file_put_contents($tmpDir . "/engine/Lattice/Node{$i}.php", $code);
        }

        $latticeApp = $tmpDir . '/app_lattice.php';
        $latticeBundle = $tmpDir . '/bundle_lattice.php';
        file_put_contents($latticeApp, <<<'PHP'
<?php
use Oshim\Lattice\Node0;
$cur = new Node0();
for ($i = 0; $i < 5; $i++) { $cur = $cur->next(); }
echo $cur->id();
PHP
        );

        $resLattice = $packager->compile($latticeApp, $latticeBundle);
        $this->assertSame('COMPILED_SUCCESS', $resLattice['status']);
        $this->assertCount(5, array_filter($resLattice['classes_bundled'], fn($c) => str_contains($c, 'Lattice')));

        $latticeOut = shell_exec(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($latticeBundle));
        $this->assertSame('0', trim((string)$latticeOut));

        // 3. Expression require safety verification in StandalonePackager
        mkdir($tmpDir . '/engine/RequireExpr', 0777, true);
        file_put_contents($tmpDir . '/engine/RequireExpr/RequireHolder.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace Oshim\RequireExpr;
class RequireHolder {
    public function load(string $path): mixed {
        $val = require $path;
        return $val;
    }
}
PHP
        );
        $reqApp = $tmpDir . '/app_req.php';
        $reqBundle = $tmpDir . '/bundle_req.php';
        file_put_contents($reqApp, <<<'PHP'
<?php
use Oshim\RequireExpr\RequireHolder;
$holder = new RequireHolder();
echo "READY";
PHP
        );
        $resReq = $packager->compile($reqApp, $reqBundle);
        $this->assertSame('COMPILED_SUCCESS', $resReq['status']);
        $lintOutput = shell_exec(escapeshellcmd(PHP_BINARY) . ' -l ' . escapeshellarg($reqBundle));
        $this->assertStringContainsString('No syntax errors detected', (string)$lintOutput);

        // 4. CLI Command invocation test (bin/oshim pack:standalone)
        $cliBundle = $tmpDir . '/bundle_cli.php';
        $cmd = new PackStandaloneCommand();
        $input = new Input(['oshim', 'pack:standalone', $sourceFile, '--output=' . $cliBundle]);
        $cliOut = new Output();

        ob_start();
        $code = $cmd->execute($input, $cliOut);
        ob_end_clean();

        $this->assertSame(0, $code);
        $this->assertTrue(is_file($cliBundle));

        // Cleanup
        exec("rm -rf " . escapeshellarg($tmpDir));
    }

    /**
     * Adversarial stress testing for Vector RAG semantic search with empty corpus and malformed embeddings.
     */
    public function testVectorRagSemanticSearchWithEmptyCorpusAndMalformedEmbeddings(): void
    {
        // 1. Empty Corpus Handling
        $emptyStore = new VectorStore();
        $this->assertSame([], $emptyStore->search([1.0, 0.0, 0.0], 5));
        $this->assertSame([], $emptyStore->search([1.0, 0.0, 0.0], 5, fn($meta) => true));

        $emptyHybrid = new HybridSearchEngine();
        $this->assertSame([], $emptyHybrid->search('Empty query test', 5));

        $emptyPipeline = new RagPipeline();
        $ragResponse = $emptyPipeline->ask('What is OSHIM?', 3);
        $this->assertSame([], $ragResponse['retrieved_contexts']);
        $this->assertSame([], $ragResponse['source_docs']);
        $this->assertNotEmpty($ragResponse['answer']);

        // 2. Zero-Norm Vector & Cosine Similarity Non-Division-by-Zero
        $store = new VectorStore();
        $store->upsert('normal_doc', [1.0, 0.5, 0.2], ['topic' => 'valid'], 'Valid Doc');
        $store->upsert('zero_doc', [0.0, 0.0, 0.0], ['topic' => 'zero'], 'Zero Doc');

        $zeroQueryResults = $store->search([0.0, 0.0, 0.0], 5);
        $this->assertCount(2, $zeroQueryResults);
        $this->assertSame(0.0, (float)$zeroQueryResults[0]['score']);
        $this->assertSame(0.0, (float)$zeroQueryResults[1]['score']);

        $normalQueryResults = $store->search([1.0, 0.5, 0.2], 5);
        $this->assertSame('normal_doc', $normalQueryResults[0]['id']);
        $this->assertTrue($normalQueryResults[0]['score'] > 0.99);

        // TfIdfEmbedder empty and stop-words only produces zero vector
        $emptyVec = TfIdfEmbedder::embed('');
        $this->assertCount(64, $emptyVec);
        $this->assertSame(0.0, array_sum($emptyVec));

        $stopWordVec = TfIdfEmbedder::embed('the is at and or');
        $this->assertCount(64, $stopWordVec);
        $this->assertSame(0.0, array_sum($stopWordVec));

        // 3. Vector Dimension Mismatch Robustness
        $dimMismatchQuery = [1.0, 0.5]; // 2 dimensions vs 3 dimensions in store
        $mismatchResults = $store->search($dimMismatchQuery, 5);
        $this->assertNotEmpty($mismatchResults);
        $this->assertTrue($mismatchResults[0]['score'] > 0.0);

        $largeDimQuery = [1.0, 0.5, 0.2, 0.9, 0.8]; // 5 dimensions vs 3 dimensions in store
        $largeMismatchResults = $store->search($largeDimQuery, 5);
        $this->assertNotEmpty($largeMismatchResults);
        $this->assertTrue($largeMismatchResults[0]['score'] > 0.0);

        // Empty vector query
        $emptyQueryResults = $store->search([], 5);
        $this->assertCount(2, $emptyQueryResults);
        $this->assertSame(0.0, (float)$emptyQueryResults[0]['score']);

        // 4. Associative and Non-Zero-Indexed Vector Keys Guarding
        $assocStore = new VectorStore();
        $assocStore->upsert('assoc_doc', ['dim_x' => 1.0, 'dim_y' => 0.5]);
        $assocResults = $assocStore->search(['dim_x' => 1.0, 'dim_y' => 0.5], 2);
        $this->assertNotEmpty($assocResults);
        $this->assertSame('assoc_doc', $assocResults[0]['id']);
        $this->assertTrue($assocResults[0]['score'] > 0.99);

        // 5. Malformed Non-UTF8 Text Ingestion
        $corruptBinary = "\x80\xFF\xFE\xAA Invalid Non-UTF8 \xC0\xAF Binary Stream";
        $binaryEmbed = TfIdfEmbedder::embed($corruptBinary);
        $this->assertCount(64, $binaryEmbed);

        $hybrid = new HybridSearchEngine();
        $hybrid->index('bin_doc', $corruptBinary);
        $binSearch = $hybrid->search('Invalid', 3);
        $this->assertNotEmpty($binSearch);
        $this->assertSame('bin_doc', $binSearch[0]['doc_id']);

        // 6. Boundary topK Handling
        $this->assertSame([], $store->search([1.0, 0.0, 0.0], 0));
        $this->assertSame([], $store->search([1.0, 0.0, 0.0], -5));
        $hugeKResults = $store->search([1.0, 0.0, 0.0], 1000000);
        $this->assertCount(2, $hugeKResults);
    }

    /**
     * Adversarial stress testing for TailwindJitCompiler with arbitrary classes, negative offsets, and complex pseudo-selectors.
     */
    public function testTailwindJitCompilerWithArbitraryClassesAndComplexPseudoSelectors(): void
    {
        // 1. Arbitrary Values: Colors, Dimensions, Nested calc, min
        $htmlArbitrary = '<div class="bg-[#1a2b3c] text-[#00ff88] border-[#334455] h-[calc(100vh-4rem)] w-[min(100vw,800px)] grid-cols-[1fr_2fr_1fr]">';
        $cssArbitrary = TailwindJitCompiler::compile($htmlArbitrary);

        $this->assertStringContainsString('background-color:#1a2b3c;', $cssArbitrary);
        $this->assertStringContainsString('color:#00ff88;', $cssArbitrary);
        $this->assertStringContainsString('border-color:#334455;', $cssArbitrary);
        $this->assertStringContainsString('height:calc(100vh-4rem);', $cssArbitrary);
        $this->assertStringContainsString('width:min(100vw,800px);', $cssArbitrary);
        $this->assertStringContainsString('grid-template-columns:1fr 2fr 1fr;', $cssArbitrary);

        // Selector escaping check: # and ( ) must be escaped
        $this->assertStringContainsString('.bg-\\[\\#1a2b3c\\]', $cssArbitrary);
        $this->assertStringContainsString('.h-\\[calc\\(100vh-4rem\\)\\]', $cssArbitrary);

        // 2. Negative Arbitrary & Standard Values
        $htmlNegative = '<div class="-top-[12px] -mt-[20px] -m-[1rem] -left-[5px] -z-[10] -top-4 -mt-6 -z-20">';
        $cssNegative = TailwindJitCompiler::compile($htmlNegative);

        $this->assertStringContainsString('top:-12px;', $cssNegative);
        $this->assertStringContainsString('margin-top:-20px;', $cssNegative);
        $this->assertStringContainsString('margin:-1rem;', $cssNegative);
        $this->assertStringContainsString('left:-5px;', $cssNegative);
        $this->assertStringContainsString('z-index:-10;', $cssNegative);
        $this->assertStringContainsString('top:-1rem;', $cssNegative);
        $this->assertStringContainsString('margin-top:-1.5rem;', $cssNegative);
        $this->assertStringContainsString('z-index:-20;', $cssNegative);

        // 3. Stacked Pseudo-Classes & Multi-Variants
        $htmlStacked = '<button class="hover:focus:text-blue-500 hover:focus:scale-105 md:hover:bg-red-500 dark:text-white dark:hover:focus:bg-slate-800">';
        $cssStacked = TailwindJitCompiler::compile($htmlStacked);

        $this->assertStringContainsString(':hover:focus{color:#3b82f6;}', $cssStacked);
        $this->assertStringContainsString(':hover:focus{transform:scale(1.05);}', $cssStacked);
        $this->assertStringContainsString('@media (min-width: 768px)', $cssStacked);
        $this->assertStringContainsString('.md\\:hover\\:bg-red-500:hover{background-color:#ef4444;}', $cssStacked);
        $this->assertStringContainsString('.dark .dark\\:text-white{color:#ffffff;}', $cssStacked);
        $this->assertStringContainsString('.dark .dark\\:hover\\:focus\\:bg-slate-800:hover:focus{background-color:#1e293b;}', $cssStacked);
    }
}
