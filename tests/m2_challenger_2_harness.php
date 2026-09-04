<?php
declare(strict_types=1);

/**
 * ⚡ OSHIM Milestone M2 Adversarial Challenger Harness 2 (Deep Exhaustive Edition)
 * Focus: StandalonePackager (K2, K5, 10-ring, cross-namespace), Vector RAG & MatrixMath, Tailwind JIT Compiler.
 */

$frameworkRoot = dirname(__DIR__);
require_once $frameworkRoot . '/engine/Bootstrap.php';
\Oshim\Bootstrap::boot($frameworkRoot);

use Oshim\Compiler\StandalonePackager;
use Oshim\Ai\Vector\VectorStore;
use Oshim\Ai\Tensor\MatrixMath;
use Oshim\Ai\Rag\HybridSearchEngine;
use Oshim\Ai\Rag\RagPipeline;
use Oshim\Ai\Embedding\TfIdfEmbedder;
use Oshim\Ui\Css\TailwindJitCompiler;

echo "======================================================================\n";
echo " OSHIM MILESTONE M2 DEEP ADVERSARIAL CHALLENGER HARNESS\n";
echo "======================================================================\n\n";

$totalAssertions = 0;
$failedAssertions = 0;

function assertCondition(bool $condition, string $description, ?string $extra = null): void
{
    global $totalAssertions, $failedAssertions;
    $totalAssertions++;
    if ($condition) {
        echo "  [PASS] {$description}\n";
    } else {
        $failedAssertions++;
        echo "  [FAIL] {$description}\n";
        if ($extra !== null) {
            echo "         Details: {$extra}\n";
        }
    }
}

// ====================================================================
// SECTION 1: STANDALONE PACKAGER ADVERSARIAL STRESS TESTING
// ====================================================================
echo ">>> SECTION 1: StandalonePackager Circular Dependency Graphs & Isolated Execution\n";

$tmpDir = sys_get_temp_dir() . '/m2_adv_pack_deep_' . uniqid();
mkdir($tmpDir . '/engine', 0777, true);
$packager = new StandalonePackager($tmpDir . '/engine');

// 1.1: 2-Node Mutual Circular Dependency (NodeA <-> NodeB)
echo "  Testing 1.1: 2-Node Mutual Circular Dependency...\n";
mkdir($tmpDir . '/engine/TwoNodeA', 0777, true);
mkdir($tmpDir . '/engine/TwoNodeB', 0777, true);

file_put_contents($tmpDir . '/engine/TwoNodeA/NodeA.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace Oshim\TwoNodeA;
use Oshim\TwoNodeB\NodeB;
class NodeA {
    private ?NodeB $b = null;
    public function setB(NodeB $b): void { $this->b = $b; }
    public function getB(): ?NodeB { return $this->b; }
    public function createB(): NodeB { return new NodeB(); }
    public function ping(): string { return 'PongFromA'; }
}
PHP
);

file_put_contents($tmpDir . '/engine/TwoNodeB/NodeB.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace Oshim\TwoNodeB;
use Oshim\TwoNodeA\NodeA;
class NodeB {
    private ?NodeA $a = null;
    public function setA(NodeA $a): void { $this->a = $a; }
    public function getA(): ?NodeA { return $this->a; }
    public function createA(): NodeA { return new NodeA(); }
    public function pong(): string { return 'PingFromB'; }
}
PHP
);

$appTwoNode = $tmpDir . '/app_two_node.php';
$bundleTwoNode = $tmpDir . '/bundle_two_node.php';
file_put_contents($appTwoNode, <<<'PHP'
<?php
use Oshim\TwoNodeA\NodeA;
use Oshim\TwoNodeB\NodeB;

$a = new NodeA();
$b = $a->createB();
$a->setB($b);
$b->setA($a);

echo $a->ping() . ':' . $b->pong() . ':' . $a->getB()->getA()->ping();
PHP
);

$resTwoNode = $packager->compile($appTwoNode, $bundleTwoNode);
assertCondition($resTwoNode['status'] === 'COMPILED_SUCCESS', "2-node package status is COMPILED_SUCCESS");
assertCondition(is_file($bundleTwoNode), "2-node bundle file exists on disk");
assertCondition(in_array('Oshim\TwoNodeA\NodeA', $resTwoNode['classes_bundled'], true), "2-node bundle contains NodeA");
assertCondition(in_array('Oshim\TwoNodeB\NodeB', $resTwoNode['classes_bundled'], true), "2-node bundle contains NodeB");

