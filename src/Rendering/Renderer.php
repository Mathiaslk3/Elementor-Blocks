<?php
// File: src/Rendering/Renderer.php
namespace NowOnline\EltBlocks\Rendering;

use NowOnline\EltBlocks\Services\PlaceholderScanner;

if (!defined('ABSPATH')) { exit; }

final class Renderer
{
    private PlaceholderScanner $scanner;
    private static $hasHeaderBlock = false;

    public function __construct(PlaceholderScanner $scanner)
    {
        $this->scanner = $scanner;
    }

    public function register(): void
    {
        add_action('wp_head',   [$this, 'frontend_css']);
        add_action('wp_footer', [$this, 'inject_header_body_class']);
    }

    public function frontend_css(): void
    {
        $sel = apply_filters('nowonline_elt_header_hide_selectors', [
            'header[role="banner"]',
            '.elementor-location-header',
            '#masthead',
            '.site-header',
            'header.site-header',
            'header.header',
            '.ast-desktop-header',
            '.ast-mobile-header-wrap',
            '.oceanwp-header',
            '.main-header',
            '.header-main',
            '.gen-header',
            '#header'
        ]);
        $prefA = array_map(static fn($s) => 'body.nowelt-replace-header ' . $s, $sel);
        $prefB = array_map(static fn($s) => 'html.nowelt-replace-header ' . $s, $sel);
        $hideCss = implode(',', array_merge($prefA, $prefB)) . '{display:none!important}';

        // Vi understøtter både gamle og nye markører + farver overlayet også
        $targets =
            '.nowonline-elt-wrapper [data-now-bg],' .
            '.nowonline-elt-wrapper .now-bg,' .
            '.nowonline-elt-wrapper [data-nowonline-bg],' .
            '.nowonline-elt-wrapper .nowonline-bg';

        $overlayTargets =
            $targets . '>.elementor-background-overlay,' .
            $targets . ' .elementor-background-overlay';

        // Knap-selector (scopet til modulet for at undgå clash)
        $btnSel = '.nowonline-elt-wrapper .nowonline-elt-module [id="now-link-link"]';

        echo '<style>'
            . '.nowonline-elt-gallery{display:flex;flex-wrap:wrap;gap:8px}'
            . '.nowonline-elt-gallery img{max-width:100%;height:auto;display:block}'
            // Containeren
            . $targets . '{background:var(--now-bg-color)!important;background-color:var(--now-bg-color)!important;}'
            // Eventuelt Elementor-overlay på containeren
            . $overlayTargets . '{background:var(--now-bg-color)!important;opacity:1!important;}'
            // Knap-styling via variabler
            . $btnSel . '{'
                . 'color:var(--now-btn-color)!important;'
                . 'border-color:var(--now-btn-bdc)!important;'
                . 'border-width:var(--now-btn-bdw)!important;'
                . 'border-radius:var(--now-btn-rad)!important;'
                . 'border-style:solid!important;'
            . '}'
            . $btnSel . ':hover,' . $btnSel . ':focus{'
                . 'color:var(--now-btn-color)!important;'
                . 'border-color:var(--now-btn-bdc)!important;'
            . '}'
            . $hideCss
            . '</style>';
    }

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

    // Kun rene farver – ingen gradients
    private static function sanitize_color(string $v): string
    {
        $v = trim($v);
        if ($v === '') return '';
        $ok = preg_match(
            '/^(?:#[0-9a-fA-F]{3,8}|(?:rgb|hsl)a?\(\s*[^()]*\)|var\(\s*--[a-zA-Z0-9_-]+\s*\)|[a-zA-Z]+)$/',
            $v
        );
        return $ok ? $v : '';
    }

    // NY: sanér CSS-længder (px, rem, em, %, osv.) – rene tal får 'px'
    private static function sanitize_length(string $v, string $defaultUnit = 'px'): string
    {
        $v = trim($v);
        if ($v === '') return '';
        if (preg_match('/^0+$/', $v)) return '0';
        if (preg_match('/^[0-9]*\.?[0-9]+$/', $v)) return $v . $defaultUnit;
        if (preg_match('/^[0-9]*\.?[0-9]+(px|rem|em|%|vh|vw|ch|ex)$/i', $v)) return $v;
        return '';
    }

