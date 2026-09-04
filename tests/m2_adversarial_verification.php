<?php
declare(strict_types=1);

namespace Tests\Adversarial;

require_once __DIR__ . '/../engine/Bootstrap.php';
$frameworkRoot = dirname(__DIR__);
\Oshim\Bootstrap::boot($frameworkRoot);

use Oshim\Ui\Router\AppRouter;
use Oshim\Kernel\MicroKernel;
use Oshim\Kernel\RouteParameterExtractor;
use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Ledger\MerkleTree;
use Oshim\Ledger\Blockchain;

echo "============================================================\n";
echo "  👑 OSHIM Framework Milestone M2 Challenger Stress Harness \n";
echo "============================================================\n\n";

$totalAssertions = 0;
$passedAssertions = 0;

function ch_assert(bool $condition, string $description): void {
    global $totalAssertions, $passedAssertions;
    $totalAssertions++;
    if (!$condition) {
        throw new \RuntimeException("CHALLENGE FAILURE: {$description}");
    }
    $passedAssertions++;
}

// =========================================================================
// 1. AppRouter Adversarial Stress Testing
// =========================================================================
echo "[1] Adversarially Stress-Testing AppRouter...\n";

$router = new AppRouter();
$router->page('/', fn() => '<h1>Home</h1>', null, 'Home');
$router->page('/about', fn() => '<h1>About</h1>', null, 'About');
$router->page('/vps/[id]', fn($p) => "<h1>VPS {$p['id']}</h1>", null, 'VPS Detail');
$router->page('/catalog/[category]/item/[itemId]', fn($p) => "Item: {$p['category']} / {$p['itemId']}", null, 'Item');

// 1.1 Malformed, empty, and multi-slashed URIs in resolve()
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
    // Neither resolve() nor dispatch() should ever throw TypeError or fatal errors
    $resolved = $router->resolve($uri);
    ch_assert($resolved === null || is_array($resolved), "resolve('{$uri}') returned valid type (null or array)");
    
    $dispatched = $router->dispatch($uri);
    ch_assert($dispatched === null || $dispatched instanceof Response, "dispatch('{$uri}') returned valid type (null or Response)");

    // Test with Request object
    $req = Request::create('GET', $uri);
    $dispReq = $router->dispatch($req);
    ch_assert($dispReq === null || $dispReq instanceof Response, "dispatch(Request::create('GET', '{$uri}')) returned valid type");
}

// 1.2 Deeply nested and extremely long URIs in resolve() and dispatch()
$deeplyNestedPath = '/' . implode('/', array_fill(0, 500, 'nest'));
$resolvedDeep = $router->resolve($deeplyNestedPath);
ch_assert($resolvedDeep === null, "Deeply nested 500-level path must return null gracefully");
$dispDeep = $router->dispatch($deeplyNestedPath);
ch_assert($dispDeep === null, "Dispatch deeply nested 500-level path must return null gracefully");

$thousandSlashes = str_repeat('/', 1000) . 'about' . str_repeat('/', 1000);
$resolvedSlashes = $router->resolve($thousandSlashes);
ch_assert($resolvedSlashes === null || is_array($resolvedSlashes), "Thousand slashes must not crash resolve()");
$dispSlashes = $router->dispatch($thousandSlashes);
ch_assert($dispSlashes === null || $dispSlashes instanceof Response, "Thousand slashes must not crash dispatch()");

$hugeUri = '/vps/' . str_repeat('A', 50000);
$resolvedHuge = $router->resolve($hugeUri);
ch_assert(is_array($resolvedHuge) && isset($resolvedHuge['params']['id']), "Huge 50KB parameter must resolve cleanly");
ch_assert(strlen($resolvedHuge['params']['id']) === 50000, "Huge parameter length preserved");
$dispHuge = $router->dispatch($hugeUri);
ch_assert($dispHuge instanceof Response && $dispHuge->getStatusCode() === 200, "Huge 50KB parameter dispatches cleanly");

// 1.3 Exact route resolution and dynamic segments
$rHome = $router->resolve('/');
ch_assert(is_array($rHome) && $rHome['page'] !== null, "Resolve / returns page");
ch_assert($rHome['params'] === [], "Home params empty");

