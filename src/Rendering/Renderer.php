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
        echo '<style>'
            . '.nowonline-elt-wrapper{--nowonline-elt-gap:24px}'
            . '.nowonline-elt-wrapper>.nowonline-elt-module{margin:var(--nowonline-elt-gap) 0}'
            . '.nowonline-elt-gallery{display:flex;flex-wrap:wrap;gap:8px}'
            . '.nowonline-elt-gallery img{max-width:100%;height:auto;display:block}'
            . '</style>';
    }

    /** Gør URL’er “fornuftige” (http// -> http://, www. -> https://www., osv.) */
    private static function fix_url(string $u): string
    {
        $u = trim($u);
        if ($u === '') return $u;

        $u = str_ireplace(['http//','https//'], ['http://','https://'], $u);
        $u = preg_replace('#^(https?://)(https?://)+#i', '$1', $u);

        if (strpos($u, '//') === 0) $u = 'https:' . $u;
        if (stripos($u, 'www.') === 0) $u = 'https://' . $u;

        if (!preg_match('#^[a-z][a-z0-9+\-.]*://#i', $u)) {
            if ($u[0] === '/') {
                $u = rtrim(home_url(), '/') . $u;
            } elseif (preg_match('#^[a-z0-9\.-]+\.[a-z]{2,}(/.*)?$#i', $u)) {
                $u = (is_ssl() ? 'https' : 'http') . '://' . $u;
            }
        }
        return $u;
    }

    private static function norm_type(array $defs, string $key): string
    {
        $k = strtolower($key);
        $t = isset($defs[$k]) ? strtolower((string)$defs[$k]) : '';
        if ($t) return $t;
        if (in_array($k, ['titel','undertitel','beskrivelse'], true)) return 'rich';
        if ($k === 'billede')  return 'img';
        if ($k === 'galleri')  return 'gallery';
        if (preg_match('#^(url|link|href)$#i', $k)) return 'url';
        return 'text';
    }

    /** key => ['url'=>string,'blank'=>bool] */
    private static function build_link_map(array $defs, array $fields): array
    {
        $out = [];
        foreach ($fields as $k => $v) {
            $key  = strtolower((string)$k);
            $type = self::norm_type($defs, $key);
            if ($type !== 'url') continue;

            $url   = '';
            $blank = false;

            if (is_array($v)) {
                $url   = (string)($v['url'] ?? '');
                $blank = !empty($v['blank']) || !empty($v['newTab']) || !empty($v['opensInNewTab']) || !empty($v['is_external'])
                      || (isset($v['target']) && strtolower((string)$v['target']) === '_blank');
            } else {
                $url = (string)$v;
            }

            $url = esc_url(self::fix_url($url));
            $out[$key] = ['url' => $url, 'blank' => $blank];
        }
        return $out;
    }

    public function render($attrs = [], $content = ''): string
    {
        $tid    = isset($attrs['templateId']) ? (int)$attrs['templateId'] : 0;
        $gap    = isset($attrs['gap']) ? (int)$attrs['gap'] : 24;
        $fields = (isset($attrs['fields']) && is_array($attrs['fields'])) ? $attrs['fields'] : [];

        if ($tid <= 0) {
            return '<div class="nowonline-elt-empty">' . esc_html__('Vælg en Elementor-template fra blok-listen.', 'nowonline') . '</div>';
        }

        $style = '--nowonline-elt-gap:' . $gap . 'px;';

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
        if (!$html) {
            return '<div class="nowonline-elt-wrapper" style="' . esc_attr($style) . '"><div class="nowonline-elt-module" data-template-id="' . (int)$tid . '"></div></div>';
        }

        $defs = $this->scanner->scan($tid);

        // --- Token-udskiftninger (bagudkompatible) ---
        if (!empty($fields)) {
            $search  = [];
            $replace = [];

            foreach ($fields as $k => $v) {
                $key  = strtolower((string)$k);
                $type = self::norm_type($defs, $key);

                if ($type === 'img' || $type === 'bg') {
                    $val = is_array($v) ? (string)($v['url'] ?? '') : (string)$v;
                    $url = esc_url(self::fix_url($val));
                    foreach (['[[img:' . $key . ']]','[[bg:' . $key . ']]','[['.$key.']]'] as $tok) {
                        $search[] = $tok; $replace[] = $url;
                    }

                } elseif ($type === 'gallery') {
                    $urls = [];
                    if (is_array($v)) {
                        foreach ($v as $u) { $u = trim((string)$u); if ($u !== '') $urls[] = esc_url(self::fix_url($u)); }
                    } else {
                        $parts = array_map('trim', explode(',', (string)$v));
                        foreach ($parts as $u) { if ($u !== '') $urls[] = esc_url(self::fix_url($u)); }
                    }
                    $items = '';
                    foreach ($urls as $u) { $items .= '<img src="' . $u . '" alt="" />'; }
                    $gallery_html = $items ? '<div class="nowonline-elt-gallery">' . $items . '</div>' : '';
                    foreach (['[[gallery:' . $key . ']]', '[[galleri:' . $key . ']]', '[[' . $key . ']]'] as $tok) {
                        $search[] = $tok; $replace[] = $gallery_html;
                    }

                } elseif ($type === 'url') {
                    $url   = '';
                    $blank = false;
                    if (is_array($v)) {
                        $url   = (string)($v['url'] ?? '');
                        $blank = !empty($v['blank']) || !empty($v['newTab']) || !empty($v['opensInNewTab']) || !empty($v['is_external'])
                              || (isset($v['target']) && strtolower((string)$v['target']) === '_blank');
                    } else {
                        $url = (string)$v;
                    }
                    $url = esc_url(self::fix_url($url));
                    $finalUrl = $url !== '' ? $url : '#';
                    $inject   = ($url !== '' && $blank) ? ' target="_blank" rel="noopener noreferrer"' : '';

                    $search = array_merge($search, [
                        'href="[[url:' . $key . ']]"', "href='[[url:" . $key . "]]'",
                        'href="[['.$key.']]"',         "href='[[".$key."]]'",
                        '[[url:' . $key . ']]',        '[[' . $key . ']]',
                        '[[target:' . $key . ']]',     '[[blank:' . $key . ']]', '[[is_external:' . $key . ']]',
                    ]);
                    $replace = array_merge($replace, [
                        'href="' . $finalUrl . '"' . $inject, "href='" . $finalUrl . "'" . $inject,
                        'href="' . $finalUrl . '"' . $inject, "href='" . $finalUrl . "'" . $inject,
                        $finalUrl, $finalUrl,
                        $inject, ($url !== '' && $blank) ? '1' : '', ($url !== '' && $blank) ? 'true' : 'false',
                    ]);
                } elseif ($type === 'textarea') {
                    $txt = nl2br( esc_html( (string)$v ) );
                    foreach (['[[textarea:' . $key . ']]', '[[' . $key . ']]'] as $tok) {
                        $search[] = $tok; $replace[] = $txt;
                    }
                } elseif ($type === 'rich' || $type === 'wysiwyg') {
                    $html_val = wp_kses_post( (string)$v );
                    foreach (['[[rich:' . $key . ']]', '[[wysiwyg:' . $key . ']]', '[[' . $key . ']]'] as $tok) {
                        $search[] = $tok; $replace[] = $html_val;
                    }
                } else {
                    $val = is_array($v) ? '' : (string)$v;
                    $txt = esc_html($val);
                    foreach (['[['.$key.']]','[[text:'.$key.']]','[[p:'.$key.']]','[[h1:'.$key.']]','[[h2:'.$key.']]','[[h3:'.$key.']]','[[h4:'.$key.']]','[[h5:'.$key.']]','[[h6:'.$key.']]'] as $tok) {
                        $search[] = $tok; $replace[] = $txt;
                    }
                }
            }

            if ($search) $html = str_replace($search, $replace, $html);
        }

        // --- Ny metode: bind links på selve <a> ---
        $linkMap = self::build_link_map($defs, $fields);

        if (!empty($linkMap)) {
            // a) <a ... data-now-key="KEY" ...>
            $html = preg_replace_callback(
                '~<a\b([^>]*?\s)data-now-key=(["\'])([^"\']+)\2([^>]*)>~i',
                function ($m) use ($linkMap) {
                    $attrs = trim($m[1] . ' ' . $m[4]);
                    $key   = strtolower($m[3]);

                    $url   = $linkMap[$key]['url']   ?? '';
                    $blank = (bool)($linkMap[$key]['blank'] ?? false);
                    if (preg_match('~\sdata-now-newtab=(["\'])(1|true)\1~i', $attrs)) $blank = true;

                    $attrs = preg_replace('/\s+href=(["\']).*?\1/i', '', $attrs);
                    $attrs = preg_replace('/\s+target=(["\']).*?\1/i', '', $attrs);
                    $attrs = preg_replace('/\s+rel=(["\']).*?\1/i', '', $attrs);

                    $finalUrl = $url !== '' ? $url : '#';
                    $inject   = ($url !== '' && $blank) ? ' target="_blank" rel="noopener noreferrer"' : '';
                    return '<a ' . $attrs . ' href="' . esc_attr($finalUrl) . '"' . $inject . '>';
                },
                $html
            );

            // b) <a class="... now-link-KEY ...">
            $html = preg_replace_callback(
                '~<a\b([^>]*class=(["\'][^"\']*?\bnow-link-([a-z0-9_-]+)\b[^"\']*\2)[^>]*)>~i',
                function ($m) use ($linkMap) {
                    $attrs = $m[1];
                    $key   = strtolower($m[3]);

                    $url   = $linkMap[$key]['url']   ?? '';
                    $blank = (bool)($linkMap[$key]['blank'] ?? false);
                    if (preg_match('~\sdata-now-newtab=(["\'])(1|true)\1~i', $attrs)) $blank = true;

                    $attrs = preg_replace('/\s+href=(["\']).*?\1/i', '', $attrs);
                    $attrs = preg_replace('/\s+target=(["\']).*?\1/i', '', $attrs);
                    $attrs = preg_replace('/\s+rel=(["\']).*?\1/i', '', $attrs);

                    $finalUrl = $url !== '' ? $url : '#';
                    $inject   = ($url !== '' && $blank) ? ' target="_blank" rel="noopener noreferrer"' : '';
                    return '<a ' . trim($attrs) . ' href="' . esc_attr($finalUrl) . '"' . $inject . '>';
                },
                $html
            );

            // c) <a id="now-link-KEY">  (Elementor Button → “Button ID”)
            $html = preg_replace_callback(
                '~<a\b([^>]*\sid=(["\'])now-link-([a-z0-9_-]+)\2[^>]*)>~i',
                function ($m) use ($linkMap) {
                    $attrs = $m[1];
                    $key   = strtolower($m[3]);

                    $url   = $linkMap[$key]['url']   ?? '';
                    $blank = (bool)($linkMap[$key]['blank'] ?? false);
                    if (preg_match('~\sdata-now-newtab=(["\'])(1|true)\1~i', $attrs)) $blank = true;

                    $attrs = preg_replace('/\s+href=(["\']).*?\1/i', '', $attrs);
                    $attrs = preg_replace('/\s+target=(["\']).*?\1/i', '', $attrs);
                    $attrs = preg_replace('/\s+rel=(["\']).*?\1/i', '', $attrs);

                    $finalUrl = $url !== '' ? $url : '#';
                    $inject   = ($url !== '' && $blank) ? ' target="_blank" rel="noopener noreferrer"' : '';
                    return '<a ' . trim($attrs) . ' href="' . esc_attr($finalUrl) . '"' . $inject . '>';
                },
                $html
            );
        }

        $data_attr = !empty($linkMap) ? ' data-nowlinks=\'' . esc_attr( wp_json_encode($linkMap) ) . '\'' : '';

        return '<div class="nowonline-elt-wrapper" style="' . esc_attr($style) . '">'
             . '<div class="nowonline-elt-module" data-template-id="' . (int)$tid . '"' . $data_attr . '>'
             . $html
             . '</div></div>';
    }
}
