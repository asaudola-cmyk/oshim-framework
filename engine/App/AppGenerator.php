<?php
declare(strict_types=1);

namespace Oshim\App;

class AppGenerator
{
    public static function createProject(string $name, string $type = 'fullstack', string $targetDir = ''): array
    {
        $manifest = AppManifest::make($name, $type);
        $manifestData = $manifest->toArray();

        $entryCode = self::generateEntrypointCode($name, $type);
        $readme = self::generateReadme($name, $type);

        return [
            'status' => 'CREATED',
            'app_name' => $name,
            'type' => $type,
            'manifest' => $manifestData,
            'entrypoint_file' => 'app.php',
            'entrypoint_code' => $entryCode,
            'readme' => $readme,
        ];
    }

    public static function generateEntrypointCode(string $name, string $type): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/engine/Bootstrap.php';
\Oshim\Bootstrap::boot();

use Oshim\Ui\Dsl\Document;
use Oshim\Ui\Dsl\Heading;
use Oshim\Ui\Dsl\Paragraph;
use Oshim\Ui\Dsl\Badge;
use Oshim\Ui\Dsl\Grid;
use Oshim\Ui\Widgets\GlassCard;
use Oshim\Ui\Widgets\NavbarWidget;
use Oshim\Ui\Widgets\FooterWidget;

// OSHIM Universal Application Entrypoint: {$name} [Type: {$type}]
echo Document::make('{$name} — OSHIM Universal App')
    ->navbar(NavbarWidget::makeNavbar('home'))
    ->body([
        Heading::h1('Welcome to {$name}')->class('oshim-brand-gradient'),
        Badge::make('Universal {$type} Application', '#00f2fe'),
        Paragraph::make('Built 100% with OSHIM Sovereign Pure PHP Framework.'),
        Grid::cols(3)->children([
            GlassCard::widget('📱 Mobile Ready')->child(Paragraph::make('Installable on iOS & Android')),
            GlassCard::widget('🖥️ Desktop Ready')->child(Paragraph::make('Native Windows, Mac & Linux Window')),
            GlassCard::widget('⚡ Turbo Speed')->child(Paragraph::make('1.4M+ RPS Throughput')),
        ])
    ])
    ->footer(FooterWidget::makeFooter())
    ->render();
PHP;
    }

    public static function generateReadme(string $name, string $type): string
    {
        return <<<MD
# {$name} (OSHIM Universal App)

Built 100% with the **OSHIM Sovereign Master Framework** (Zero 3rd-Party Dependencies).

## Run & Bundle Targets:
- **Web App**: `php engine/Cli/oshim.php app:run --target=web`
- **Mobile Bundle**: `php engine/Cli/oshim.php app:bundle --platform=android`
- **Desktop Executable**: `php engine/Cli/oshim.php app:bundle --platform=windows`
- **Cross-Platform**: `php engine/Cli/oshim.php app:bundle --platform=all`
MD;
    }
}