$twoNodeOutput = shell_exec(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($bundleTwoNode));
assertCondition(trim((string)$twoNodeOutput) === 'PongFromA:PingFromB:PongFromA', "2-node bundle executed in isolated PHP process produces exact expected output", "Got: " . trim((string)$twoNodeOutput));

// 1.2: 5-Node Complete Lattice Directed Graph K5 (every node calls all other 4 nodes)
echo "  Testing 1.2: 5-Node Complete Directed Lattice Graph (K5)...\n";
mkdir($tmpDir . '/engine/K5Lattice', 0777, true);

for ($i = 0; $i < 5; $i++) {
    $uses = '';
    $methods = '';
    for ($j = 0; $j < 5; $j++) {
        if ($i === $j) continue;
        $uses .= "use Oshim\\K5Lattice\\KNode{$j};\n";
        $methods .= "    public function toNode{$j}(): KNode{$j} { return new KNode{$j}(); }\n";
    }
    $code = "<?php\ndeclare(strict_types=1);\nnamespace Oshim\\K5Lattice;\n{$uses}class KNode{$i} {\n    public function id(): int { return {$i}; }\n{$methods}}\n";
    file_put_contents($tmpDir . "/engine/K5Lattice/KNode{$i}.php", $code);
}

$appK5 = $tmpDir . '/app_k5.php';
$bundleK5 = $tmpDir . '/bundle_k5.php';
$k5AppCode = "<?php\nuse Oshim\\K5Lattice\\KNode0;\n\$n = new KNode0();\necho \$n->id() . '->' . \$n->toNode1()->toNode2()->toNode3()->toNode4()->toNode0()->id();\n";
file_put_contents($appK5, $k5AppCode);

$resK5 = $packager->compile($appK5, $bundleK5);
assertCondition($resK5['status'] === 'COMPILED_SUCCESS', "5-node K5 lattice package status is COMPILED_SUCCESS");
$k5Bundled = array_filter($resK5['classes_bundled'], fn($c) => str_contains($c, 'KNode'));
assertCondition(count($k5Bundled) === 5, "5-node K5 lattice discovered and bundled all 5 KNode classes", "Count: " . count($k5Bundled));

$k5Output = shell_exec(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($bundleK5));
assertCondition(trim((string)$k5Output) === '0->0', "5-node K5 lattice executed in isolated process traversed entire ring (0->0)", "Got: " . trim((string)$k5Output));

// 1.3: 10-Node Ring Graph (Node0 -> Node1 -> ... -> Node9 -> Node0)
echo "  Testing 1.3: 10-Node Deep Ring Circular Graph...\n";
mkdir($tmpDir . '/engine/Ring10', 0777, true);

for ($i = 0; $i < 10; $i++) {
    $next = ($i + 1) % 10;
    $code = "<?php\ndeclare(strict_types=1);\nnamespace Oshim\\Ring10;\nuse Oshim\\Ring10\\RNode{$next};\nclass RNode{$i} {\n    public function next(): RNode{$next} { return new RNode{$next}(); }\n    public function val(): int { return {$i}; }\n}\n";
    file_put_contents($tmpDir . "/engine/Ring10/RNode{$i}.php", $code);
}

$appRing = $tmpDir . '/app_ring.php';
$bundleRing = $tmpDir . '/bundle_ring.php';
$ringAppCode = "<?php\nuse Oshim\\Ring10\\RNode0;\n\$cur = new RNode0();\n\$log = [];\nfor (\$i = 0; \$i < 10; \$i++) { \$log[] = \$cur->val(); \$cur = \$cur->next(); }\necho implode(',', \$log);\n";
file_put_contents($appRing, $ringAppCode);

$resRing = $packager->compile($appRing, $bundleRing);
assertCondition($resRing['status'] === 'COMPILED_SUCCESS', "10-node ring package status is COMPILED_SUCCESS");
$ringBundled = array_filter($resRing['classes_bundled'], fn($c) => str_contains($c, 'RNode'));
assertCondition(count($ringBundled) === 10, "10-node ring bundled all 10 RNode classes", "Count: " . count($ringBundled));

