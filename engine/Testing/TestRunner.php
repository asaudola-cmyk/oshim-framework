<?php
declare(strict_types=1);

namespace Oshim\Testing;

use Oshim\Cli\Output;
use Oshim\Testing\Exceptions\AssertionFailedException;
use Throwable;

class TestRunner
{
    protected Output $output;

    public function __construct(?Output $output = null)
    {
        $this->output = $output ?? new Output();
    }

    /**
     * Run test suite across specified paths.
     *
     * @param list<string> $paths
     * @param string|null $filter
     * @return int Exit code (0 for success, 1 for failure)
     */
    public function run(array $paths, ?string $filter = null): int
    {
        $start = microtime(true);
        $suites = TestSuite::discover($paths, $filter);

        $totalTests = 0;
        foreach ($suites as $methods) {
            $totalTests += count($methods);
        }

        $this->output->writeln("<bold><cyan>OSHIM Zero-Dependency Test Runner</cyan></bold>");
        $this->output->writeln("Discovered <bold>{$totalTests}</bold> test methods across <bold>" . count($suites) . "</bold> test suites.");
        $this->output->writeln();

        if ($totalTests === 0) {
            $this->output->warning("No tests found to execute.");
            return 0;
        }

        $passed = 0;
        $failed = 0;
        $errors = 0;
        /** @var list<array{class: string, method: string, type: string, message: string, diff: ?string, trace: string}> */
        $failures = [];

        Assert::resetCount();

        foreach ($suites as $className => $methods) {
            $shortClass = (new \ReflectionClass($className))->getShortName();
            $this->output->write(" <bold>{$shortClass}</bold>: ");

            foreach ($methods as $method) {
                /** @var TestCase $testInstance */
                $testInstance = new $className();

                try {
                    $testInstance->setUp();
                    $testInstance->$method();
                    $testInstance->tearDown();

                    $this->output->write("<green>.</green>");
                    $passed++;
                } catch (AssertionFailedException $e) {
                    $testInstance->tearDown();
                    $this->output->write("<red>F</red>");
                    $failed++;
                    $failures[] = [
                        'class'   => $className,
                        'method'  => $method,
                        'type'    => 'FAIL',
                        'message' => $e->getMessage(),
                        'diff'    => $e->getDiff(),
                        'trace'   => $e->getFile() . ':' . $e->getLine(),
                    ];
                } catch (Throwable $e) {
                    $testInstance->tearDown();
                    $this->output->write("<yellow>E</yellow>");
                    $errors++;
                    $failures[] = [
                        'class'   => $className,
                        'method'  => $method,
                        'type'    => 'ERROR',
                        'message' => $e->getMessage(),
                        'diff'    => null,
                        'trace'   => $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString(),
                    ];
                } finally {
                    $this->isolateGlobalState();
                }
            }

            $this->output->writeln();
        }

        $elapsed = round((microtime(true) - $start) * 1000, 2);
        $totalAssertions = Assert::getCount();
        $memMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        $this->output->writeln();

        // Print Failures
        if (!empty($failures)) {
            $this->output->writeln("<bold><red>Failures & Errors:</red></bold>");
            $this->output->writeln();

            foreach ($failures as $idx => $failure) {
                $num = $idx + 1;
                $this->output->writeln("<red>{$num}) {$failure['class']}::{$failure['method']}</red> [{$failure['type']}]");
                $this->output->writeln("   {$failure['message']}");
                if ($failure['diff'] !== null) {
                    $this->output->writeln("   <yellow>" . str_replace("\n", "\n   ", $failure['diff']) . "</yellow>");
                }
                $this->output->writeln("   <dim>in {$failure['trace']}</dim>");
                $this->output->writeln();
            }
        }

        // Summary Banner
        $this->output->writeln(str_repeat('-', 60));
        if ($failed === 0 && $errors === 0) {
            $this->output->writeln(
                "<bg_green><bold><white> PASS </white></bold></bg_green> " .
                "<green>All tests passed!</green> " .
                "({$passed} tests, {$totalAssertions} assertions, {$elapsed}ms, {$memMb}MB peak memory)"
            );
            return 0;
        } else {
            $this->output->writeln(
                "<bg_red><bold><white> FAIL </white></bold></bg_red> " .
                "<red>{$failed} failed, {$errors} errors, {$passed} passed</red> " .
                "({$totalTests} total tests, {$totalAssertions} assertions, {$elapsed}ms, {$memMb}MB)"
            );
            return 1;
        }
    }

    private function isolateGlobalState(): void
    {
        if (class_exists(\Oshim\Ai\Tokenizer\GgufTokenizer::class)) {
            \Oshim\Ai\Tokenizer\GgufTokenizer::reset();
        }

        if (class_exists(\Oshim\Async\EventLoop::class)) {
            \Oshim\Async\EventLoop::reset();
        }

        if (class_exists(\Oshim\Async\FiberScheduler::class)) {
            \Oshim\Async\FiberScheduler::setInstance(null);
        }
    }
}
