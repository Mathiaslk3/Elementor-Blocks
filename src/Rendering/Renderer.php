<?php
// Fil: src/Rendering/Renderer.php
namespace NowOnline\EltBlocks\Rendering;

use NowOnline\EltBlocks\Services\PlaceholderScanner;
use NowOnline\EltBlocks\Services\DataHelper;
use NowOnline\EltBlocks\Services\DomProcessor;

if (!defined('ABSPATH')) { exit; }

// libxml constants - stadig nødvendige for DomProcessor
if (!defined('LIBXML_HTML_NOIMPLIED')) define('LIBXML_HTML_NOIMPLIED', 0);
if (!defined('LIBXML_HTML_NODEFDTD')) define('LIBXML_HTML_NODEFDTD', 0);

/**
 * Orkestrerer server-side rendering af blokken.
 * Henter data, kalder DomProcessor til at modificere HTML,
 * og DynamicCss til at bygge stlyes.
 */
final class Renderer
{
    private PlaceholderScanner $scanner;
    private DomProcessor $domProcessor;
    private DynamicCss $dynamicCss;
    private DataHelper $dataHelper;

    public function __construct(
        PlaceholderScanner $scanner,
        DomProcessor $domProcessor,
        DynamicCss $dynamicCss,
        DataHelper $dataHelper
    ) {
        $this->scanner = $scanner;
        $this->domProcessor = $domProcessor;
        $this->dynamicCss = $dynamicCss;
        $this->dataHelper = $dataHelper;
    }

    /**
     * Registrerer *ikke* længere hooks. Det gør FrontendHooks.php.
     */
    public function register(): void
    {
        // Tom. Ansvaret er flyttet til FrontendHooks.php
    }
    
    /**
     * Normaliser Elementor "pipe" attributter.
     */
    private static function normalize_elementor_attributes(string $html): string
    {
        $html = preg_replace('/\bdata-now-(img|image|bg)\s*\|\s*([a-zA-Z0-9_\-]+)/i', 'data-now-$1="$2"', $html);
        $html = preg_replace('/\bdata-now-(gallery|galleri)\s*\|\s*([a-zA-Z0-9_\-]+)/i', 'data-now-$1="$2"', $html);
        $html = preg_replace('/\bdata-now-(video|poster)\s*\|\s*([a-zA-Z0-9_\-]+)/i', 'data-now-$1="$2"', $html);
        $html = preg_replace('/\bdata-now-key\s*\|\s*([a-zA-Z0-9_\-]+)/i', 'data-now-key="$1"', $html);
        return $html;
    }

    /**
     * Normaliser felttype baseret på definitioner.
     */
    private static function norm_type(array $defs, string $key): string
    {
        $k = strtolower($key);
        $t = isset($defs[$k]) ? strtolower((string)$defs[$k]) : '';
        if ($t) return $t;
        if (in_array($k, ['titel','undertitel','beskrivelse'], true)) return 'rich';
        if ($k === 'billede')  return 'img';
        if ($k === 'galleri')  return 'gallery';
        if ($k === 'video')    return 'video';
        if (in_array($k, ['poster','videoposter','plakat'], true)) return 'poster';
        if (preg_match('#^(url|link|href)$#i', $k)) return 'url';
        return 'text';
    }

