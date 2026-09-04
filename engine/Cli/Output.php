<?php
declare(strict_types=1);

namespace Oshim\Cli;

class Output
{
    protected bool $decorated = true;

    public function __construct(?bool $decorated = null)
    {
        if ($decorated !== null) {
            $this->decorated = $decorated;
        } else {
            $this->decorated = function_exists('posix_isatty') && defined('STDOUT') && is_resource(STDOUT)
                ? posix_isatty(STDOUT)
                : true;
        }
    }

    public function setDecorated(bool $decorated): void
    {
        $this->decorated = $decorated;
    }

    public function isDecorated(): bool
    {
        return $this->decorated;
    }

    public function write(string $message): static
    {
        echo $this->format($message);
        return $this;
    }

    public function writeln(string $message = ''): static
    {
        $this->write($message . PHP_EOL);
        return $this;
    }

    public function line(string $message = ''): static
    {
        return $this->writeln($message);
    }

    public function info(string $message): static
    {
        return $this->writeln("<cyan>{$message}</cyan>");
    }

    public function success(string $message): static
    {
        return $this->writeln("<green>✔ {$message}</green>");
    }

    public function warning(string $message): static
    {
        return $this->writeln("<yellow>⚠ {$message}</yellow>");
    }

    public function error(string $message): static
    {
        return $this->writeln("<red>✖ {$message}</red>");
    }

    public function table(array $headers, array $rows): static
    {
        $colWidths = [];

        foreach ($headers as $i => $header) {
            $colWidths[$i] = mb_strlen((string)$header);
        }

        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $len = mb_strlen((string)$cell);
                if (!isset($colWidths[$i]) || $len > $colWidths[$i]) {
                    $colWidths[$i] = $len;
                }
            }
        }

        // Top border
        $sep = '+';
        foreach ($colWidths as $width) {
            $sep .= str_repeat('-', $width + 2) . '+';
        }

        $this->writeln($sep);

        // Headers
        $headerLine = '|';
        foreach ($headers as $i => $header) {
            $headerLine .= ' ' . str_pad((string)$header, $colWidths[$i]) . ' |';
        }
        $this->writeln("<bold>{$headerLine}</bold>");
        $this->writeln($sep);

        // Rows
        foreach ($rows as $row) {
            $rowLine = '|';
            foreach ($row as $i => $cell) {
                $rowLine .= ' ' . str_pad((string)$cell, $colWidths[$i]) . ' |';
            }
            $this->writeln($rowLine);
        }

        $this->writeln($sep);

        return $this;
    }

    public function progressBar(int $current, int $total, int $barSize = 30): static
    {
        $percent = $total > 0 ? ($current / $total) : 0.0;
        $bar = (int)round($percent * $barSize);

        $filled = str_repeat('█', $bar);
        $empty = str_repeat('░', $barSize - $bar);
        $pct = round($percent * 100);

        fwrite(STDOUT, sprintf("\r[%s%s] %3d%% (%d/%d)", $filled, $empty, $pct, $current, $total));
        if ($current >= $total) {
            fwrite(STDOUT, PHP_EOL);
        }

        return $this;
    }

    public function format(string $text): string
    {
        if (!$this->decorated) {
            return strip_tags($text);
        }

        $styles = [
            'bold'      => "\033[1m",
            'dim'       => "\033[2m",
            'red'       => "\033[31m",
            'green'     => "\033[32m",
            'yellow'    => "\033[33m",
            'blue'      => "\033[34m",
            'magenta'   => "\033[35m",
            'cyan'      => "\033[36m",
            'white'     => "\033[37m",
            'bg_red'    => "\033[41m",
            'bg_green'  => "\033[42m",
            'bg_yellow' => "\033[43m",
            'bg_blue'   => "\033[44m",
        ];

        $reset = "\033[0m";

        foreach ($styles as $tag => $code) {
            $text = str_replace("<{$tag}>", $code, $text);
            $text = str_replace("</{$tag}>", $reset, $text);
        }

        return $text;
    }
}