    private static function normalize_elementor_attributes(string $html): string
    {
        $html = preg_replace('/\bdata-now-(img|image|bg)\s*\|\s*([a-zA-Z0-9_\-]+)/i', 'data-now-$1="$2"', $html);
        $html = preg_replace('/\bdata-now-(gallery|galleri)\s*\|\s*([a-zA-Z0-9_\-]+)/i', 'data-now-$1="$2"', $html);
        $html = preg_replace('/\bdata-now-(video|poster)\s*\|\s*([a-zA-Z0-9_\-]+)/i', 'data-now-$1="$2"', $html);
        $html = preg_replace('/\bdata-now-key\s*\|\s*([a-zA-Z0-9_\-]+)/i', 'data-now-key="$1"', $html);
        return $html;
    }

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

    private static function build_media_maps(array $defs, array $fields): array
    {
        $img = $bg = [];
        foreach ($fields as $k => $v) {
            $key  = strtolower((string)$k);
            $type = self::norm_type($defs, $key);
            if ($type !== 'img' && $type !== 'bg') continue;
            $val = is_array($v) ? (string)($v['url'] ?? '') : (string)$v;
            $url = esc_url(self::fix_url($val));
            if ($url === '') continue;
            if ($type === 'img') $img[$key] = $url;
            else                 $bg[$key]  = $url;
        }
        return [$img, $bg];
    }

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

