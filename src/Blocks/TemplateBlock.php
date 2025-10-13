<?php
// File: src/Blocks/TemplateBlock.php
namespace NowOnline\EltBlocks\Blocks;

use NowOnline\EltBlocks\Rendering\Renderer;
use NowOnline\EltBlocks\Services\PlaceholderScanner;

if (!defined('ABSPATH')) { exit; }

final class TemplateBlock
{
    public const NAME = 'nowonline/elt-template';

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
            'attributes'      => [
                'templateId' => ['type' => 'number', 'default' => 0],
                'gap'        => ['type' => 'number', 'default' => 24],
                'fields'     => ['type' => 'object', 'default' => []],
                'design'     => ['type' => 'object', 'default' => []],
                'background' => ['type' => 'object', 'default' => []],
                'responsive' => ['type' => 'object', 'default' => []],
                'spacing'    => ['type' => 'object', 'default' => []],
                // Tving fuldbredde som standard og i UI
                'align'      => ['type' => 'string', 'default' => 'full'],
            ],
            'render_callback' => [$this, 'render'],
            'supports'        => [
                'inserter' => true,
                // Kun fuldbredde er tilladt (hindrer side-om-side layoutvalg)
                'align'    => [ 'full' ],
                // Deaktiver unødvendige ting der kan påvirke layout
                'anchor'   => false,
                'html'     => false,
                // Lad kun padding være redigerbar (margin kan skabe sidelægning)
                'spacing'  => [ 'margin' => false, 'padding' => true ],
            ],
            'editor_script'   => 'nowonline-elt-blocks-js',
            'style'           => null,                       // why: frontend CSS injected by Renderer
            'editor_style'    => 'nowonline-elt-blocks-css', // why: editor CSS (inserter thumbs, preview)
        ]);
    }

    /**
     * Server-side render wrapper.
     * Builds a minimal Renderer with a fresh PlaceholderScanner.
     */
    public function render(array $attrs = [], string $content = '', $block = null): string
    {
        if (class_exists(Renderer::class) && class_exists(PlaceholderScanner::class)){
            $renderer = new Renderer(new PlaceholderScanner());
            return $renderer->render($attrs, $content);
        }
        return '';
    }
}