$ringOutput = shell_exec(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($bundleRing));
assertCondition(trim((string)$ringOutput) === '0,1,2,3,4,5,6,7,8,9', "10-node ring executed in isolated process traversed entire sequence (0-9)", "Got: " . trim((string)$ringOutput));

// 1.4: Cross-Namespace Mutual Circular References
echo "  Testing 1.4: Cross-Namespace Mutual Dependencies (Alpha <-> Beta)...\n";
mkdir($tmpDir . '/engine/CrossAlpha', 0777, true);
mkdir($tmpDir . '/engine/CrossBeta', 0777, true);

file_put_contents($tmpDir . '/engine/CrossAlpha/AlphaService.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace Oshim\CrossAlpha;
use Oshim\CrossBeta\BetaService;
class AlphaService {
    public function callBeta(): string {
        $beta = new BetaService();
        return 'Alpha->' . $beta->name();
    }
    public function name(): string { return 'Alpha'; }
}
PHP
);

file_put_contents($tmpDir . '/engine/CrossBeta/BetaService.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace Oshim\CrossBeta;
use Oshim\CrossAlpha\AlphaService;
class BetaService {
    public function callAlpha(): string {
        $alpha = new AlphaService();
        return 'Beta->' . $alpha->name();
    }
    public function name(): string { return 'Beta'; }
}
PHP
);

$appCross = $tmpDir . '/app_cross.php';
$bundleCross = $tmpDir . '/bundle_cross.php';
file_put_contents($appCross, <<<'PHP'
<?php
use Oshim\CrossAlpha\AlphaService;
use Oshim\CrossBeta\BetaService;

$alpha = new AlphaService();
$beta = new BetaService();
echo $alpha->callBeta() . '|' . $beta->callAlpha();
PHP
);

$resCross = $packager->compile($appCross, $bundleCross);
assertCondition($resCross['status'] === 'COMPILED_SUCCESS', "Cross-namespace package status is COMPILED_SUCCESS");
$crossOutput = shell_exec(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($bundleCross));
assertCondition(trim((string)$crossOutput) === 'Alpha->Beta|Beta->Alpha', "Cross-namespace bundle executed in isolated process", "Got: " . trim((string)$crossOutput));

// 1.5: Parent / Child Inheritance Topological Order & Factory Reference
echo "  Testing 1.5: Parent/Child Mutual Reference & Topological Order...\n";
mkdir($tmpDir . '/engine/InheritMut', 0777, true);

file_put_contents($tmpDir . '/engine/InheritMut/BaseElement.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace Oshim\InheritMut;
use Oshim\InheritMut\ChildElement;
class BaseElement {
    public function name(): string { return 'Base'; }
    public function makeChild(): ChildElement { return new ChildElement(); }
}
PHP
);

file_put_contents($tmpDir . '/engine/InheritMut/ChildElement.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace Oshim\InheritMut;
class ChildElement extends BaseElement {
    public function name(): string { return 'Child:' . parent::name(); }
}
PHP
);

$appInherit = $tmpDir . '/app_inherit.php';
$bundleInherit = $tmpDir . '/bundle_inherit.php';
file_put_contents($appInherit, <<<'PHP'
<?php
use Oshim\InheritMut\BaseElement;
$base = new BaseElement();
$child = $base->makeChild();
echo $child->name();
PHP
);

$resInherit = $packager->compile($appInherit, $bundleInherit);
assertCondition($resInherit['status'] === 'COMPILED_SUCCESS', "Inheritance mutual ref package status is COMPILED_SUCCESS");

// Verify BaseElement is sorted BEFORE ChildElement in bundle
$bundleContent = (string)file_get_contents($bundleInherit);
$posBase = strpos($bundleContent, 'class BaseElement');
$posChild = strpos($bundleContent, 'class ChildElement');
assertCondition($posBase !== false && $posChild !== false && $posBase < $posChild, "BaseElement is topologically ordered before ChildElement in generated bundle");

