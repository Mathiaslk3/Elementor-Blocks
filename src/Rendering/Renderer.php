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

        // Hent Elementor-output
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
            // defs: key => type
            $defs = $this->scanner->scan($tid);

            // Byg hurtig map af key->type (lowercased)
            $typeMap = [];
            foreach ((array) $defs as $k => $t) {
                $typeMap[strtolower((string)$k)] = strtolower((string)$t ?: 'text');
            }

            // Normalisér type-navne
            $normalize = static function(string $t, string $keyGuess = ''): string {
                $t = strtolower(trim($t));
                $k = strtolower(trim($keyGuess));
                if ($t === '') $t = 'text';

                // direkte aliaser
                if (in_array($t, ['rich','wysiwyg','richtext','rte','editor','html'], true)) return 'rich';
                if (in_array($t, ['textarea','longtext','multiline','text_area'], true))    return 'textarea';
                if (in_array($t, ['url','link','href'], true))                               return 'url';
                if (in_array($t, ['img','image','picture','photo'], true))                   return 'img';
                if (in_array($t, ['bg','background','background_image'], true))              return 'bg';
                if ($t === 'paragraph')                                                     return 'p';

                // inferer fra key hvis type var "text"/tom
                if ($t === 'text') {
                    if (preg_match('/(rich|wysiwyg|rte|editor|html)/', $k)) return 'rich';
                    if (preg_match('/textarea|longtext|multiline/', $k))    return 'textarea';
                    if (preg_match('/url|link|href/', $k))                   return 'url';
                    if (preg_match('/^(img|image|photo)/', $k))              return 'img';
                    if (preg_match('/bg|background/', $k))                   return 'bg';
                }
                return $t;
            };

            // Konverter WYSIWYG til "inline" (til brug i Heading m.m.)
            $inlineFromRich = static function(string $htmlValue): string {
                $safe = wp_kses_post($htmlValue);
                // Saml p-paragraffer til linjer med <br>, fjern p-wrappers
                $safe = preg_replace('~\s*</p>\s*<p[^>]*>\s*~i', "<br>", $safe ?? '');
                $safe = preg_replace('~^\s*<p[^>]*>\s*~i', '', $safe ?? '');
                $safe = preg_replace('~\s*</p>\s*$~i', '', $safe ?? '');
                // Hvis der stadig er <p> tilbage, drop tags men bevar indhold
                $safe = preg_replace('~</?p[^>]*>~i', '', $safe ?? '');
                return $safe;
            };

            // Samlet token-erstatning med regex
            // Matcher [[ key ]] eller [[alias:key]] — alias kan fx være rich, wysiwyg, textarea, url, img, bg, text, p, h1..h6, rich-inline
            $pattern = '/\[\[\s*(?:(?<alias>rich|wysiwyg|rich-inline|textarea|url|img|bg|text|p|h[1-6])\s*:\s*)?(?<key>[a-z0-9_-]+)\s*\]\]/i';

            $html = preg_replace_callback($pattern, function($m) use ($fields, $typeMap, $normalize, $inlineFromRich) {
                $key   = strtolower($m['key']);
                $alias = isset($m['alias']) ? strtolower($m['alias']) : '';

                if (!array_key_exists($key, $fields)) {
                    // Ukendt/ikke-udfyldt: fjern token
                    return '';
                }

                $raw  = (string) $fields[$key];
                $type = $alias ?: ($typeMap[$key] ?? 'text');
                $type = $normalize($type, $key);

                switch ($type) {
                    case 'rich':
                        // Normal WYSIWYG
                        return wp_kses_post($raw);

                    case 'rich-inline':
                        // Inline-version til Heading etc.
                        return $inlineFromRich($raw);

                    case 'textarea':
                        return nl2br( esc_html($raw) );

                    case 'url':
                        return esc_url($raw);

                    case 'img':
                    case 'bg':
                        return esc_url($raw);

                    // tekst/p/h1..h6 -> ren tekst
                    default:
                        return esc_html($raw);
                }
            }, $html);
        }

        return '<div class="nowonline-elt-wrapper" style="' . esc_attr($style) . '"><div class="nowonline-elt-module" data-template-id="' . (int) $tid . '">' . $html . '</div></div>';
    }
}
