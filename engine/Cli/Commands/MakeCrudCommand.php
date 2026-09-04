<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;

class MakeCrudCommand extends Command
{
    protected string $name = 'make:crud';
    protected string $description = 'Generate Model, Migration, Controller, and Reactive CRUD View in 1-click';

    protected function configure(): void
    {
        $this->addArgument('name', Input::REQUIRED, 'The name of the Model');
    }

    public function execute(Input $input, Output $output): int
    {
        $modelName = ucfirst((string)($input->getArgument('name') ?: ($input->getArguments()[0] ?? '')));

        if (empty($modelName)) {
            $output->writeln("<error>Error: Please provide a Model name (e.g. ./bin/oshim make:crud Product)</error>");
            return 1;
        }

        $tableName = strtolower($modelName) . 's';
        $appRoot = dirname(__DIR__, 3);

        $output->writeln("<bold><cyan>⚡ 1-Click Sovereign CRUD Generator: {$modelName}</cyan></bold>");

        // 1. Generate Model
        $modelDir = $appRoot . '/app/Models';
        @mkdir($modelDir, 0755, true);
        $modelCode = <<<PHP
<?php
declare(strict_types=1);

namespace App\Models;

use Oshim\Database\ORM\Model;

class {$modelName} extends Model
{
    protected string \$table = '{$tableName}';
    protected array \$fillable = ['name', 'status', 'description'];
}
PHP;
        file_put_contents("{$modelDir}/{$modelName}.php", $modelCode);
        $output->writeln("<info>✔ Created Model:</info> app/Models/{$modelName}.php");

        // 2. Generate Migration
        $migrationDir = $appRoot . '/database/migrations';
        @mkdir($migrationDir, 0755, true);
        $datePrefix = date('Y_m_d_His');
        $migrationCode = <<<PHP
<?php
declare(strict_types=1);

use Oshim\Database\Migrations\Migration;
use Oshim\Database\Schema\Blueprint;
use Oshim\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            \$table->increments('id');
            \$table->string('name');
            \$table->string('status')->default('active');
            \$table->text('description')->nullable();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};
PHP;
        file_put_contents("{$migrationDir}/{$datePrefix}_create_{$tableName}_table.php", $migrationCode);
        $output->writeln("<info>✔ Created Migration:</info> database/migrations/{$datePrefix}_create_{$tableName}_table.php");

        // 3. Generate Controller
        $controllerDir = $appRoot . '/app/Controllers';
        @mkdir($controllerDir, 0755, true);
        $controllerCode = <<<PHP
<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\\{$modelName};
use Oshim\Http\Request;
use Oshim\Http\Response;

class {$modelName}Controller
{
    public static function index(): Response
    {
        \$items = {$modelName}::paginate(15);
        return Response::json(\$items->toArray());
    }

    public static function store(Request \$request): Response
    {
        \$data = json_decode(\$request->getContent(), true) ?? [];
        \$item = {$modelName}::create(\$data);
        return Response::json(['success' => true, 'item' => \$item]);
    }
}
PHP;
        file_put_contents("{$controllerDir}/{$modelName}Controller.php", $controllerCode);
        $output->writeln("<info>✔ Created Controller:</info> app/Controllers/{$modelName}Controller.php");

        $output->writeln("<bold><green>🚀 1-Click CRUD Generated successfully for {$modelName}!</green></bold>");
        return 0;
    }
}