$rAbout = $router->resolve('/about');
ch_assert(is_array($rAbout) && $rAbout['page'] !== null, "Resolve /about returns page");
$rAboutTrailing = $router->resolve('/about/');
ch_assert(is_array($rAboutTrailing) && $rAboutTrailing['page'] !== null, "Resolve /about/ with trailing slash matches");
$rAboutMultiTrailing = $router->resolve('/about///');
ch_assert(is_array($rAboutMultiTrailing) && $rAboutMultiTrailing['page'] !== null, "Resolve /about/// with multi-trailing slash matches");

$rVps = $router->resolve('/vps/node-alpha-99');
ch_assert(is_array($rVps) && $rVps['params']['id'] === 'node-alpha-99', "Dynamic param id extracted");

$rItem = $router->resolve('/catalog/servers/item/rack-01');
ch_assert(is_array($rItem) && $rItem['params']['category'] === 'servers' && $rItem['params']['itemId'] === 'rack-01', "Multi-segment params extracted");

// 1.4 URL-decoded dynamic segments
$rEncoded = $router->resolve('/catalog/cloud%20storage/item/backup%2301%26save');
ch_assert(is_array($rEncoded), "Encoded URI must resolve");
ch_assert($rEncoded['params']['category'] === 'cloud storage', "Category must be url-decoded");
ch_assert($rEncoded['params']['itemId'] === 'backup#01&save', "Item ID must be url-decoded");

// 1.5 AppRouter dispatch() with string and Request object
$dispStr = $router->dispatch('/about');
ch_assert($dispStr instanceof Response && $dispStr->getStatusCode() === 200, "Dispatch string URI returns 200 Response");
ch_assert(str_contains($dispStr->getContent(), '<h1>About</h1>'), "Dispatch string rendered HTML");

$dispReq = $router->dispatch(Request::create('GET', '/vps/4096'));
ch_assert($dispReq instanceof Response && $dispReq->getStatusCode() === 200, "Dispatch Request returns 200 Response");
ch_assert(str_contains($dispReq->getContent(), 'VPS 4096'), "Dispatch Request rendered VPS content");

$dispSoftNav = $router->dispatch(new Request('GET', '/about', ['X-Oshim-Soft-Nav' => '1']));
ch_assert($dispSoftNav instanceof Response && $dispSoftNav->getStatusCode() === 200, "Dispatch soft nav returns Response");

$dispNotFound = $router->dispatch('/non-existent-route-xyz');
ch_assert($dispNotFound === null, "Dispatch non-existent route returns null");

echo "    ✔ AppRouter stress tests passed (malformed URIs, 500-level nesting, 50KB segments, URL decoding, dispatching).\n\n";

// =========================================================================
// 2. MicroKernel Routing & RouteParameterExtractor Stress Testing
// =========================================================================
echo "[2] Adversarially Stress-Testing MicroKernel & RouteParameterExtractor...\n";

// 2.1 RouteParameterExtractor scalar type coercion
class DummyController {
    public function show(Request $req, int $id, float $score, bool $active): array {
        return ['id' => $id, 'score' => $score, 'active' => $active];
    }
}

$req = new Request('GET', '/test');

// Exact name match
$args1 = RouteParameterExtractor::resolveArgs(
    fn(Request $r, int $id, float $rate, bool $enabled, string $label) => [$id, $rate, $enabled, $label],
    $req,
    ['id' => '123', 'rate' => '45.67', 'enabled' => 'true', 'label' => 'Production']
);
ch_assert($args1[0] === $req, "Arg 0 is Request");
ch_assert($args1[1] === 123, "Arg 1 coerced to int 123");
ch_assert($args1[2] === 45.67, "Arg 2 coerced to float 45.67");
ch_assert($args1[3] === true, "Arg 3 coerced to bool true");
ch_assert($args1[4] === 'Production', "Arg 4 string preserved");

