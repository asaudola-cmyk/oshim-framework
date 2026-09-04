<?php
declare(strict_types=1);

namespace Oshim\Tests\Harness;

/**
 * ANSI colored terminal and TAP v13 formatters.
 */
class Reporter
{
    private bool $useColor;
    private bool $tap;
    private bool $verbose;
    private int $testIndex = 0;
    private int $column = 0;

    public function __construct(bool $useColor = true, bool $tap = false, bool $verbose = false)
    {
        $this->useColor = $useColor;
        $this->tap = $tap;
        $this->verbose = $verbose;
    }

    public function writeHeader(int $totalTests): void
    {
        if ($this->tap) {
            echo "TAP version 13\n";
            echo "1..{$totalTests}\n";
            return;
        }

        echo $this->color("OSHIM Cloud Test Suite Runner (Pure PHP 8.3+ Zero-Dependency)\n", 'cyan');
        echo "Running {$totalTests} tests...\n\n";
    }

    public function writeTestProgress(TestResult $result): void
    {
        $this->testIndex++;

        if ($this->tap) {
            $status = $result->isPassed() ? 'ok' : 'not ok';
            $diag = '';
            if ($result->isSkipped()) {
                $diag = ' # SKIP ' . ($result->getMessage() ?: 'Skipped');
            } elseif ($result->isFailed() || $result->isError()) {
                $diag = ' # ' . ($result->isFailed() ? 'FAILED' : 'ERROR') . ': ' . ($result->getMessage() ?: 'Unknown error');
            }

            echo "{$status} {$this->testIndex} - {$result->getClassName()}::{$result->getMethodName()}{$diag}\n";

            if ($result->isFailed() || $result->isError()) {
                echo "  ---\n";
                echo "  message: " . json_encode($result->getMessage() ?? '') . "\n";
                if ($result->getFile()) {
                    echo "  file: {$result->getFile()}\n";
                    echo "  line: {$result->getLine()}\n";
                }
                if ($result->getDiff()) {
                    echo "  diff: |\n";
                    foreach (explode("\n", $result->getDiff()) as $line) {
                        echo "    {$line}\n";
                    }
                }
                echo "  ...\n";
            }
            return;
        }

        if ($this->verbose) {
            $statusStr = match (true) {
                $result->isPassed() => $this->color('[PASS]', 'green'),
                $result->isSkipped() => $this->color('[SKIP]', 'yellow'),
                $result->isFailed() => $this->color('[FAIL]', 'red'),
                default => $this->color('[ERROR]', 'magenta'),
            };
            $duration = sprintf('%.4fs', $result->getDuration());
            $name = $result->getClassName() . '::' . $result->getMethodName();
            echo sprintf("%-65s %s (%s)\n", $name, $statusStr, $duration);
            return;
        }

        // Standard dot progress
        $char = match (true) {
            $result->isPassed() => $this->color('.', 'green'),
            $result->isSkipped() => $this->color('S', 'yellow'),
            $result->isFailed() => $this->color('F', 'red'),
            default => $this->color('E', 'magenta'),
        };

        echo $char;
        $this->column++;
        if ($this->column >= 60) {
            echo " [{$this->testIndex}]\n";
            $this->column = 0;
        }
    }

    /**
     * @param array<TestResult> $results
     */
    public function writeSummary(array $results, float $totalTime, int $peakMemory): void
    {
        if (!$this->tap && !$this->verbose && $this->column > 0) {
            echo "\n";
        }

        if ($this->tap) {
            return;
        }

        $failures = array_filter($results, fn(TestResult $r) => $r->isFailed() || $r->isError());
        $skipped = array_filter($results, fn(TestResult $r) => $r->isSkipped());
        $passed = array_filter($results, fn(TestResult $r) => $r->isPassed());
        $totalAssertions = array_sum(array_map(fn(TestResult $r) => $r->getAssertions(), $results));

        if (!empty($failures)) {
            echo "\n" . $this->color("FAILURES / ERRORS:\n", 'red');
            $idx = 1;
            foreach ($failures as $failure) {
                $title = "{$idx}) {$failure->getClassName()}::{$failure->getMethodName()}";
                echo "\n" . $this->color($title, 'bold') . "\n";
                echo $this->color($failure->getMessage() ?? 'No failure message provided', 'red') . "\n";

                if ($failure->getDiff()) {
                    echo "\n" . $this->color($failure->getDiff(), 'yellow') . "\n";
                }

                if ($failure->getFile()) {
                    echo "at " . $failure->getFile() . ":" . $failure->getLine() . "\n";
                }

                if ($this->verbose && $failure->getTrace()) {
                    echo "Stack trace:\n" . $this->formatStackTrace($failure->getTrace()) . "\n";
                }
                $idx++;
            }
        }

        // Summary Table
        echo "\n" . $this->renderSummaryTable($results, $totalTime) . "\n";

        $memMb = sprintf('%.2f MB', $peakMemory / 1024 / 1024);
        $timeStr = sprintf('%.3fs', $totalTime);

        if (empty($failures)) {
            $msg = sprintf("OK (%d tests, %d assertions, %s, %s peak memory)", count($results), $totalAssertions, $timeStr, $memMb);
            echo $this->color("\n✔ " . $msg . "\n", 'green_bg');
        } else {
            $msg = sprintf("FAILURES! (Tests: %d, Assertions: %d, Failures: %d, Errors: %d, Skipped: %d, %s)",
                count($results),
                $totalAssertions,
                count(array_filter($results, fn($r) => $r->isFailed())),
                count(array_filter($results, fn($r) => $r->isError())),
                count($skipped),
                $timeStr
            );
            echo $this->color("\n✘ " . $msg . "\n", 'red_bg');
        }
    }

