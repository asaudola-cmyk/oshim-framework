<?php
declare(strict_types=1);

ob_start(); // Buffer output to prevent session_start headers error

require_once __DIR__ . '/engine/Bootstrap.php';
\Oshim\Bootstrap::boot(__DIR__);

use App\Models\User;
use App\Models\Document;
use Oshim\Http\Request;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use Oshim\Ui\LiveDom\LiveDom;

echo "--- SaaS Integration Test ---\n";

// 1. Clear test user
$user = User::query()->where('email', '=', 'test@saas.com')->first();
if ($user) {
    Document::query()->where('user_id', '=', $user->id)->delete();
    $user->delete();
}

// 2. Test Register
echo "Testing Registration...\n";
$auth = new AuthController();
$req = Request::create('POST', '/workspace/register', [
    'name' => 'Test User',
    'email' => 'test@saas.com',
    'password' => 'secret123'
]);
$auth->register($req);

$user = User::query()->where('email', '=', 'test@saas.com')->first();
if (!$user) {
    die("FAILED: User not registered.\n");
}
echo "PASSED: User registered (ID: {$user->id}).\n";

// 3. Test Dashboard Controller Generate (mock request)
echo "Testing AI Generation API...\n";
$_SESSION['user_id'] = $user->id; // mock session
$dash = new DashboardController();
$req = Request::create('POST', '/workspace/api/generate', [
    'prompt' => 'Hello AI'
]);
$resp = $dash->generate($req);
$data = json_decode($resp->getContent(), true);
if (empty($data['id'])) {
    die("FAILED: AI Generate failed. Response: " . $resp->getContent() . "\n");
}
echo "PASSED: Document generated (ID: {$data['id']}).\n";

// 4. Test LiveDom execution manually
echo "Testing LiveDOM Component Execution...\n";
$comp = new \App\Components\AiGeneratorComponent();
$comp->hydrate(['prompt' => 'Test LiveDOM Prompt', 'isGenerating' => false]);
// call action
$comp->callAction('generate');
if (empty($comp->generatedContent)) {
    die("FAILED: LiveDOM Component didn't generate.\n");
}
echo "PASSED: LiveDOM generated content via action.\n";

echo "\nALL TESTS PASSED SUCCESSFULLY.\n";
$out = ob_get_clean();
echo $out;