$inheritOutput = shell_exec(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($bundleInherit));
assertCondition(trim((string)$inheritOutput) === 'Child:Base', "Inherit mutual ref bundle executed in isolated PHP process produces exact output", "Got: " . trim((string)$inheritOutput));

// Cleanup temp test packager directory
exec("rm -rf " . escapeshellarg($tmpDir));

echo "\n";

// ====================================================================
// SECTION 2: VECTOR RAG & MATRIX MATH ADVERSARIAL STRESS TESTING
// ====================================================================
echo ">>> SECTION 2: Vector RAG & MatrixMath Adversarial Stress Testing\n";

// 2.1: Empty Corpus Handling
echo "  Testing 2.1: Empty Corpus Handling...\n";
$emptyStore = new VectorStore(VectorStore::METRIC_COSINE);
assertCondition($emptyStore->search([1.0, 2.0, 3.0], 5) === [], "VectorStore search on empty corpus (cosine) returns empty array");

$emptyStoreEuclidean = new VectorStore(VectorStore::METRIC_EUCLIDEAN);
assertCondition($emptyStoreEuclidean->search([1.0, 2.0, 3.0], 5) === [], "VectorStore search on empty corpus (euclidean) returns empty array");

$emptyStoreDot = new VectorStore(VectorStore::METRIC_DOT);
assertCondition($emptyStoreDot->search([1.0, 2.0, 3.0], 5) === [], "VectorStore search on empty corpus (dot) returns empty array");

assertCondition($emptyStore->search([], 5) === [], "VectorStore search with empty vector query returns empty array");

$emptyHybrid = new HybridSearchEngine();
assertCondition($emptyHybrid->search("Any query against empty", 5) === [], "HybridSearchEngine search on empty index returns empty array");
assertCondition($emptyHybrid->search("", 5) === [], "HybridSearchEngine search with empty string query returns empty array");
assertCondition($emptyHybrid->search("Query", 0) === [], "HybridSearchEngine search with topK=0 returns empty array");
assertCondition($emptyHybrid->search("Query", -5) === [], "HybridSearchEngine search with topK=-5 returns empty array");

$emptyPipeline = new RagPipeline();
$pipelineRes = $emptyPipeline->ask("How does sovereign meta-framework work?", 3);
assertCondition(is_array($pipelineRes), "RagPipeline ask() returns array on empty corpus");
assertCondition($pipelineRes['retrieved_contexts'] === [], "RagPipeline retrieved_contexts is empty on empty corpus");
assertCondition($pipelineRes['source_docs'] === [], "RagPipeline source_docs is empty on empty corpus");
assertCondition(!empty($pipelineRes['answer']), "RagPipeline answer is non-empty fallback string on empty corpus");

// 2.2: Zero-Norm Vectors & Non-Division-by-Zero
echo "  Testing 2.2: Zero-Norm Vectors & Division-by-Zero Safety...\n";
$vStore = new VectorStore(VectorStore::METRIC_COSINE);
$vStore->upsert('zero_1', [0.0, 0.0, 0.0]);
$vStore->upsert('zero_2', [0.0, 0.0, 0.0]);
$vStore->upsert('nonzero', [1.0, 1.0, 1.0]);

$zeroSearch = $vStore->search([0.0, 0.0, 0.0], 5);
assertCondition(count($zeroSearch) === 3, "VectorStore searches zero query without fatal or division by zero");
assertCondition((float)$zeroSearch[0]['score'] === 0.0, "Zero query cosine score is 0.0");

$nonZeroSearch = $vStore->search([1.0, 1.0, 1.0], 5);
assertCondition($nonZeroSearch[0]['id'] === 'nonzero', "Non-zero query correctly ranks 'nonzero' first");
assertCondition((float)$nonZeroSearch[0]['score'] > 0.99, "Non-zero query score is ~1.0");
assertCondition((float)$nonZeroSearch[1]['score'] === 0.0, "Zero-vector entries score 0.0 against non-zero query");

