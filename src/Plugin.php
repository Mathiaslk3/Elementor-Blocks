<?php
// File: src/Plugin.php
namespace NowOnline\EltBlocks;

if (!defined('ABSPATH')) { exit; }

final class Plugin
{
    public const VER = '2.13.24';
    private static ?Plugin $instance = null;

    /** @var array<string,mixed> */
    private array $services = [];

    private function __construct() {}

    public static function boot(): void
    {
        if (self::$instance instanceof self) return;
        self::$instance = new self();
        self::$instance->register();
    }

    private function register(): void
    {
        // Base services
        $this->get(\NowOnline\EltBlocks\Repository\TemplatesRepo::class);
        $this->get(\NowOnline\EltBlocks\Services\PlaceholderScanner::class);

        // Admin
        $sp = $this->get(\NowOnline\EltBlocks\Admin\SettingsPage::class);
        if ($sp && method_exists($sp, 'register')) { $sp->register(); }

        $ajax = $this->get(\NowOnline\EltBlocks\Admin\Ajax::class);
        if ($ajax && method_exists($ajax, 'register')) { $ajax->register(); }

        // NEW: Modern editor UI
        $ui = $this->get(\NowOnline\EltBlocks\Admin\AdminUI::class);
        if ($ui && method_exists($ui, 'register')) { $ui->register(); }

        // Assets
        $assets = $this->get(\NowOnline\EltBlocks\Assets\Assets::class);
        if ($assets && method_exists($assets, 'register')) { $assets->register(self::VER); }

        // Runtime
        $renderer = $this->get(\NowOnline\EltBlocks\Rendering\Renderer::class);
        if ($renderer && method_exists($renderer, 'register')) { $renderer->register(); }

        $block = $this->get(\NowOnline\EltBlocks\Blocks\TemplateBlock::class);
        if ($block && method_exists($block, 'register')) { $block->register(); }
    }

    /** Tiny service container */
    public function get(string $class)
    {
        if (isset($this->services[$class])) return $this->services[$class];

        switch ($class) {
            case \NowOnline\EltBlocks\Repository\TemplatesRepo::class:
                $this->services[$class] = new \NowOnline\EltBlocks\Repository\TemplatesRepo();
                break;

            case \NowOnline\EltBlocks\Services\PlaceholderScanner::class:
                $this->services[$class] = new \NowOnline\EltBlocks\Services\PlaceholderScanner();
                break;

            case \NowOnline\EltBlocks\Assets\Assets::class:
                $this->services[$class] = new \NowOnline\EltBlocks\Assets\Assets(
                    $this->get(\NowOnline\EltBlocks\Repository\TemplatesRepo::class),
                    $this->get(\NowOnline\EltBlocks\Services\PlaceholderScanner::class)
                );
                break;

            case \NowOnline\EltBlocks\Rendering\Renderer::class:
                // Defensive: hvis klassen ikke kan loades, crash ikke hele sitet
                if (!class_exists(\NowOnline\EltBlocks\Rendering\Renderer::class)) {
                    if (function_exists('error_log')) {
                        error_log('[NowOnline EltBlocks] Renderer class missing – site kept alive.');
                    }
                    $this->services[$class] = null;
                    break;
                }
                $this->services[$class] = new \NowOnline\EltBlocks\Rendering\Renderer(
                    $this->get(\NowOnline\EltBlocks\Services\PlaceholderScanner::class)
                );
                break;

            case \NowOnline\EltBlocks\Blocks\TemplateBlock::class:
                $this->services[$class] = new \NowOnline\EltBlocks\Blocks\TemplateBlock();
                break;

            case \NowOnline\EltBlocks\Admin\SettingsPage::class:
                $this->services[$class] = new \NowOnline\EltBlocks\Admin\SettingsPage();
                break;

            case \NowOnline\EltBlocks\Admin\Ajax::class:
                $this->services[$class] = new \NowOnline\EltBlocks\Admin\Ajax();
                break;

            case \NowOnline\EltBlocks\Admin\AdminUI::class:
                $this->services[$class] = new \NowOnline\EltBlocks\Admin\AdminUI();
                break;

            default:
                // Sidste udvej – prøv at instantiere hvis klassen findes, ellers returnér null
                $this->services[$class] = class_exists($class) ? new $class() : null;
        }
        return $this->services[$class];
    }
}
