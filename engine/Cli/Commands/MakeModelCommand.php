<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;

class MakeModelCommand extends Command
{
    protected string $name = 'make:model';
    protected string $description = 'Create a new Active-Record Model class';

    protected function configure(): void
    {
        $this->addArgument('name', Input::REQUIRED, 'The name of the Model class')
             ->addOption('migration', 'm', Input::VALUE_NONE, 'Create a migration for the model');
    }

    public function execute(Input $input, Output $output): int
    {
        $name = (string)$input->getArgument(0);
        if ($name === '') {
            $output->error("Model name is required.");
            return 1;
        }

        $name = ucfirst($name);
        $basePath = defined('OSHIM_BASE_PATH') ? OSHIM_BASE_PATH : dirname(__DIR__, 3);
        $dir = $basePath . '/app/Models';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filePath = "{$dir}/{$name}.php";
        if (is_file($filePath)) {
            $output->warning("Model [{$name}] already exists.");
            return 0;
        }

        $template = <<<PHP
<?php
declare(strict_types=1);

namespace App\Models;

use Oshim\Database\ORM\Model;

class {$name} extends Model
{
    protected array \$fillable = [];
}

PHP;

        file_put_contents($filePath, $template);
        $output->success("Model [{$name}] created successfully at app/Models/{$name}.php");

        if ($input->hasOption('migration')) {
            $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
            $tableName = str_ends_with($snake, 's') ? $snake : $snake . 's';
            $migCmd = new MakeMigrationCommand();
            $migCmd->execute(new Input(['oshim', 'make:migration', "create_{$tableName}_table", "--create={$tableName}"]), $output);
        }

        return 0;
    }
}