// Negative numbers & boolean variations
$boolCases = [
    '1' => true,
    'true' => true,
    'TRUE' => true,
    'yes' => true,
    'YES' => true,
    'on' => true,
    'ON' => true,
    '0' => false,
    'false' => false,
    'no' => false,
    'off' => false,
    'random' => false,
    '' => false,
];
foreach ($boolCases as $rawBool => $expectedBool) {
    $argsBool = RouteParameterExtractor::resolveArgs(
        fn(bool $flag) => $flag,
        $req,
        ['flag' => $rawBool]
    );
    ch_assert($argsBool[0] === $expectedBool, "Boolean raw '{$rawBool}' correctly coerced to " . var_export($expectedBool, true));
}

// Negative int & float & scientific notation
$argsNeg = RouteParameterExtractor::resolveArgs(
    fn(int $negInt, float $negFloat, float $sciFloat) => [$negInt, $negFloat, $sciFloat],
    $req,
    ['negInt' => '-500', 'negFloat' => '-123.456', 'sciFloat' => '1.5e-3']
);
ch_assert($argsNeg[0] === -500, "Negative int coerced correctly");
ch_assert($argsNeg[1] === -123.456, "Negative float coerced correctly");
ch_assert($argsNeg[2] === 0.0015, "Scientific notation float coerced correctly");

// 2.2 Positional fallback on mismatched parameter names
$argsPositional = RouteParameterExtractor::resolveArgs(
    fn(int $targetA, string $targetB) => [$targetA, $targetB],
    $req,
    ['route_param_x' => '999', 'route_param_y' => 'fallback_string']
);
ch_assert($argsPositional[0] === 999, "Positional fallback coerced int 999");
ch_assert($argsPositional[1] === 'fallback_string', "Positional fallback captured string");

// 2.3 Mixed named match and positional fallback
$argsMixed = RouteParameterExtractor::resolveArgs(
    fn(string $namedExact, int $posFallback) => [$namedExact, $posFallback],
    $req,
    ['other_key' => '777', 'namedExact' => 'matched_value']
);
ch_assert($argsMixed[0] === 'matched_value', "Named exact match resolved first");
ch_assert($argsMixed[1] === 777, "Remaining key used for positional fallback");

// 2.4 Missing arguments with default values and nullable types
$argsDefaults = RouteParameterExtractor::resolveArgs(
    fn(int $id, string $role = 'guest', ?float $optScore = null) => [$id, $role, $optScore],
    $req,
    ['id' => '101']
);
ch_assert($argsDefaults[0] === 101, "Provided arg coerced");
ch_assert($argsDefaults[1] === 'guest', "Default value retained");
ch_assert($argsDefaults[2] === null, "Nullable arg set to null");

// 2.5 Controller method invocation via resolveArgs
$ctrl = new DummyController();
$argsCtrl = RouteParameterExtractor::resolveArgs(
    [$ctrl, 'show'],
    $req,
    ['id' => '88', 'score' => '9.5', 'active' => 'yes']
);
ch_assert($argsCtrl[0] === $req, "Controller method injected Request");
ch_assert($argsCtrl[1] === 88, "Controller id coerced to int");
ch_assert($argsCtrl[2] === 9.5, "Controller score coerced to float");
ch_assert($argsCtrl[3] === true, "Controller active coerced to bool");

// 2.6 MicroKernel end-to-end routing with parameters and HTTP methods
$kernel = MicroKernel::create();
$kernel->get('/api/users/{id}', fn(Request $r, int $id) => Response::json(['user' => $id, 'has' => $r->has('id')]));
$kernel->post('/api/items/{category}/{itemId}', fn(string $category, string $itemId) => "CREATED {$category}:{$itemId}");
$kernel->get('/api/search/{query}', fn(string $query) => "SEARCH: {$query}");
$kernel->get('/api/optional/{tag?}', fn(?string $tag = 'default') => "TAG: " . ($tag ?? 'none'));

// Test GET /api/users/12345
$resUser = $kernel->handle(new Request('GET', '/api/users/12345'));
ch_assert($resUser->statusCode() === 200, "MicroKernel GET user returned 200");
$userData = json_decode((string)$resUser->body(), true);
ch_assert($userData['user'] === 12345, "User id passed as integer 12345");
ch_assert($userData['has'] === true, "Request->has('id') is true");

