<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;

class MakeMigrationCommand extends Command
{
    protected string $name = 'make:migration';
    protected string $description = 'Create a new migration file';

    protected function configure(): void
    {
        $this->addArgument('name', Input::REQUIRED, 'The name of the migration')
             ->addOption('create', null, Input::VALUE_OPTIONAL, 'The table to be created')
             ->addOption('table', null, Input::VALUE_OPTIONAL, 'The table to be modified');
    }

    public function execute(Input $input, Output $output): int
    {
        $name = (string)$input->getArgument(0);
        if ($name === '') {
            $output->error("Migration name is required.");
            return 1;
        }

        $basePath = defined('OSHIM_BASE_PATH') ? OSHIM_BASE_PATH : dirname(__DIR__, 3);
        $dir = $basePath . '/database/migrations';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $cleanName = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $name));
        $timestamp = date('Y_m_d_His');
        $fileName = "{$timestamp}_{$cleanName}.php";
        $filePath = "{$dir}/{$fileName}";

        $createTable = $input->getOption('create');
        $modifyTable = $input->getOption('table');

        if ($createTable) {
            $table = $createTable;
            $body = <<<PHP
return new class extends Migration {
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};
PHP;
        } elseif ($modifyTable) {
            $table = $modifyTable;
            $body = <<<PHP
return new class extends Migration {
    public function up(): void
    {
        Schema::table('{$table}', function (Blueprint \$table) {
            // \$table->string('column')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('{$table}', function (Blueprint \$table) {
            // \$table->dropColumn('column');
        });
    }
};
PHP;
        } else {
            $body = <<<PHP
return new class extends Migration {
    public function up(): void
    {
    }

    public function down(): void
    {
    }
};
PHP;
        }

        $template = <<<PHP
<?php
declare(strict_types=1);

use Oshim\Database\Migrations\Migration;
use Oshim\Database\Schema\Schema;
use Oshim\Database\Schema\Blueprint;

{$body}

PHP;

        file_put_contents($filePath, $template);
        $output->success("Migration [{$fileName}] created successfully in database/migrations/");

        return 0;
    }
}
