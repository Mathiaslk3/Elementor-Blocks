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

    /** Elementor “pipe” attributes → normale data-attributter */
    private static function normalize_elementor_attributes(string $html): string
    {
        // data-now-img | key  →  data-now-img="key"
        $html = preg_replace('/\bdata-now-(img|image|bg)\s*\|\s*([a-zA-Z0-9_\-]+)/i', 'data-now-$1="$2"', $html);
        // data-now-key | key  →  data-now-key="key"
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

    /** returnerer to maps: [$imgMap, $bgMap] */
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

    /** Erstat <img> + alle <source> i et HTML-stykke med én URL */
    private static function replace_img_and_sources_in_html(string $html, string $url): string
    {
        // Første <img>
        $html = preg_replace_callback(
            '~<img\b([^>]*)>~i',
            function ($mm) use ($url) {
                $attrs = $mm[1];
                $attrs = preg_replace('/\s+(src|srcset|sizes|data-src|data-srcset|data-lazy-src|data-lazy-srcset)=(["\']).*?\2/i', '', $attrs);
                return '<img ' . trim($attrs) . ' src="' . esc_url($url) . '">';
            },
            $html,
            1
        );

        // Alle <source>
        $html = preg_replace_callback(
            '~<source\b([^>]*)>~i',
            function ($mm) use ($url) {
                $attrs = $mm[1];
                $attrs = preg_replace('/\s+(srcset|data-srcset)=(["\']).*?\2/i', '', $attrs);
                return '<source ' . trim($attrs) . ' srcset="' . esc_url($url) . '">';
            },
            $html
        );

        return $html;
    }

    /** Robust DOM-pass for billeder og baggrunde (håndterer picture/source/lazyload) */
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

        // IMG via data-now-img/data-now-image
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

            // IMG via class now-img-KEY / now-image-KEY
            foreach ($xpath->query('//*[contains(@class,"now-img-") or contains(@class,"now-image-")]') as $wrap) {
                /** @var \DOMElement $wrap */
                $class = ' ' . $wrap->getAttribute('class') . ' ';
                if (preg_match('/\bnow-(?:img|image)-([a-z0-9_-]+)\b/i', $class, $m)) {
                    $key = strtolower($m[1]);
                    if (isset($imgMap[$key])) {
                        $url = $imgMap[$key];

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
        }

        // BG via data-now-bg eller class now-bg-KEY
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

        // Returner indholdet under root
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
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

        // Hent Elementor HTML
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

        // Normalisér evt. “pipe”-attributes fra Elementor
        $html = self::normalize_elementor_attributes($html);

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

        // --- Links bundet direkte på <a> ---
        $linkMap = self::build_link_map($defs, $fields);
        if (!empty($linkMap)) {
            // a) data-now-key="KEY"
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

            // b) class="... now-link-KEY ..."
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

            // c) id="now-link-KEY" (Button ID)
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

        // --- Billeder & baggrunde ---
        [$imgMap, $bgMap] = self::build_media_maps($defs, $fields);

        // Prøv DOM-pass (mest robust). Fald tilbage til regex hvis DOM ikke er tilgængelig.
        if (!empty($imgMap) || !empty($bgMap)) {
            if (class_exists('\DOMDocument')) {
                $html = self::rewrite_media_dom($html, $imgMap, $bgMap);
            } else {
                // Fallback: dine eksisterende regex’er

                if (!empty($imgMap)) {
                    // <img ... data-now-img="KEY" ...>
                    $html = preg_replace_callback(
                        '~<img\b([^>]*?\s)data-now-(?:img|image)=(["\'])([^"\']+)\2([^>]*)>~i',
                        function ($m) use ($imgMap) {
                            $attrs = trim($m[1] . ' ' . $m[4]);
                            $key   = strtolower($m[3]);
                            $url   = $imgMap[$key] ?? '';
                            if ($url === '') return $m[0];
                            $attrs = preg_replace('/\s+(src|srcset|sizes|data-src|data-srcset|data-lazy-src|data-lazy-srcset)=(["\']).*?\2/i', '', $attrs);
                            return '<img ' . trim($attrs) . ' src="' . esc_url($url) . '">';
                        },
                        $html
                    );

                    // <img class="... now-img-KEY ..." / now-image-KEY>
                    $html = preg_replace_callback(
                        '~<img\b([^>]*class=(["\'][^"\']*?\bnow-(?:img|image)-([a-z0-9_-]+)\b[^"\']*\2)[^>]*)>~i',
                        function ($m) use ($imgMap) {
                            $attrs = $m[1];
                            $key   = strtolower($m[3]);
                            $url   = $imgMap[$key] ?? '';
                            if ($url === '') return $m[0];
                            $attrs = preg_replace('/\s+(src|srcset|sizes|data-src|data-srcset|data-lazy-src|data-lazy-srcset)=(["\']).*?\2/i', '', $attrs);
                            return '<img ' . trim($attrs) . ' src="' . esc_url($url) . '">';
                        },
                        $html
                    );

                    // Wrapper med data-now-img / class now-img-KEY
                    $html = preg_replace_callback(
                        '~<([a-z0-9:_-]+)\b([^>]*)\sdata-now-(?:img|image)=(["\'])([^"\']+)\3([^>]*)>(.*?)</\1>~is',
                        function ($m) use ($imgMap) {
                            $tag   = $m[1];
                            $attrs = trim($m[2] . ' ' . $m[5]);
                            $key   = strtolower($m[4]);
                            $body  = $m[6];
                            $url   = $imgMap[$key] ?? '';
                            if ($url === '') return $m[0];
                            $body  = Renderer::replace_img_and_sources_in_html($body, $url);
                            return '<' . $tag . ' ' . $attrs . '>' . $body . '</' . $tag . '>';
                        },
                        $html
                    );

                    $html = preg_replace_callback(
                        '~<([a-z0-9:_-]+)\b([^>]*class=(["\'][^"\']*?\bnow-(?:img|image)-([a-z0-9_-]+)\b[^"\']*\3)[^>]*)>(.*?)</\1>~is',
                        function ($m) use ($imgMap) {
                            $tag   = $m[1];
                            $attrs = $m[2];
                            $key   = strtolower($m[4]);
                            $body  = $m[5];
                            $url   = $imgMap[$key] ?? '';
                            if ($url === '') return $m[0];
                            $body  = Renderer::replace_img_and_sources_in_html($body, $url);
                            return '<' . $tag . ' ' . trim($attrs) . '>' . $body . '</' . $tag . '>';
                        },
                        $html
                    );
                }

                if (!empty($bgMap)) {
                    // data-now-bg="KEY"
                    $html = preg_replace_callback(
                        '~<([a-z0-9:_-]+)\b([^>]*?\s)data-now-bg=(["\'])([^"\']+)\3([^>]*)>~i',
                        function ($m) use ($bgMap) {
                            $tag   = $m[1];
                            $attrs = trim($m[2] . ' ' . $m[5]);
                            $key   = strtolower($m[4]);
                            $url   = $bgMap[$key] ?? '';
                            if ($url === '') return $m[0];

                            if (preg_match('/\sstyle=(["\'])(.*?)\1/i', $attrs, $sm)) {
                                $style = preg_replace('/background-image\s*:\s*[^;]+;?/i', '', $sm[2]);
                                $new   = trim(rtrim($style, '; ') . '; background-image:url(' . esc_url($url) . ')');
                                $attrs = str_replace($sm[0], ' style="' . esc_attr($new) . '"', $attrs);
                            } else {
                                $attrs .= ' style="background-image:url(' . esc_url($url) . ')"';
                            }
                            return '<' . $tag . ' ' . trim($attrs) . '>';
                        },
                        $html
                    );

                    // class="... now-bg-KEY ..."
                    $html = preg_replace_callback(
                        '~<([a-z0-9:_-]+)\b([^>]*class=(["\'][^"\']*?\bnow-bg-([a-z0-9_-]+)\b[^"\']*\3)[^>]*)>~i',
                        function ($m) use ($bgMap) {
                            $tag   = $m[1];
                            $attrs = $m[2];
                            $key   = strtolower($m[4]);
                            $url   = $bgMap[$key] ?? '';
                            if ($url === '') return $m[0];

                            if (preg_match('/\sstyle=(["\'])(.*?)\1/i', $attrs, $sm)) {
                                $style = preg_replace('/background-image\s*:\s*[^;]+;?/i', '', $sm[2]);
                                $new   = trim(rtrim($style, '; ') . '; background-image:url(' . esc_url($url) . ')');
                                $attrs = str_replace($sm[0], ' style="' . esc_attr($new) . '"', $attrs);
                            } else {
                                $attrs .= ' style="background-image:url(' . esc_url($url) . ')"';
                            }
                            return '<' . $tag . ' ' . trim($attrs) . '>';
                        },
                        $html
                    );
                }
            }
        }

        // (Valgfri) eksport som data-attribut
        $data_attr = !empty($linkMap) ? ' data-nowlinks=\'' . esc_attr( wp_json_encode($linkMap) ) . '\'' : '';

        return '<div class="nowonline-elt-wrapper" style="' . esc_attr($style) . '">'
             . '<div class="nowonline-elt-module" data-template-id="' . (int)$tid . '"' . $data_attr . '>'
             . $html
             . '</div></div>';
    }
}
