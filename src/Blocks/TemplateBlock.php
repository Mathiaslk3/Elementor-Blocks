<?php
// Fil: src/Blocks/TemplateBlock.php
namespace NowOnline\EltBlocks\Blocks;

use NowOnline\EltBlocks\Rendering\Renderer;
// PlaceholderScanner er ikke nødvendig her længere

if (!defined('ABSPATH')) { exit; }

final class TemplateBlock
{
    public const NAME = 'nowonline/elt-template';

    /**
     * @var Renderer Den renderer-service vi modtager fra Plugin.php
     */
    private Renderer $renderer;

    /**
     * OPDATERET: Modtag den renderer-service, der er bygget af Plugin-containeren.
     */
    public function __construct(Renderer $renderer)
    {
        $this->renderer = $renderer;
    }

    public function register(): void
    {
        add_action('init', [$this, 'register_block']);
    }

    public function register_block(): void
    {
        if (!function_exists('register_block_type')) return;

        register_block_type(self::NAME, [
            'api_version'     => 2,
            'category'        => 'nowonline-elementor',
            
            // OPDATERET: Komplet liste af attributter, der matcher src-js/index.js
'attributes'      => [
                'templateId' => ['type' => 'number', 'default' => 0],
                'gap'        => ['type' => 'number', 'default' => 24],
                'fields'     => ['type' => 'object', 'default' => []],
                'design'     => ['type' => 'object', 'default' => []],
                'background' => ['type' => 'object', 'default' => []],
                'responsive' => ['type' => 'object', 'default' => []],
                'spacing'    => ['type' => 'object', 'default' => []],
                'align'      => ['type' => 'string', 'default' => 'full'],
                
                // Design-attributter
                'containerBg' => ['type' => 'string', 'default' => ''],
                'btnTextColor' => ['type' => 'string', 'default' => ''],
                'btnBorderColor' => ['type' => 'string', 'default' => ''],
                'btnBorderWidth' => ['type' => 'string', 'default' => ''],
                'btnBorderRadius' => ['type' => 'string', 'default' => ''],

                // Typografi-attributter
                'fsH1' => ['type' => 'string', 'default' => ''],
                'fsH2' => ['type' => 'string', 'default' => ''],
                'fsH3' => ['type' => 'string', 'default' => ''],
                'fsH4' => ['type' => 'string', 'default' => ''],
                'fsH5' => ['type' => 'string', 'default' => ''],
                'fsH6' => ['type' => 'string', 'default' => ''],
                'fsBody' => ['type' => 'string', 'default' => ''],
                'fsBtn' => ['type' => 'string', 'default' => ''],

                // Baggrund-attributter
                'bgVideo' => ['type' => 'string', 'default' => ''],
                'bgImg' => ['type' => 'string', 'default' => ''],
                'bgImgTablet' => ['type' => 'string', 'default' => ''],
                'bgImgMobile' => ['type' => 'string', 'default' => ''],
                'bgPos' => ['type' => 'string', 'default' => ''],     // <-- RETTET
                'bgSize' => ['type' => 'string', 'default' => ''],    // <-- RETTET
                'bgFixed' => ['type' => 'boolean', 'default' => false],
                'bgRepeat' => ['type' => 'string', 'default' => ''],  // <-- RETTET

                // Advanced-attributter
                'hideDesktop' => ['type' => 'boolean', 'default' => false],
                'hideTablet' => ['type' => 'boolean', 'default' => false],
                'hideMobile' => ['type' => 'boolean', 'default' => false],
                'padTopDesktop' => ['type' => 'string', 'default' => ''],
                'padBottomDesktop' => ['type' => 'string', 'default' => ''],
                'padTopLaptop' => ['type' => 'string', 'default' => ''],
                'padBottomLaptop' => ['type' => 'string', 'default' => ''],
                'padTopTablet' => ['type' => 'string', 'default' => ''],
                'padBottomTablet' => ['type' => 'string', 'default' => ''],
                'padTopMobile' => ['type' => 'string', 'default' => ''],
                'padBottomMobile' => ['type' => 'string', 'default' => ''],
            ],
            
            'render_callback' => [$this, 'render'],
            'supports'        => [
                'inserter' => true,
                'align'    => [ 'full' ],
                'anchor'   => false,
                'html'     => false,
                'spacing'  => [ 'margin' => false, 'padding' => true ],
            ],
            'editor_script'   => 'nowonline-elt-blocks-js',
            'style'           => null,
            'editor_style'    => 'nowonline-elt-blocks-css',
        ]);
    }

    /**
     * OPDATERET: Server-side render wrapper.
     * Bruger nu den injicerede Renderer-service.
     */
    public function render(array $attrs = [], string $content = '', $block = null): string
    {
        // Vi behøver ikke længere at tjekke om klasser eksisterer,
        // for vi fik $this->renderer injiceret i constructoren.
        return $this->renderer->render($attrs, $content);
    }
}