    /**
     * Bygger et map af links (url, blank) ud fra fields.
     */
    private function build_link_map(array $defs, array $fields): array
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
            $url = esc_url($this->dataHelper::fix_url($url));
            $out[$key] = ['url' => $url, 'blank' => $blank];
        }
        return $out;
    }

    /** Opdag bg‐nøgler direkte i HTML */
    private static function discover_bg_keys_in_html(string $html): array
    {
        $keys = [];
        if (preg_match_all('/\bdata-now-bg=["\']([a-zA-Z0-9_-]+)["\']/i', $html, $m)) {
            foreach ($m[1] as $k) $keys[strtolower($k)] = true;
        }
        if (preg_match_all('/\bnow-bg-([a-z0-9_-]+)\b/i', $html, $m)) {
            foreach ($m[1] as $k) $keys[strtolower($k)] = true;
        }
        return array_keys($keys);
    }

    /** Bygger maps for billeder (img) og baggrunde (bg). */
    private function build_media_maps(array $defs, array $fields, string $html): array
    {
        $img = $bg = [];
        $bgKeysInHtml = array_flip(self::discover_bg_keys_in_html($html));

        foreach ($fields as $k => $v) {
            $key  = strtolower((string)$k);
            $type = self::norm_type($defs, $key);

            if ($type === 'text' && isset($bgKeysInHtml[$key])) {
                $type = 'bg'; // nøgle bruges som bg i templaten
            }

            if ($type !== 'img' && $type !== 'bg') continue;

            $val = is_array($v) ? (string)($v['url'] ?? '') : (string)$v;
            $url = esc_url($this->dataHelper::fix_url($val));
            if ($url === '') continue;

            if ($type === 'img') $img[$key] = $url;
            else                 $bg[$key]  = $url;
        }
        return [$img, $bg];
    }

    /** Opdag galleri-nøgler direkte i HTML */
    private static function discover_gallery_keys_in_html(string $html): array
    {
        $keys = [];
        if (preg_match_all('/\bdata-now-(?:gallery|galleri)=["\']([a-zA-Z0-9_-]+)["\']/i', $html, $m)) {
            foreach ($m[1] as $k) $keys[strtolower($k)] = true;
        }
        if (preg_match_all('/\bnow-(?:gallery|galleri)-([a-z0-9_-]+)\b/i', $html, $m)) {
            foreach ($m[1] as $k) $keys[strtolower($k)] = true;
        }
        return array_keys($keys);
    }

    /** Bygger et map af gallerier (key => [urls]) */
    private function build_gallery_map(array $defs, array $fields, string $html): array
    {
        $out = [];
        $alsoKeys = array_flip(self::discover_gallery_keys_in_html($html));
        foreach ($fields as $k => $v) {
            $key  = strtolower((string)$k);
            $isGallery = (self::norm_type($defs, $key) === 'gallery') || isset($alsoKeys[$key]);
            if (!$isGallery) continue;
            $urls = [];
            if (is_array($v)) {
                foreach ($v as $u) {
                    $uu = is_array($u) ? (string)($u['url'] ?? '') : (string)$u;
                    $uu = trim($uu);
                    if ($uu !== '') $urls[] = esc_url($this->dataHelper::fix_url($uu));
                }
            } else {
                $parts = array_map('trim', explode(',', (string)$v));
                foreach ($parts as $uu) if ($uu !== '') $urls[] = esc_url($this->dataHelper::fix_url($uu));
            }
            if ($urls) $out[$key] = $urls;
        }
        return $out;
    }

    /** Bygger maps for video og video-posters. */
    private function build_video_maps(array $defs, array $fields): array
    {
        $video = [];
        $poster = [];
        foreach ($fields as $k => $v) {
            $key = strtolower((string)$k);
            $t   = self::norm_type($defs, $key);
            if ($t === 'video') {
                $val = is_array($v) ? (string)($v['url'] ?? '') : (string)$v;
                $url = esc_url($this->dataHelper::fix_url($val));
                if ($url !== '') $video[$key] = $url;
            } elseif ($t === 'poster') {
                $val = is_array($v) ? (string)($v['url'] ?? '') : (string)$v;
                $url = esc_url($this->dataHelper::fix_url($val));
                if ($url !== '') $poster[$key] = $url;
            }
        }
        return [$video, $poster];
    }

    /** Simpel fallback til at bygge et galleri (hvis DOM-processor fejler). */
    private static function build_simple_gallery(array $urls): string
    {
        if (empty($urls)) return '';
        $items = '';
        foreach ($urls as $u) { $items .= '<img src="' . esc_url($u) . '" alt="">'; }
        return '<div class="nowonline-elt-gallery">' . $items . '</div>';
    }

    /** Simpel fallback til at bygge en video (hvis DOM-processor fejler). */
    private static function build_simple_video(string $url): string
    {
        if ($url === '') return '';
        // Bruger DomProcessor's helper
        $v = DomProcessor::to_embed_url($url);
        if ($v['kind'] === 'youtube' || $v['kind'] === 'vimeo') {
            return '<iframe src="'.esc_url($v['url']).'" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
        }
        return '<video class="elementor-video" controls><source src="'.esc_url($v['url']).'"></video>';
    }

    /**
     * Tjek om en skabelon er en "header".
     */
    private static function is_elementor_header_template(int $post_id): bool
    {
        if (!function_exists('wp_get_post_terms')) return false;
        $slugs = wp_get_post_terms($post_id, 'elementor_library_type', ['fields' => 'slugs']);
        return is_array($slugs) && in_array('header', array_map('strtolower', $slugs), true);
    }


    /**
     * Hoved-renderingsmetoden.
     */
    public function render($attrs = [], $content = ''): string
    {
        $tid    = isset($attrs['templateId']) ? (int)$attrs['templateId'] : 0;
        $fields = (isset($attrs['fields']) && is_array($attrs['fields'])) ? $attrs['fields'] : [];
        $bgColor = isset($attrs['containerBg']) ? $this->dataHelper::sanitize_color((string)$attrs['containerBg']) : '';

        // Hent dynamiske styles fra den nye service
        $styles = $this->dynamicCss->build_wrapper_styles($attrs);
        $styleAttr = $styles['style_attr'];
        $extraClass = $styles['class_attr'];
        $styleResponsiveTag = $styles['style_tag'];
        $uidAttr = $styles['uid_attr'];

        if ($tid <= 0) {
            return '<div class="nowonline-elt-empty">' . esc_html__('Vælg en Elementor-template fra blok-listen.', 'nowonline') . '</div>';
        }

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
            // Returner tom wrapper med responsive styles
            return '<div class="nowonline-elt-wrapper'.$extraClass.'"'.$styleAttr.$uidAttr.'>'
                 . $styleResponsiveTag
                 . '<div class="nowonline-elt-module" data-template-id="' . (int)$tid . '"></div></div>';
        }

        $html = self::normalize_elementor_attributes($html);
        $defs = $this->scanner->scan($tid);

        // Byg alle data-maps
        $linkMap = $this->build_link_map($defs, $fields);
        [$videoMap, $posterMap] = $this->build_video_maps($defs, $fields);
        [$imgMap, $bgMap] = $this->build_media_maps($defs, $fields, $html);
        $galMap = $this->build_gallery_map($defs, $fields, $html);

        // === Token-erstatning (Simpel str_replace) ===
        if (!empty($fields)) {
            $search  = [];
            $replace = [];
            foreach ($fields as $k => $v) {
                $key  = strtolower((string)$k);
                $type = self::norm_type($defs, $key);

                if ($type === 'img' || $type === 'bg') {
                    $val = is_array($v) ? (string)($v['url'] ?? '') : (string)$v;
                    $url = esc_url($this->dataHelper::fix_url($val));
                    foreach (['[[img:' . $key . ']]','[[bg:' . $key . ']]','[['.$key.']]'] as $tok) { $search[] = $tok; $replace[] = $url; }
                } elseif ($type === 'gallery') {
                    $urls = [];
                    if (is_array($v)) {
                        foreach ($v as $u) {
                            $uu = is_array($u) ? (string)($u['url'] ?? '') : (string)$u;
                            if (trim($uu) !== '') $urls[] = esc_url($this->dataHelper::fix_url($uu));
                        }
                    } else {
                        foreach (explode(',', (string)$v) as $u) if (trim($u) !== '') $urls[] = esc_url($this->dataHelper::fix_url($u));
                    }
                    $gallery_html = self::build_simple_gallery($urls);
                    foreach (['[[gallery:' . $key . ']]','[[galleri:' . $key . ']]','[[' . $key . ']]'] as $tok) { $search[] = $tok; $replace[] = $gallery_html; }
                
                } elseif ($type === 'video') {
                    $val = is_array($v) ? (string)($v['url'] ?? '') : (string)$v;
                    $url = esc_url($this->dataHelper::fix_url($val));
                    $vid_html = self::build_simple_video($url);
                    foreach (['[[video:' . $key . ']]','[[' . $key . ']]'] as $tok) { $search[] = $tok; $replace[] = $vid_html; }

                } elseif ($type === 'url') {
                    $url = $linkMap[$key]['url'] ?? '#';
                    $blank = $linkMap[$key]['blank'] ?? false;
                    $inject = ($url !== '#' && $blank) ? ' target="_blank" rel="noopener noreferrer"' : '';
                    $search = array_merge($search, [
                        'href="[[url:' . $key . ']]"', "href='[[url:" . $key . "]]'",
                        'href="[['.$key.']]"',         "href='[[".$key."]]'",
                        '[[url:' . $key . ']]',        '[[' . $key . ']]',
                        '[[target:' . $key . ']]',
                    ]);
                    $replace = array_merge($replace, [
                        'href="' . $url . '"' . $inject, "href='" . $url . "'" . $inject,
                        'href="' . $url . '"' . $inject, "href='" . $url . "'" . $inject,
                        $url, $url,
                        $inject,
                    ]);
                } elseif ($type === 'textarea') {
                    $txt = nl2br( esc_html( (string)$v ) );
                    foreach (['[[textarea:' . $key . ']]', '[[' . $key . ']]'] as $tok) { $search[] = $tok; $replace[] = $txt; }
                } elseif ($type === 'rich' || $type === 'wysiwyg') {
                    $inlineKeys = ['titel','title','heading','overskrift','headline','undertitel','subtitle'];
                    $inlineOnly = in_array($key, $inlineKeys, true);
                    $html_val = $this->dataHelper::sanitize_rich_html((string)$v, $inlineOnly);
                    $tokens = ['[[rich:' . $key . ']]', '[[wysiwyg:' . $key . ']]', '[[' . $key . ']]', '[[text:' . $key . ']]'];
                    foreach ($tokens as $tok) { $search[] = $tok; $replace[] = $html_val; }
                } else {
                    $val = is_array($v) ? '' : (string)$v;
                    $txt = esc_html($val);
                    foreach (['[['.$key.']]','[[text:'.$key.']]'] as $tok) {
                        $search[] = $tok; $replace[] = $txt;
                    }
                }
            }
            if ($search) $html = str_replace($search, $replace, $html);
        }

        // === DOM-baseret erstatning (via DomProcessor) ===
        
        // Links (href/target) på a[data-now-key]
        if (!empty($linkMap)) {
            $html = preg_replace_callback(
                '~<a\b([^>]*?\s)data-now-key=(["\'])([^"\']+)\2([^>]*)>~i',
                function ($m) use ($linkMap) {
                    $attrs = trim($m[1] . ' ' . $m[4]);
                    $key   = strtolower($m[3]);
                    $url   = $linkMap[$key]['url'] ?? '';
                    $blank = !empty($linkMap[$key]['blank']);
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
            $html = preg_replace_callback(
                '~<a\b([^>]*class=(["\'][^"\']*?\bnow-link-([a-z0-9_-]+)\b[^"\']*\2)[^>]*)>~i',
                function ($m) use ($linkMap) {
                    $attrs = $m[1];
                    $key   = strtolower($m[3]);
                    $url   = $linkMap[$key]['url'] ?? '';
                    $blank = !empty($linkMap[$key]['blank']);
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
            $html = preg_replace_callback(
                '~<a\b([^>]*\sid=(["\'])now-link-([a-z0-9_-]+)\2[^>]*)>~i',
                function ($m) use ($linkMap) {
                    $attrs = $m[1];
                    $key   = strtolower($m[3]);
                    $url   = $linkMap[$key]['url'] ?? '';
                    $blank = !empty($linkMap[$key]['blank']);
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

        // Kald DomProcessor for de tunge opgaver
        $html = $this->domProcessor->rewrite_button_labels_dom($html, $fields);
        $html = $this->domProcessor->rewrite_core_content_dom($html, $fields, $defs);
        $html = $this->domProcessor->rewrite_media_dom($html, $imgMap, $bgMap);
        $html = $this->domProcessor->rewrite_galleries_dom($html, $galMap);
        $html = $this->domProcessor->rewrite_videos_dom($html, $videoMap, $posterMap);

        // Håndter header-status
        $isHeader = self::is_elementor_header_template($tid);
        if ($isHeader) {
            FrontendHooks::mark_header_block_rendered();
        }

        // Anvend inline baggrundsfarve (hvis sat)
        if ($bgColor !== '') {
            $html = $this->domProcessor->apply_bg_color_inline($html, $bgColor);
        }

        // Anvend baggrundsmedie (video/billede)
        $bgOpts = [
            'img'       => $this->dataHelper::fix_url($attrs['bgImg'] ?? ''), // <--- RETTET
            'imgTablet' => $this->dataHelper::fix_url($attrs['bgImgTablet'] ?? ''), // <--- RETTET
            'imgMobile' => $this->dataHelper::fix_url($attrs['bgImgMobile'] ?? ''), // <--- RETTET
            'pos'       => $this->dataHelper::sanitize_bg_pos($attrs['bgPos'] ?? ''),
            'size'      => $this->dataHelper::sanitize_bg_size($attrs['bgSize'] ?? ''),
            'repeat'    => $this->dataHelper::sanitize_bg_repeat($attrs['bgRepeat'] ?? ''),
            'fixed'     => !empty($attrs['bgFixed']),
            'video'     => $this->dataHelper::fix_url($attrs['bgVideo'] ?? ''),
        ];
        $html = $this->domProcessor->apply_bg_media_inline($html, $bgOpts, $videoMap);

        // Byg data-attribut til links
        $data_attr = '';
        if (!empty($linkMap)) {
            $data_attr .= ' data-nowlinks=\'' . esc_attr( wp_json_encode($linkMap) ) . '\'';
        }
        if ($isHeader) $data_attr .= ' data-nowelt-is-header="1"';

        // Saml den endelige HTML
        return '<div class="nowonline-elt-wrapper'.$extraClass.'"'.$styleAttr.$uidAttr.'>'
             . $styleResponsiveTag
             . '<div class="nowonline-elt-module" data-template-id="' . (int)$tid . '"'.$data_attr.'>'
             . $html
             . '</div></div>';
    }
}