// MatrixMath direct methods
assertCondition(MatrixMath::cosineSimilarity([0.0, 0.0], [0.0, 0.0]) === 0.0, "MatrixMath::cosineSimilarity([0,0],[0,0]) === 0.0");
assertCondition(MatrixMath::cosineSimilarity([1.0, 2.0], [0.0, 0.0]) === 0.0, "MatrixMath::cosineSimilarity([1,2],[0,0]) === 0.0");
assertCondition(MatrixMath::l2Normalize([0.0, 0.0, 0.0]) === [0.0, 0.0, 0.0], "MatrixMath::l2Normalize([0,0,0]) returns zero vector without dividing by zero");

// TfIdf zero vectors
$emptyVec = TfIdfEmbedder::embed("");
assertCondition(count($emptyVec) === 64, "TfIdfEmbedder::embed('') returns 64-dim vector");
assertCondition(array_sum($emptyVec) === 0.0, "TfIdfEmbedder::embed('') returns all-zero vector");

$stopWordVec = TfIdfEmbedder::embed("the is at which on a an and or in to of for by with this that it as are");
assertCondition(count($stopWordVec) === 64, "TfIdfEmbedder stop-words only returns 64-dim vector");
assertCondition(array_sum($stopWordVec) === 0.0, "TfIdfEmbedder stop-words only returns all-zero vector");

// 2.3: Associative Non-Sequential Vector Keys
echo "  Testing 2.3: Associative and Non-Sequential Vector Keys...\n";
$assocStore = new VectorStore(VectorStore::METRIC_COSINE);
$assocStore->upsert('doc_string_keys', ['dim_a' => 1.0, 'dim_b' => 2.0, 'dim_c' => 3.0]);
$assocStore->upsert('doc_negative_keys', [-5 => 1.0, -10 => 2.0, -20 => 3.0]);
$assocStore->upsert('doc_sparse_keys', [100 => 1.0, 500 => 2.0, 999 => 3.0]);

$assocRes = $assocStore->search(['query_x' => 1.0, 'query_y' => 2.0, 'query_z' => 3.0], 5);
assertCondition(count($assocRes) === 3, "Associative query search returns all 3 documents without Undefined array key error");
assertCondition((float)$assocRes[0]['score'] > 0.99, "Associative identical-ratio vector has score ~1.0");

$dotAssocStore = new VectorStore(VectorStore::METRIC_DOT);
$dotAssocStore->upsert('d1', ['foo' => 2.0, 'bar' => 3.0]);
$dotRes = $dotAssocStore->search(['baz' => 4.0, 'qux' => 5.0], 1);
assertCondition(count($dotRes) === 1, "Associative METRIC_DOT search executed cleanly");
assertCondition((float)$dotRes[0]['score'] === 23.0, "Associative dot product computed correctly (2*4 + 3*5 = 23.0)", "Got: " . $dotRes[0]['score']);

$euclidAssocStore = new VectorStore(VectorStore::METRIC_EUCLIDEAN);
$euclidAssocStore->upsert('d1', ['k1' => 0.0, 'k2' => 0.0]);
$euclidRes = $euclidAssocStore->search(['k9' => 3.0, 'k8' => 4.0], 1);
assertCondition(count($euclidRes) === 1, "Associative METRIC_EUCLIDEAN search executed cleanly");
assertCondition(abs((float)$euclidRes[0]['score'] - 0.166667) < 0.001, "Euclidean score computed accurately (1 / (1+5) ≈ 0.166667)", "Got: " . $euclidRes[0]['score']);

// Import/Export persistence with associative keys
$jsonExp = $assocStore->exportJson();
$assocStoreImported = new VectorStore();
$assocStoreImported->importJson($jsonExp);
assertCondition($assocStoreImported->count() === 3, "VectorStore successfully exported and re-imported 3 associative vector records");
$importSearch = $assocStoreImported->search([1.0, 2.0, 3.0], 1);
assertCondition(count($importSearch) === 1 && (float)$importSearch[0]['score'] > 0.99, "Re-imported associative vector search produces score ~1.0");

// 2.4: Malformed Non-UTF8 Byte Streams
echo "  Testing 2.4: Malformed Non-UTF8 Byte Streams...\n";
$corruptedStreams = [
    "\x80\xFF\xFE\xAA Invalid Non-UTF8 \xC0\xAF Binary Stream",
    "\x00\x01\x02\x03\x04\x05\xFF\xFF\xAA\xBB",
    "\xF0\x28\x8C\x28 Malformed 4-byte UTF8 sequence",
    str_repeat("\xFF", 100),
    "Valid prefix \xC3\x28 invalid second byte \x80\x80 valid suffix",
];