// Test POST /api/items/electronics/tv-4k
$resPost = $kernel->handle(new Request('POST', '/api/items/electronics/tv-4k'));
ch_assert($resPost->statusCode() === 200, "MicroKernel POST item returned 200");
ch_assert((string)$resPost->body() === 'CREATED electronics:tv-4k', "Handler received both parameters");

// Test URL decoding: /api/search/deep%20learning%20%2B%20neural%20networks
$resSearch = $kernel->handle(new Request('GET', '/api/search/deep%20learning%20%2B%20neural%20networks'));
ch_assert((string)$resSearch->body() === 'SEARCH: deep learning + neural networks', "Parameter was properly URL-decoded");

// Test Optional Parameter: present vs absent
$resOptPresent = $kernel->handle(new Request('GET', '/api/optional/special'));
ch_assert((string)$resOptPresent->body() === 'TAG: special', "Optional parameter present");

$resOptAbsent = $kernel->handle(new Request('GET', '/api/optional'));
ch_assert((string)$resOptAbsent->body() === 'TAG: default', "Optional parameter absent uses default");

// Test 404 on unmatched route and wrong method
$res404 = $kernel->handle(new Request('GET', '/api/not/existing'));
ch_assert($res404->statusCode() === 404, "Unmatched route returns 404");

$resWrongMethod = $kernel->handle(new Request('DELETE', '/api/users/12345'));
ch_assert($resWrongMethod->statusCode() === 404, "Wrong method returns 404");

echo "    ✔ MicroKernel & RouteParameterExtractor passed (strict casting, defaults, mismatched names, optional params, 404s).\n\n";

// =========================================================================
// 3. MerkleTree & Blockchain Ledger Adversarial Stress Testing
// =========================================================================
echo "[3] Adversarially Stress-Testing MerkleTree & Cryptographic Ledger...\n";

// 3.1 Empty Tree
$emptyTree = new MerkleTree([]);
$emptyRoot = $emptyTree->getRoot();
ch_assert($emptyRoot === hash('sha256', ''), "Empty tree root is sha256('')");
ch_assert($emptyTree->getLeaves() === [], "Empty tree has 0 leaves");

try {
    $emptyTree->getProof(0);
    ch_assert(false, "getProof(0) on empty tree must throw OutOfBoundsException");
} catch (\OutOfBoundsException $e) {
    ch_assert(str_contains($e->getMessage(), 'out of bounds'), "Caught OutOfBoundsException on empty tree");
}

ch_assert(MerkleTree::verifyProof('', [], '') === false, "Empty strings must not verify");
ch_assert(MerkleTree::verifyProof(hash('sha256', 'a'), [], '') === false, "Empty root must not verify");
ch_assert(MerkleTree::verifyProof('', [], hash('sha256', 'a')) === false, "Empty leaf must not verify");

// 3.2 Single-Leaf Tree
$singleTree = new MerkleTree(['lone_tx']);
$singleLeaves = $singleTree->getLeaves();
$singleRoot = $singleTree->getRoot();
ch_assert(count($singleLeaves) === 1, "Single tree has 1 leaf");
ch_assert($singleRoot === $singleLeaves[0], "Single leaf hash equals tree root");

$singleProof = $singleTree->getProof(0);
ch_assert($singleProof === [], "Single leaf proof is empty array");
ch_assert(MerkleTree::verifyProof($singleLeaves[0], $singleProof, $singleRoot) === true, "Single leaf verifies with empty proof");

// Tampered leaf against single root
$fakeLeaf = hash('sha256', 'tampered_tx');
ch_assert(MerkleTree::verifyProof($fakeLeaf, $singleProof, $singleRoot) === false, "Tampered single leaf fails");

// Out of bounds on single tree
foreach ([-1, 1, 2, 100] as $oobIndex) {
    try {
        $singleTree->getProof($oobIndex);
        ch_assert(false, "getProof({$oobIndex}) on single tree must throw OutOfBoundsException");
    } catch (\OutOfBoundsException $e) {
        ch_assert(true, "Caught expected OutOfBoundsException for index {$oobIndex}");
    }
}

