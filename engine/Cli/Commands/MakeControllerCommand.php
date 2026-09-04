<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;

class MakeControllerCommand extends Command
{
    protected string $name = 'make:controller';
    protected string $description = 'Create a new Controller class';

    protected function configure(): void
    {
        $this->addArgument('name', Input::REQUIRED, 'The name of the Controller class')
             ->addOption('resource', 'r', Input::VALUE_NONE, 'Generate a resource controller with CRUD action stubs');
    }

    public function execute(Input $input, Output $output): int
    {
        $name = (string)$input->getArgument(0);
        if ($name === '') {
            $output->error("Controller name is required.");
            return 1;
        }

        $name = ucfirst($name);
        if (!str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }

        $basePath = defined('OSHIM_BASE_PATH') ? OSHIM_BASE_PATH : dirname(__DIR__, 3);
        $dir = $basePath . '/app/Controllers';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filePath = "{$dir}/{$name}.php";
        if (is_file($filePath)) {
            $output->warning("Controller [{$name}] already exists.");
            return 0;
        }

        if ($input->hasOption('resource')) {
            $actions = <<<PHP
    public function index(Request \$request): Response
    {
        return Response::json(['data' => []]);
    }

    public function store(Request \$request): Response
    {
        return Response::json(['message' => 'Created'], 201);
    }

    public function show(Request \$request, string \$id): Response
    {
        return Response::json(['id' => \$id]);
    }

    public function update(Request \$request, string \$id): Response
    {
        return Response::json(['message' => 'Updated']);
    }

    public function destroy(Request \$request, string \$id): Response
    {
        return Response::noContent();
    }
PHP;
        } else {
            $actions = <<<PHP
    public function index(Request \$request): Response
    {
        return Response::html("<h1>Welcome from {$name}</h1>");
    }
PHP;
        }

        $template = <<<PHP
<?php
declare(strict_types=1);

namespace App\Controllers;

use Oshim\Http\Request;
use Oshim\Http\Response;

class {$name}
{
{$actions}
}

PHP;

        file_put_contents($filePath, $template);
        $output->success("Controller [{$name}] created successfully at app/Controllers/{$name}.php");

        return 0;
    }
}