foreach ($corruptedStreams as $idx => $stream) {
    $tokens = TfIdfEmbedder::tokenize($stream);
    assertCondition(is_array($tokens), "TfIdfEmbedder::tokenize on corrupt stream #{$idx} returns array");

    $embed = TfIdfEmbedder::embed($stream);
    assertCondition(count($embed) === 64, "TfIdfEmbedder::embed on corrupt stream #{$idx} returns 64-dim vector");

    TfIdfEmbedder::indexDocument($stream);
}

$hSearch = new HybridSearchEngine();
$hSearch->index('corrupt_doc', "\x80\xFF\xFE Corrupt Binary Document Content with Keyword SovereignMeta");
$searchRes = $hSearch->search("SovereignMeta", 3);
assertCondition(count($searchRes) >= 1 && $searchRes[0]['doc_id'] === 'corrupt_doc', "HybridSearchEngine searches corrupt doc successfully and finds keyword");

$searchCorruptQuery = $hSearch->search("\xFF\xFE\xAA", 3);
assertCondition(is_array($searchCorruptQuery), "HybridSearchEngine handles corrupt binary query without crashing");

// 2.5: Boundary topK Values
echo "  Testing 2.5: Boundary topK Values...\n";
$boundStore = new VectorStore();
$boundStore->upsert('doc1', [1.0, 0.0]);
$boundStore->upsert('doc2', [0.0, 1.0]);

assertCondition($boundStore->search([1.0, 0.0], 0) === [], "VectorStore::search topK = 0 returns []");
assertCondition($boundStore->search([1.0, 0.0], -1) === [], "VectorStore::search topK = -1 returns []");
assertCondition($boundStore->search([1.0, 0.0], -999) === [], "VectorStore::search topK = -999 returns []");
assertCondition(count($boundStore->search([1.0, 0.0], 1)) === 1, "VectorStore::search topK = 1 returns exactly 1 item");
assertCondition(count($boundStore->search([1.0, 0.0], 1000000)) === 2, "VectorStore::search topK = 1000000 returns all available items without error");

echo "\n";

// ====================================================================
// SECTION 3: TAILWIND JIT COMPILER ADVERSARIAL STRESS TESTING
// ====================================================================
echo ">>> SECTION 3: Tailwind JIT Compiler Adversarial Stress Testing\n";

// 3.1: Arbitrary Bracket Classes
echo "  Testing 3.1: Arbitrary Bracket Values (Colors, Dimensions, Calcs)...\n";
$html1 = '<div class="bg-[#1a2b3c] text-[#00ff88] border-[#334455] h-[calc(100vh-4rem)] w-[min(100vw,800px)] grid-cols-[1fr_2fr_1fr] p-[15px] m-[2.5rem] rounded-[10px] gap-[18px] top-[50px] left-[10vw] z-[999]">';
$css1 = TailwindJitCompiler::compile($html1);

assertCondition(str_contains($css1, 'background-color:#1a2b3c;'), "Arbitrary hex bg-[#1a2b3c] compiles to background-color:#1a2b3c;");
assertCondition(str_contains($css1, 'color:#00ff88;'), "Arbitrary hex text-[#00ff88] compiles to color:#00ff88;");
assertCondition(str_contains($css1, 'border-color:#334455;'), "Arbitrary hex border-[#334455] compiles to border-color:#334455;");
assertCondition(str_contains($css1, 'height:calc(100vh-4rem);'), "Arbitrary h-[calc(100vh-4rem)] compiles to height:calc(100vh-4rem);");
assertCondition(str_contains($css1, 'width:min(100vw,800px);'), "Arbitrary w-[min(100vw,800px)] compiles to width:min(100vw,800px);");
assertCondition(str_contains($css1, 'grid-template-columns:1fr 2fr 1fr;'), "Arbitrary grid-cols-[1fr_2fr_1fr] compiles with underscore replacement");
assertCondition(str_contains($css1, 'padding:15px;'), "Arbitrary p-[15px] compiles to padding:15px;");
assertCondition(str_contains($css1, 'margin:2.5rem;'), "Arbitrary m-[2.5rem] compiles to margin:2.5rem;");
assertCondition(str_contains($css1, 'border-radius:10px;'), "Arbitrary rounded-[10px] compiles to border-radius:10px;");
assertCondition(str_contains($css1, 'gap:18px;'), "Arbitrary gap-[18px] compiles to gap:18px;");
assertCondition(str_contains($css1, 'top:50px;'), "Arbitrary top-[50px] compiles to top:50px;");
assertCondition(str_contains($css1, 'left:10vw;'), "Arbitrary left-[10vw] compiles to left:10vw;");
assertCondition(str_contains($css1, 'z-index:999;'), "Arbitrary z-[999] compiles to z-index:999;");

