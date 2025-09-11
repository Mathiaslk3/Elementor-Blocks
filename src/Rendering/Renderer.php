<?php
// File: src/Rendering/Renderer.php
namespace NowOnline\EltBlocks\Rendering;

use NowOnline\EltBlocks\Services\PlaceholderScanner;

if (!defined('ABSPATH')) { exit; }

final class Renderer
{
    private PlaceholderScanner $scanner;

    public function __construct(PlaceholderScanner $scanner)
    {
        $this->scanner = $scanner;
    }

    public function register(): void
    {
        add_action('wp_head', [$this, 'frontend_css']);
    }

    public function frontend_css(): void
    {
        // why: simple gap control without extra stylesheet
        echo '<style>.nowonline-elt-wrapper{--nowonline-elt-gap:24px}.nowonline-elt-wrapper>.nowonline-elt-module{margin:var(--nowonline-elt-gap) 0}</style>';
    }

    /**
     * Server-side render callback for the block.
     *
     * @param array $attrs
     * @param string $content
     */
    public function render($attrs = [], $content = ''): string
    {
        $tid    = isset($attrs['templateId']) ? (int) $attrs['templateId'] : 0;
        $gap    = isset($attrs['gap']) ? (int) $attrs['gap'] : 24;
        $fields = (isset($attrs['fields']) && is_array($attrs['fields'])) ? $attrs['fields'] : [];

        if ($tid <= 0) {
            return '<div class="nowonline-elt-empty">' . esc_html__('Vælg en Elementor-template fra blok-listen.', 'nowonline') . '</div>';
        }

        $style = '--nowonline-elt-gap:' . $gap . 'px;';

        // Fetch template HTML from Elementor
        $html = '';
        if (did_action('elementor/loaded') && class_exists('\\Elementor\\Plugin')) {
            $inst = \Elementor\Plugin::$instance;
            if ($inst && isset($inst->frontend) && method_exists($inst->frontend, 'get_builder_content_for_display')) {
                $html = $inst->frontend->get_builder_content_for_display($tid, true);
            }
        }
        if (!$html && function_exists('do_shortcode')) {
            $html = do_shortcode('[elementor-template id="' . $tid . '"]');
        }

        // Replace placeholders with provided fields
        if ($html && !empty($fields)) {
            $defs = $this->scanner->scan($tid); // key => type
            $search = [];
            $replace = [];
            foreach ($fields as $k => $v) {
                $key = strtolower((string) $k);
                $type = isset($defs[$key]) ? $defs[$key] : 'text';
                if ($type === 'img' || $type === 'bg') {
                    $url = esc_url((string) $v);
                    foreach (['[[img:' . $key . ']]', '[[bg:' . $key . ']]'] as $tok) { $search[] = $tok; $replace[] = $url; }
                } elseif ($type === 'url') {
                    $url = esc_url((string) $v);
                    foreach (['[[url:' . $key . ']]', '[[' . $key . ']]'] as $tok) { $search[] = $tok; $replace[] = $url; }
                } elseif ($type === 'textarea') {
                    $txt = nl2br( esc_html( (string) $v ) );
                    foreach (['[[' . $key . ']]', '[[textarea:' . $key . ']]'] as $tok) { $search[] = $tok; $replace[] = $txt; }
                } elseif ($type === 'rich' || $type === 'wysiwyg') {
                    $html_val = wp_kses_post( (string) $v );
                    foreach (['[[rich:' . $key . ']]', '[[wysiwyg:' . $key . ']]'] as $tok) { $search[] = $tok; $replace[] = $html_val; }
                } else { // text / p / h1..h6
                    $txt = esc_html( (string) $v );
                    foreach (['[[' . $key . ']]','[[text:' . $key . ']]','[[p:' . $key . ']]','[[h1:' . $key . ']]','[[h2:' . $key . ']]','[[h3:' . $key . ']]','[[h4:' . $key . ']]','[[h5:' . $key . ']]','[[h6:' . $key . ']]'] as $tok) { $search[] = $tok; $replace[] = $txt; }
                }
            }
            if ($search) {
                $html = str_replace($search, $replace, $html);
            }
        }

        return '<div class="nowonline-elt-wrapper" style="' . esc_attr($style) . '"><div class="nowonline-elt-module" data-template-id="' . (int) $tid . '">' . $html . '</div></div>';
    }
}
