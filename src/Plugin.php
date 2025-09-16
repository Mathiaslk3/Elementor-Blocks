<?php
namespace NowOnline\EltBlocks;

if (!defined('ABSPATH')) { exit; }

final class Plugin
{
    public const VER = '2.12.32';
    private static ?Plugin $instance = null;

    /** @var array<class-string,object> */
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
        $this->get(\NowOnline\EltBlocks\Admin\SettingsPage::class)->register();
        $this->get(\NowOnline\EltBlocks\Admin\Ajax::class)->register();

        // Assets
        $this->get(\NowOnline\EltBlocks\Assets\Assets::class)->register(self::VER);

        // Runtime
        $this->get(\NowOnline\EltBlocks\Rendering\Renderer::class)->register();
        $this->get(\NowOnline\EltBlocks\Blocks\TemplateBlock::class)->register();
    }

    /** tiny service container */
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
            default:
                $this->services[$class] = new $class();
        }
        return $this->services[$class];
    }
}
