<?php
// Fil: src/Plugin.php
namespace NowOnline\EltBlocks;

if (!defined('ABSPATH')) { exit; }

// Importer alle de nye klasser, vi har lavet
use NowOnline\EltBlocks\Rendering\Renderer;
use NowOnline\EltBlocks\Rendering\FrontendHooks;
use NowOnline\EltBlocks\Rendering\DynamicCss;
use NowOnline\EltBlocks\Services\PlaceholderScanner;
use NowOnline\EltBlocks\Services\DomProcessor;
use NowOnline\EltBlocks\Services\DataHelper; 
use NowOnline\EltBlocks\Repository\TemplatesRepo;

final class Plugin
{
    public const VER = '2.13.29';
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
        // Base services - sørg for at alle er registreret FØR de bruges
        $this->get(TemplatesRepo::class);
        $this->get(PlaceholderScanner::class);
        $this->get(DomProcessor::class);
        $this->get(DynamicCss::class);
        $this->get(DataHelper::class); // Vigtig!

        // Admin
        $sp = $this->get(\NowOnline\EltBlocks\Admin\SettingsPage::class);
        if ($sp && method_exists($sp, 'register')) { $sp->register(); }

        $ajax = $this->get(\NowOnline\EltBlocks\Admin\Ajax::class);
        if ($ajax && method_exists($ajax, 'register')) { $ajax->register(); }

        $ui = $this->get(\NowOnline\EltBlocks\Admin\AdminUI::class);
        if ($ui && method_exists($ui, 'register')) { $ui->register(); }

        // Assets
        $assets = $this->get(\NowOnline\EltBlocks\Assets\Assets::class);
        if ($assets && method_exists($assets, 'register')) { $assets->register(self::VER); }

        // Runtime
        $this->get(Renderer::class); // Bygger og gemmer Renderer-servicen
        
        $hooks = $this->get(FrontendHooks::class); // Henter FrontendHooks
        if ($hooks && method_exists($hooks, 'register')) { $hooks->register(); }

        $block = $this->get(\NowOnline\EltBlocks\Blocks\TemplateBlock::class); // Henter blokken
        if ($block && method_exists($block, 'register')) { $block->register(); }

        // Integrationer (fra Plan 1)
        $this->get(\NowOnline\EltBlocks\Integrations\TinyMCE::class)->register();
        $this->get(\NowOnline\EltBlocks\Integrations\Sanitizers::class)->register();
        $this->get(\NowOnline\EltBlocks\Integrations\DesignControls::class)->register();
        $this->get(\NowOnline\EltBlocks\Assets\CommonAssets::class)->register();
    }

    /** Tiny service container */
    public function get(string $class)
    {
        if (isset($this->services[$class])) return $this->services[$class];

        $class = ltrim($class, '\\');
        if (!str_starts_with($class, 'NowOnline\\EltBlocks\\')) {
             $class = __NAMESPACE__ . '\\' . $class;
        }

        switch ($class) {
            case TemplatesRepo::class:
                $this->services[$class] = new TemplatesRepo();
                break;
            case PlaceholderScanner::class:
                $this->services[$class] = new PlaceholderScanner();
                break;
            case DomProcessor::class:
                $this->services[$class] = new DomProcessor();
                break;
            case DynamicCss::class:
                $this->services[$class] = new DynamicCss();
                break;
            case DataHelper::class: 
                $this->services[$class] = new DataHelper();
                break;
            case FrontendHooks::class:
                $this->services[$class] = new FrontendHooks();
                break;

            case \NowOnline\EltBlocks\Assets\Assets::class:
                $this->services[$class] = new \NowOnline\EltBlocks\Assets\Assets(
                    $this->get(TemplatesRepo::class),
                    $this->get(PlaceholderScanner::class)
                );
                break;

            case Renderer::class:
                if (!class_exists(Renderer::class)) {
                    if (function_exists('error_log')) {
                        error_log('[NowOnline EltBlocks] Renderer class missing – site kept alive.');
                    }
                    $this->services[$class] = null;
                    break;
                }
                
                // --- DETTE ER DEN VIGTIGE ÆNDRING ---
                // Den nye Renderer modtager alle 4 services.
                $this->services[$class] = new Renderer(
                    $this->get(PlaceholderScanner::class),
                    $this->get(DomProcessor::class),
                    $this->get(DynamicCss::class),
                    $this->get(DataHelper::class) 
                );
                break;

            case \NowOnline\EltBlocks\Blocks\TemplateBlock::class:
                $this->services[$class] = new \NowOnline\EltBlocks\Blocks\TemplateBlock(
                    $this->get(Renderer::class) // Injicer den fuldt byggede Renderer
                );
                break;

            // ... (Resten af dine case statements for Admin, Integrations, osv.) ...
            case \NowOnline\EltBlocks\Admin\SettingsPage::class:
                $this->services[$class] = new \NowOnline\EltBlocks\Admin\SettingsPage();
                break;
            case \NowOnline\EltBlocks\Admin\Ajax::class:
                $this->services[$class] = new \NowOnline\EltBlocks\Admin\Ajax();
                break;
            case \NowOnline\EltBlocks\Admin\AdminUI::class:
                $this->services[$class] = new \NowOnline\EltBlocks\Admin\AdminUI();
                break;
            case \NowOnline\EltBlocks\Integrations\TinyMCE::class:
                $this->services[$class] = new \NowOnline\EltBlocks\Integrations\TinyMCE();
                break;
            case \NowOnline\EltBlocks\Integrations\Sanitizers::class:
                $this->services[$class] = new \NowOnline\EltBlocks\Integrations\Sanitizers();
                break;
            case \NowOnline\EltBlocks\Integrations\DesignControls::class:
                $this->services[$class] = new \NowOnline\EltBlocks\Integrations\DesignControls();
                break;
            case \NowOnline\EltBlocks\Assets\CommonAssets::class:
                $this->services[$class] = new \NowOnline\EltBlocks\Assets\CommonAssets();
                break;

            default:
                $this->services[$class] = class_exists($class) ? new $class() : null;
        }
        return $this->services[$class];
    }
}