    private static function build_gallery_map(array $defs, array $fields, string $html): array
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
                    if ($uu !== '') $urls[] = esc_url(self::fix_url($uu));
                }
            } else {
                $parts = array_map('trim', explode(',', (string)$v));
                foreach ($parts as $uu) if ($uu !== '') $urls[] = esc_url(self::fix_url($uu));
            }
            if ($urls) $out[$key] = $urls;
        }
        return $out;
    }

    private static function replace_img_and_sources_in_html(string $html, string $url): string
    {
        $html = preg_replace_callback(
            '~<img\b([^>]*)>~i',
            function ($mm) use ($url) {
                $attrs = $mm[1];
                $attrs = preg_replace('/\s+(src|srcset|sizes|data-src|data-srcset|data-lazy-src|data-lazy-srcset)=((["\']).*?\3)/i', '', $attrs);
                return '<img ' . trim($attrs) . ' src="' . esc_url($url) . '">';
            },
            $html,
            1
        );

        $html = preg_replace_callback(
            '~<source\b([^>]*)>~i',
            function ($mm) use ($url) {
                $attrs = $mm[1];
                $attrs = preg_replace('/\s+(srcset|data-srcset)=((["\']).*?\3)/i', '', $attrs);
                return '<source ' . trim($attrs) . ' srcset="' . esc_url($url) . '">';
            },
            $html
        );

        return $html;
    }

    private static function rewrite_media_dom(string $html, array $imgMap, array $bgMap): string
    {
        if (empty($imgMap) && empty($bgMap)) return $html;
        if (!class_exists('\DOMDocument')) return $html;

        $doc = new \DOMDocument();
        \libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="__nowroot">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        \libxml_clear_errors();

        $xpath = new \DOMXPath($doc);
        $root  = $doc->getElementById('__nowroot');

        $wipe = function(\DOMElement $el, array $names) {
            foreach ($names as $n) if ($el->hasAttribute($n)) $el->removeAttribute($n);
        };
        $setBg = function(\DOMElement $el, string $url) {
            $style = $el->getAttribute('style');
            $style = preg_replace('/background-image\s*:\s*[^;]+;?/i', '', (string)$style);
            $style = rtrim(trim((string)$style), '; ');
            if ($style !== '') $style .= '; ';
            $style .= 'background-image:url(' . esc_url($url) . ')';
            $el->setAttribute('style', $style);
        };

        if (!empty($imgMap)) {
            foreach ($xpath->query('//*[@data-now-img or @data-now-image]') as $wrap) {
                /** @var \DOMElement $wrap */
                $key = strtolower($wrap->getAttribute('data-now-img') ?: $wrap->getAttribute('data-now-image'));
                if ($key && isset($imgMap[$key])) {
                    $url = $imgMap[$key];
                    $img = $xpath->query('.//img', $wrap)->item(0);
                    if ($img instanceof \DOMElement) {
                        $wipe($img, ['src','srcset','sizes','data-src','data-srcset','data-lazy-src','data-lazy-srcset']);
                        $img->setAttribute('src', esc_url($url));
                    }
                    foreach ($xpath->query('.//source', $wrap) as $src) {
                        /** @var \DOMElement $src */
                        $wipe($src, ['srcset','data-srcset']);
                        $src->setAttribute('srcset', esc_url($url));
                    }
                }
            }
            foreach ($xpath->query('//*[contains(@class,"now-img-") or contains(@class,"now-image-")]') as $wrap) {
                /** @var \DOMElement $wrap */
                $class = ' ' . $wrap->getAttribute('class') . ' ';
                if (preg_match('/\bnow-(?:img|image)-([a-z0-9_-]+)\b/i', $class, $m)) {
                    $key = strtolower($m[1]);
                    $url = $imgMap[$key] ?? '';
                    if ($url === '') continue;
                    $img = $xpath->query('.//img', $wrap)->item(0);
                    if ($img instanceof \DOMElement) {
                        $wipe($img, ['src','srcset','sizes','data-src','data-srcset','data-lazy-src','data-lazy-srcset']);
                        $img->setAttribute('src', esc_url($url));
                    }
                    foreach ($xpath->query('.//source', $wrap) as $src) {
                        $wipe($src, ['srcset','data-srcset']);
                        $src->setAttribute('srcset', esc_url($url));
                    }
                }
            }
        }

        if (!empty($bgMap)) {
            foreach ($xpath->query('//*[@data-now-bg]') as $el) {
                /** @var \DOMElement $el */
                $key = strtolower($el->getAttribute('data-now-bg'));
                if ($key && isset($bgMap[$key])) $setBg($el, $bgMap[$key]);
            }
            foreach ($xpath->query('//*[contains(@class,"now-bg-")]') as $el) {
                /** @var \DOMElement $el */
                $class = ' ' . $el->getAttribute('class') . ' ';
                if (preg_match('/\bnow-bg-([a-z0-9_-]+)\b/i', $class, $m)) {
                    $key = strtolower($m[1]);
                    if (isset($bgMap[$key])) $setBg($el, $bgMap[$key]);
                }
            }
        }

        $out = '';
        foreach ($root->childNodes as $child) { $out .= $doc->saveHTML($child); }
        return $out;
    }

    private static function parse_elementor_settings(\DOMElement $el): array
    {
        $raw = html_entity_decode((string)$el->getAttribute('data-settings'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $j = $raw ? json_decode($raw, true) : [];
        return is_array($j) ? $j : [];
    }

    private static function build_slide_from_prototype(\DOMDocument $doc, ?\DOMElement $proto, string $url, bool $lazyDefault): \DOMElement
    {
        if ($proto instanceof \DOMElement) {
            $slide = $proto->cloneNode(true);
            $cls = ' ' . $slide->getAttribute('class') . ' ';
            $cls = preg_replace('/\bswiper-slide-(?:duplicate|prev|next|active)\b/', '', $cls);
            $slide->setAttribute('class', trim(preg_replace('/\s+/', ' ', $cls)));
            foreach (['aria-label','aria-hidden','style','inert','data-swiper-slide-index'] as $rm) {
                if ($slide->hasAttribute($rm)) $slide->removeAttribute($rm);
            }
            $img = $slide->getElementsByTagName('img')->item(0);
            if (!($img instanceof \DOMElement)) {
                $img = $doc->createElement('img');
                $img->setAttribute('class', 'swiper-slide-image');
                $slide->appendChild($img);
            }
            $imgClass = ' ' . $img->getAttribute('class') . ' ';
            $lazy = (strpos($imgClass, ' swiper-lazy ') !== false) || $img->hasAttribute('data-src') || $lazyDefault;
            foreach (['src','srcset','sizes','data-src','data-srcset','data-lazy','data-lazy-src','data-lazy-srcset'] as $rm) {
                if ($img->hasAttribute($rm)) $img->removeAttribute($rm);
            }
            if ($lazy) {
                if (strpos($imgClass, ' swiper-lazy ') === false) {
                    $img->setAttribute('class', trim($img->getAttribute('class') . ' swiper-lazy'));
                }
                $img->setAttribute('data-src', esc_url($url));
                $img->setAttribute('src', esc_url($url));
            } else {
                $img->setAttribute('src', esc_url($url));
            }
            if (!$img->hasAttribute('alt')) $img->setAttribute('alt','');
            return $slide;
        }
        $slide  = $doc->createElement('div');
        $slide->setAttribute('class', 'swiper-slide');
        $fig    = $doc->createElement('figure');
        $fig->setAttribute('class', 'swiper-slide-inner');
        $img    = $doc->createElement('img');
        $img->setAttribute('class', $lazyDefault ? 'swiper-slide-image swiper-lazy' : 'swiper-slide-image');
        if ($lazyDefault) { $img->setAttribute('data-src', esc_url($url)); $img->setAttribute('src', esc_url($url)); }
        else { $img->setAttribute('src', esc_url($url)); }
        $img->setAttribute('alt','');
        $fig->appendChild($img);
        $slide->appendChild($fig);
        return $slide;
    }

    private static function rewrite_galleries_dom(string $html, array $galMap): string
    {
        if (empty($galMap) || !class_exists('\DOMDocument')) return $html;

        $doc = new \DOMDocument();
        \libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="__nowroot">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        \libxml_clear_errors();

        $xpath = new \DOMXPath($doc);
        $root  = $doc->getElementById('__nowroot');

        $nodes = $xpath->query('//*[@data-now-gallery or @data-now-galleri or contains(@class,"now-gallery-") or contains(@class,"now-galleri-")]');
        foreach ($nodes as $el) {
            /** @var \DOMElement $el */
            $key = strtolower(
                $el->getAttribute('data-now-gallery')
                ?: $el->getAttribute('data-now-galleri')
            );
            if ($key === '') {
                $cls = ' ' . $el->getAttribute('class') . ' ';
                if (preg_match('/\bnow-(?:gallery|galleri)-([a-z0-9_-]+)\b/i', $cls, $m)) {
                    $key = strtolower($m[1]);
                }
            }
            if ($key === '' || empty($galMap[$key])) continue;
            $urls = $galMap[$key];

            $settings    = self::parse_elementor_settings($el);
            $lazyDefault = !empty($settings['lazyload']) && ($settings['lazyload'] === 'yes' || $settings['lazyload'] === true);

            $wrapper = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " swiper-wrapper ")]', $el)->item(0);
            $proto   = null;
            if ($wrapper instanceof \DOMElement) {
                $protoList = $xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " swiper-slide ")]', $wrapper);
                if ($protoList && $protoList->length) $proto = $protoList->item(0);
            }

            if ($wrapper instanceof \DOMElement) {
                while ($wrapper->firstChild) $wrapper->removeChild($wrapper->firstChild);
                foreach ($urls as $u) {
                    $wrapper->appendChild(self::build_slide_from_prototype($doc, $proto, $u, $lazyDefault));
                }
            } else {
                while ($el->firstChild) $el->removeChild($el->firstChild);
                $grid = $doc->createElement('div');
                $grid->setAttribute('class', 'nowonline-elt-gallery');
                foreach ($urls as $u) {
                    $img = $doc->createElement('img');
                    $img->setAttribute('decoding','async');
                    $img->setAttribute('src', esc_url($u));
                    $img->setAttribute('alt','');
                    $grid->appendChild($img);
                }
                $el->appendChild($grid);
            }
        }

        $out = '';
        foreach ($root->childNodes as $child) $out .= $doc->saveHTML($child);
        return $out;
    }

    /** Sæt bg-farve inline på .nowonline-bg/[data-nowonline-bg] og på evt. overlay-barn */
    private static function apply_bg_color_inline(string $html, string $color): string
    {
        if ($color === '') return $html;

        if (class_exists('\DOMDocument')) {
            $doc = new \DOMDocument();
            \libxml_use_internal_errors(true);
            $doc->loadHTML('<?xml encoding="utf-8"?><div id="__nowroot">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            \libxml_clear_errors();

            $xpath = new \DOMXPath($doc);
            $root  = $doc->getElementById('__nowroot');

            $nodes = $xpath->query('//*[@data-nowonline-bg or contains(concat(" ", normalize-space(@class), " "), " nowonline-bg ")]');
            foreach ($nodes as $el) {
                /** @var \DOMElement $el */
                $style = (string)$el->getAttribute('style');
                // ryd eksisterende background-properties
                $style = preg_replace('/(?:^|;)\s*background(?:-color)?\s*:\s*[^;]*;?/i', ';', $style);
                $style = trim(preg_replace('/;+/', ';', $style), '; ');
                if ($style !== '') $style .= '; ';
                $style .= 'background:' . $color . ';background-color:' . $color;
                $el->setAttribute('style', $style);

                // farv overlay-div hvis den findes
                foreach ($el->getElementsByTagName('div') as $child) {
                    if (strpos(' '.$child->getAttribute('class').' ', ' elementor-background-overlay ') !== false) {
                        $ov = (string)$child->getAttribute('style');
                        $ov = preg_replace('/(?:^|;)\s*background(?:-color)?\s*:\s*[^;]*;?/i', ';', $ov);
                        $ov = trim(preg_replace('/;+/', ';', $ov), '; ');
                        if ($ov !== '') $ov .= '; ';
                        $ov .= 'background:' . $color . ';background-color:' . $color . ';opacity:1';
                        $child->setAttribute('style', $ov);
                    }
                }
            }

            $out = '';
            foreach ($root->childNodes as $child) { $out .= $doc->saveHTML($child); }
            return $out;
        }

        // Fallback uden DOM
        $c = esc_attr($color);
        $html = preg_replace(
            '~(<[a-z0-9:_-]+\b(?=[^>]*\bclass=(["\'])[^\2>]*\bnowonline-bg\b[^\2>]*\2)[^>]*)(>)~i',
            '$1 style="background:'.$c.';background-color:'.$c.'"$3',
            $html
        );
        $html = preg_replace(
            '~(<[a-z0-9:_-]+\b(?=[^>]*\bdata-nowonline-bg\b)[^>]*)(>)~i',
            '$1 style="background:'.$c.';background-color:'.$c.'"$2',
            $html
        );
        return $html;
    }

    private static function build_video_maps(array $defs, array $fields): array
    {
        $video = [];
        $poster = [];
        foreach ($fields as $k => $v) {
            $key = strtolower((string)$k);
            $t   = self::norm_type($defs, $key);
            if ($t === 'video') {
                $val = is_array($v) ? (string)($v['url'] ?? '') : (string)$v;
                $url = esc_url(self::fix_url($val));
                if ($url !== '') $video[$key] = $url;
            } elseif ($t === 'poster') {
                $val = is_array($v) ? (string)($v['url'] ?? '') : (string)$v;
                $url = esc_url(self::fix_url($val));
                if ($url !== '') $poster[$key] = $url;
            }
        }
        return [$video, $poster];
    }

    private static function is_video_file(string $u): bool
    {
        return (bool)preg_match('~\.(mp4|m4v|webm|ogv|ogg)(\?.*)?$~i', $u);
    }

    private static function to_embed_url(string $u): array
    {
        $url = trim($u);
        if ($url === '') return ['kind'=>'file','url'=>$url];
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');

        if (strpos($host,'youtube.com') !== false || strpos($host,'youtu.be') !== false) {
            if (preg_match('~youtu\.be/([^?&/]+)~i', $url, $m)) {
                return ['kind'=>'youtube', 'url'=>'https://www.youtube.com/embed/'.$m[1]];
            }
            if (preg_match('~youtube\.com/shorts/([^?&/]+)~i', $url, $m)) {
                return ['kind'=>'youtube', 'url'=>'https://www.youtube.com/embed/'.$m[1]];
            }
            if (preg_match('~[?&]v=([^?&/]+)~i', $url, $m)) {
                return ['kind'=>'youtube', 'url'=>'https://www.youtube.com/embed/'.$m[1]];
            }
            if (strpos($url,'/embed/') !== false) {
                return ['kind'=>'youtube', 'url'=>$url];
            }
        }

        if (strpos($host,'vimeo.com') !== false) {
            if (preg_match('~vimeo\.com/(?:video/)?([0-9]+)~i', $url, $m)) {
                return ['kind'=>'vimeo', 'url'=>'https://player.vimeo.com/video/'.$m[1]];
            }
            if (preg_match('~player\.vimeo\.com/video/([0-9]+)~i', $url, $m)) {
                return ['kind'=>'vimeo', 'url'=>$url];
            }
        }

        return ['kind'=> self::is_video_file($url) ? 'file' : 'file', 'url'=>$url];
    }

    private static function rewrite_videos_dom(string $html, array $videoMap, array $posterMap): string
    {
        if (empty($videoMap)) return $html;
        if (!class_exists('\DOMDocument')) return $html;

        $doc = new \DOMDocument();
        \libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="__nowroot">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        \libxml_clear_errors();

        $xpath = new \DOMXPath($doc);
        $root  = $doc->getElementById('__nowroot');

        $nodes = $xpath->query('//*[@data-now-video or contains(@class,"now-video-")]');
        foreach ($nodes as $el) {
            /** @var \DOMElement $el */
            $key = strtolower($el->getAttribute('data-now-video'));
            if ($key === '') {
                $cls = ' ' . $el->getAttribute('class') . ' ';
                if (preg_match('/\bnow-video-([a-z0-9_-]+)\b/i', $cls, $m)) $key = strtolower($m[1]);
            }
            if ($key === '' || empty($videoMap[$key])) continue;

            $vid = self::to_embed_url($videoMap[$key]);
            $poster = '';

            if ($el->hasAttribute('data-now-poster')) {
                $pKey = strtolower($el->getAttribute('data-now-poster'));
                $poster = $posterMap[$pKey] ?? '';
            } elseif (!empty($posterMap[$key])) {
                $poster = $posterMap[$key];
            }

            $iframe = $xpath->query('.//iframe', $el)->item(0);
            if ($iframe instanceof \DOMElement && ($vid['kind'] === 'youtube' || $vid['kind'] === 'vimeo')) {
                if ($iframe->hasAttribute('srcdoc')) $iframe->removeAttribute('srcdoc');
                $iframe->setAttribute('src', esc_url($vid['url']));
                continue;
            }

            $video = $xpath->query('.//video', $el)->item(0);
            if ($video instanceof \DOMElement) {
                if ($poster !== '') $video->setAttribute('poster', esc_url($poster));
                if ($video->hasAttribute('src')) $video->removeAttribute('src');
                foreach (iterator_to_array($video->getElementsByTagName('source')) as $src) {
                    $video->removeChild($src);
                }
                $source = $doc->createElement('source');
                $source->setAttribute('src', esc_url($vid['url']));
                $video->appendChild($source);
                continue;
            }

            if ($vid['kind'] === 'youtube' || $vid['kind'] === 'vimeo') {
                $new = $doc->createElement('iframe');
                $new->setAttribute('src', esc_url($vid['url']));
                $new->setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
                $new->setAttribute('allowfullscreen', 'allowfullscreen');
                $el->appendChild($new);
            } else {
                $new = $doc->createElement('video');
                $new->setAttribute('class', 'elementor-video');
                $new->setAttribute('controls', 'controls');
                if ($poster !== '') $new->setAttribute('poster', esc_url($poster));
                $src = $doc->createElement('source');
                $src->setAttribute('src', esc_url($vid['url']));
                $new->appendChild($src);
                $el->appendChild($new);
            }
        }

        $out = '';
        foreach ($root->childNodes as $child) $out .= $doc->saveHTML($child);
        return $out;
    }

    private static function build_simple_gallery(array $urls): string
    {
        if (empty($urls)) return '';
        $items = '';
        foreach ($urls as $u) { $items .= '<img src="' . esc_url($u) . '" alt="">'; }
        return '<div class="nowonline-elt-gallery">' . $items . '</div>';
    }

    private static function build_simple_video(string $url): string
    {
        if ($url === '') return '';
        $v = self::to_embed_url($url);
        if ($v['kind'] === 'youtube' || $v['kind'] === 'vimeo') {
            return '<iframe src="'.esc_url($v['url']).'" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
        }
        return '<video class="elementor-video" controls><source src="'.esc_url($v['url']).'"></video>';
    }

    private static function is_elementor_header_template(int $post_id): bool
    {
        if (!function_exists('wp_get_post_terms')) return false;
        $slugs = wp_get_post_terms($post_id, 'elementor_library_type', ['fields' => 'slugs']);
        return is_array($slugs) && in_array('header', array_map('strtolower', $slugs), true);
    }

    public function inject_header_body_class(): void
    {
        if (!self::$hasHeaderBlock) return;
        echo "<script>(function(){var d=document;d.documentElement.classList.add('nowelt-replace-header');if(d.body){d.body.classList.add('nowelt-replace-header');}})();</script>";
    }

    public function render($attrs = [], $content = ''): string
    {
        $tid    = isset($attrs['templateId']) ? (int)$attrs['templateId'] : 0;
        $gap    = isset($attrs['gap']) ? (int)$attrs['gap'] : 24;
        $fields = (isset($attrs['fields']) && is_array($attrs['fields'])) ? $attrs['fields'] : [];

        // === Design-attributter ===
        $bgColor        = isset($attrs['containerBg'])   ? self::sanitize_color((string)$attrs['containerBg'])          : '';
        $btnTextColor   = isset($attrs['btnTextColor'])  ? self::sanitize_color((string)$attrs['btnTextColor'])         : '';
        $btnBorderColor = isset($attrs['btnBorderColor'])? self::sanitize_color((string)$attrs['btnBorderColor'])       : '';
        $btnBorderWidth = isset($attrs['btnBorderWidth'])? self::sanitize_length((string)$attrs['btnBorderWidth'])      : '';
        $btnBorderRad   = isset($attrs['btnBorderRadius'])? self::sanitize_length((string)$attrs['btnBorderRadius'])    : '';

        // Saml CSS-variabler på wrapperen
        $vars = [];
        if ($bgColor        !== '') $vars['--now-bg-color']  = $bgColor;
        if ($btnTextColor   !== '') $vars['--now-btn-color'] = $btnTextColor;
        if ($btnBorderColor !== '') $vars['--now-btn-bdc']   = $btnBorderColor;
        if ($btnBorderWidth !== '') $vars['--now-btn-bdw']   = $btnBorderWidth;
        if ($btnBorderRad   !== '') $vars['--now-btn-rad']   = $btnBorderRad;

        $styleAttr = '';
        if ($vars) {
            $styleAttr = ' style="' . esc_attr(implode(' ', array_map(
                static function($k,$v){ return $k.':'.$v.';'; },
                array_keys($vars), $vars
            ))) . '"';
        }

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
            return '<div class="nowonline-elt-wrapper"'.$styleAttr.'><div class="nowonline-elt-module" data-template-id="' . (int)$tid . '"></div></div>';
        }

        $html = self::normalize_elementor_attributes($html);
        $defs = $this->scanner->scan($tid);

        if (!empty($fields)) {
            $search  = [];
            $replace = [];
            foreach ($fields as $k => $v) {
                $key  = strtolower((string)$k);
                $type = self::norm_type($defs, $key);

                if ($type === 'img' || $type === 'bg') {
                    $val = is_array($v) ? (string)($v['url'] ?? '') : (string)$v;
                    $url = esc_url(self::fix_url($val));
                    foreach (['[[img:' . $key . ']]','[[bg:' . $key . ']]','[['.$key.']]'] as $tok) { $search[] = $tok; $replace[] = $url; }
                } elseif ($type === 'gallery') {
                    $urls = [];
                    if (is_array($v)) {
                        foreach ($v as $u) {
                            $uu = is_array($u) ? (string)($u['url'] ?? '') : (string)$u;
                            $uu = trim($uu);
                            if ($uu !== '') $urls[] = esc_url(self::fix_url($uu));
                        }
                    } else {
                        $parts = array_map('trim', explode(',', (string)$v));
                        foreach ($parts as $u) if ($u !== '') $urls[] = esc_url(self::fix_url($u));
                    }
                    $gallery_html = self::build_simple_gallery($urls);
                    foreach (['[[gallery:' . $key . ']]', '[[galleri:' . $key . ']]', '[[' . $key . ']]'] as $tok) { $search[] = $tok; $replace[] = $gallery_html; }
                } elseif ($type === 'video') {
                    $val = is_array($v) ? (string)($v['url'] ?? '') : (string)$v;
                    $url = esc_url(self::fix_url($val));
                    $vid_html = self::build_simple_video($url);
                    foreach (['[[video:' . $key . ']]', '[[' . $key . ']]'] as $tok) { $search[] = $tok; $replace[] = $vid_html; }
                } elseif ($type === 'url') {
                    $url   = '';
                    $blank = false;
                    if (is_array($v)) {
                        $url   = (string)($v['url'] ?? '');
                        $blank = !empty($v['blank']) || !empty($v['newTab']) || !empty($v['opensInNewTab']) || !empty($v['is_external'])
                              || (isset($v['target']) && strtolower((string)$v['target']) === '_blank');
                    } else { $url = (string)$v; }
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
                    foreach (['[[textarea:' . $key . ']]', '[[' . $key . ']]'] as $tok) { $search[] = $tok; $replace[] = $txt; }
                } elseif ($type === 'rich' || $type === 'wysiwyg') {
                    $html_val = wp_kses_post( (string)$v );
                    foreach (['[[rich:' . $key . ']]', '[[wysiwyg:' . $key . ']]', '[[' . $key . ']]'] as $tok) { $search[] = $tok; $replace[] = $html_val; }
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

        $linkMap = self::build_link_map($defs, $fields);

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

        [$imgMap, $bgMap] = self::build_media_maps($defs, $fields);
        if (!empty($imgMap) || !empty($bgMap)) {
            if (class_exists('\DOMDocument')) {
                $html = self::rewrite_media_dom($html, $imgMap, $bgMap);
            } else {
                // regex fallback udeladt
            }
        }

        $galMap = self::build_gallery_map($defs, $fields, $html);
        if (!empty($galMap)) { $html = self::rewrite_galleries_dom($html, $galMap); }

        [$videoMap, $posterMap] = self::build_video_maps($defs, $fields);
        if (!empty($videoMap)) { $html = self::rewrite_videos_dom($html, $posterMap); }

        $isHeader = self::is_elementor_header_template($tid);
        if ($isHeader) self::$hasHeaderBlock = true;

        // Injektion af valgt baggrundsfarve
        if ($bgColor !== '') {
            $html = self::apply_bg_color_inline($html, $bgColor);
        }

        $data_attr = !empty($linkMap) ? ' data-nowlinks=\'' . esc_attr( wp_json_encode($linkMap) ) . '\'' : '';
        if ($isHeader) $data_attr .= ' data-nowelt-is-header="1"';

        return '<div class="nowonline-elt-wrapper"'.$styleAttr.'>'
             . '<div class="nowonline-elt-module" data-template-id="' . (int)$tid . '"' . $data_attr . '>'
             . $html
             . '</div></div>';
    }
}