// CSS Selector Escaping Verification
assertCondition(str_contains($css1, '.bg-\\[\\#1a2b3c\\]'), "CSS selector for .bg-[#1a2b3c] has escaped [#]");
assertCondition(str_contains($css1, '.h-\\[calc\\(100vh-4rem\\)\\]'), "CSS selector for .h-[calc(100vh-4rem)] has escaped brackets and parens");

// 3.2: Negative Arbitrary Values
echo "  Testing 3.2: Negative Arbitrary Values (-prop-[val])...\n";
$html2 = '<div class="-top-[12px] -mt-[20px] -m-[1rem] -left-[5px] -z-[10] -bottom-[30px] -right-[15px] -mb-[8px] -ml-[4px] -mr-[6px]">';
$css2 = TailwindJitCompiler::compile($html2);

assertCondition(str_contains($css2, 'top:-12px;'), "Negative arbitrary -top-[12px] compiles to top:-12px;");
assertCondition(str_contains($css2, 'margin-top:-20px;'), "Negative arbitrary -mt-[20px] compiles to margin-top:-20px;");
assertCondition(str_contains($css2, 'margin:-1rem;'), "Negative arbitrary -m-[1rem] compiles to margin:-1rem;");
assertCondition(str_contains($css2, 'left:-5px;'), "Negative arbitrary -left-[5px] compiles to left:-5px;");
assertCondition(str_contains($css2, 'z-index:-10;'), "Negative arbitrary -z-[10] compiles to z-index:-10;");
assertCondition(str_contains($css2, 'bottom:-30px;'), "Negative arbitrary -bottom-[30px] compiles to bottom:-30px;");
assertCondition(str_contains($css2, 'right:-15px;'), "Negative arbitrary -right-[15px] compiles to right:-15px;");
assertCondition(str_contains($css2, 'margin-bottom:-8px;'), "Negative arbitrary -mb-[8px] compiles to margin-bottom:-8px;");
assertCondition(str_contains($css2, 'margin-left:-4px;'), "Negative arbitrary -ml-[4px] compiles to margin-left:-4px;");
assertCondition(str_contains($css2, 'margin-right:-6px;'), "Negative arbitrary -mr-[6px] compiles to margin-right:-6px;");

// 3.3: Negative Standard Values
echo "  Testing 3.3: Negative Standard Values (-top-4, -z-20, etc.)...\n";
$html3 = '<div class="-top-4 -mt-6 -z-20 -bottom-2 -left-8 -right-1 -m-4 -mb-8 -ml-2 -mr-3">';
$css3 = TailwindJitCompiler::compile($html3);

assertCondition(str_contains($css3, 'top:-1rem;'), "Negative standard -top-4 compiles to top:-1rem;");
assertCondition(str_contains($css3, 'margin-top:-1.5rem;'), "Negative standard -mt-6 compiles to margin-top:-1.5rem;");
assertCondition(str_contains($css3, 'z-index:-20;'), "Negative standard -z-20 compiles to z-index:-20;");
assertCondition(str_contains($css3, 'bottom:-0.5rem;'), "Negative standard -bottom-2 compiles to bottom:-0.5rem;");
assertCondition(str_contains($css3, 'left:-2rem;'), "Negative standard -left-8 compiles to left:-2rem;");
assertCondition(str_contains($css3, 'right:-0.25rem;'), "Negative standard -right-1 compiles to right:-0.25rem;");
assertCondition(str_contains($css3, 'margin:-1rem;'), "Negative standard -m-4 compiles to margin:-1rem;");
assertCondition(str_contains($css3, 'margin-bottom:-2rem;'), "Negative standard -mb-8 compiles to margin-bottom:-2rem;");
assertCondition(str_contains($css3, 'margin-left:-0.5rem;'), "Negative standard -ml-2 compiles to margin-left:-0.5rem;");
assertCondition(str_contains($css3, 'margin-right:-0.75rem;'), "Negative standard -mr-3 compiles to margin-right:-0.75rem;");

