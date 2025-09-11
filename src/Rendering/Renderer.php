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
        echo '<style>.nowonline-elt-wrapper{--nowonline-elt-gap:24px}.nowonline-elt-wrapper>.nowonline-elt-module{margin:var(--nowonline-elt-gap) 0}</style>';
    }

    /**
     * Server-side render callback for the block.
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

    // Get Elementor HTML
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

    if ($html && !empty($fields)) {
        $defs = $this->scanner->scan($tid); // key => type

        // helper: normalize type from defs or key (Danish aliases)
        $normType = static function (string $key) use ($defs): string {
            $k = strtolower($key);
            $t = isset($defs[$k]) ? strtolower((string)$defs[$k]) : '';
            if ($t) return $t;
            if (in_array($k, ['titel','undertitel','beskrivelse'], true)) return 'rich';
            if ($k === 'billede') return 'img';
            if ($k === 'galleri') return 'gallery';
            return 'text';
        };

        $search  = [];
        $replace = [];

        foreach ($fields as $k => $v) {
            $key  = strtolower((string)$k);
            $type = $normType($key);

            if ($type === 'img' || $type === 'bg') {
                $url = esc_url((string) $v);
                foreach (['[[img:' . $key . ']]', '[[bg:' . $key . ']]', '[['.$key.']]'] as $tok) {
                    $search[]  = $tok; 
                    $replace[] = $url;
                }

            } elseif ($type === 'url') {
                $url = esc_url((string) $v);
                foreach (['[[url:' . $key . ']]', '[[' . $key . ']]'] as $tok) {
                    $search[]  = $tok; 
                    $replace[] = $url;
                }

            } elseif ($type === 'textarea') {
                $txt = nl2br( esc_html( (string) $v ) );
                foreach (['[[textarea:' . $key . ']]', '[[' . $key . ']]'] as $tok) {
                    $search[]  = $tok; 
                    $replace[] = $txt;
                }

            } elseif ($type === 'rich' || $type === 'wysiwyg') {
                $html_val = wp_kses_post( (string) $v );
                foreach (['[[rich:' . $key . ']]', '[[wysiwyg:' . $key . ']]', '[['.$key.']]'] as $tok) {
                    $search[]  = $tok; 
                    $replace[] = $html_val;
                }

            } elseif ($type === 'gallery') {
                // Accept array of URLs (preferred) or comma-separated string
                $urls = [];
                if (is_array($v)) {
                    foreach ($v as $u) { $u = trim((string)$u); if ($u !== '') $urls[] = esc_url($u); }
                } else {
                    $parts = array_map('trim', explode(',', (string)$v));
                    foreach ($parts as $u) { if ($u !== '') $urls[] = esc_url($u); }
                }
                $gallery_html = '';
                if ($urls) {
                    $items = '';
                    foreach ($urls as $u) {
                        $items .= '<img src="'. $u .'" alt="" />';
                    }
                    $gallery_html = '<div class="nowonline-elt-gallery">'. $items .'</div>';
                }
                foreach (['[[gallery:' . $key . ']]', '[[galleri:' . $key . ']]', '[['.$key.']]'] as $tok) {
                    $search[]  = $tok; 
                    $replace[] = $gallery_html;
                }

            } else { // text / p / h1..h6 / default
                $txt = esc_html( (string) $v );
                foreach (['[[' . $key . ']]','[[text:' . $key . ']]','[[p:' . $key . ']]','[[h1:' . $key . ']]','[[h2:' . $key . ']]','[[h3:' . $key . ']]','[[h4:' . $key . ']]','[[h5:' . $key . ']]','[[h6:' . $key . ']]'] as $tok) {
                    $search[]  = $tok; 
                    $replace[] = $txt;
                }
            }
        }

        if ($search) {
            $html = str_replace($search, $replace, $html);
        }
    }

    return '<div class="nowonline-elt-wrapper" style="' . esc_attr($style) . '"><div class="nowonline-elt-module" data-template-id="' . (int) $tid . '">' . $html . '</div></div>';
}

}