// 3.3 Power-of-Two and Odd-Sized Trees: Comprehensive Verification
$treeSizes = [2, 3, 4, 5, 7, 8, 9, 15, 16, 17, 31, 32, 33, 63, 64, 65];

foreach ($treeSizes as $size) {
    $txs = [];
    for ($i = 0; $i < $size; $i++) {
        $txs[] = ['tx_id' => $i, 'sender' => "User_{$i}", 'amount' => $i * 10.5];
    }
    $t = new MerkleTree($txs);
    $root = $t->getRoot();
    $leaves = $t->getLeaves();

    ch_assert(count($leaves) === $size, "Tree size {$size} has {$size} leaves");
    ch_assert(strlen($root) === 64, "Tree size {$size} has 64-char hex root");

    // Every single leaf proof must verify
    for ($i = 0; $i < $size; $i++) {
        $proof = $t->getProof($i);
        ch_assert(MerkleTree::verifyProof($leaves[$i], $proof, $root), "Tree size {$size}: Leaf {$i} proof must verify");

        // Adversarial test 1: Altered leaf hash must FAIL
        $tampered = hash('sha256', "tampered_leaf_{$i}");
        ch_assert(MerkleTree::verifyProof($tampered, $proof, $root) === false, "Tree size {$size}: Tampered leaf {$i} must fail");

        // Adversarial test 2: If proof is not empty, mutated proof step must FAIL
        if (!empty($proof)) {
            $corruptProof = $proof;
            $corruptProof[0]['hash'] = hash('sha256', 'corrupted_step');
            ch_assert(MerkleTree::verifyProof($leaves[$i], $corruptProof, $root) === false, "Tree size {$size}: Mutated step must fail");

            // Inverted position: If step sibling hash != current leaf hash, position inversion must fail
            // (When an odd element pairs with itself, sibling == current, so position is symmetric)
            if ($proof[0]['hash'] !== $leaves[$i]) {
                $invProof = $proof;
                $invProof[0]['position'] = ($invProof[0]['position'] === 'left') ? 'right' : 'left';
                ch_assert(MerkleTree::verifyProof($leaves[$i], $invProof, $root) === false, "Tree size {$size}: Inverted position must fail");
            }

            // Truncated proof
            $truncProof = $proof;
            array_pop($truncProof);
            ch_assert(MerkleTree::verifyProof($leaves[$i], $truncProof, $root) === false, "Tree size {$size}: Truncated proof must fail");
        }

        // Adversarial test 3: Proof cross-application (proof of leaf i used for leaf (i+1)%size)
        if ($size > 1) {
            $wrongIndex = ($i + 1) % $size;
            $wrongProof = $t->getProof($wrongIndex);
            ch_assert(MerkleTree::verifyProof($leaves[$i], $wrongProof, $root) === false, "Tree size {$size}: Cross-proof must fail");
        }
    }

    // Bounds checking for size
    try {
        $t->getProof(-1);
        ch_assert(false, "Tree size {$size}: getProof(-1) must throw");
    } catch (\OutOfBoundsException $e) {
        ch_assert(true, "OutOfBoundsException for -1");
    }

    try {
        $t->getProof($size);
        ch_assert(false, "Tree size {$size}: getProof({$size}) must throw");
    } catch (\OutOfBoundsException $e) {
        ch_assert(true, "OutOfBoundsException for {$size}");
    }
}

// 3.4 Malformed Proof Structures Resilience
$dummyLeaf = hash('sha256', 'tx_dummy');
$dummyRoot = hash('sha256', 'root_dummy');

$malformedProofs = [
    [['invalid_structure' => true]],
    [['position' => 'left']], // missing hash
    [['hash' => hash('sha256', 'step')]], // missing position
    [['position' => 'center', 'hash' => hash('sha256', 'step')]], // invalid position
    [['position' => 'left', 'hash' => 12345]], // non-string hash
    ['string_instead_of_array_step'],
    [[null]],
    [['position' => null, 'hash' => null]],
    [['position' => 'left', 'hash' => 'abc'], 'string_step'], // mixed valid and invalid
];