    public function renderSummaryTable(array $results, float $totalTime): string
    {
        $tiers = [
            'Unit Tests'                         => fn(TestResult $r) => str_contains($r->getClassName(), 'Unit'),
            'Functional Tests'                   => fn(TestResult $r) => str_contains($r->getClassName(), 'Functional'),
            'Tier 1: Feature Coverage (F01-F28)' => fn(TestResult $r) => str_contains($r->getClassName(), 'Tier1'),
            'Tier 2: Boundary & Corner Cases'    => fn(TestResult $r) => str_contains($r->getClassName(), 'Tier2'),
            'Tier 3: Pairwise Combinations'      => fn(TestResult $r) => str_contains($r->getClassName(), 'Tier3'),
            'Tier 4: Real-World Scenarios'       => fn(TestResult $r) => str_contains($r->getClassName(), 'Tier4'),
        ];

        $out = "+------------------------------------+-------+--------+--------+--------+----------+\n";
        $out .= "| Suite / Tier                       | Tests | Passed | Failed | Errors | Time (s) |\n";
        $out .= "+------------------------------------+-------+--------+--------+--------+----------+\n";

        foreach ($tiers as $tierName => $filter) {
            $tierResults = array_filter($results, $filter);
            if (empty($tierResults)) {
                continue;
            }

            $count = count($tierResults);
            $p = count(array_filter($tierResults, fn($r) => $r->isPassed()));
            $f = count(array_filter($tierResults, fn($r) => $r->isFailed()));
            $e = count(array_filter($tierResults, fn($r) => $r->isError()));
            $time = sprintf('%.3fs', array_sum(array_map(fn($r) => $r->getDuration(), $tierResults)));

            $out .= sprintf("| %-34s | %5d | %6d | %6d | %6d | %8s |\n", $tierName, $count, $p, $f, $e, $time);
        }

        $totalCount = count($results);
        $totalP = count(array_filter($results, fn($r) => $r->isPassed()));
        $totalF = count(array_filter($results, fn($r) => $r->isFailed()));
        $totalE = count(array_filter($results, fn($r) => $r->isError()));
        $totalTimeStr = sprintf('%.3fs', $totalTime);

        $out .= "+------------------------------------+-------+--------+--------+--------+----------+\n";
        $out .= sprintf("| TOTAL                              | %5d | %6d | %6d | %6d | %8s |\n", $totalCount, $totalP, $totalF, $totalE, $totalTimeStr);
        $out .= "+------------------------------------+-------+--------+--------+--------+----------+";

        return $out;
    }

    public function writeWarning(string $text): void
    {
        echo $this->color($text, 'yellow');
    }

    public function writeInfo(string $text): void
    {
        echo $this->color($text, 'cyan');
    }

    public function color(string $text, string $color): string
    {
        if (!$this->useColor) {
            return $text;
        }

        $codes = [
            'green'    => "\033[32m",
            'red'      => "\033[31m",
            'yellow'   => "\033[33m",
            'cyan'     => "\033[36m",
            'magenta'  => "\033[35m",
            'bold'     => "\033[1m",
            'dim'      => "\033[2m",
            'green_bg' => "\033[42;30m",
            'red_bg'   => "\033[41;37m",
            'reset'    => "\033[0m",
        ];

        return ($codes[$color] ?? '') . $text . $codes['reset'];
    }

    private function formatStackTrace(string $trace): string
    {
        $lines = explode("\n", $trace);
        $filtered = array_filter($lines, fn($l) => !str_contains($l, 'tests/Harness/'));
        return implode("\n", array_slice($filtered, 0, 8));
    }
}