// 3.4: Stacked Pseudo-Selectors & Media Variants
echo "  Testing 3.4: Stacked Pseudo-Selectors & Media Variants...\n";
$html4 = '<button class="hover:focus:text-blue-500 hover:focus:scale-105 active:hover:bg-green-600 md:hover:bg-red-500 lg:focus:bg-blue-600">';
$css4 = TailwindJitCompiler::compile($html4);

assertCondition(str_contains($css4, '.hover\\:focus\\:text-blue-500:hover:focus{color:#3b82f6;}'), "Stacked hover:focus:text-blue-500 selector and body match");
assertCondition(str_contains($css4, '.hover\\:focus\\:scale-105:hover:focus{transform:scale(1.05);}'), "Stacked hover:focus:scale-105 selector and body match");
assertCondition(str_contains($css4, '.active\\:hover\\:bg-green-600:active:hover{background-color:#16a34a;}'), "Stacked active:hover:bg-green-600 selector and body match");
assertCondition(str_contains($css4, '@media (min-width: 768px)'), "Media query @media (min-width: 768px) present for md:hover:");
assertCondition(str_contains($css4, '.md\\:hover\\:bg-red-500:hover{background-color:#ef4444;}'), "Media query rule .md:hover:bg-red-500:hover present");
assertCondition(str_contains($css4, '@media (min-width: 1024px)'), "Media query @media (min-width: 1024px) present for lg:focus:");
assertCondition(str_contains($css4, '.lg\\:focus\\:bg-blue-600:focus{background-color:#2563eb;}'), "Media query rule .lg:focus:bg-blue-600:focus present");

// 3.5: Dark Mode with Multi-Variants
echo "  Testing 3.5: Dark Mode with Multi-Variants...\n";
$html5 = '<div class="dark:text-white dark:bg-slate-900 dark:hover:text-yellow-400 dark:hover:focus:bg-slate-800 md:dark:hover:focus:bg-red-500 sm:dark:active:text-blue-400">';
$css5 = TailwindJitCompiler::compile($html5);

assertCondition(str_contains($css5, '.dark .dark\\:text-white{color:#ffffff;}'), "Dark mode simple utility .dark .dark:text-white matches");
assertCondition(str_contains($css5, '.dark .dark\\:bg-slate-900{background-color:#0f172a;}'), "Dark mode .dark .dark:bg-slate-900 matches");
assertCondition(str_contains($css5, '.dark .dark\\:hover\\:text-yellow-400:hover{color:#facc15;}'), "Dark mode with hover .dark .dark:hover:text-yellow-400:hover matches");
assertCondition(str_contains($css5, '.dark .dark\\:hover\\:focus\\:bg-slate-800:hover:focus{background-color:#1e293b;}'), "Dark mode with stacked hover:focus matches");
assertCondition(str_contains($css5, '.dark .md\\:dark\\:hover\\:focus\\:bg-red-500:hover:focus{background-color:#ef4444;}'), "Dark mode stacked with media query and hover:focus matches");
assertCondition(str_contains($css5, '.dark .sm\\:dark\\:active\\:text-blue-400:active{color:#60a5fa;}'), "Dark mode with small breakpoint and active state matches");

echo "\n======================================================================\n";
echo " HARNESS RESULTS: Total Assertions: {$totalAssertions}, Failures: {$failedAssertions}\n";
echo "======================================================================\n";

if ($failedAssertions === 0) {
    echo "🎉 ALL ADVERSARIAL CHALLENGE ASSERTIONS PASSED WITH ZERO FAILURES!\n";
    exit(0);
} else {
    echo "❌ SOME ADVERSARIAL CHALLENGE ASSERTIONS FAILED!\n";
    exit(1);
}