foreach ($malformedProofs as $idx => $badProof) {
    /** @phpstan-ignore-next-line */
    $result = MerkleTree::verifyProof($dummyLeaf, $badProof, $dummyRoot);
    ch_assert($result === false, "Malformed proof structure #{$idx} must return false without fatal error");
}

// 3.5 Blockchain Ledger Corrupted Blocks Verification
$chain = new Blockchain(difficulty: 1);
$chain->record(['tx' => 1, 'from' => 'Alice', 'to' => 'Bob', 'amount' => 50]);
$chain->record(['tx' => 2, 'from' => 'Bob', 'to' => 'Charlie', 'amount' => 25]);
$chain->minePending();

$chain->record(['tx' => 3, 'from' => 'Charlie', 'to' => 'Dave', 'amount' => 10]);
$chain->minePending();

ch_assert($chain->isValid() === true, "Valid blockchain passes validation");
ch_assert(count($chain->getChain()) === 3, "Blockchain has Genesis + 2 mined blocks");

// Tamper test 1: Modify transaction payload in block 1
$tampered1 = $chain->toArray();
$tampered1['chain'][1]['transactions'][0]['amount'] = 9999999;
$tmpFile1 = tempnam(sys_get_temp_dir(), 'adv_chain_tamper1_');
file_put_contents($tmpFile1, json_encode($tampered1));
$loaded1 = Blockchain::loadFromFile($tmpFile1);
ch_assert($loaded1->isValid() === false, "Chain with tampered transaction amount is INVALID");
@unlink($tmpFile1);

// Tamper test 2: Inject fraudulent transaction into block 2
$tampered2 = $chain->toArray();
$tampered2['chain'][2]['transactions'][] = ['tx' => 999, 'fraud' => true];
$tmpFile2 = tempnam(sys_get_temp_dir(), 'adv_chain_tamper2_');
file_put_contents($tmpFile2, json_encode($tampered2));
$loaded2 = Blockchain::loadFromFile($tmpFile2);
ch_assert($loaded2->isValid() === false, "Chain with injected transaction is INVALID");
@unlink($tmpFile2);

// Tamper test 3: Break previous_hash linkage
$tampered3 = $chain->toArray();
$tampered3['chain'][2]['previous_hash'] = hash('sha256', 'broken_link');
$tmpFile3 = tempnam(sys_get_temp_dir(), 'adv_chain_tamper3_');
file_put_contents($tmpFile3, json_encode($tampered3));
$loaded3 = Blockchain::loadFromFile($tmpFile3);
ch_assert($loaded3->isValid() === false, "Chain with broken hash link is INVALID");
@unlink($tmpFile3);

// Tamper test 4: Reorder transactions within block 1
$tampered4 = $chain->toArray();
$txs = $tampered4['chain'][1]['transactions'];
$tampered4['chain'][1]['transactions'] = array_reverse($txs);
$tmpFile4 = tempnam(sys_get_temp_dir(), 'adv_chain_tamper4_');
file_put_contents($tmpFile4, json_encode($tampered4));
$loaded4 = Blockchain::loadFromFile($tmpFile4);
ch_assert($loaded4->isValid() === false, "Chain with reordered transactions produces different Merkle root and is INVALID");
@unlink($tmpFile4);

// Tamper test 5: Modify nonce in block 1
$tampered5 = $chain->toArray();
$tampered5['chain'][1]['nonce'] = 999999;
$tmpFile5 = tempnam(sys_get_temp_dir(), 'adv_chain_tamper5_');
file_put_contents($tmpFile5, json_encode($tampered5));
$loaded5 = Blockchain::loadFromFile($tmpFile5);
ch_assert($loaded5->isValid() === false, "Chain with tampered nonce is INVALID");
@unlink($tmpFile5);

echo "    ✔ MerkleTree & Blockchain Ledger passed (empty/single/odd/power-of-two trees, bounds, malformed proofs, chain tampering).\n\n";

echo "============================================================\n";
echo "  ✔ ALL EMPIRICAL CHALLENGES PASSED!\n";
echo "  ✔ Total assertions verified: {$passedAssertions} / {$totalAssertions}\n";
echo "============================================================\n";
