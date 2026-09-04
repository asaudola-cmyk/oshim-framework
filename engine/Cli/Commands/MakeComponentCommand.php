<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;

class MakeComponentCommand extends Command
{
    protected string $name = 'make:component';
    protected string $description = 'Create a new server-driven UI Component class';

    protected function configure(): void
    {
        $this->addArgument('name', Input::REQUIRED, 'The name of the UI Component class');
    }

    public function execute(Input $input, Output $output): int
    {
        $name = (string)$input->getArgument(0);
        if ($name === '') {
            $output->error("Component name is required.");
            return 1;
        }

        $name = ucfirst($name);
        $basePath = defined('OSHIM_BASE_PATH') ? OSHIM_BASE_PATH : dirname(__DIR__, 3);
        $dir = $basePath . '/app/Components';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filePath = "{$dir}/{$name}.php";
        if (is_file($filePath)) {
            $output->warning("Component [{$name}] already exists.");
            return 0;
        }

        $template = <<<PHP
<?php
declare(strict_types=1);

namespace App\Components;

class {$name}
{
    protected array \$props = [];
    protected array \$state = [];

    public function mount(array \$props = []): void
    {
        \$this->props = \$props;
    }

    public function render(): string
    {
        return "<div class=\"oshim-card glass-panel\"><h3>{$name}</h3></div>";
    }
}

PHP;

        file_put_contents($filePath, $template);
        $output->success("Component [{$name}] created successfully at app/Components/{$name}.php");

        return 0;
    }
}
