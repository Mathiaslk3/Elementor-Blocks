<?php
// File: src/Rendering/Renderer.php
namespace NowOnline\EltBlocks\Rendering;

use NowOnline\EltBlocks\Services\PlaceholderScanner;

if (!defined('ABSPATH')) { exit; }

// libxml constants may be missing on some hosts (PHP8 -> fatal if used undefined)
if (!defined('LIBXML_HTML_NOIMPLIED')) define('LIBXML_HTML_NOIMPLIED', 0);
if (!defined('LIBXML_HTML_NODEFDTD')) define('LIBXML_HTML_NODEFDTD', 0);

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
        // Kør CSS sent så vi vinder over Elementor/tema (og undgår minify-race)
        add_action('wp_head',   [$this, 'frontend_css'], 999);
        add_action('wp_footer', [$this, 'inject_header_body_class']);
    }

    public function frontend_css(): void
    {
        // VIGTIGT: Ingen frontend-CSS i admin (Gutenberg m.m.)
        if (is_admin()) return;

        $sel = apply_filters('nowonline_elt_header_hide_selectors', [
            'header[role="banner"]','.elementor-location-header','#masthead','.site-header',
            'header.site-header','header.header','.ast-desktop-header','.ast-mobile-header-wrap',
            '.oceanwp-header','.main-header','.header-main','.gen-header','#header'
        ]);
        $prefA = array_map(static fn($s) => 'body.nowelt-replace-header ' . $s, $sel);
        $prefB = array_map(static fn($s) => 'html.nowelt-replace-header ' . $s, $sel);
        $hideCss = implode(',', array_merge($prefA, $prefB)) . '{display:none!important}';

        $targets = '.nowonline-elt-wrapper [data-now-bg],.nowonline-elt-wrapper .now-bg,'
                 . '.nowonline-elt-wrapper [data-nowonline-bg],.nowonline-elt-wrapper .nowonline-bg';
        $overlayTargets = $targets . '>.elementor-background-overlay,' . $targets . ' .elementor-background-overlay';

        // Kun hvis wrapperen faktisk har mindst én --now-btn-* variabel sat
        $btnScope = '.nowonline-elt-wrapper[style*="--now-btn-"] .nowonline-elt-module';

        // Begræns til <a> (Elementor-knapper er typisk <a>) + dine now-link-varianter
        $btnSel = $btnScope . ' a[data-now-key],'
                . $btnScope . ' a[class*="now-link-"],'
                . $btnScope . ' a[id^="now-link-"],'
                . $btnScope . ' a.elementor-button,'
                . $btnScope . ' a.elementor-button-link';

        // === Desktop font-size mapping – AKTIVERES KUN pr. level via wrapper-klasser ===
        $mk = static function(string $level): string {
            $sel = '.nowonline-elt-wrapper.nowelt-fs-'.$level.' .nowonline-elt-module ';
            return $sel.$level.','.
                   $sel.'.elementor-widget-heading '.$level.'.elementor-heading-title' .
                   '{font-size:var(--now-fs-'.$level.')!important;}';
        };

        $fsCss =
              $mk('h1')
            . $mk('h2')
            . $mk('h3')
            . $mk('h4')
            . $mk('h5')
            . $mk('h6')
            . '.nowonline-elt-wrapper.nowelt-fs-body .nowonline-elt-module p,'
            . '.nowonline-elt-wrapper.nowelt-fs-body .nowonline-elt-module .elementor-widget-text-editor,'
            . '.nowonline-elt-wrapper.nowelt-fs-body .nowonline-elt-module .elementor-widget-text-editor p'
            . '{font-size:var(--now-fs-body)!important;}'
            . '.nowonline-elt-wrapper.nowelt-fs-btn .nowonline-elt-module a.elementor-button,'
            . '.nowonline-elt-wrapper.nowelt-fs-btn .nowonline-elt-module .elementor-button'
            . '{font-size:var(--now-fs-btn)!important;}';

        // Neutraliser INLINE font-size/line-height i overskrifter KUN hvis wrapper har heading-fs:
        $killBase = '.nowonline-elt-wrapper.nowelt-fs-headings .nowonline-elt-module';
        $killInlineSel = implode(',', [
            $killBase.' .elementor-heading-title[style*="font-size"]',
            $killBase.' .elementor-heading-title [style*="font-size"]',
            $killBase.' h1[style*="font-size"]', $killBase.' h1 [style*="font-size"]',
            $killBase.' h2[style*="font-size"]', $killBase.' h2 [style*="font-size"]',
            $killBase.' h3[style*="font-size"]', $killBase.' h3 [style*="font-size"]',
            $killBase.' h4[style*="font-size"]', $killBase.' h4 [style*="font-size"]',
            $killBase.' h5[style*="font-size"]', $killBase.' h5 [style*="font-size"]',
            $killBase.' h6[style*="font-size"]', $killBase.' h6 [style*="font-size"]'
        ]);
        $killInlineCss = $killInlineSel.'{font-size:inherit!important;line-height:inherit!important;}';

        // Byg CSS
        $css  = '';
        $css .= '.nowonline-elt-gallery{display:flex;flex-wrap:wrap;gap:8px}';
        $css .= '.nowonline-elt-gallery img{max-width:100%;height:auto;display:block}';
        // Kun background-color (ellers nulstilles background-image)
        $css .= $targets.'{background-color:var(--now-bg-color)!important;}';
        $css .= $overlayTargets.'{background-color:var(--now-bg-color)!important;}';

        // Knap-variabler – ingen tvungen border-style (template arver som default)
        $css .= $btnSel.'{'
              . 'color:var(--now-btn-color)!important;'
              . 'border-color:var(--now-btn-bdc)!important;'
              . 'border-width:var(--now-btn-bdw)!important;'
              . 'border-radius:var(--now-btn-rad)!important;'
              . '}';
        $css .= $btnSel.':hover,'.$btnSel.':focus{'
              . 'color:var(--now-btn-color)!important;'
              . 'border-color:var(--now-btn-bdc)!important;'
              . '}';

        // Var-mapping KUN på desktop (≥1025px) og kun for wrappers med de relevante klasser
        $css .= '@media (min-width:1025px){'.$fsCss.'}';

        // Nulstil inline font-size/line-height for headings (scopet til wrappers med heading-fs)
        $css .= $killInlineCss;

        $css .= '.nowonline-elt-wrapper .nowelt-has-bgvid{position:relative;overflow:hidden;}';
        $css .= '.nowonline-elt-wrapper .nowelt-bg-video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:0;pointer-events:none;}';

        // Skjul header selectors
        $css .= $hideCss;

        echo '<style>'.$css.'</style>';
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

        // undgå mixed content når site går på SSL
        if (is_ssl() && stripos($u, 'http://') === 0) {
            $homeHost = parse_url(home_url(), PHP_URL_HOST);
            $urlHost  = parse_url($u, PHP_URL_HOST);
            if (!$urlHost || !$homeHost || strcasecmp((string)$urlHost, (string)$homeHost) === 0) {
                $u = preg_replace('#^http://#i', 'https://', $u);
            }
        }

        return $u;
    }

    private static function sanitize_color(string $v): string
    {
        $v = trim($v);
        if ($v === '') return '';
        $ok = preg_match('/^(?:#[0-9a-fA-F]{3,8}|(?:rgb|hsl)a?\(\s*[^()]*\)|var\(\s*--[a-zA-Z0-9_-]+\s*\)|[a-zA-Z]+)$/', $v);
        return $ok ? $v : '';
    }

    private static function sanitize_length(string $v, string $defaultUnit = 'px'): string
    {
        $v = trim($v);
        if ($v === '') return '';
        if (preg_match('/^0+$/', $v)) return '0';
        if (preg_match('/^[0-9]*\.?[0-9]+$/', $v)) return $v . $defaultUnit;
        if (preg_match('/^[0-9]*\.?[0-9]+(px|rem|em|%|vh|vw|ch|ex)$/i', $v)) return $v;
        return '';
    }

    private static function sanitize_spacing(?string $v): string
    {
        $v = trim((string)$v);
        if ($v === '' || strcasecmp($v,'ingen')===0 || strcasecmp($v,'standard')===0) return '';
        return self::sanitize_length($v);
    }

    private static function sanitize_bg_pos(string $v): string
    {
        $v = strtolower(trim($v));
        if ($v === '') return '';
        $allowed = ['center center','top center','bottom center','center left','center right','top left','top right','bottom left','bottom right','center','top','bottom','left','right'];
        if (in_array($v, $allowed, true)) return $v;
        if (preg_match('#^[0-9]+%(\s+[0-9]+%)$#', $v)) return $v;
        return '';
    }

    private static function sanitize_bg_size(string $v): string
    {
        $v = strtolower(trim($v));
        if ($v === '') return '';
        if (in_array($v, ['cover','contain','auto'], true)) return $v;
        if (preg_match('#^[0-9]*\.?[0-9]+(px|%|rem|em|vh|vw)(\s+[0-9]*\.?[0-9]+(px|%|rem|em|vh|vw))?$#i', $v)) return $v;
        return '';
    }

    /**
     * Server-side sanitizer til rich HTML.
     * inlineOnly=true => fjerner blok-tags, style-attributter og spans,
     * så teksten arver styling fra templaten (Elementor).
     */
    private static function sanitize_rich_html(string $html, bool $inlineOnly = false): string
    {
        $html = (string)$html;
        if ($html === '') return '';

        // Fjern class-attributter (bl.a. Elementor-klasser) før wp_kses
        $html = preg_replace('/\sclass=("|\').*?\1/i', '', $html);

        if ($inlineOnly) {
            $html = preg_replace('#</?(?:p|div|h[1-6]|section|article|header|footer|blockquote|ul|ol|li)[^>]*>#i', '', $html);
            $html = preg_replace('/\sstyle=("|\').*?\1/i', '', $html);
            $html = preg_replace('#</?span[^>]*>#i', '', $html);

            $allowed = [
                'a'      => ['href' => true, 'target' => true, 'rel' => true],
                'strong' => [], 'em' => [], 'b' => [], 'i' => [], 'u' => [],
                'br'     => [], 'code' => [], 'sup' => [], 'sub' => [],
            ];
            return wp_kses($html, $allowed);
        }

        return wp_kses_post($html);
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

    /** NY: Opdag bg‐nøgler direkte i HTML (så data-now-bg|hero virker uden scanner‐defs) */
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

    /** Ændret: modtager $html og tvinger type=bg for nøgler brugt i HTML */
    private static function build_media_maps(array $defs, array $fields, string $html): array
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

    private static function safeDom(string $html, callable $fn): string
    {
        if (!class_exists('\DOMDocument')) return $html;
        try {
            $doc = new \DOMDocument();
            \libxml_use_internal_errors(true);
            $doc->loadHTML('<?xml encoding="utf-8"?><div id="__nowroot">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            \libxml_clear_errors();
            $xpath = new \DOMXPath($doc);
            $root  = $doc->getElementById('__nowroot');
            $fn($doc, $xpath, $root);
            $out = '';
            foreach ($root->childNodes as $child) { $out .= $doc->saveHTML($child); }
            return $out;
        } catch (\Throwable $e) {
            if (function_exists('error_log')) error_log('[NowOnline EltBlocks] DOM fail: '.$e->getMessage());
            return $html;
        }
    }

    private static function rewrite_media_dom(string $html, array $imgMap, array $bgMap): string
    {
        if (empty($imgMap) && empty($bgMap)) return $html;
        return self::safeDom($html, function(\DOMDocument $doc, \DOMXPath $xpath, \DOMElement $root) use ($imgMap, $bgMap) {
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
        });
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
        if (empty($galMap)) return $html;
        return self::safeDom($html, function(\DOMDocument $doc, \DOMXPath $xpath, \DOMElement $root) use ($galMap) {
            $nodes = $xpath->query('//*[@data-now-gallery or @data-now-galleri or contains(@class,"now-gallery-") or contains(@class,"now-galleri-")]');
            foreach ($nodes as $el) {
                /** @var \DOMElement $el */
                $key = strtolower($el->getAttribute('data-now-gallery') ?: $el->getAttribute('data-now-galleri'));
                if ($key === '') {
                    $cls = ' ' . $el->getAttribute('class') . ' ';
                    if (preg_match('/\bnow-(?:gallery|galleri)-([a-z0-9_-]+)\b/i', $cls, $m)) $key = strtolower($m[1]);
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
        });
    }

    /** Sæt kun background-color inline (bevar background-image) */
    private static function apply_bg_color_inline(string $html, string $color): string
    {
        if ($color === '') return $html;
        return self::safeDom($html, function(\DOMDocument $doc, \DOMXPath $xpath, \DOMElement $root) use ($color) {
            $nodes = $xpath->query('//*[@data-nowonline-bg or contains(concat(" ", normalize-space(@class), " "), " nowonline-bg ")]');
            foreach ($nodes as $el) {
                /** @var \DOMElement $el */
                $style = (string)$el->getAttribute('style');
                $style = preg_replace('/(?:^|;)\s*background(?:-color)?\s*:\s*[^;]*;?/i', ';', $style);
                $style = trim(preg_replace('/;+/', ';', $style), '; ');
                if ($style !== '') $style .= '; ';
                $style .= 'background-color:' . $color;
                $el->setAttribute('style', $style);

                foreach ($el->getElementsByTagName('div') as $child) {
                    if (strpos(' '.$child->getAttribute('class').' ', ' elementor-background-overlay ') !== false) {
                        $ov = (string)$child->getAttribute('style');
                        $ov = preg_replace('/(?:^|;)\s*background(?:-color)?\s*:\s*[^;]*;?/i', ';', $ov);
                        $ov = trim(preg_replace('/;+/', ';', $ov), '; ');
                        if ($ov !== '') $ov .= '; ';
                        $ov .= 'background-color:' . $color . ';';
                        $child->setAttribute('style', $ov);
                    }
                }
            }
        });
    }

    /** Find container til baggrundsmedie */
    private static function discover_bg_target_node(\DOMXPath $xpath): ?\DOMElement
    {
        $q = [
            '//*[@data-nowonline-bg]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " nowonline-bg ")]',
            '//*[@data-now-bg]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " now-bg ")]',
            '//*[@data-now-video]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " now-video- ")]',
            '//*[@data-element_type="container" and (contains(@data-settings, "\"background_background\":\"video\"") or @bgvideo)]',
        ];
        foreach ($q as $expr) {
            $n = $xpath->query($expr)->item(0);
            if ($n instanceof \DOMElement) return $n;
        }
               return null;
    }

    private static function is_video_file(string $u): bool
    {
        return (bool)preg_match('~\.(mp4|m4v|webm|ogv|ogg)(\?.*)?$~i', $u);
    }

    /** Billede/Video baggrund – robust og failsafe */
    private static function apply_bg_media_inline(string $html, array $opts, array $videoMap = []): string
    {
        $img        = isset($opts['img']) ? (string)$opts['img'] : '';
        $imgTablet  = isset($opts['imgTablet']) ? (string)$opts['imgTablet'] : '';
        $imgMobile  = isset($opts['imgMobile']) ? (string)$opts['imgMobile'] : '';
        $pos        = isset($opts['pos']) ? (string)$opts['pos'] : '';
        $size       = isset($opts['size']) ? (string)$opts['size'] : '';
        $fixed      = !empty($opts['fixed']);
        $video      = isset($opts['video']) ? (string)$opts['video'] : '';

        if ($img === '' && $imgTablet === '' && $imgMobile === '' && $video === '') return $html;

        return self::safeDom($html, function(\DOMDocument $doc, \DOMXPath $xpath, \DOMElement $root) use ($img,$imgTablet,$imgMobile,$pos,$size,$fixed,$video,$videoMap) {
            $node = self::discover_bg_target_node($xpath);
            if (!($node instanceof \DOMElement)) return;

            // Video via attrs eller data-now-video
            $vidUrl = $video;
            if ($vidUrl === '') {
                $key = '';
                if ($node->hasAttribute('data-now-video')) {
                    $key = strtolower($node->getAttribute('data-now-video'));
                } else {
                    $cls = ' ' . $node->getAttribute('class') . ' ';
                    if (preg_match('/\bnow-video-([a-z0-9_-]+)\b/i', $cls, $m)) $key = strtolower($m[1]);
                }
                if ($key !== '' && !empty($videoMap[$key]) && self::is_video_file($videoMap[$key])) {
                    $vidUrl = (string)$videoMap[$key];
                }
            }

            if ($vidUrl !== '' && self::is_video_file($vidUrl)) {
                $cls = ' ' . $node->getAttribute('class') . ' ';
                if (strpos($cls, ' nowelt-has-bgvid ') === false) {
                    $node->setAttribute('class', trim($node->getAttribute('class') . ' nowelt-has-bgvid'));
                }
                foreach (iterator_to_array($node->getElementsByTagName('video')) as $v) {
                    if (strpos(' ' . $v->getAttribute('class') . ' ', ' nowelt-bg-video ') !== false) {
                        $node->removeChild($v);
                    }
                }
                $vid = $doc->createElement('video');
                $vid->setAttribute('class', 'nowelt-bg-video');
                $vid->setAttribute('autoplay', 'autoplay');
                $vid->setAttribute('muted', 'muted');
                $vid->setAttribute('loop', 'loop');
                $vid->setAttribute('playsinline', 'playsinline');
                $vid->setAttribute('preload', 'auto');
                $vid->setAttribute('data-no-lazy', '1');
                $vid->setAttribute('data-skip-lazy', '1');
                $vid->setAttribute('data-rocket-lazyload', 'ignore');

                $source = $doc->createElement('source');
                $source->setAttribute('src', esc_url($vidUrl));
                $vid->appendChild($source);

                if ($node->firstChild) $node->insertBefore($vid, $node->firstChild);
                else $node->appendChild($vid);

                $style = (string)$node->getAttribute('style');
                $style = preg_replace('/(?:^|;)\s*background-(position|size|attachment)\s*:\s*[^;]*;?/i', ';', $style);
                $style = trim(preg_replace('/;+/', ';', $style), '; ');
                if ($style !== '') $style .= '; ';
                if ($pos  !== '') $style .= 'background-position:' . $pos . ';';
                if ($size !== '') $style .= 'background-size:' . $size . ';';
                $style .= 'background-attachment:' . ($fixed ? 'fixed' : 'scroll') . ';';
                $node->setAttribute('style', $style);
                return;
            }

            // fallback: billede
            $style = (string)$node->getAttribute('style');
            $style = preg_replace('/(?:^|;)\s*background-(image|position|size|attachment)\s*:\s*[^;]*;?/i', ';', $style);
            $style = trim(preg_replace('/;+/', ';', $style), '; ');
            if ($style !== '') $style .= '; ';
            if ($img  !== '') $style .= 'background-image:url(' . esc_url($img) . ');';
            if ($pos  !== '') $style .= 'background-position:' . $pos . ';';
            if ($size !== '') $style .= 'background-size:' . $size . ';';
            if ($fixed)       $style .= 'background-attachment:fixed;';
            $node->setAttribute('style', $style);

            $uid = 'nowbg-' . uniqid();
            $node->setAttribute('data-nowbg-id', $uid);

            $css = '';
            if ($imgTablet !== '') $css .= '@media (max-width:1024px){[data-nowbg-id="'.$uid.'"]{background-image:url(' . esc_url($imgTablet) . ')}}';
            if ($imgMobile !== '') $css .= '@media (max-width:767px){[data-nowbg-id="'.$uid.'"]{background-image:url(' . esc_url($imgMobile) . ')}}';
            if ($css !== '') {
                $styleTag = $doc->createElement('style');
                $styleTag->appendChild($doc->createTextNode($css));
                $root->appendChild($styleTag);
            }
        });
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

    private static function to_embed_url(string $u): array
    {
        $url = trim($u);
        if ($url === '') return ['kind'=>'file','url'=>$url];
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');

        if (strpos($host,'youtube.com') !== false || strpos($host,'youtu.be') !== false) {
            if (preg_match('~youtu\.be/([^?&/]+)~i', $url, $m)) return ['kind'=>'youtube', 'url'=>'https://www.youtube.com/embed/'.$m[1]];
            if (preg_match('~youtube\.com/shorts/([^?&/]+)~i', $url, $m)) return ['kind'=>'youtube', 'url'=>'https://www.youtube.com/embed/'.$m[1]];
            if (preg_match('~[?&]v=([^?&/]+)~i', $url, $m)) return ['kind'=>'youtube', 'url'=>'https://www.youtube.com/embed/'.$m[1]];
            if (strpos($url,'/embed/') !== false) return ['kind'=>'youtube', 'url'=>$url];
        }

        if (strpos($host,'vimeo.com') !== false) {
            if (preg_match('~vimeo\.com/(?:video/)?([0-9]+)~i', $url, $m)) return ['kind'=>'vimeo', 'url'=>'https://player.vimeo.com/video/'.$m[1]];
            if (preg_match('~player\.vimeo\.com/video/([0-9]+)~i', $url, $m)) return ['kind'=>'vimeo', 'url'=>$url];
        }

        return ['kind'=> self::is_video_file($url) ? 'file' : 'file', 'url'=>$url];
    }

    private static function rewrite_videos_dom(string $html, array $videoMap, array $posterMap): string
    {
        if (empty($videoMap)) return $html;
        return self::safeDom($html, function(\DOMDocument $doc, \DOMXPath $xpath, \DOMElement $root) use ($videoMap, $posterMap) {
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
        });
    }

    /** Opdater knap-labels (ID, data-now-label, now-label-*, data-now-key) uden at bryde Elementor markup */
    private static function rewrite_button_labels_dom(string $html, array $fields): string
    {
        $map = [];
        foreach ($fields as $k => $v) { $map[strtolower((string)$k)] = $v; }

        $getLabel = function(string $key) use ($map): string {
            $key = strtolower($key);
            $cands = [
                $key, $key.'label', $key.'_label',
                $key.'text', $key.'_text',
                $key.'title', $key.'_title',
                'label_'.$key, 'text_'.$key, 'title_'.$key,
            ];
            foreach ($cands as $ck) {
                if (!array_key_exists($ck, $map)) continue;
                $val = $map[$ck];
                $txt = '';
                if (is_array($val)) {
                    $txt = (string)($val['title'] ?? $val['text'] ?? $val['label'] ?? '');
                } else {
                    $txt = (string)$val;
                }
                $txt = trim(wp_strip_all_tags($txt));
                if ($txt !== '') return $txt;
            }

            if (array_key_exists($key, $map) && is_array($map[$key])) {
                $arr = $map[$key];
                $txt = (string)($arr['title'] ?? $arr['text'] ?? $arr['label'] ?? '');
                $txt = trim(wp_strip_all_tags($txt));
                if ($txt !== '') return $txt;
            }
            return '';
        };

        return self::safeDom($html, function(\DOMDocument $doc, \DOMXPath $xpath, \DOMElement $root) use ($getLabel) {
            $nodes = $xpath->query('//a['
                . '@data-now-label or '
                . 'contains(concat(" ", normalize-space(@class), " "), " now-label- ") or '
                . '@data-now-key or '
                . 'starts-with(@id,"now-link-")'
                . ']');

            if (!$nodes) return;

            foreach ($nodes as $a) {
                /** @var \DOMElement $a */
                $labelKey = strtolower($a->getAttribute('data-now-label'));

                if ($labelKey === '') {
                    $cls = ' ' . $a->getAttribute('class') . ' ';
                    if (preg_match('/\bnow-label-([a-z0-9_-]+)\b/i', $cls, $m)) {
                        $labelKey = strtolower($m[1]);
                    }
                }
                if ($labelKey === '' && $a->hasAttribute('data-now-key')) {
                    $labelKey = strtolower($a->getAttribute('data-now-key'));
                }
                if ($labelKey === '' && $a->hasAttribute('id')) {
                    if (preg_match('/^now-link-([a-z0-9_-]+)$/i', $a->getAttribute('id'), $m)) {
                        $labelKey = strtolower($m[1]);
                    }
                }
                if ($labelKey === '') continue;

                $labelText = $getLabel($labelKey);
                if ($labelText === '') continue;

                $txtNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " elementor-button-text ")]', $a)->item(0);
                if ($txtNode instanceof \DOMElement) {
                    while ($txtNode->firstChild) $txtNode->removeChild($txtNode->firstChild);
                    $txtNode->appendChild($doc->createTextNode($labelText));
                    continue;
                }

                $leftIcons = []; $rightIcons = [];
                $iconNodes = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " elementor-button-icon ")]', $a);
                if ($iconNodes) {
                    foreach ($iconNodes as $ic) {
                        /** @var \DOMElement $ic */
                        $clone = $ic->cloneNode(true);
                        if (strpos(' '.$ic->getAttribute('class').' ', ' elementor-align-icon-right ') !== false) $rightIcons[] = $clone;
                        else $leftIcons[] = $clone;
                    }
                }

                while ($a->firstChild) $a->removeChild($a->firstChild);

                $wrap = $doc->createElement('span');
                $wrap->setAttribute('class', 'elementor-button-content-wrapper');
                foreach ($leftIcons as $ic) $wrap->appendChild($ic);

                $span = $doc->createElement('span');
                $span->setAttribute('class', 'elementor-button-text');
                $span->appendChild($doc->createTextNode($labelText));
                $wrap->appendChild($span);

                foreach ($rightIcons as $ic) $wrap->appendChild($ic);
                $a->appendChild($wrap);
            }
        });
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
        // VIGTIGT: Ingen DOM-klasse-scripts i admin
        if (is_admin()) return;

        if (!self::$hasHeaderBlock) return;
        echo "<script>(function(){var d=document;d.documentElement.classList.add('nowelt-replace-header');if(d.body){d.body.classList.add('nowelt-replace-header');}})();</script>";
    }

    /** Hjælper: find første ikke-tomme tekstværdi ud fra en liste af keys */
    private static function first_text_from_fields(array $fields, array $keys, bool $inlineOnly): string
    {
        foreach ($keys as $k) {
            if (!array_key_exists($k, $fields)) continue;
            $val = $fields[$k];
            $raw = '';
            if (is_array($val)) {
                $raw = (string)($val['title'] ?? $val['text'] ?? $val['label'] ?? '');
            } else {
                $raw = (string)$val;
            }
            $san = self::sanitize_rich_html($raw, $inlineOnly);
            $san = trim(wp_strip_all_tags($san));
            if ($san !== '') return $san;
        }
        return '';
    }

    /** Hjælper: find første ikke-tomme RICH HTML (bevar markup) ud fra keys */
    private static function first_html_from_fields(array $fields, array $keys): string
    {
        foreach ($keys as $k) {
            if (!array_key_exists($k, $fields)) continue;
            $val = $fields[$k];
            $raw = is_array($val) ? (string)($val['html'] ?? $val['content'] ?? $val['text'] ?? $val['title'] ?? '') : (string)$val;
            $san = self::sanitize_rich_html($raw, false);
            $txt = trim(wp_strip_all_tags($san));
            if ($txt !== '') return $san;
        }
        return '';
    }

    /** Skriv titel/undertitel/beskrivelse ind i Elementor-widgets, selv uden [[placeholder]] */
    private static function rewrite_core_content_dom(string $html, array $fields, array $defs): string
    {
        if (empty($fields)) return $html;

        // Nøglekandidater (lowercase)
        $titleKeys     = ['titel','title','heading','overskrift','headline'];
        $subtitleKeys  = ['undertitel','subtitle','tagline'];
        $descKeys      = ['beskrivelse','description','tekst','text','content','indhold'];

        // Værdier
        $titleText    = self::first_text_from_fields($fields, $titleKeys, true);
        $subtitleText = self::first_text_from_fields($fields, $subtitleKeys, true);
        $descHtml     = self::first_html_from_fields($fields, $descKeys);

        if ($titleText === '' && $subtitleText === '' && $descHtml === '') return $html;

        return self::safeDom($html, function(\DOMDocument $doc, \DOMXPath $xpath, \DOMElement $root) use ($titleText, $subtitleText, $descHtml) {
            // Headings (Elementor)
            if ($titleText !== '') {
                $h1 = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " elementor-heading-title ")]')->item(0);
                if ($h1 instanceof \DOMElement) {
                    while ($h1->firstChild) $h1->removeChild($h1->firstChild);
                    $h1->appendChild($doc->createTextNode($titleText));
                }
            }
            if ($subtitleText !== '') {
                $nodes = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " elementor-heading-title ")]');
                if ($nodes && $nodes->length >= 2) {
                    $h2 = $nodes->item(1);
                    if ($h2 instanceof \DOMElement) {
                        while ($h2->firstChild) $h2->removeChild($h2->firstChild);
                        $h2->appendChild($doc->createTextNode($subtitleText));
                    }
                }
            }

            // Tekst-editor (Elementor)
            if ($descHtml !== '') {
                $cont = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " elementor-widget-text-editor ")]//*[contains(concat(" ", normalize-space(@class), " "), " elementor-widget-container ")]')->item(0);
                if ($cont instanceof \DOMElement) {
                    while ($cont->firstChild) $cont->removeChild($cont->firstChild);
                    // Indsæt HTML-fragment
                    $frag = $doc->createDocumentFragment();
                    if (@$frag->appendXML('<div>'.$descHtml.'</div>')) {
                        $tmp = $frag->firstChild;
                        if ($tmp) {
                            while ($tmp->firstChild) { $cont->appendChild($tmp->firstChild); }
                        }
                    } else {
                        // fallback: som tekst
                        $cont->appendChild($doc->createTextNode(wp_strip_all_tags($descHtml)));
                    }
                }
            }
        });
    }

    public function render($attrs = [], $content = ''): string
    {
        $tid    = isset($attrs['templateId']) ? (int)$attrs['templateId'] : 0;
        $gap    = isset($attrs['gap']) ? (int)$attrs['gap'] : 24;
        $fields = (isset($attrs['fields']) && is_array($attrs['fields'])) ? $attrs['fields'] : [];

        $bgColor        = isset($attrs['containerBg'])   ? self::sanitize_color((string)$attrs['containerBg'])          : '';
        $btnTextColor   = isset($attrs['btnTextColor'])  ? self::sanitize_color((string)$attrs['btnTextColor'])         : '';
        $btnBorderColor = isset($attrs['btnBorderColor'])? self::sanitize_color((string)$attrs['btnBorderColor'])       : '';
        $btnBorderWidth = isset($attrs['btnBorderWidth'])? self::sanitize_length((string)$attrs['btnBorderWidth'])      : '';
        $btnBorderRad   = isset($attrs['btnBorderRadius'])? self::sanitize_length((string)$attrs['btnBorderRadius'])    : '';

        // Desktop-only font size attributes
        $fsH1  = self::sanitize_length($attrs['fsH1']  ?? '');
        $fsH2  = self::sanitize_length($attrs['fsH2']  ?? '');
        $fsH3  = self::sanitize_length($attrs['fsH3']  ?? '');
        $fsH4  = self::sanitize_length($attrs['fsH4']  ?? '');
        $fsH5  = self::sanitize_length($attrs['fsH5']  ?? '');
        $fsH6  = self::sanitize_length($attrs['fsH6']  ?? '');
        $fsBody= self::sanitize_length($attrs['fsBody']?? '');
        $fsBtn = self::sanitize_length($attrs['fsBtn'] ?? '');

        $bgVideo   = isset($attrs['bgVideo'])     ? esc_url(self::fix_url((string)$attrs['bgVideo']))           : '';
        $bgImg     = isset($attrs['bgImg'])       ? esc_url(self::fix_url((string)$attrs['bgImg']))             : '';
        $bgImgTab  = isset($attrs['bgImgTablet']) ? esc_url(self::fix_url((string)$attrs['bgImgTablet']))       : '';
        $bgImgMob  = isset($attrs['bgImgMobile']) ? esc_url(self::fix_url((string)$attrs['bgImgMobile']))       : '';
        $bgPos     = isset($attrs['bgPos'])       ? self::sanitize_bg_pos((string)$attrs['bgPos'])              : '';
        $bgSize    = isset($attrs['bgSize'])      ? self::sanitize_bg_size((string)$attrs['bgSize'])            : '';
        $bgFixed   = !empty($attrs['bgFixed']);

        // Responsive visibility + spacing
        $hideDesktop = !empty($attrs['hideDesktop']);
        $hideTablet  = !empty($attrs['hideTablet']);
        $hideMobile  = !empty($attrs['hideMobile']);

        $padTopDesktop    = self::sanitize_spacing($attrs['padTopDesktop']    ?? '');
        $padBottomDesktop = self::sanitize_spacing($attrs['padBottomDesktop'] ?? '');
        $padTopLaptop     = self::sanitize_spacing($attrs['padTopLaptop']     ?? '');
        $padBottomLaptop  = self::sanitize_spacing($attrs['padBottomLaptop']  ?? '');
        $padTopTablet     = self::sanitize_spacing($attrs['padTopTablet']     ?? '');
        $padBottomTablet  = self::sanitize_spacing($attrs['padBottomTablet']  ?? '');
        $padTopMobile     = self::sanitize_spacing($attrs['padTopMobile']     ?? '');
        $padBottomMobile  = self::sanitize_spacing($attrs['padBottomMobile']  ?? '');

        // === Custom properties på wrapper (baggrund/knap)
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

        // === Desktop FS-variabler + wrapper-klasser pr. level
        $desktopVars = '';
        $fsClasses   = [];
        $hasHeadingFs = false;

        if ($fsH1  !== '') { $desktopVars .= '--now-fs-h1:'.$fsH1.';'; $fsClasses[]='nowelt-fs-h1'; $hasHeadingFs = true; }
        if ($fsH2  !== '') { $desktopVars .= '--now-fs-h2:'.$fsH2.';'; $fsClasses[]='nowelt-fs-h2'; $hasHeadingFs = true; }
        if ($fsH3  !== '') { $desktopVars .= '--now-fs-h3:'.$fsH3.';'; $fsClasses[]='nowelt-fs-h3'; $hasHeadingFs = true; }
        if ($fsH4  !== '') { $desktopVars .= '--now-fs-h4:'.$fsH4.';'; $fsClasses[]='nowelt-fs-h4'; $hasHeadingFs = true; }
        if ($fsH5  !== '') { $desktopVars .= '--now-fs-h5:'.$fsH5.';'; $fsClasses[]='nowelt-fs-h5'; $hasHeadingFs = true; }
        if ($fsH6  !== '') { $desktopVars .= '--now-fs-h6:'.$fsH6.';'; $fsClasses[]='nowelt-fs-h6'; $hasHeadingFs = true; }
        if ($fsBody!== '') { $desktopVars .= '--now-fs-body:'.$fsBody.';'; $fsClasses[]='nowelt-fs-body'; }
        if ($fsBtn !== '') { $desktopVars .= '--now-fs-btn:'.$fsBtn.';';  $fsClasses[]='nowelt-fs-btn'; }

        if ($hasHeadingFs) $fsClasses[] = 'nowelt-fs-headings';

        $extraClass = $fsClasses ? ' '.implode(' ', $fsClasses) : '';

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
            // tom wrapper + responsive CSS
            $uid = 'nowblk-' . uniqid();
            $sel = '[data-nowblk-id="'.$uid.'"]';
            $respCss = '';

            if ($padTopDesktop || $padBottomDesktop) {
                $respCss .= $sel.'{'
                    . ($padTopDesktop    ? 'padding-top:'.$padTopDesktop.';' : '')
                    . ($padBottomDesktop ? 'padding-bottom:'.$padBottomDesktop.';' : '')
                    . '}';
            }
            if ($padTopLaptop || $padBottomLaptop) {
                $respCss .= '@media (max-width:1440px){'.$sel.'{'
                    . ($padTopLaptop    ? 'padding-top:'.$padTopLaptop.';' : '')
                    . ($padBottomLaptop ? 'padding-bottom:'.$padBottomLaptop.';' : '')
                    . '}}';
            }
            if ($padTopTablet || $padBottomTablet) {
                $respCss .= '@media (max-width:1024px){'.$sel.'{'
                    . ($padTopTablet    ? 'padding-top:'.$padTopTablet.';' : '')
                    . ($padBottomTablet ? 'padding-bottom:'.$padBottomTablet.';' : '')
                    . '}}';
            }
            if ($padTopMobile || $padBottomMobile) {
                $respCss .= '@media (max-width:767px){'.$sel.'{'
                    . ($padTopMobile    ? 'padding-top:'.$padTopMobile.';' : '')
                    . ($padBottomMobile ? 'padding-bottom:'.$padBottomMobile.';' : '')
                    . '}}';
            }
            if ($hideDesktop) $respCss .= '@media (min-width:1025px){'.$sel.'{display:none!important}}';
            if ($hideTablet)  $respCss .= '@media (min-width:768px) and (max-width:1024px){'.$sel.'{display:none!important}}';
            if ($hideMobile)  $respCss .= '@media (max-width:767px){'.$sel.'{display:none!important}}';

            // injicer desktop FS-variabler kun hvis sat:
            if ($desktopVars !== '') {
                $respCss .= '@media (min-width:1025px){'.$sel.'{'.$desktopVars.'}}';
            }

            $styleTag = $respCss ? '<style>'.$respCss.'</style>' : '';
            return '<div class="nowonline-elt-wrapper'.$extraClass.'"'.$styleAttr.' data-nowblk-id="'.$uid.'">'.$styleTag.'<div class="nowonline-elt-module" data-template-id="' . (int)$tid . '"></div></div>';
        }

        $html = self::normalize_elementor_attributes($html);
        $defs = $this->scanner->scan($tid);

        // Video/poster maps før BG
        [$videoMap, $posterMap] = self::build_video_maps($defs, $fields);

        // === Token-udskiftning
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
                    foreach (['[[gallery:' . $key . ']]','[[galleri:' . $key . ']]','[[' . $key . ']]'] as $tok) { $search[] = $tok; $replace[] = $gallery_html; }
                } elseif ($type === 'video') {
                    $val = is_array($v) ? (string)($v['url'] ?? '') : (string)$v;
                    $url = esc_url(self::fix_url($val));
                    $vid_html = self::build_simple_video($url);
                    foreach (['[[video:' . $key . ']]','[[' . $key . ']]'] as $tok) { $search[] = $tok; $replace[] = $vid_html; }
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
                    $inlineKeys = ['titel','title','heading','overskrift','headline','undertitel','subtitle'];
                    $inlineOnly = in_array($key, $inlineKeys, true);
                    $html_val = self::sanitize_rich_html((string)$v, $inlineOnly);

                    $tokens = ['[[rich:' . $key . ']]', '[[wysiwyg:' . $key . ']]', '[[' . $key . ']]',
                               '[[text:' . $key . ']]','[[p:' . $key . ']]',
                               '[[h1:' . $key . ']]','[[h2:' . $key . ']]','[[h3:' . $key . ']]',
                               '[[h4:' . $key . ']]','[[h5:' . $key . ']]','[[h6:' . $key . ']]'];
                    foreach ($tokens as $tok) { $search[] = $tok; $replace[] = $html_val; }
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

        // Links (href/target) på a[data-now-key], .now-link-*, #now-link-*
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

        // Knap-labels
        $html = self::rewrite_button_labels_dom($html, $fields);

        // NY: Skriv titel/undertitel/beskrivelse ind i Elementor widgets hvis der ikke var placeholders
        $html = self::rewrite_core_content_dom($html, $fields, $defs);

        // Media & galleries & videos
        [$imgMap, $bgMap] = self::build_media_maps($defs, $fields, $html);
        if (!empty($imgMap) || !empty($bgMap)) {
            $html = self::rewrite_media_dom($html, $imgMap, $bgMap);
        }

        $galMap = self::build_gallery_map($defs, $fields, $html);
        if (!empty($galMap)) { $html = self::rewrite_galleries_dom($html, $galMap); }

        if (!empty($videoMap)) { $html = self::rewrite_videos_dom($html, $videoMap, $posterMap); }

        $isHeader = self::is_elementor_header_template($tid);
        if ($isHeader) self::$hasHeaderBlock = true;

        if ($bgColor !== '') {
            $html = self::apply_bg_color_inline($html, $bgColor);
        }

        // Baggrund: billede/video (failsafe)
        $html = self::apply_bg_media_inline($html, [
            'img'       => $bgImg,
            'imgTablet' => $bgImgTab,
            'imgMobile' => $bgImgMob,
            'pos'       => $bgPos,
            'size'      => $bgSize,
            'fixed'     => $bgFixed,
            'video'     => $bgVideo,
        ], $videoMap);

        // Responsive CSS: hide/padding pr. device + DESKTOP-ONLY font-size vars
        $uid = 'nowblk-' . uniqid();
        $sel = '[data-nowblk-id="'.$uid.'"]';
        $respCss = '';

        if ($padTopDesktop || $padBottomDesktop) {
            $respCss .= $sel.'{'
                . ($padTopDesktop    ? 'padding-top:'.$padTopDesktop.';' : '')
                . ($padBottomDesktop ? 'padding-bottom:'.$padBottomDesktop.';' : '')
                . '}';
        }
        if ($padTopLaptop || $padBottomLaptop) {
            $respCss .= '@media (max-width:1440px){'.$sel.'{'
                . ($padTopLaptop    ? 'padding-top:'.$padTopLaptop.';' : '')
                . ($padBottomLaptop ? 'padding-bottom:'.$padBottomLaptop.';' : '')
                . '}}';
        }
        if ($padTopTablet || $padBottomTablet) {
            $respCss .= '@media (max-width:1024px){'.$sel.'{'
                . ($padTopTablet    ? 'padding-top:'.$padTopTablet.';' : '')
                . ($padBottomTablet ? 'padding-bottom:'.$padBottomTablet.';' : '')
                . '}}';
        }
        if ($padTopMobile || $padBottomMobile) {
            $respCss .= '@media (max-width:767px){'.$sel.'{'
                . ($padTopMobile    ? 'padding-top:'.$padTopMobile.';' : '')
                . ($padBottomMobile ? 'padding-bottom:'.$padBottomMobile.';' : '')
                . '}}';
        }

        if ($hideDesktop) $respCss .= '@media (min-width:1025px){'.$sel.'{display:none!important}}';
        if ($hideTablet)  $respCss .= '@media (min-width:768px) and (max-width:1024px){'.$sel.'{display:none!important}}';
        if ($hideMobile)  $respCss .= '@media (max-width:767px){'.$sel.'{display:none!important}}';

        if ($desktopVars !== '') {
            $respCss .= '@media (min-width:1025px){'.$sel.'{'.$desktopVars.'}}';
        }

        $styleResponsiveTag = $respCss ? '<style>'.$respCss.'</style>' : '';

        $data_attr = '';
        $linkMap = $linkMap ?? [];
        if (!empty($linkMap)) {
            $data_attr .= ' data-nowlinks=\'' . esc_attr( wp_json_encode($linkMap) ) . '\'';
        }
        if ($isHeader) $data_attr .= ' data-nowelt-is-header="1"';

        return '<div class="nowonline-elt-wrapper'.$extraClass.'"'.$styleAttr.' data-nowblk-id="'.$uid.'">'
             . $styleResponsiveTag
             . '<div class="nowonline-elt-module" data-template-id="' . (int)$tid . '"'.$data_attr.'>'
             . $html
             . '</div></div>';
    }
}
