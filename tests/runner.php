<?php
declare(strict_types=1);

/**
 * 👑 OSHIM Framework Standalone Test Runner
 */
$frameworkRoot = dirname(__DIR__);
require_once $frameworkRoot . '/engine/Bootstrap.php';
\Oshim\Bootstrap::boot($frameworkRoot);

use Oshim\Testing\TestRunner;

$runner = new TestRunner();
$exitCode = $runner->run([$frameworkRoot . '/tests/Unit']);
exit($exitCode);
