<?php
declare(strict_types=1);

namespace Oshim\Tests\Harness;

use ErrorException;
use Throwable;

/**
 * Core test execution engine and CLI orchestrator.
 */
class Runner
{
    public static function main(array $argv): int
    {
        $options = self::parseArguments($argv);

        if ($options['help']) {
            self::printHelp();
            return 0;
        }

        return self::run($options);
    }

    public static function run(array $options = []): int
    {
        $reporter = new Reporter(!$options['no-color'], $options['tap'], $options['verbose']);
        $suite = new TestSuite();
        $suite->discover($options);

        if ($suite->isEmpty()) {
            $reporter->writeWarning("No tests found matching the specified criteria.\n");
            return 0;
        }

        $reporter->writeHeader($suite->count());
        $results = [];
        $startTime = hrtime(true);

        foreach ($suite->getTestClasses() as $className => $methods) {
            if (method_exists($className, 'setUpBeforeClass')) {
                try {
                    $className::setUpBeforeClass();
                } catch (Throwable $e) {
                    $reporter->writeWarning("setUpBeforeClass() failed for {$className}: {$e->getMessage()}\n");
                }
            }

            foreach ($methods as $method) {
                /** @var TestCase $test */
                $test = new $className($method);
                $result = self::runSingleTest($test, $method, $options);
                $results[] = $result;
                $reporter->writeTestProgress($result);

                if (($result->isFailed() || $result->isError()) && $options['bail']) {
                    break 2;
                }
            }

            if (method_exists($className, 'tearDownAfterClass')) {
                try {
                    $className::tearDownAfterClass();
                } catch (Throwable $e) {
                    $reporter->writeWarning("tearDownAfterClass() failed for {$className}: {$e->getMessage()}\n");
                }
            }
        }

        $totalTime = (hrtime(true) - $startTime) / 1e9;
        $peakMemory = memory_get_peak_usage(true);

        $reporter->writeSummary($results, $totalTime, $peakMemory);

        $hasFailures = array_filter($results, fn(TestResult $r) => !$r->isPassed() && !$r->isSkipped());
        return count($hasFailures) === 0 ? 0 : 1;
    }

    private static function runSingleTest(TestCase $test, string $method, array $options): TestResult
    {
        $className = get_class($test);
        $result = new TestResult($className, $method);
        $assertionsBefore = Assert::getAssertionCount();

        set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
            if (!(error_reporting() & $errno)) {
                return false;
            }
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        $start = hrtime(true);
        try {
            // Reflect protected setUp method
            $setupMethod = new \ReflectionMethod($test, 'setUp');
            $setupMethod->setAccessible(true);
            $setupMethod->invoke($test);

            $test->$method();
            $result->markPassed();
        } catch (AssertionException $e) {
            $result->markFailed($e->getMessage(), $e->getTraceAsString(), $e->getFile(), $e->getLine(), $e->getDiff());
        } catch (TestSkippedException $e) {
            $result->markSkipped($e->getMessage());
        } catch (Throwable $e) {
            $result->markError($e->getMessage(), $e->getTraceAsString(), $e->getFile(), $e->getLine());
        } finally {
            try {
                $tearDownMethod = new \ReflectionMethod($test, 'tearDown');
                $tearDownMethod->setAccessible(true);
                $tearDownMethod->invoke($test);
            } catch (Throwable $e) {
                if ($result->isPassed()) {
                    $result->markError("tearDown() failed: " . $e->getMessage(), $e->getTraceAsString(), $e->getFile(), $e->getLine());
                }
            }
            restore_error_handler();
        }

        $duration = (hrtime(true) - $start) / 1e9;
        $assertionsAfter = Assert::getAssertionCount();
        $assertionsDelta = max(0, $assertionsAfter - $assertionsBefore);
        $result->setMetrics($duration, $assertionsDelta, memory_get_usage());

        return $result;
    }

    public static function parseArguments(array $argv): array
    {
        $options = [
            'tier'     => null,
            'feature'  => null,
            'filter'   => null,
            'path'     => null,
            'tap'      => false,
            'verbose'  => false,
            'no-color' => false,
            'bail'     => false,
            'help'     => false,
        ];

        // Skip script name ($argv[0])
        $args = array_slice($argv, 1);

        foreach ($args as $arg) {
            if ($arg === '--tap') {
                $options['tap'] = true;
            } elseif ($arg === '--verbose' || $arg === '-v') {
                $options['verbose'] = true;
            } elseif ($arg === '--no-color') {
                $options['no-color'] = true;
            } elseif ($arg === '--bail' || $arg === '-x' || $arg === '--stop-on-failure') {
                $options['bail'] = true;
            } elseif ($arg === '--help' || $arg === '-h') {
                $options['help'] = true;
            } elseif (str_starts_with($arg, '--tier=')) {
                $options['tier'] = (int)substr($arg, 7);
            } elseif (str_starts_with($arg, '-t=')) {
                $options['tier'] = (int)substr($arg, 3);
            } elseif (str_starts_with($arg, '--feature=')) {
                $options['feature'] = strtoupper(substr($arg, 10));
            } elseif (str_starts_with($arg, '-f=')) {
                $options['feature'] = strtoupper(substr($arg, 3));
            } elseif (str_starts_with($arg, '--filter=')) {
                $options['filter'] = substr($arg, 9);
            } elseif (!str_starts_with($arg, '-')) {
                $options['path'] = $arg;
            }
        }

        if (getenv('NO_COLOR') !== false) {
            $options['no-color'] = true;
        }

        return $options;
    }

    public static function printHelp(): void
    {
        echo <<<HELP
OSHIM Cloud Zero-Dependency Test Runner (Pure PHP 8.3+)
Usage:
  php tests/runner.php [options] [test_file_or_dir]
  php bin/oshim test [options]

Options:
  --tier=<1-4>, -t=<1-4>   Filter tests by tier (1: Feature, 2: Boundary, 3: Pairwise, 4: Scenarios)
  --feature=<Fxx>, -f=<Fxx> Filter tests by feature code (e.g. --feature=F01)
  --filter=<Pattern>       Filter tests by class or method name regex/substring
  --tap                    Output in Test Anything Protocol (TAP v13) format
  --verbose, -v            Display verbose per-test execution timing and diagnostics
  --bail, -x               Stop execution immediately on first failure or error
  --no-color               Disable colored ANSI terminal output
  --help, -h               Show this help message

HELP;
    }
}
